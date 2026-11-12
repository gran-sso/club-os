<?php

namespace App\Domain\Membership\Services;

use App\Domain\Finance\Services\JournalService;
use App\Domain\Finance\Services\PeriodClosingService;
use App\Domain\Integrations\Contracts\CardReaderInterface;
use App\Domain\Integrations\Models\ImportBatch;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberNote;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Models\WalletTransaction;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Shared\Services\BusinessDateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberService
{
    public const MAX_CSV_IMPORT_ROWS = 2500;

    /**
     * Register a new club member with personal details and initialized wallet.
     */
    public function registerMember(
        array $personData,
        int $categoryId,
        string $membershipNumber,
        ?string $joinedAt = null,
        ?string $validUntil = null,
        array $memberAttributes = []
    ): Member {
        $num = trim($membershipNumber);
        if (Member::where('membership_number', $num)->exists()) {
            throw new InvalidArgumentException("Membership number '{$num}' is already registered.");
        }

        return DB::transaction(function () use ($personData, $categoryId, $num, $joinedAt, $validUntil, $memberAttributes) {
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

            $member = Member::create(array_merge([
                'membership_number' => $num,
                'person_id' => $person->id,
                'membership_category_id' => $categoryId,
                'status' => MemberStatus::ACTIVE,
                'joined_at' => $joinedAt ?? now()->toDateString(),
                'valid_until' => $validUntil,
            ], $memberAttributes));

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
                'photo_path' => $personData['photo_path'] ?? null,
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
     * Update a person's optional portrait photo path and delete any unreferenced previous file.
     */
    public function updatePersonPhoto(Person $person, ?string $newPhotoPath): void
    {
        $oldPath = $person->photo_path;
        $person->update([
            'photo_path' => $newPhotoPath ?: null,
        ]);

        if ($oldPath && $oldPath !== $newPhotoPath) {
            $this->cleanupUnusedPhoto($oldPath);
        }
    }

    /**
     * Delete a stored photo from private local storage if no Person record references it.
     */
    public function cleanupUnusedPhoto(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Person::where('photo_path', $path)->exists()) {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Canonical CSV columns for member import templates and error-row exports.
     *
     * @return array<int, string>
     */
    public static function importCsvHeaders(): array
    {
        return [
            'membership_number',
            'first_name',
            'last_name',
            'phone',
            'email',
            'category_code',
            'status',
            'joined_at',
            'valid_until',
            'admission_fee_status',
            'annual_fee_status',
            'admission_fee_amount_rupees',
            'fee_paid_date',
            'historical_receipt_ref',
            'opening_balance_rupees',
            'rfid_card_number',
            'id_proof_type',
            'id_proof_number',
            'notes',
        ];
    }

    /**
     * Compute end of the current Indian Financial Year (31 March) for annual dues validity defaults.
     */
    public static function defaultAnnualValidUntilDate(?\Carbon\CarbonInterface $referenceDate = null): string
    {
        $now = $referenceDate ? Carbon::instance($referenceDate) : now();
        $fyEndYear = $now->month >= 4 ? $now->year + 1 : $now->year;

        return sprintf('%04d-03-31', $fyEndYear);
    }

    /**
     * Generate an Excel-ready UTF-8 BOM CSV template containing sample rows for onboarding existing members.
     */
    public function generateImportTemplateCsv(): string
    {
        $fyEnd = self::defaultAnnualValidUntilDate();
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, self::importCsvHeaders());
        fputcsv($stream, [
            'MC-EX-101',
            'Suresh',
            'Nair',
            '+919847011223',
            'suresh.nair@example.com',
            'ORD',
            'active',
            '2021-06-15',
            $fyEnd,
            'paid',
            'paid',
            '50000.00',
            '2021-06-15',
            'REG-2021-045',
            '5000.00',
            'CARD-EX-101',
            'Aadhaar Card',
            'XXXX-XXXX-4321',
            'Sample Ordinary Member — replace example rows before uploading',
        ]);
        fputcsv($stream, [
            'MC-EX-102',
            'Lakshmi',
            'Menon',
            '+919895022334',
            'lakshmi.menon@example.com',
            'LIFE',
            'active',
            '2018-04-01',
            '',
            'paid',
            'paid',
            '200000.00',
            '2018-04-01',
            'LIFE-089',
            '12500.00',
            '',
            'Passport',
            'P9876543',
            'Sample Life Member — leave valid_until blank for continuous lifetime validity',
        ]);
        fputcsv($stream, [
            'MC-EX-103',
            'Rajesh',
            'Kurup',
            '+919447033445',
            '',
            'CORP',
            'active',
            '2023-01-10',
            $fyEnd,
            'paid',
            'paid',
            '500000.00',
            '2023-01-10',
            'CORP-12',
            '0.00',
            '',
            '',
            '',
            'Sample Corporate Member',
        ]);

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    /**
     * Stream an Excel-ready UTF-8 BOM CSV export of member records.
     */
    public function streamMembersCsvExport(iterable $members, bool $includeKyc = false, ?string $filename = null): StreamedResponse
    {
        $downloadName = $filename ?: ('malabar_club_members_' . now()->format('Ymd_His') . '.csv');

        return response()->streamDownload(function () use ($members, $includeKyc) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            $headers = [
                'membership_number',
                'first_name',
                'last_name',
                'phone',
                'email',
                'category_code',
                'category_name',
                'status',
                'joined_at',
                'valid_until',
                'admission_fee_status',
                'admission_fee_amount_rupees',
                'annual_fee_status',
                'last_fee_paid_at',
                'historical_receipt_ref',
                'wallet_balance_rupees',
                'rfid_card_number',
            ];
            if ($includeKyc) {
                $headers[] = 'id_proof_type';
                $headers[] = 'id_proof_number';
            }
            $headers[] = 'notes';

            fputcsv($handle, $headers);

            foreach ($members as $member) {
                /** @var Member $member */
                $member->loadMissing(['person', 'category', 'wallet', 'activeCard']);

                $row = [
                    $this->sanitizeCsvCell((string) $member->membership_number),
                    $this->sanitizeCsvCell((string) ($member->person?->first_name ?? '')),
                    $this->sanitizeCsvCell((string) ($member->person?->last_name ?? '')),
                    $this->sanitizeCsvCell((string) ($member->person?->phone ?? '')),
                    $this->sanitizeCsvCell((string) ($member->person?->email ?? '')),
                    $this->sanitizeCsvCell((string) ($member->category?->code ?? '')),
                    $this->sanitizeCsvCell((string) ($member->category?->name ?? '')),
                    $member->status instanceof MemberStatus ? $member->status->value : (string) $member->status,
                    $member->joined_at?->toDateString() ?? '',
                    $member->valid_until?->toDateString() ?? '',
                    (string) ($member->admission_fee_status ?? 'pending'),
                    number_format(((int) ($member->admission_fee_paise ?? 0)) / 100, 2, '.', ''),
                    (string) ($member->fee_status ?? 'pending'),
                    $member->last_fee_paid_at?->toDateString() ?? '',
                    $this->sanitizeCsvCell((string) ($member->last_fee_receipt_number ?: ($member->admission_fee_receipt_number ?? ''))),
                    number_format(((int) ($member->wallet?->balance_paise ?? 0)) / 100, 2, '.', ''),
                    $this->sanitizeCsvCell((string) ($member->activeCard?->card_number ?? '')),
                ];

                if ($includeKyc) {
                    $row[] = $this->sanitizeCsvCell((string) ($member->person?->id_proof_type ?? ''));
                    $row[] = $this->sanitizeCsvCell((string) ($member->person?->id_proof_number ?? ''));
                }

                $row[] = $this->sanitizeCsvCell((string) ($member->person?->notes ?? ''));

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $downloadName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build a downloadable CSV containing only rejected rows plus an error reason column so staff can fix and re-upload.
     */
    public function buildErrorRowsCsv(array $errors, array $headers = []): string
    {
        $exportHeaders = ! empty($headers) ? array_values($headers) : self::importCsvHeaders();
        $exportHeaders = array_values(array_filter(
            $exportHeaders,
            fn ($h) => ! in_array($h, ['import_error_reason', 'error', 'error_reason'], true)
        ));
        $finalHeaders = [...$exportHeaders, 'import_error_reason'];

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $finalHeaders);

        foreach ($errors as $errorRow) {
            $rawRow = is_array($errorRow['raw_row'] ?? null) ? $errorRow['raw_row'] : [];
            $csvRow = [];
            foreach ($exportHeaders as $col) {
                $val = $rawRow[$col] ?? '';
                if ($col === 'membership_number' && $val === '' && ! empty($errorRow['membership_number'])) {
                    $val = (string) $errorRow['membership_number'];
                }
                $csvRow[] = $this->sanitizeCsvCell((string) $val);
            }
            $csvRow[] = $this->sanitizeCsvCell((string) ($errorRow['error'] ?? 'Validation failed'));
            fputcsv($stream, $csvRow);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    /**
     * Preview CSV Member import: validates rows, detects DB/file duplicates, supports historical fee flags & opening wallet balances.
     * Applies NO database mutations before confirmation.
     */
    public function previewCsvImport(string $csvContent, array $options = []): array
    {
        $parsed = $this->parseCsvDocument($csvContent);
        $headers = $parsed['headers'];
        $rows = $parsed['rows'];

        if (empty($headers) || empty($rows)) {
            return [
                'total_rows' => 0,
                'valid_count' => 0,
                'failed_count' => 0,
                'warning_count' => 0,
                'headers' => $headers,
                'valid_rows' => [],
                'errors' => [],
                'warnings' => [],
                'summary' => [
                    'total_opening_balance_paise' => 0,
                    'opening_wallet_count' => 0,
                    'cards_to_issue_count' => 0,
                    'paid_count' => 0,
                    'pending_count' => 0,
                ],
            ];
        }

        if (count($rows) > self::MAX_CSV_IMPORT_ROWS) {
            throw new InvalidArgumentException(sprintf(
                'CSV contains %d data rows, exceeding the per-batch web limit of %d rows. Please split into smaller files.',
                count($rows),
                self::MAX_CSV_IMPORT_ROWS
            ));
        }

        $feeMode = ($options['fee_mode'] ?? 'already_paid') === 'pending' ? 'pending' : 'already_paid';
        $blankValidUntilMode = $options['blank_valid_until_mode'] ?? 'fy_end';
        $customValidUntil = ! empty($options['custom_valid_until'])
            ? $this->parseFlexibleDate((string) $options['custom_valid_until'])
            : null;
        if ($blankValidUntilMode === 'custom_date' && $customValidUntil === null) {
            throw new InvalidArgumentException('A valid custom expiry date must be supplied when Blank Validity handling is set to Custom Date.');
        }

        $categories = MembershipCategory::where('is_active', true)->get()
            ->keyBy(fn (MembershipCategory $cat) => strtoupper(trim($cat->code)));

        /** @var CardReaderInterface $cardReader */
        $cardReader = app(CardReaderInterface::class);

        // Batch-check existing DB membership numbers and RFID cards upfront to avoid N+1 queries.
        $candidateNumbers = [];
        $candidateCards = [];
        foreach ($rows as $item) {
            $num = trim((string) ($item['data']['membership_number'] ?? ''));
            if ($num !== '') {
                $candidateNumbers[] = $num;
            }
            $rawCard = trim((string) ($item['data']['rfid_card_number'] ?? ''));
            if ($rawCard !== '') {
                $parsedCard = $cardReader->parseInput($rawCard);
                if ($parsedCard) {
                    $candidateCards[] = $parsedCard;
                }
            }
        }

        $existingMemberNumbers = [];
        foreach (array_chunk(array_unique($candidateNumbers), 500) as $chunk) {
            foreach (Member::whereIn('membership_number', $chunk)->pluck('membership_number') as $existingNum) {
                $existingMemberNumbers[$existingNum] = true;
            }
        }

        $existingCardNumbers = [];
        foreach (array_chunk(array_unique($candidateCards), 500) as $chunk) {
            foreach (Card::whereIn('card_number', $chunk)->pluck('card_number') as $existingCard) {
                $existingCardNumbers[$existingCard] = true;
            }
        }

        $hasCategoryColumn = in_array('category_code', $headers, true);
        $validRows = [];
        $errors = [];
        $warnings = [];
        $seenNumbers = [];
        $seenCards = [];

        $totalOpeningBalancePaise = 0;
        $openingWalletCount = 0;
        $cardsToIssueCount = 0;
        $paidCount = 0;
        $pendingCount = 0;

        foreach ($rows as $item) {
            $rowNum = $item['row'];
            $row = $item['data'];
            $memNumber = trim((string) ($row['membership_number'] ?? ''));
            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);

            $makeError = function (string $message) use ($rowNum, $memNumber, $fullName, $row): array {
                return [
                    'row' => $rowNum,
                    'membership_number' => $memNumber,
                    'name' => $fullName,
                    'error' => $message,
                    'raw_row' => $row,
                ];
            };

            if ($memNumber === '') {
                $errors[] = $makeError('Missing membership number.');
                continue;
            }

            if (isset($seenNumbers[$memNumber])) {
                $errors[] = $makeError("Duplicate membership number in CSV: '{$memNumber}' (first seen at row {$seenNumbers[$memNumber]}).");
                continue;
            }
            $seenNumbers[$memNumber] = $rowNum;

            if (isset($existingMemberNumbers[$memNumber])) {
                $errors[] = $makeError("Membership number '{$memNumber}' already exists in database.");
                continue;
            }

            if ($firstName === '') {
                $errors[] = $makeError("Missing member first_name for '{$memNumber}'.");
                continue;
            }

            $catCode = $hasCategoryColumn
                ? strtoupper(trim((string) ($row['category_code'] ?? '')))
                : 'ORD';

            if ($catCode === '' || ! $categories->has($catCode)) {
                $errors[] = $makeError("Unknown or inactive category_code '{$catCode}'.");
                continue;
            }
            /** @var MembershipCategory $category */
            $category = $categories->get($catCode);

            $statusRaw = strtolower(trim((string) ($row['status'] ?? '')));
            $memberStatus = $statusRaw !== '' ? MemberStatus::tryFrom($statusRaw) : MemberStatus::ACTIVE;
            if (! $memberStatus) {
                $errors[] = $makeError("Invalid status '{$row['status']}'. Allowed: active, suspended, expired, resigned, terminated.");
                continue;
            }

            $joinedAtRaw = trim((string) ($row['joined_at'] ?? ''));
            if ($joinedAtRaw === '') {
                $joinedAt = now()->toDateString();
            } else {
                $joinedAt = $this->parseFlexibleDate($joinedAtRaw);
                if ($joinedAt === null) {
                    $errors[] = $makeError("Invalid joined_at date format: '{$joinedAtRaw}'. Use YYYY-MM-DD or DD/MM/YYYY.");
                    continue;
                }
            }

            // Resolve membership validity based on category recurrence rules & batch option
            $validUntilRaw = trim((string) ($row['valid_until'] ?? ''));
            $isPermanentCategory = ((int) $category->annual_fee_paise) === 0;
            $validUntil = null;

            if ($validUntilRaw !== '') {
                $validUntil = $this->parseFlexibleDate($validUntilRaw);
                if ($validUntil === null) {
                    $errors[] = $makeError("Invalid valid_until date format: '{$validUntilRaw}'. Use YYYY-MM-DD or DD/MM/YYYY.");
                    continue;
                }
            } elseif (! $isPermanentCategory) {
                $validUntil = match ($blankValidUntilMode) {
                    'one_year_from_joined' => Carbon::parse($joinedAt)->addYear()->toDateString(),
                    'custom_date' => $customValidUntil,
                    'leave_blank' => null,
                    'require' => null,
                    default => self::defaultAnnualValidUntilDate(),
                };

                if ($blankValidUntilMode === 'require') {
                    $errors[] = $makeError("Missing valid_until for annual membership category '{$category->code}'.");
                    continue;
                }
            }

            // Validate historical fee paid date (optional, falls back to joined_at)
            $feePaidDateRaw = trim((string) ($row['fee_paid_date'] ?? ''));
            $feePaidDate = $joinedAt;
            if ($feePaidDateRaw !== '') {
                $parsedFeeDate = $this->parseFlexibleDate($feePaidDateRaw);
                if ($parsedFeeDate === null) {
                    $errors[] = $makeError("Invalid fee_paid_date format: '{$feePaidDateRaw}'.");
                    continue;
                }
                $feePaidDate = $parsedFeeDate;
            }

            // Validate historical admission fee amount (optional, defaults to category's fee)
            $admissionAmountRaw = trim((string) ($row['admission_fee_amount_rupees'] ?? ''));
            if ($admissionAmountRaw !== '') {
                try {
                    $admissionFeePaise = $this->parseRupeesToPaise($admissionAmountRaw);
                } catch (InvalidArgumentException $e) {
                    $errors[] = $makeError("admission_fee_amount_rupees error: {$e->getMessage()}");
                    continue;
                }
            } else {
                $admissionFeePaise = (int) ($category->admission_fee_paise ?? 0);
            }

            // Normalize admission & annual fee statuses
            try {
                $admissionStatus = $this->normalizeFeeStatus(
                    (string) ($row['admission_fee_status'] ?? ''),
                    defaultStatus: $feeMode === 'already_paid'
                        ? ($admissionFeePaise === 0 && ((int) ($category->admission_fee_paise ?? 0)) === 0 ? 'waived' : 'paid')
                        : (((int) ($category->admission_fee_paise ?? 0)) === 0 ? 'waived' : 'pending')
                );

                $isExpiredRecord = $memberStatus === MemberStatus::EXPIRED
                    || ($validUntil !== null && Carbon::parse($validUntil)->endOfDay()->lt(now()->startOfDay()));

                $annualStatus = $this->normalizeFeeStatus(
                    (string) ($row['annual_fee_status'] ?? ''),
                    defaultStatus: $feeMode === 'already_paid'
                        ? ($isExpiredRecord ? 'pending' : 'paid')
                        : 'pending'
                );
            } catch (InvalidArgumentException $e) {
                $errors[] = $makeError($e->getMessage());
                continue;
            }

            // Validate optional opening wallet balance
            $openingRaw = trim((string) ($row['opening_balance_rupees'] ?? ''));
            $openingBalancePaise = 0;
            if ($openingRaw !== '') {
                try {
                    $openingBalancePaise = $this->parseRupeesToPaise($openingRaw);
                } catch (InvalidArgumentException $e) {
                    $errors[] = $makeError("opening_balance_rupees error: {$e->getMessage()} Note: legacy debts/negative balances cannot be imported into wallets.");
                    continue;
                }
            }

            // Validate optional RFID card UID
            $rawCard = trim((string) ($row['rfid_card_number'] ?? ''));
            $parsedCard = null;
            if ($rawCard !== '') {
                $parsedCard = $cardReader->parseInput($rawCard);
                if (! $parsedCard) {
                    $errors[] = $makeError("Invalid RFID card format '{$rawCard}'.");
                    continue;
                }
                if (isset($seenCards[$parsedCard])) {
                    $errors[] = $makeError("Duplicate rfid_card_number in CSV: '{$parsedCard}' (first seen at row {$seenCards[$parsedCard]}).");
                    continue;
                }
                $seenCards[$parsedCard] = $rowNum;

                if (isset($existingCardNumbers[$parsedCard])) {
                    $errors[] = $makeError("RFID card '{$parsedCard}' is already registered in database.");
                    continue;
                }
            }

            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $makeError("Invalid email address format: '{$email}'.");
                continue;
            }

            $phone = trim((string) ($row['phone'] ?? ''));
            if ($phone === '') {
                $warnings[] = [
                    'row' => $rowNum,
                    'membership_number' => $memNumber,
                    'name' => $fullName,
                    'warning' => 'Missing mobile number: Member Portal self-activation PIN setup requires a phone number later.',
                ];
            }

            if ($memberStatus === MemberStatus::ACTIVE && $validUntil !== null && Carbon::parse($validUntil)->endOfDay()->lt(now()->startOfDay())) {
                $warnings[] = [
                    'row' => $rowNum,
                    'membership_number' => $memNumber,
                    'name' => $fullName,
                    'warning' => "Validity date '{$validUntil}' is in the past while status is Active; annual renewal dues marked pending.",
                ];
            }

            $totalOpeningBalancePaise += $openingBalancePaise;
            if ($openingBalancePaise > 0) {
                $openingWalletCount++;
            }
            if ($parsedCard !== null) {
                $cardsToIssueCount++;
            }
            if ($annualStatus === 'paid' || $annualStatus === 'waived') {
                $paidCount++;
            } else {
                $pendingCount++;
            }

            $validRows[] = [
                'row' => $rowNum,
                'membership_number' => $memNumber,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'email' => $email,
                'category_id' => $category->id,
                'category_code' => $category->code,
                'category_name' => $category->name,
                'status' => $memberStatus->value,
                'joined_at' => $joinedAt,
                'valid_until' => $validUntil,
                'admission_fee_status' => $admissionStatus,
                'admission_fee_paise' => $admissionFeePaise,
                'annual_fee_status' => $annualStatus,
                'fee_paid_date' => $feePaidDate,
                'historical_receipt_ref' => trim((string) ($row['historical_receipt_ref'] ?? '')),
                'opening_balance_paise' => $openingBalancePaise,
                'rfid_card_number' => $parsedCard,
                'id_proof_type' => trim((string) ($row['id_proof_type'] ?? '')),
                'id_proof_number' => trim((string) ($row['id_proof_number'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? '')),
            ];
        }

        return [
            'total_rows' => count($rows),
            'valid_count' => count($validRows),
            'failed_count' => count($errors),
            'warning_count' => count($warnings),
            'headers' => $headers,
            'valid_rows' => $validRows,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'total_opening_balance_paise' => $totalOpeningBalancePaise,
                'opening_wallet_count' => $openingWalletCount,
                'cards_to_issue_count' => $cardsToIssueCount,
                'paid_count' => $paidCount,
                'pending_count' => $pendingCount,
            ],
        ];
    }

    /**
     * Save a dry-run preview batch record prior to operator confirmation.
     */
    public function createPreviewBatch(array $preview, int $userId): ImportBatch
    {
        return ImportBatch::create([
            'batch_number' => $this->nextImportBatchNumber(),
            'type' => 'members',
            'total_rows' => (int) ($preview['total_rows'] ?? 0),
            'imported_rows' => 0,
            'failed_rows' => (int) ($preview['failed_count'] ?? 0),
            'preview_data' => [
                'headers' => $preview['headers'] ?? [],
                'valid_rows' => $preview['valid_rows'] ?? [],
                'warnings' => $preview['warnings'] ?? [],
                'summary' => $preview['summary'] ?? [],
            ],
            'errors' => $preview['errors'] ?? [],
            'status' => 'preview',
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Execute approved member import batch cleanly without issuing current-day PaymentReceipts for historical fees,
     * and posting one reconciled opening journal (Debit 3010 / Credit 2010) when opening wallet balances exist.
     */
    public function executeImport(array $validRows, int $userId, array $options = []): ImportBatch
    {
        return DB::transaction(function () use ($validRows, $userId, $options) {
            /** @var ImportBatch|null $existingBatch */
            $existingBatch = null;
            if (! empty($options['import_batch_id'])) {
                $existingBatch = ImportBatch::where('id', (int) $options['import_batch_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($existingBatch->status !== 'preview') {
                    throw new InvalidArgumentException("Import batch '{$existingBatch->batch_number}' has already been {$existingBatch->status}.");
                }
            }

            $batchNumber = $existingBatch ? $existingBatch->batch_number : $this->nextImportBatchNumber();

            $defaultCategory = MembershipCategory::firstOrCreate(
                ['code' => 'ORD'],
                ['name' => 'Ordinary Member', 'admission_fee_paise' => 5000000, 'annual_fee_paise' => 1200000, 'has_voting_rights' => true, 'is_active' => true]
            );

            $categoriesByCode = MembershipCategory::all()->keyBy(fn (MembershipCategory $c) => strtoupper(trim($c->code)));

            $candidateNums = array_values(array_filter(array_map(fn ($r) => trim((string) ($r['membership_number'] ?? '')), $validRows)));
            foreach (array_chunk($candidateNums, 500) as $chunk) {
                $clash = Member::whereIn('membership_number', $chunk)->first();
                if ($clash) {
                    throw new InvalidArgumentException("Membership number '{$clash->membership_number}' was already created before commit.");
                }
            }

            $candidateCards = array_values(array_filter(array_map(fn ($r) => trim((string) ($r['rfid_card_number'] ?? '')), $validRows)));
            foreach (array_chunk($candidateCards, 500) as $chunk) {
                $cardClash = Card::whereIn('card_number', $chunk)->first();
                if ($cardClash) {
                    throw new InvalidArgumentException("RFID card '{$cardClash->card_number}' is already assigned in the database.");
                }
            }

            // Determine if opening balances will post an accounting journal (and assert period is open before mutating).
            $batchOpeningTotalCheck = array_sum(array_map(fn ($r) => (int) ($r['opening_balance_paise'] ?? 0), $validRows));
            $openingDateStr = (string) ($options['opening_balance_date'] ?? app(BusinessDateService::class)->getBusinessDate());
            $openingDate = Carbon::parse($openingDateStr);
            if ($batchOpeningTotalCheck > 0) {
                app(PeriodClosingService::class)->assertDateNotClosed($openingDate);
            }

            if ($existingBatch) {
                $batch = $existingBatch;
            } else {
                $batch = ImportBatch::create([
                    'batch_number' => $batchNumber,
                    'type' => 'members',
                    'total_rows' => count($validRows),
                    'imported_rows' => 0,
                    'failed_rows' => 0,
                    'status' => 'preview',
                    'created_by_user_id' => $userId,
                ]);
            }

            /** @var CardService $cardService */
            $cardService = app(CardService::class);
            $feeModeDefault = ($options['fee_mode'] ?? 'already_paid') === 'pending' ? 'pending' : 'already_paid';

            $imported = 0;
            $totalOpeningBalancePaise = 0;
            $openingWalletCount = 0;
            $cardsIssuedCount = 0;

            foreach ($validRows as $row) {
                $catCode = strtoupper(trim((string) ($row['category_code'] ?? 'ORD')));
                /** @var MembershipCategory $category */
                $category = $categoriesByCode->get($catCode) ?? $defaultCategory;

                $joinedAt = $row['joined_at'] ?? now()->toDateString();
                $isPermanent = ((int) $category->annual_fee_paise) === 0;
                $validUntil = array_key_exists('valid_until', $row)
                    ? ($row['valid_until'] ?: null)
                    : ($isPermanent ? null : self::defaultAnnualValidUntilDate());

                $statusEnum = MemberStatus::tryFrom((string) ($row['status'] ?? 'active')) ?? MemberStatus::ACTIVE;

                $admissionPaise = array_key_exists('admission_fee_paise', $row)
                    ? (int) $row['admission_fee_paise']
                    : (int) ($category->admission_fee_paise ?? 0);

                $defaultAdmissionStatus = $feeModeDefault === 'already_paid'
                    ? (((int) ($category->admission_fee_paise ?? 0)) === 0 && $admissionPaise === 0 ? 'waived' : 'paid')
                    : (((int) ($category->admission_fee_paise ?? 0)) === 0 ? 'waived' : 'pending');
                $admissionStatus = $row['admission_fee_status'] ?? $defaultAdmissionStatus;

                $isExpired = $statusEnum === MemberStatus::EXPIRED
                    || ($validUntil !== null && Carbon::parse($validUntil)->endOfDay()->lt(now()->startOfDay()));
                $defaultAnnualStatus = $feeModeDefault === 'already_paid'
                    ? ($isExpired ? 'pending' : 'paid')
                    : 'pending';
                $annualStatus = $row['annual_fee_status'] ?? $defaultAnnualStatus;

                $feePaidDate = ! empty($row['fee_paid_date']) ? Carbon::parse((string) $row['fee_paid_date']) : Carbon::parse((string) $joinedAt);
                $historicalRef = trim((string) ($row['historical_receipt_ref'] ?? ''));

                $member = $this->registerMember(
                    personData: [
                        'first_name' => $row['first_name'] ?? 'Member',
                        'last_name' => ($row['last_name'] ?? '') !== '' ? $row['last_name'] : null,
                        'phone' => ($row['phone'] ?? '') !== '' ? $row['phone'] : null,
                        'email' => ($row['email'] ?? '') !== '' ? $row['email'] : null,
                        'id_proof_type' => ($row['id_proof_type'] ?? '') !== '' ? $row['id_proof_type'] : null,
                        'id_proof_number' => ($row['id_proof_number'] ?? '') !== '' ? $row['id_proof_number'] : null,
                        'notes' => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
                    ],
                    categoryId: $category->id,
                    membershipNumber: $row['membership_number'],
                    joinedAt: $joinedAt,
                    validUntil: $validUntil,
                    memberAttributes: [
                        'status' => $statusEnum,
                        'admission_fee_status' => $admissionStatus,
                        'admission_fee_paise' => $admissionPaise,
                        'admission_fee_paid_at' => $admissionStatus === 'paid' ? $feePaidDate : null,
                        'admission_fee_receipt_number' => $admissionStatus === 'paid' && $historicalRef !== '' ? $historicalRef : null,
                        'fee_status' => $annualStatus,
                        'last_fee_paid_at' => $annualStatus === 'paid' ? $feePaidDate : null,
                        'last_fee_receipt_number' => $annualStatus === 'paid' && $historicalRef !== '' ? $historicalRef : null,
                    ]
                );

                if (! empty($row['rfid_card_number'])) {
                    $cardService->issueCard($member, (string) $row['rfid_card_number'], "Bulk import batch {$batchNumber}");
                    $cardsIssuedCount++;
                }

                $openingPaise = (int) ($row['opening_balance_paise'] ?? 0);
                if ($openingPaise > 0 && $member->wallet) {
                    /** @var WalletAccount $lockedWallet */
                    $lockedWallet = WalletAccount::where('id', $member->wallet->id)->lockForUpdate()->firstOrFail();
                    $newBal = $lockedWallet->balance_paise + $openingPaise;
                    $lockedWallet->update([
                        'balance_paise' => $newBal,
                        'version' => $lockedWallet->version + 1,
                    ]);

                    WalletTransaction::create([
                        'wallet_account_id' => $lockedWallet->id,
                        'type' => 'opening_balance',
                        'amount_paise' => $openingPaise,
                        'balance_after_paise' => $newBal,
                        'reference_type' => 'import_batch',
                        'reference_id' => $batch->id,
                        'notes' => "Opening prepaid balance migrated via Batch {$batchNumber}",
                        'created_by_user_id' => $userId,
                    ]);

                    $totalOpeningBalancePaise += $openingPaise;
                    $openingWalletCount++;
                }

                $noteParts = [
                    "Onboarded via Bulk Import Batch #{$batchNumber} ({$category->code}).",
                    'Admission Fee: ' . ucfirst($admissionStatus) . ' (₹' . number_format($admissionPaise / 100, 2) . ').',
                    'Annual Subscription: ' . ucfirst($annualStatus) . ($validUntil ? ' (valid until ' . Carbon::parse($validUntil)->format('d M Y') . ').' : ' (lifetime/continuous).'),
                ];
                if ($historicalRef !== '') {
                    $noteParts[] = "Historical Ref: {$historicalRef}.";
                }
                if ($openingPaise > 0) {
                    $noteParts[] = 'Opening Prepaid Balance: ₹' . number_format($openingPaise / 100, 2) . '.';
                }
                if (empty($row['phone'])) {
                    $noteParts[] = 'Note: Mobile number missing at migration — add phone before Member Portal PIN setup.';
                }

                MemberNote::create([
                    'member_id' => $member->id,
                    'created_by_user_id' => $userId,
                    'category' => 'general',
                    'note' => implode(' ', $noteParts),
                ]);

                $imported++;
            }

            $journalNumber = null;
            if ($totalOpeningBalancePaise > 0) {
                $journal = app(JournalService::class)->post(
                    sourceType: 'opening_balance',
                    sourceId: $batch->id,
                    description: "Opening member prepaid wallet balances — Batch {$batchNumber} ({$openingWalletCount} accounts)",
                    lines: [
                        [
                            'account_code' => '3010',
                            'debit_paise' => $totalOpeningBalancePaise,
                            'narration' => "Opening capital/reserve offset for migrated member wallet advances ({$batchNumber})",
                        ],
                        [
                            'account_code' => '2010',
                            'credit_paise' => $totalOpeningBalancePaise,
                            'narration' => "Member prepaid advances liability migrated via {$batchNumber}",
                        ],
                    ],
                    businessDate: $openingDate,
                    userId: $userId,
                    queueOutbox: (bool) ($options['queue_tally_outbox'] ?? false)
                );
                $journalNumber = $journal->journal_number;
            }

            $previewData = is_array($batch->preview_data) ? $batch->preview_data : [];
            $previewData['execution_summary'] = [
                'imported_count' => $imported,
                'cards_issued_count' => $cardsIssuedCount,
                'opening_wallet_count' => $openingWalletCount,
                'total_opening_balance_paise' => $totalOpeningBalancePaise,
                'journal_number' => $journalNumber,
                'opening_balance_date' => $openingDate->toDateString(),
                'committed_at' => now()->toDateTimeString(),
            ];

            $batch->update([
                'imported_rows' => $imported,
                'total_rows' => max((int) $batch->total_rows, $imported + (int) $batch->failed_rows),
                'preview_data' => $previewData,
                'status' => 'completed',
            ]);

            return $batch->fresh();
        });
    }

    private function nextImportBatchNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = "IMP-{$yearMonth}-";
        $maxExisting = ImportBatch::where('batch_number', 'LIKE', "{$prefix}%")
            ->pluck('batch_number')
            ->map(function (string $bn) use ($prefix) {
                return (int) str_replace($prefix, '', $bn);
            })
            ->max() ?? 0;

        return sprintf('%s%05d', $prefix, $maxExisting + 1);
    }

    /**
     * Parse raw CSV string (stripping UTF-8 BOM, normalizing aliases, supporting quoted multi-line cells).
     *
     * @return array{headers: array<int, string>, rows: array<int, array{row: int, data: array<string, string>}>}
     */
    private function parseCsvDocument(string $csvContent): array
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent) ?? $csvContent;
        $clean = str_replace(["\r\n", "\r"], "\n", trim($clean));
        if ($clean === '') {
            return ['headers' => [], 'rows' => []];
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $clean);
        rewind($stream);

        $rawHeader = fgetcsv($stream);
        if (! is_array($rawHeader) || empty(array_filter($rawHeader, fn ($v) => trim((string) $v) !== ''))) {
            fclose($stream);

            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(fn ($col) => $this->normalizeHeaderColumn((string) $col), $rawHeader);
        $rows = [];
        $rowIndex = 1;

        while (($cols = fgetcsv($stream)) !== false) {
            $rowIndex++;
            if (! is_array($cols)) {
                continue;
            }
            // Skip completely empty blank lines
            $hasAnyValue = false;
            foreach ($cols as $cell) {
                if (trim((string) $cell) !== '') {
                    $hasAnyValue = true;
                    break;
                }
            }
            if (! $hasAnyValue) {
                continue;
            }

            if (count($cols) < count($headers)) {
                $cols = array_pad($cols, count($headers), '');
            } elseif (count($cols) > count($headers)) {
                $cols = array_slice($cols, 0, count($headers));
            }

            $assoc = [];
            foreach ($headers as $i => $h) {
                if ($h !== '') {
                    $assoc[$h] = trim((string) ($cols[$i] ?? ''));
                }
            }

            $rows[] = [
                'row' => $rowIndex,
                'data' => $assoc,
            ];
        }

        fclose($stream);

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function normalizeHeaderColumn(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[\s\-]+/', '_', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized) ?? $normalized;

        return match ($normalized) {
            'member_no', 'member_number', 'membership_no', 'member_' => 'membership_number',
            'mobile', 'mobile_number', 'phone_number', 'contact_phone' => 'phone',
            'category', 'membership_category', 'category_id' => 'category_code',
            'join_date', 'date_of_admission', 'admission_date' => 'joined_at',
            'expiry_date', 'validity_date', 'valid_till', 'membership_valid_until' => 'valid_until',
            'admission_fee', 'admission_fee_rupees' => 'admission_fee_amount_rupees',
            'fee_status', 'annual_dues_status' => 'annual_fee_status',
            'receipt_no', 'receipt_ref', 'receipt_number', 'last_receipt' => 'historical_receipt_ref',
            'opening_balance', 'wallet_balance', 'opening_wallet_balance', 'wallet_balance_rupees' => 'opening_balance_rupees',
            'card_number', 'rfid', 'rfid_card', 'smartcard_uid' => 'rfid_card_number',
            default => $normalized,
        };
    }

    private function parseFlexibleDate(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }

        $formats = ['!Y-m-d', '!d/m/Y', '!j/n/Y', '!d-m-Y', '!j-n-Y', '!Y/m/d'];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $v);
                $errors = Carbon::getLastErrors();
                if (
                    $dt !== false
                    && (empty($errors) || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))
                    && $dt->format(ltrim($fmt, '!')) === $v
                ) {
                    return $dt->toDateString();
                }
            } catch (\Throwable) {
                // try next format
            }
        }

        // Allow named month strings like "15 Jan 2021" while rejecting arbitrary invalid strings
        if (preg_match('/[a-zA-Z]{3}/', $v)) {
            try {
                return Carbon::parse($v)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function parseRupeesToPaise(string $raw): int
    {
        $clean = str_replace([',', '₹', 'Rs.', 'Rs', 'INR', ' '], '', trim($raw));
        if ($clean === '') {
            return 0;
        }

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $clean, $matches)) {
            throw new InvalidArgumentException("Invalid rupee amount '{$raw}'.");
        }

        if ($matches[1] === '-') {
            throw new InvalidArgumentException("Negative amount '{$raw}' is not permitted.");
        }

        $decimals = $matches[3] ?? '';
        if (strlen($decimals) > 2) {
            throw new InvalidArgumentException("Amount '{$raw}' cannot exceed 2 decimal places.");
        }

        $rupeesInt = (int) $matches[2];
        $paisePart = (int) str_pad($decimals, 2, '0', STR_PAD_RIGHT);

        return ($rupeesInt * 100) + $paisePart;
    }

    private function normalizeFeeStatus(string $raw, string $defaultStatus): string
    {
        $clean = strtolower(trim($raw));
        if ($clean === '') {
            return $defaultStatus;
        }

        return match ($clean) {
            'paid', 'settled', 'yes', 'y', 'true', '1' => 'paid',
            'waived', 'exempt', 'honorary', 'nil', 'na', 'n/a' => 'waived',
            'pending', 'unpaid', 'due', 'no', 'n', 'false', '0' => 'pending',
            default => throw new InvalidArgumentException("Invalid fee status '{$raw}'. Use paid, waived, or pending."),
        };
    }

    private function sanitizeCsvCell(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        // Neutralize leading + / - formulas while keeping valid phone prefixes (+91...) intact
        if (($firstChar === '+' || $firstChar === '-') && isset($value[1]) && ! ctype_digit($value[1])) {
            return "'" . $value;
        }

        return $value;
    }
}
