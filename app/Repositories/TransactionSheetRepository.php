<?php

namespace App\Repositories;

use App\Sheets\SheetClient;
use App\Sheets\SheetValue;

class TransactionSheetRepository
{
    /**
     * Transactions sheet column order (header row from Trigger.gs):
     * txId | timestamp | userEmail | userDisplayName | itemId | itemName |
     * qty | type | status | borrowDate | dueDate | returnDate | approvedBy | notes.
     *
     * Payload keys mirror the rowToTx_() mapping in Code.gs.
     */
    private const COLUMNS = [
        'txId', 'timestamp', 'userEmail', 'userDisplay', 'itemId', 'itemName', 'qty',
        'type', 'status', 'borrowDate', 'dueDate', 'returnDate', 'approvedBy', 'notes',
    ];

    /**
     * Mutable field => 1-based column, mirroring the getRange() cell writes
     * of approveRequest()/rejectRequest()/returnItem() in Borrow.gs.
     */
    private const UPDATE_COLUMNS = [
        'timestamp' => 2,
        'userEmail' => 3,
        'userDisplay' => 4,
        'itemId' => 5,
        'itemName' => 6,
        'qty' => 7,
        'type' => 8,
        'status' => 9,
        'borrowDate' => 10,
        'dueDate' => 11,
        'returnDate' => 12,
        'approvedBy' => 13,
        'notes' => 14,
    ];

    public function __construct(private readonly SheetClient $client) {}

    /**
     * @param  array<string, mixed>  $transaction
     */
    public function appendTransaction(array $transaction): void
    {
        $row = [];

        foreach (self::COLUMNS as $column) {
            $row[] = $transaction[$column] ?? '';
        }

        $this->client->appendRow('Transactions', $row);
    }

    /**
     * @return array{txId: string, timestamp: string, userEmail: string, userDisplay: string, itemId: string, itemName: string, qty: int|float, type: string, status: string, borrowDate: string, dueDate: string, returnDate: string, approvedBy: string, notes: string}|null
     */
    public function findTransaction(string $txId): ?array
    {
        foreach ($this->client->readRows('Transactions') as $row) {
            if (($row[0] ?? '') === $txId) {
                return $this->mapTransaction($row);
            }
        }

        return null;
    }

    /**
     * Update specific fields of a transaction row by column name, mirroring
     * the cell-level writes of approve/reject/return in Borrow.gs. Unknown
     * field names are ignored; a missing transaction returns false.
     *
     * @param  array<string, mixed>  $changes
     */
    public function updateTransaction(string $txId, array $changes): bool
    {
        foreach ($this->client->readRows('Transactions') as $index => $row) {
            if (($row[0] ?? '') !== $txId) {
                continue;
            }

            $cells = [];

            foreach ($changes as $field => $value) {
                if (isset(self::UPDATE_COLUMNS[$field])) {
                    $cells[self::UPDATE_COLUMNS[$field]] = $value;
                }
            }

            if ($cells !== []) {
                $this->client->updateCells('Transactions', $index + 2, $cells);
            }

            return true;
        }

        return false;
    }

    /**
     * All transactions in sheet order (oldest first); callers reverse when
     * they need newest-first, matching how getTransactions() worked.
     *
     * @return list<array{txId: string, timestamp: string, userEmail: string, userDisplay: string, itemId: string, itemName: string, qty: int|float, type: string, status: string, borrowDate: string, dueDate: string, returnDate: string, approvedBy: string, notes: string}>
     */
    public function allTransactions(): array
    {
        $transactions = [];

        foreach ($this->client->readRows('Transactions') as $row) {
            if (! isset($row[0]) || $row[0] === '') {
                continue;
            }

            $transactions[] = $this->mapTransaction($row);
        }

        return $transactions;
    }

    /**
     * @param  list<mixed>  $row
     * @return array{txId: string, timestamp: string, userEmail: string, userDisplay: string, itemId: string, itemName: string, qty: int|float, type: string, status: string, borrowDate: string, dueDate: string, returnDate: string, approvedBy: string, notes: string}
     */
    private function mapTransaction(array $row): array
    {
        $values = [];

        foreach (self::COLUMNS as $index => $column) {
            $values[$column] = $row[$index] ?? '';
        }

        return [
            'txId' => (string) $values['txId'],
            'timestamp' => SheetValue::toDateString($values['timestamp']),
            'userEmail' => (string) $values['userEmail'],
            'userDisplay' => (string) $values['userDisplay'],
            'itemId' => (string) $values['itemId'],
            'itemName' => (string) $values['itemName'],
            'qty' => SheetValue::toNumber($values['qty']),
            'type' => (string) $values['type'],
            'status' => (string) $values['status'],
            'borrowDate' => SheetValue::toDateString($values['borrowDate']),
            'dueDate' => SheetValue::toDateString($values['dueDate']),
            'returnDate' => SheetValue::toDateString($values['returnDate']),
            'approvedBy' => (string) $values['approvedBy'],
            'notes' => (string) $values['notes'],
        ];
    }
}
