<?php

namespace App\Repositories;

use App\Sheets\SheetClient;
use App\Sheets\SheetValue;
use Illuminate\Support\Str;

class ItemSheetRepository
{
    public function __construct(private readonly SheetClient $client) {}

    /**
     * List all non-deleted items, mirroring listItems() in Inventory.gs.
     *
     * @return list<array{itemId: string, sku: string, name: string, category: string, location: string, totalQty: int|float, availableQty: int|float, reorderThreshold: int|float, unitPrice: int|float, createdAt: string, updatedAt: string}>
     */
    public function allItems(): array
    {
        $items = [];

        foreach ($this->client->readRows('Items') as $row) {
            if (! isset($row[0]) || $row[0] === '') {
                continue;
            }

            if ($this->isDeleted($row)) {
                continue;
            }

            $items[] = $this->mapItem($row);
        }

        return $items;
    }

    /**
     * Find one non-deleted item by id, mirroring findItemRow_() in Inventory.gs.
     *
     * @return array{itemId: string, sku: string, name: string, category: string, location: string, totalQty: int|float, availableQty: int|float, reorderThreshold: int|float, unitPrice: int|float, createdAt: string, updatedAt: string}|null
     */
    public function findItem(string $itemId): ?array
    {
        foreach ($this->client->readRows('Items') as $row) {
            if (($row[0] ?? '') === $itemId && ! $this->isDeleted($row)) {
                return $this->mapItem($row);
            }
        }

        return null;
    }

    /**
     * Append a new item row with the defaults from createItem() in Inventory.gs
     * and return the generated UUID.
     *
     * @param  array{sku?: string, name: string, category?: string, location?: string, totalQty?: int|float|string|null, availableQty?: int|float|string|null, reorderThreshold?: int|float|string|null, unitPrice?: int|float|string|null}  $payload
     */
    public function appendItem(array $payload): string
    {
        $itemId = (string) Str::uuid();
        $now = now()->format('Y-m-d H:i:s');
        $totalQty = SheetValue::toNumber($payload['totalQty'] ?? 0);
        $availableQty = ($payload['availableQty'] ?? null) !== null
            ? SheetValue::toNumber($payload['availableQty'])
            : $totalQty;
        $reorderThreshold = SheetValue::toNumber($payload['reorderThreshold'] ?? 0) ?: 5;
        $unitPrice = SheetValue::toNumber($payload['unitPrice'] ?? 0) ?: 0;

        $this->client->appendRow('Items', [
            $itemId,
            (string) ($payload['sku'] ?? ''),
            (string) $payload['name'],
            (string) ($payload['category'] ?? ''),
            (string) ($payload['location'] ?? ''),
            $totalQty,
            $availableQty,
            $reorderThreshold,
            $unitPrice,
            $now,
            $now,
            false,
        ]);

        return $itemId;
    }

    /**
     * Partially rewrite an item row, mirroring updateItem() in Inventory.gs:
     * omitted payload fields keep their current values, createdAt and the
     * deleted flag are untouched, and updatedAt is refreshed.
     *
     * @param  array{sku?: string, name?: string, category?: string, location?: string, totalQty?: int|float|string, availableQty?: int|float|string, reorderThreshold?: int|float|string, unitPrice?: int|float|string}  $payload
     */
    public function updateItem(string $itemId, array $payload): bool
    {
        foreach ($this->client->readRows('Items') as $index => $row) {
            if (($row[0] ?? '') !== $itemId || $this->isDeleted($row)) {
                continue;
            }

            $updated = [
                $row[0],
                array_key_exists('sku', $payload) ? (string) $payload['sku'] : ($row[1] ?? ''),
                array_key_exists('name', $payload) ? (string) $payload['name'] : ($row[2] ?? ''),
                array_key_exists('category', $payload) ? (string) $payload['category'] : ($row[3] ?? ''),
                array_key_exists('location', $payload) ? (string) $payload['location'] : ($row[4] ?? ''),
                array_key_exists('totalQty', $payload) ? SheetValue::toNumber($payload['totalQty']) : SheetValue::toNumber($row[5] ?? 0),
                array_key_exists('availableQty', $payload) ? SheetValue::toNumber($payload['availableQty']) : SheetValue::toNumber($row[6] ?? 0),
                array_key_exists('reorderThreshold', $payload) ? SheetValue::toNumber($payload['reorderThreshold']) : SheetValue::toNumber($row[7] ?? 0),
                array_key_exists('unitPrice', $payload) ? SheetValue::toNumber($payload['unitPrice']) : SheetValue::toNumber($row[8] ?? 0),
                $row[9] ?? '',
                now()->format('Y-m-d H:i:s'),
                $row[11] ?? false,
            ];

            $cells = [];
            foreach ($updated as $position => $value) {
                $cells[$position + 1] = $value;
            }
            $this->client->updateCells('Items', $index + 2, $cells);

            return true;
        }

        return false;
    }

