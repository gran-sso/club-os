<?php

namespace App\Domain\Integrations;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Shared\Enums\AccountType;
use App\Domain\Shared\Models\SystemSetting;
use DOMDocument;
use SimpleXMLElement;

class TallyXmlSerializer
{
    public function resolveTargetCompany(?string $override = null): string
    {
        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        $company = SystemSetting::get('tally_company_name')
            ?: SystemSetting::get('tally_company')
            ?: config('services.tally.company', 'Malabar Club');

        $company = trim((string) $company);

        return $company !== '' ? $company : 'Malabar Club';
    }

    public function resolveIncludeCostCentres(array $options = []): bool
    {
        if (array_key_exists('include_cost_centres', $options)) {
            return (bool) $options['include_cost_centres'];
        }

        return SystemSetting::getBool(
            'tally_include_cost_centres',
            (bool) config('services.tally.include_cost_centres', false)
        );
    }

    /**
     * Serialize a list of JournalHeaders into valid TallyPrime Import Data XML format.
     *
     * @param iterable<JournalHeader> $journalHeaders
     * @param array{company_name?: string|null, include_cost_centres?: bool} $options
     */
    public function serializeVouchers(iterable $journalHeaders, array $options = []): string
    {
        $companyName = $this->resolveTargetCompany($options['company_name'] ?? null);
        $includeCostCentres = $this->resolveIncludeCostCentres($options);

        $xml = new SimpleXMLElement('<ENVELOPE/>');
        $header = $xml->addChild('HEADER');
        $this->addTextNode($header, 'TALLYREQUEST', 'Import Data');

        $body = $xml->addChild('BODY');
        $importData = $body->addChild('IMPORTDATA');

        $reqDesc = $importData->addChild('REQUESTDESC');
        $this->addTextNode($reqDesc, 'REPORTNAME', 'Vouchers');
        $staticVars = $reqDesc->addChild('STATICVARIABLES');
        $this->addTextNode($staticVars, 'SVCURRENTCOMPANY', $companyName);

        $reqData = $importData->addChild('REQUESTDATA');

        foreach ($journalHeaders as $journal) {
            if ($journal instanceof JournalHeader) {
                $journal->loadMissing(['lines.account']);
            }

            $tallyMsg = $reqData->addChild('TALLYMESSAGE');
            $voucher = $tallyMsg->addChild('VOUCHER');

            $voucherDate = $journal->business_date->format('Ymd');
            $guid = 'malabar-club-jnl-' . $journal->journal_number;

            $voucher->addAttribute('VCHTYPE', 'Journal');
            $voucher->addAttribute('ACTION', 'Create');
            $voucher->addAttribute('OBJVIEW', 'Accounting Voucher View');

            $this->addTextNode($voucher, 'DATE', $voucherDate);
            $this->addTextNode($voucher, 'EFFECTIVEDATE', $voucherDate);
            $this->addTextNode($voucher, 'GUID', $guid);
            $this->addTextNode($voucher, 'REMOTEID', $guid);
            $this->addTextNode($voucher, 'VOUCHERTYPENAME', 'Journal');
            $this->addTextNode($voucher, 'VOUCHERNUMBER', (string) $journal->journal_number);
            $this->addTextNode($voucher, 'PERSISTEDVIEW', 'Accounting Voucher View');
            $this->addTextNode($voucher, 'NARRATION', (string) ($journal->description ?? ''));

            foreach ($journal->lines as $line) {
                $ledgerName = trim((string) ($line->account?->name ?? ''));
                if ($ledgerName === '') {
                    continue;
                }

                $isDebit = (int) $line->debit_paise > 0;
                $paise = $isDebit ? (int) $line->debit_paise : (int) $line->credit_paise;
                // Tally XML convention: debit amounts are negative with ISDEEMEDPOSITIVE=Yes
                $signedAmount = number_format(($isDebit ? -1 : 1) * ($paise / 100), 2, '.', '');
                $deemedPositive = $isDebit ? 'Yes' : 'No';

                $ledgerEntry = $voucher->addChild('ALLLEDGERENTRIES.LIST');
                $this->addTextNode($ledgerEntry, 'LEDGERNAME', $ledgerName);
                $this->addTextNode($ledgerEntry, 'ISDEEMEDPOSITIVE', $deemedPositive);
                $this->addTextNode($ledgerEntry, 'AMOUNT', $signedAmount);

                $accountType = $line->account?->type;
                $isPnLAccount = in_array($accountType, [AccountType::REVENUE, AccountType::EXPENSE], true);

                if ($includeCostCentres && $isPnLAccount && filled($line->department_code)) {
                    $catAlloc = $ledgerEntry->addChild('CATEGORYALLOCATIONS.LIST');
                    $this->addTextNode($catAlloc, 'CATEGORY', 'Primary Cost Category');
                    $this->addTextNode($catAlloc, 'ISDEEMEDPOSITIVE', $deemedPositive);

                    $ccAlloc = $catAlloc->addChild('COSTCENTREALLOCATIONS.LIST');
                    $this->addTextNode($ccAlloc, 'NAME', (string) $line->department_code);
                    $this->addTextNode($ccAlloc, 'AMOUNT', $signedAmount);
                }
            }
        }

        return $this->formatXml($xml);
    }

