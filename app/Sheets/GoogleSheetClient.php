<?php

namespace App\Sheets;

use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateValuesRequest;
use Google\Service\Sheets\ValueRange;

/**
 * Production SheetClient backed by the Google Sheets API v4 via a
 * service account. Reads use UNFORMATTED_VALUE so dates arrive as
 * serial numbers (converted by SheetValue) or raw strings; writes use
 * RAW so user-supplied strings such as SKUs are never parsed as
 * formulas or locale-dependent values.
 */
class GoogleSheetClient implements SheetClient
{
    private readonly Sheets $service;

    public function __construct(
        GoogleClient $google,
        private readonly string $spreadsheetId,
    ) {
        $this->service = new Sheets($google);
    }

    public function readRows(string $sheetName): array
    {
        try {
            $response = $this->service->spreadsheets_values->get(
                $this->spreadsheetId,
                $this->quote($sheetName),
                ['valueRenderOption' => 'UNFORMATTED_VALUE'],
            );

            $values = $response->getValues() ?? [];

            return array_values(array_slice($values, 1));
        } catch (\Throwable $e) {
            error_log('[Sheets] readRows(' . $sheetName . ') failed: ' . $e->getMessage());
            return [];
        }
    }

    public function appendRow(string $sheetName, array $row): void
    {
        try {
            $this->service->spreadsheets_values->append(
                $this->spreadsheetId,
                $this->tableRange($sheetName),
                new ValueRange(['values' => [$row]]),
                ['valueInputOption' => 'RAW'],
            );
        } catch (\Throwable $e) {
            error_log('[Sheets] appendRow(' . $sheetName . ') failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateCells(string $sheetName, int $spreadsheetRow, array $cellsByColumn): void
    {
        try {
            $data = [];

            foreach ($cellsByColumn as $column => $value) {
                $data[] = new ValueRange([
                    'range' => sprintf(
                        '%s!%s%d',
                        $this->quote($sheetName),
                        self::columnLetter((int) $column),
                        $spreadsheetRow,
                    ),
                    'values' => [[$value]],
                ]);
            }

            $this->service->spreadsheets_values->batchUpdate(
                $this->spreadsheetId,
                new BatchUpdateValuesRequest([
                    'valueInputOption' => 'RAW',
                    'data' => $data,
                ]),
            );
        } catch (\Throwable $e) {
            error_log('[Sheets] updateCells(' . $sheetName . ') failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Range starting at A1 of the sheet; the append() API treats it as the
     * table to extend, exactly like how SpreadsheetApp matched the header row.
     */
    private function tableRange(string $sheetName): string
    {
        return $this->quote($sheetName).'!A1';
    }

    private function quote(string $sheetName): string
    {
        return "'".str_replace("'", "''", $sheetName)."'";
    }

    private static function columnLetter(int $column): string
    {
        $letter = '';

        while ($column > 0) {
            $column--;
            $letter = chr(65 + ($column % 26)).$letter;
            $column = intdiv($column, 26);
        }

        return $letter;
    }
}