    /**
     * Soft-delete an item by flagging the deleted column, mirroring deleteItem().
     */
    public function softDeleteItem(string $itemId): bool
    {
        foreach ($this->client->readRows('Items') as $index => $row) {
            if (($row[0] ?? '') !== $itemId || $this->isDeleted($row)) {
                continue;
            }

            $this->client->updateCells('Items', $index + 2, [12 => true]);

            return true;
        }

        return false;
    }

    /**
     * Write both stock columns and refresh updatedAt, mirroring the
     * getRange() writes of adjustStock() in Inventory.gs.
     */
    public function updateQuantities(string $itemId, int|float $totalQty, int|float $availableQty): bool
    {
        $row = $this->findRawRow($itemId);

        if ($row === null) {
            return false;
        }

        $this->client->updateCells('Items', $row['spreadsheetRow'], [
            6 => $totalQty,
            7 => $availableQty,
            11 => now()->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Write only availableQty and refresh updatedAt, mirroring the cell
     * writes of approveRequest()/returnItem() in Borrow.gs.
     */
    public function updateAvailableQty(string $itemId, int|float $availableQty): bool
    {
        $row = $this->findRawRow($itemId);

        if ($row === null) {
            return false;
        }

        $this->client->updateCells('Items', $row['spreadsheetRow'], [
            7 => $availableQty,
            11 => now()->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * @return array{spreadsheetRow: int, values: list<mixed>}|null
     */
    private function findRawRow(string $itemId): ?array
    {
        foreach ($this->client->readRows('Items') as $index => $row) {
            if (($row[0] ?? '') === $itemId && ! $this->isDeleted($row)) {
                return ['spreadsheetRow' => $index + 2, 'values' => $row];
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $row
     * @return array{itemId: string, sku: string, name: string, category: string, location: string, totalQty: int|float, availableQty: int|float, reorderThreshold: int|float, unitPrice: int|float, createdAt: string, updatedAt: string}
     */
    private function mapItem(array $row): array
    {
        return [
            'itemId' => (string) $row[0],
            'sku' => (string) ($row[1] ?? ''),
            'name' => (string) ($row[2] ?? ''),
            'category' => (string) ($row[3] ?? ''),
            'location' => (string) ($row[4] ?? ''),
            'totalQty' => SheetValue::toNumber($row[5] ?? 0),
            'availableQty' => SheetValue::toNumber($row[6] ?? 0),
            'reorderThreshold' => SheetValue::toNumber($row[7] ?? 0),
            'unitPrice' => SheetValue::toNumber($row[8] ?? 0),
            'createdAt' => SheetValue::toDateString($row[9] ?? ''),
            'updatedAt' => SheetValue::toDateString($row[10] ?? ''),
        ];
    }

    /**
     * @param  list<mixed>  $row
     */
    private function isDeleted(array $row): bool
    {
        return SheetValue::toBool($row[11] ?? false);
    }
}
