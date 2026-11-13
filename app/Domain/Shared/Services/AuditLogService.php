<?php

namespace App\Domain\Shared\Services;

use App\Domain\Billing\Models\Shift;
use App\Domain\Facility\Models\Facility;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Models\AuditLog;
use App\Domain\Shared\Models\SystemSetting;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Throwable;

class AuditLogService
{
    protected const SENSITIVE_KEYS = [
        'password',
        'remember_token',
        'token',
        'secret',
        'tally_bridge_token_hash',
        'portal_pin_hash',
        'aadhaar_number',
        'pan_number',
    ];

    /**
     * Record an audit trail action.
     * Guaranteed fail-safe: failures during audit logging never interrupt the primary transaction.
     */
    public function log(
        string $category,
        string $action,
        string $description,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null
    ): ?AuditLog {
        try {
            $user = $user ?? auth()->user();

            $userName = $user ? $user->name : 'System';
            $userRole = null;
            if ($user) {
                $userRole = $user->roles->first()?->name ?? 'staff';
            }

            $ipAddress = request()?->ip();
            $userAgent = request()?->userAgent();
            if ($userAgent !== null) {
                $userAgent = mb_substr($userAgent, 0, 255);
            }

            $cleanOld = $oldValues !== null ? $this->maskSensitiveFields($oldValues) : null;
            $cleanNew = $newValues !== null ? $this->maskSensitiveFields($newValues) : null;

            return AuditLog::create([
                'user_id' => $user?->id,
                'user_name' => mb_substr($userName, 0, 150),
                'user_role' => $userRole ? mb_substr($userRole, 0, 50) : null,
                'category' => mb_substr($category, 0, 50),
                'action' => mb_substr($action, 0, 80),
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'description' => mb_substr($description, 0, 255),
                'old_values' => $cleanOld,
                'new_values' => $cleanNew,
                'ip_address' => $ipAddress ? mb_substr($ipAddress, 0, 45) : null,
                'user_agent' => $userAgent,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to write audit log entry: ' . $e->getMessage(), [
                'category' => $category,
                'action' => $action,
                'description' => $description,
            ]);

            return null;
        }
    }

    /**
     * Prune audit log entries older than the club's configured retention threshold.
     */
    public function pruneExpired(?int $overrideDays = null): int
    {
        if ($overrideDays !== null) {
            $days = max(1, min(365, $overrideDays));
        } else {
            $days = (int) SystemSetting::get('audit_log_retention_days', 30);
            $days = max(7, min(365, $days));
        }

        $cutoff = now()->subDays($days);

        return AuditLog::where('created_at', '<=', $cutoff)->delete();
    }

    public function logLogin(User $user, string $guard = 'web'): ?AuditLog
    {
        return $this->log(
            category: 'security',
            action: 'auth.login',
            description: "Staff user {$user->name} logged in ({$guard})",
            auditable: $user,
            user: $user
        );
    }

    public function logFailedLogin(?string $email, ?string $ip = null): ?AuditLog
    {
        $targetEmail = $email ?: 'unknown';
        $ip = $ip ?? request()?->ip() ?? 'unknown';

        return $this->log(
            category: 'security',
            action: 'auth.failed',
            description: "Failed login attempt for email '{$targetEmail}' from IP {$ip}",
            oldValues: ['email' => $targetEmail, 'ip' => $ip]
        );
    }

    public function logLogout(?User $user): ?AuditLog
    {
        $name = $user ? $user->name : 'Staff user';

        return $this->log(
            category: 'security',
            action: 'auth.logout',
            description: "{$name} logged out",
            auditable: $user,
            user: $user
        );
    }

    public function logTariffChange(Tariff $tariff, string $action, ?array $old = null, ?array $new = null): ?AuditLog
    {
        $verb = match ($action) {
            'created' => 'Created',
            'deleted' => 'Deleted',
            default => 'Updated',
        };

        $priceStr = '₹' . number_format($tariff->rate_paise / 100, 2);
        $desc = "{$verb} menu tariff {$tariff->name} ({$tariff->code}) at {$priceStr} [{$tariff->department_code}]";

        return $this->log(
            category: 'tariffs',
            action: "tariff.{$action}",
            description: $desc,
            auditable: $tariff,
            oldValues: $old,
            newValues: $new
        );
    }

