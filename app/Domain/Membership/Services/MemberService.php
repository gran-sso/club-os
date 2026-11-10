<?php

namespace App\Domain\Membership\Services;

use App\Domain\Integrations\Models\ImportBatch;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Shared\Enums\MemberStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MemberService
{
    /**
     * Register a new club member with personal details and initialized wallet.
     */
    public function registerMember(
        array $personData,
        int $categoryId,
        string $membershipNumber,
        ?string $joinedAt = null,
        ?string $validUntil = null
    ): Member {
        $num = trim($membershipNumber);
        if (Member::where('membership_number', $num)->exists()) {
            throw new InvalidArgumentException("Membership number '{$num}' is already registered.");
        }

        return DB::transaction(function () use ($personData, $categoryId, $num, $joinedAt, $validUntil) {
            $person = Person::create([
                'first_name' => $personData['first_name'],
                'last_name' => $personData['last_name'] ?? null,
                'phone' => $personData['phone'] ?? null,
                'email' => $personData['email'] ?? null,
                'id_proof_type' => $personData['id_proof_type'] ?? null,
                'id_proof_number' => $personData['id_proof_number'] ?? null,
                'id_proof_path' => $personData['id_proof_path'] ?? null,
                'application_form_path' => $personData['application_form_path'] ?? null,
                'photo_path' => $personData['photo_path'] ?? null,
                'notes' => $personData['notes'] ?? null,
            ]);

            $member = Member::create([
                'membership_number' => $num,
                'person_id' => $person->id,
                'membership_category_id' => $categoryId,
                'status' => MemberStatus::ACTIVE,
                'joined_at' => $joinedAt ?? now()->toDateString(),
                'valid_until' => $validUntil,
            ]);

            // Authoritative server-side wallet account initialized with 0 balance
            WalletAccount::create([
                'walletable_type' => Member::class,
                'walletable_id' => $member->id,
                'balance_paise' => 0,
                'reserved_paise' => 0,
                'currency' => 'INR',
                'status' => 'active',
                'version' => 1,
            ]);

            return $member->fresh(['person', 'category', 'wallet']);
        });
    }

    /**
     * Register a dependent family member.
     * Default rule: cannot spend primary wallet unless explicitly authorized.
     */
    public function registerDependent(
        Member $member,
        array $personData,
        string $relationship,
        bool $canSpendWallet = false
    ): Dependent {
        return DB::transaction(function () use ($member, $personData, $relationship, $canSpendWallet) {
            $person = Person::create([
                'first_name' => $personData['first_name'],
                'last_name' => $personData['last_name'] ?? null,
                'phone' => $personData['phone'] ?? null,
                'email' => $personData['email'] ?? null,
                'notes' => $personData['notes'] ?? null,
            ]);

            return Dependent::create([
                'member_id' => $member->id,
                'person_id' => $person->id,
                'relationship' => $relationship,
                'can_spend_wallet' => $canSpendWallet,
            ]);
        });
    }

    /**
     * Preview CSV Member import and detect duplicate numbers or date formatting issues.
     */
    public function previewCsvImport(string $csvContent): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $csvContent)));
        if (empty($lines)) {
            return ['total_rows' => 0, 'valid_rows' => [], 'errors' => []];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map('strtolower', array_map('trim', $header));

        $validRows = [];
        $errors = [];
        $seenNumbers = [];

        foreach ($lines as $index => $line) {
            $rowNum = $index + 2; // line index + 1 for header + 1 for 1-based
            $cols = str_getcsv($line);

            if (count($cols) < count($header)) {
                $errors[] = [
                    'row' => $rowNum,
                    'error' => "Row has fewer columns than header.",
                    'data' => $line,
                ];
                continue;
            }

            $row = array_combine($header, array_slice($cols, 0, count($header)));
            $memNumber = trim($row['membership_number'] ?? '');

            if (empty($memNumber)) {
                $errors[] = ['row' => $rowNum, 'error' => "Missing membership number."];
                continue;
            }

            // Check duplicate in file
            if (isset($seenNumbers[$memNumber])) {
                $errors[] = ['row' => $rowNum, 'error' => "Duplicate membership number in CSV: '{$memNumber}' (first seen at row {$seenNumbers[$memNumber]})."];
                continue;
            }
            $seenNumbers[$memNumber] = $rowNum;

            // Check duplicate in database
            if (Member::where('membership_number', $memNumber)->exists()) {
                $errors[] = ['row' => $rowNum, 'error' => "Membership number '{$memNumber}' already exists in database."];
                continue;
            }

            // Validate date format
            $joinedAtStr = trim($row['joined_at'] ?? now()->toDateString());
            try {
                $joinedAt = Carbon::parse($joinedAtStr)->toDateString();
            } catch (\Exception $e) {
                $errors[] = ['row' => $rowNum, 'error' => "Invalid joined_at date format: '{$joinedAtStr}'."];
                continue;
            }

            $validRows[] = [
                'membership_number' => $memNumber,
                'first_name' => trim($row['first_name'] ?? 'Member'),
                'last_name' => trim($row['last_name'] ?? ''),
                'phone' => trim($row['phone'] ?? ''),
                'email' => trim($row['email'] ?? ''),
                'category_code' => trim($row['category_code'] ?? 'ORD'),
                'joined_at' => $joinedAt,
            ];
        }

        return [
            'total_rows' => count($lines),
            'valid_count' => count($validRows),
            'failed_count' => count($errors),
            'valid_rows' => $validRows,
            'errors' => $errors,
        ];
    }

    /**
     * Execute approved member import batch.
     */
    public function executeImport(array $validRows, int $userId): ImportBatch
    {
        return DB::transaction(function () use ($validRows, $userId) {
            $yearMonth = now()->format('Ym');
            $count = ImportBatch::where('batch_number', 'LIKE', "IMP-{$yearMonth}-%")->count() + 1;
            $batchNumber = sprintf("IMP-%s-%05d", $yearMonth, $count);

            $defaultCategory = MembershipCategory::firstOrCreate(
                ['code' => 'ORD'],
                ['name' => 'Ordinary Member', 'annual_fee_paise' => 1200000, 'has_voting_rights' => true, 'is_active' => true]
            );

            $imported = 0;
            foreach ($validRows as $row) {
                $category = MembershipCategory::where('code', $row['category_code'] ?? 'ORD')->first() ?? $defaultCategory;

                $this->registerMember(
                    personData: [
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'] ?? null,
                        'phone' => $row['phone'] ?? null,
                        'email' => $row['email'] ?? null,
                    ],
                    categoryId: $category->id,
                    membershipNumber: $row['membership_number'],
                    joinedAt: $row['joined_at']
                );
                $imported++;
            }

            return ImportBatch::create([
                'batch_number' => $batchNumber,
                'type' => 'members',
                'total_rows' => count($validRows),
                'imported_rows' => $imported,
                'failed_rows' => 0,
                'status' => 'completed',
                'created_by_user_id' => $userId,
            ]);
        });
    }
}
