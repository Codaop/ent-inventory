<?php

namespace App\Sheets;

interface SheetClient
{
    /**
     * Read all data rows of a sheet, excluding the header row.
     *
     * @return list<list<mixed>>
     */
    public function readRows(string $sheetName): array;

    /**
     * Append a row to the end of a sheet.
     *
     * @param  list<mixed>  $row
     */
    public function appendRow(string $sheetName, array $row): void;

    /**
     * Update individual cells of a row. Column numbers are 1-based,
     * matching the Apps Script getRange(row, column) convention.
     *
     * @param  array<int, mixed>  $cellsByColumn  1-based column number => value
     */
    public function updateCells(string $sheetName, int $spreadsheetRow, array $cellsByColumn): void;
}