    public function logMemberChange(Member $member, string $action, ?array $old = null, ?array $new = null): ?AuditLog
    {
        $desc = match ($action) {
            'admitted' => "Admitted new member {$member->full_name} ({$member->membership_number})",
            'status_changed' => "Changed status of member {$member->membership_number} to " . ($new['status'] ?? 'unknown'),
            'photo_updated' => "Updated verification portrait for member {$member->membership_number}",
            'photo_removed' => "Removed verification portrait for member {$member->membership_number}",
            default => "Updated profile for member {$member->full_name} ({$member->membership_number})",
        };

        return $this->log(
            category: 'members',
            action: "member.{$action}",
            description: $desc,
            auditable: $member,
            oldValues: $old,
            newValues: $new
        );
    }

    public function logSettingsChange(array $changedKeys, ?array $old = null, ?array $new = null, ?array $newValues = null, ?array $oldValues = null): ?AuditLog
    {
        $keysList = implode(', ', $changedKeys);
        $desc = "Updated system settings: {$keysList}";

        return $this->log(
            category: 'governance',
            action: 'settings.updated',
            description: $desc,
            oldValues: $old ?? $oldValues,
            newValues: $new ?? $newValues
        );
    }

    public function logShiftVariance(Shift $shift, int $variancePaise, ?string $notes = null): ?AuditLog
    {
        $isShortage = $variancePaise < 0;
        $varianceRs = '₹' . number_format(abs($variancePaise) / 100, 2);
        $type = $isShortage ? "Cash Shortage of {$varianceRs}" : "Cash Excess of {$varianceRs}";

        $desc = "Shift #{$shift->shift_number} closed with {$type} at {$shift->counter?->name}";
        if ($notes) {
            $desc .= " — Reason: {$notes}";
        }

        return $this->log(
            category: 'shifts',
            action: 'shift.closed_with_variance',
            description: $desc,
            auditable: $shift,
            oldValues: ['expected_cash_paise' => $shift->expected_cash_paise],
            newValues: [
                'closing_cash_paise' => $shift->closing_cash_paise,
                'variance_paise' => $variancePaise,
                'reconciliation_notes' => $notes,
            ]
        );
    }

    public function logRoleChange(Role $role, string $action, ?array $permissions = null): ?AuditLog
    {
        $verb = match ($action) {
            'created' => 'Created',
            'deleted' => 'Deleted',
            'restored_defaults' => 'Restored defaults for',
            default => 'Updated permissions on',
        };

        $desc = "{$verb} role '{$role->name}'";

        return $this->log(
            category: 'governance',
            action: "role.{$action}",
            description: $desc,
            auditable: $role,
            newValues: $permissions ? ['permissions' => $permissions] : null
        );
    }

    public function logDataExport(string $exportType, int $recordCount, ?string $notes = null): ?AuditLog
    {
        $desc = "Exported {$recordCount} records via {$exportType}";
        if ($notes) {
            $desc .= " ({$notes})";
        }

        return $this->log(
            category: 'data_privacy',
            action: 'data.exported',
            description: $desc,
            newValues: ['export_type' => $exportType, 'record_count' => $recordCount]
        );
    }

    public function logKycView(Model $target, string $documentType): ?AuditLog
    {
        $desc = "Viewed confidential KYC {$documentType} for " . class_basename($target) . " #{$target->getKey()}";

        return $this->log(
            category: 'data_privacy',
            action: 'kyc.viewed',
            description: $desc,
            auditable: $target,
            newValues: ['document_type' => $documentType]
        );
    }

    public function logFacilityStatusChange(Facility $facility, string $action, ?string $oldStatus = null, ?string $newStatus = null): ?AuditLog
    {
        $desc = "Updated facility {$facility->name} ({$facility->code}) [{$action}]: {$oldStatus} -> {$newStatus}";

        return $this->log(
            category: 'facilities',
            action: "facility.{$action}",
            description: $desc,
            auditable: $facility,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus]
        );
    }

    /**
     * Recursively mask sensitive keys in arrays.
     */
    protected function maskSensitiveFields(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveFields($value);
            }
        }

        return $data;
    }
}
