<?php

namespace App\Services;

use Carbon\Carbon;

class JournalEntryTransformer
{
    public const string DEFAULT_BANK_ACCOUNT = '1110 Operating Account';
    private const array ACCOUNT_MAP = [
        'Boarding' => '4010 Boarding',
        'Day Care' => '4020 Daycare',
        'Full Service Grooming' => '4030 Grooming',
        'Additional Grooming Services' => '4030 Grooming',
        'Bath' => '4030 Grooming',
        'Nail Trims' => '4030 Grooming',
        'Pet Sitting' => '4040 Pet Sitting',
        'Training' => '4050 Training',
        'No Account Code Set' => '4060 Other',
        'Interview' => '4060 Other',
        'Retail Product' => '4070 Retail Product',
        'Enrichment' => '4080 Enrichment',
        'Tips' => '2171 Tips Payable CBW',
    ];

    public function transform(string $csvContent, string $date, string $bankAccount = self::DEFAULT_BANK_ACCOUNT): array
    {
        $warnings = [];
        $credits = []; // qboAccount => ['amount' => float, 'sources' => string[]]

        foreach ($this->parseInputCsv($csvContent) as $row) {
            $accountCode = trim($row[0] ?? '');
            $amount = (float)str_replace(',', '', trim($row[1] ?? '0'));

            if (strtolower($accountCode) === 'totals') break;
            if ($amount == 0.0) continue;

            $qboAccount = $this->mapAccount($accountCode, $warnings);

            $credits[$qboAccount] ??= ['amount' => 0.0, 'sources' => []];
            $credits[$qboAccount]['amount'] += $amount;
            $credits[$qboAccount]['sources'][] = $accountCode;
        }

        $totalAmount = round(array_sum(array_column($credits, 'amount')), 2);
        $journalNo = 'GINGR-' . $date;
        $journalDate = Carbon::parse($date)->format('m/d/Y');

        $bankIsDebit = $totalAmount >= 0;
        $jeRows = [[
            'JournalNo' => $journalNo,
            'JournalDate' => $journalDate,
            'Currency' => 'USD',
            'Account' => $bankAccount,
            'Debits' => $bankIsDebit ? $totalAmount : '',
            'Credits' => $bankIsDebit ? '' : abs($totalAmount),
            'Description' => 'Gingr Daily Revenue',
            'Name' => $bankAccount,
        ]];

        foreach ($credits as $qboAccount => $data) {
            $sources = $data['sources'];
            $description = count($sources) === 1 ? $sources[0] : $qboAccount;
            $amount = round($data['amount'], 2);
            $isCredit = $amount >= 0;

            $jeRows[] = [
                'JournalNo' => $journalNo,
                'JournalDate' => $journalDate,
                'Currency' => 'USD',
                'Account' => $qboAccount,
                'Debits' => $isCredit ? '' : abs($amount),
                'Credits' => $isCredit ? $amount : '',
                'Description' => $description,
                'Name' => '',
            ];
        }

        return [
            'rows' => $jeRows,
            'warnings' => $warnings,
            'total' => $totalAmount,
        ];
    }

    private function parseInputCsv(string $content): array
    {
        $rows = [];
        $skip = 3; // 2 description rows + 1 column header row
        $lines = preg_split('/\r\n|\r|\n/', $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($skip > 0) {
                $skip--;
                continue;
            }
            $rows[] = str_getcsv($line);
        }

        return $rows;
    }

    private function mapAccount(string $code, array &$warnings): string
    {
        if (array_key_exists($code, self::ACCOUNT_MAP)) {
            return self::ACCOUNT_MAP[$code];
        }

        $warnings[] = "Unknown account code \"{$code}\" — mapped to Other";
        return 'Other';
    }
}
