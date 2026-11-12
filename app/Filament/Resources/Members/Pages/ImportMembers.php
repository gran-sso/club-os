<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Integrations\Models\ImportBatch;
use App\Domain\Membership\Services\MemberService;
use App\Domain\Shared\Services\BusinessDateService;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportMembers extends Page
{
    use WithFileUploads;

    protected static string $resource = MemberResource::class;

    protected static ?string $title = 'Import Members from CSV (Excel)';

    protected string $view = 'filament.resources.members.pages.import-members';

    public $csvFile = null;

    public string $feeMode = 'already_paid';

    public string $blankValidUntilMode = 'fy_end';

    public ?string $customValidUntil = null;

    public string $openingBalanceDate = '';

    public bool $queueTallyOutbox = false;

    public ?array $previewResult = null;

    public ?int $previewBatchId = null;

    public ?string $previewBatchNumber = null;

    public ?array $completedBatchResult = null;

    public ?string $uploadError = null;

    public static function canAccess(array $parameters = []): bool
    {
        return static::getResource()::canCreate();
    }

    public static function authorizeResourceAccess(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function mount(): void
    {
        static::authorizeResourceAccess();
        $this->openingBalanceDate = app(BusinessDateService::class)->getBusinessDate();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Download Sample CSV')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('info')
                ->action(fn (): StreamedResponse => $this->downloadTemplate()),

            Action::make('backToMembers')
                ->label('Back to Members')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => MemberResource::getUrl('index')),
        ];
    }

    public function downloadTemplate(): StreamedResponse
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('members.manage'), 403);

        $csv = app(MemberService::class)->generateImportTemplateCsv();

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'malabar_club_members_import_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function runPreview(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('members.manage'), 403);

        $this->uploadError = null;
        $this->completedBatchResult = null;

        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'feeMode' => ['required', 'in:already_paid,pending'],
            'blankValidUntilMode' => ['required', 'in:fy_end,one_year_from_joined,custom_date,require'],
            'customValidUntil' => ['nullable', 'required_if:blankValidUntilMode,custom_date', 'date'],
            'openingBalanceDate' => ['required', 'date'],
        ]);

        try {
            $realPath = method_exists($this->csvFile, 'getRealPath') ? $this->csvFile->getRealPath() : null;
            $content = ($realPath && is_file($realPath))
                ? (string) file_get_contents($realPath)
                : (string) $this->csvFile->get();

            $memberService = app(MemberService::class);
            $preview = $memberService->previewCsvImport($content, [
                'fee_mode' => $this->feeMode,
                'blank_valid_until_mode' => $this->blankValidUntilMode,
                'custom_valid_until' => $this->customValidUntil,
            ]);

            if (($preview['total_rows'] ?? 0) === 0) {
                $this->uploadError = 'The uploaded CSV contains headers only or no readable member data rows.';
                $this->previewResult = null;
                $this->previewBatchId = null;
                $this->previewBatchNumber = null;

                return;
            }

            $batch = $memberService->createPreviewBatch($preview, (int) auth()->id());

            $this->previewResult = $preview;
            $this->previewBatchId = $batch->id;
            $this->previewBatchNumber = $batch->batch_number;
        } catch (\Throwable $e) {
            $this->uploadError = $e->getMessage();
            $this->previewResult = null;
            $this->previewBatchId = null;
            $this->previewBatchNumber = null;
        }
    }

    public function confirmImport(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('members.manage'), 403);

        if (empty($this->previewResult['valid_rows']) || ! $this->previewBatchId) {
            Notification::make()
                ->title('Nothing Ready to Import')
                ->body('Please upload a CSV and resolve errors before confirming.')
                ->warning()
                ->send();

            return;
        }

        try {
            $batch = app(MemberService::class)->executeImport(
                $this->previewResult['valid_rows'],
                (int) auth()->id(),
                [
                    'import_batch_id' => $this->previewBatchId,
                    'fee_mode' => $this->feeMode,
                    'opening_balance_date' => $this->openingBalanceDate,
                    'queue_tally_outbox' => $this->queueTallyOutbox,
                ]
            );

            $execSummary = $batch->preview_data['execution_summary'] ?? [];

            $this->completedBatchResult = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'imported_rows' => $batch->imported_rows,
                'failed_rows' => $batch->failed_rows,
                'errors' => $batch->errors ?? [],
                'headers' => $this->previewResult['headers'] ?? MemberService::importCsvHeaders(),
                'execution_summary' => $execSummary,
            ];

            $this->previewResult = null;
            $this->previewBatchId = null;
            $this->previewBatchNumber = null;
            $this->csvFile = null;

            $msg = "Imported {$batch->imported_rows} member(s) under Batch {$batch->batch_number}.";
            if (! empty($execSummary['journal_number'])) {
                $openingRupees = number_format(((int) ($execSummary['total_opening_balance_paise'] ?? 0)) / 100, 2);
                $msg .= " Opening wallet journal {$execSummary['journal_number']} posted (₹{$openingRupees}).";
            }
            if ($batch->failed_rows > 0) {
                $msg .= " {$batch->failed_rows} row(s) with errors were skipped and remain downloadable below.";
            }

            Notification::make()
                ->title('Bulk Member Import Completed')
                ->body($msg)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->uploadError = $e->getMessage();
            Notification::make()
                ->title('Import Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetPreview(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('members.manage'), 403);

        if ($this->previewBatchId) {
            ImportBatch::where('id', $this->previewBatchId)
                ->where('status', 'preview')
                ->update(['status' => 'rejected']);
        }

        $this->csvFile = null;
        $this->previewResult = null;
        $this->previewBatchId = null;
        $this->previewBatchNumber = null;
        $this->uploadError = null;
    }

    public function downloadPreviewErrorsCsv(): ?StreamedResponse
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('members.manage'), 403);

        $errors = $this->previewResult['errors'] ?? [];
        if (empty($errors)) {
            return null;
        }

        $csv = app(MemberService::class)->buildErrorRowsCsv(
            $errors,
            $this->previewResult['headers'] ?? []
        );

        $name = 'malabar_club_import_errors_' . ($this->previewBatchNumber ?: now()->format('Ymd_His')) . '.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadCompletedErrorsCsv(): ?StreamedResponse
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('members.manage'), 403);

        $errors = $this->completedBatchResult['errors'] ?? [];
        if (empty($errors)) {
            return null;
        }

        $csv = app(MemberService::class)->buildErrorRowsCsv(
            $errors,
            $this->completedBatchResult['headers'] ?? []
        );

        $batchNo = $this->completedBatchResult['batch_number'] ?? now()->format('Ymd_His');

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, "malabar_club_remaining_errors_{$batchNo}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadBatchErrorsCsv(int $batchId): ?StreamedResponse
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('members.manage'), 403);

        $batch = ImportBatch::where('type', 'members')->findOrFail($batchId);
        $errors = $batch->errors ?? [];
        if (empty($errors)) {
            return null;
        }

        $headers = $batch->preview_data['headers'] ?? [];
        $csv = app(MemberService::class)->buildErrorRowsCsv($errors, $headers);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, "malabar_club_errors_{$batch->batch_number}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getRecentBatchesProperty(): Collection
    {
        return ImportBatch::with('creator')
            ->where('type', 'members')
            ->latest()
            ->take(10)
            ->get();
    }

    public function getDefaultFyEndDateProperty(): string
    {
        return MemberService::defaultAnnualValidUntilDate();
    }
}