    /**
     * Serialize Club Chart of Accounts Ledgers (and optional Department Cost Centres)
     * into TallyPrime All Masters Import XML so a Test/Target Company can be initialized cleanly.
     *
     * @param iterable<Account> $accounts
     * @param iterable<\App\Domain\Tariff\Models\Department> $departments
     * @param array{company_name?: string|null, include_cost_centres?: bool} $options
     */
    public function serializeMasters(iterable $accounts, iterable $departments = [], array $options = []): string
    {
        $companyName = $this->resolveTargetCompany($options['company_name'] ?? null);
        $includeCostCentres = $this->resolveIncludeCostCentres($options);

        $xml = new SimpleXMLElement('<ENVELOPE/>');
        $header = $xml->addChild('HEADER');
        $this->addTextNode($header, 'TALLYREQUEST', 'Import Data');

        $body = $xml->addChild('BODY');
        $importData = $body->addChild('IMPORTDATA');

        $reqDesc = $importData->addChild('REQUESTDESC');
        $this->addTextNode($reqDesc, 'REPORTNAME', 'All Masters');
        $staticVars = $reqDesc->addChild('STATICVARIABLES');
        $this->addTextNode($staticVars, 'SVCURRENTCOMPANY', $companyName);

        $reqData = $importData->addChild('REQUESTDATA');

        foreach ($accounts as $account) {
            $tallyMsg = $reqData->addChild('TALLYMESSAGE');
            $ledger = $tallyMsg->addChild('LEDGER');
            $ledger->addAttribute('NAME', $account->name);
            $ledger->addAttribute('ACTION', 'Create');

            $nameList = $ledger->addChild('LANGUAGENAME.LIST');
            $names = $nameList->addChild('NAME.LIST');
            $this->addTextNode($names, 'NAME', $account->name);

            $isPnL = in_array($account->type, [AccountType::REVENUE, AccountType::EXPENSE], true);
            $this->addTextNode($ledger, 'PARENT', $this->resolveTallyParentGroup($account));
            $this->addTextNode($ledger, 'ISCOSTCENTRESON', ($includeCostCentres && $isPnL) ? 'Yes' : 'No');
            $this->addTextNode($ledger, 'AFFECTSSTOCK', 'No');
            $this->addTextNode($ledger, 'MAILINGNAME', $account->name);
        }

        if ($includeCostCentres) {
            foreach ($departments as $department) {
                $tallyMsg = $reqData->addChild('TALLYMESSAGE');
                $cc = $tallyMsg->addChild('COSTCENTRE');
                $cc->addAttribute('NAME', $department->code);
                $cc->addAttribute('ACTION', 'Create');

                $nameList = $cc->addChild('LANGUAGENAME.LIST');
                $names = $nameList->addChild('NAME.LIST');
                $this->addTextNode($names, 'NAME', $department->code);

                $this->addTextNode($cc, 'CATEGORY', 'Primary Cost Category');
            }
        }

        return $this->formatXml($xml);
    }

    public function resolveTallyParentGroup(Account $account): string
    {
        return match ($account->code) {
            '1010' => 'Cash-in-Hand',
            '1020' => 'Bank Accounts',
            '2030' => 'Duties & Taxes',
            '5010', '5020' => 'Direct Expenses',
            default => match ($account->type) {
                AccountType::ASSET => 'Current Assets',
                AccountType::LIABILITY => 'Current Liabilities',
                AccountType::EQUITY => 'Capital Account',
                AccountType::REVENUE => 'Direct Incomes',
                AccountType::EXPENSE => 'Indirect Expenses',
            },
        };
    }

    protected function addTextNode(SimpleXMLElement $parent, string $name, string $value): SimpleXMLElement
    {
        $node = $parent->addChild($name);
        $node[0] = $value;

        return $node;
    }

    protected function formatXml(SimpleXMLElement $xml): string
    {
        $dom = dom_import_simplexml($xml)->ownerDocument;
        if ($dom instanceof DOMDocument) {
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            return (string) $dom->saveXML();
        }

        return (string) $xml->asXML();
    }
}
