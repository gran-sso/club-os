<?php

namespace App\Domain\Integrations;

use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Models\JournalLine;

class TallyXmlSerializer
{
    /**
     * Serialize a list of JournalHeaders into valid Tally Prime import XML format.
     *
     * @param iterable<JournalHeader> $journalHeaders
     */
    public function serializeVouchers(iterable $journalHeaders): string
    {
        $xml = new \SimpleXMLElement('<ENVELOPE/>');
        
        $header = $xml->addChild('HEADER');
        $header->addChild('TALLYREQUEST', 'Import Data');

        $body = $xml->addChild('BODY');
        $importData = $body->addChild('IMPORTDATA');
        $reqData = $importData->addChild('REQUESTDATA');

        foreach ($journalHeaders as $journal) {
            $tallyMsg = $reqData->addChild('TALLYMESSAGE');
            $voucher = $tallyMsg->addChild('VOUCHER');
            
            // Format YYYYMMDD for Tally
            $voucherDate = $journal->business_date->format('Ymd');
            $voucher->addAttribute('VCHTYPE', 'Journal');
            $voucher->addAttribute('ACTION', 'Create');
            $voucher->addChild('DATE', $voucherDate);
            $voucher->addChild('VOUCHERTYPENAME', 'Journal');
            $voucher->addChild('VOUCHERNUMBER', $journal->journal_number);
            $voucher->addChild('NARRATION', htmlspecialchars($journal->description));

            foreach ($journal->lines as $line) {
                $ledgerEntry = $voucher->addChild('ALLLEDGERENTRIES.LIST');
                $ledgerEntry->addChild('LEDGERNAME', htmlspecialchars($line->account->name));

                // In Tally: Debits are positive (IsDeemedPositive = Yes), Credits are negative (IsDeemedPositive = No)
                if ($line->debit_paise > 0) {
                    $ledgerEntry->addChild('ISDEEMEDPOSITIVE', 'Yes');
                    $amountRupees = -1 * ($line->debit_paise / 100.0); // Tally convention: debits have negative sign in AMOUNT tag
                    $ledgerEntry->addChild('AMOUNT', number_format($amountRupees, 2, '.', ''));
                } else {
                    $ledgerEntry->addChild('ISDEEMEDPOSITIVE', 'No');
                    $amountRupees = ($line->credit_paise / 100.0);
                    $ledgerEntry->addChild('AMOUNT', number_format($amountRupees, 2, '.', ''));
                }

                if ($line->department_code) {
                    $costCentre = $ledgerEntry->addChild('COSTCENTRE.LIST');
                    $costCentre->addChild('NAME', htmlspecialchars($line->department_code));
                }
            }
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }
}
