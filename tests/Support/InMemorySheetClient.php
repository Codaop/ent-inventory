<?php

namespace Tests\Support;

use App\Sheets\SheetClient;

/**
 * In-memory SheetClient fake. Constructor receives full sheet contents
 * including the header row, mirroring how a real spreadsheet is laid out.
 */
final class InMemorySheetClient implements SheetClient
{
    /**
     * @param  array<string, list<list<mixed>>>  $sheets  sheet name => rows (including header)
     */
    public function __construct(private array $sheets = []) {}

    public function readRows(string $sheetName): array
    {
        $rows = $this->sheets[$sheetName] ?? [];

        return array_values(array_slice($rows, 1));
    }

    public function appendRow(string $sheetName, array $row): void
    {
        $this->sheets[$sheetName][] = $row;
    }

    public function updateCells(string $sheetName, int $spreadsheetRow, array $cellsByColumn): void
    {
        $rowIndex = $spreadsheetRow - 1;

        while (count($this->sheets[$sheetName] ?? []) <= $rowIndex) {
            $this->sheets[$sheetName][] = [];
        }

        foreach ($cellsByColumn as $column => $value) {
            $cellIndex = $column - 1;

            while (count($this->sheets[$sheetName][$rowIndex]) <= $cellIndex) {
                $this->sheets[$sheetName][$rowIndex][] = '';
            }

            $this->sheets[$sheetName][$rowIndex][$cellIndex] = $value;
        }
    }

    /**
     * Full sheet contents including header row.
     *
     * @return list<list<mixed>>
     */
    public function sheet(string $sheetName): array
    {
        return $this->sheets[$sheetName] ?? [];
    }
}
