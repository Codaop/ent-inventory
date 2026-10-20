<?php

namespace Tests\Unit;

use App\Repositories\ItemSheetRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemorySheetClient;

class ItemSheetRepositoryTest extends TestCase
{
    public function test_reads_items_skipping_deleted_rows(): void
    {
        $client = new InMemorySheetClient([
            'Items' => [
                ['itemId', 'sku', 'name', 'category', 'location', 'totalQty', 'availableQty', 'reorderThreshold', 'unitPrice', 'createdAt', 'updatedAt', 'deleted'],
                ['it-1', 'SKU-1', 'Router', 'Network', 'Rack A', 10, 8, 5, 150000, '2026-01-01 10:00:00', '2026-01-02 11:00:00', false],
                ['it-2', 'SKU-2', 'Old Cable', 'Cable', 'Bin 1', 5, 5, 2, 10000, '2026-01-01 10:00:00', '2026-01-01 10:00:00', true],
                ['it-3', 'SKU-3', 'Retired AP', 'Network', 'Rack B', 2, 2, 1, 90000, '2026-01-01 10:00:00', '2026-01-01 10:00:00', 'TRUE'],
            ],
        ]);

        $repository = new ItemSheetRepository($client);

        $items = $repository->allItems();
        $this->assertCount(1, $items, 'deleted items are excluded');
        $this->assertSame('it-1', $items[0]['itemId']);
        $this->assertSame('Router', $items[0]['name']);
        $this->assertSame(8, $items[0]['availableQty']);
        $this->assertSame('2026-01-02 11:00:00', $items[0]['updatedAt']);

        $found = $repository->findItem('it-1');
        $this->assertNotNull($found);
        $this->assertSame('Network', $found['category']);

        $this->assertNull($repository->findItem('it-2'), 'deleted item is not found');
        $this->assertNull($repository->findItem('missing'), 'unknown item is not found');
    }

    public function test_admin_can_create_update_and_soft_delete_items(): void
    {
        $client = new InMemorySheetClient([
            'Items' => [
                ['itemId', 'sku', 'name', 'category', 'location', 'totalQty', 'availableQty', 'reorderThreshold', 'unitPrice', 'createdAt', 'updatedAt', 'deleted'],
            ],
            'Categories' => [['name'], ['Network']],
        ]);
        $repository = new ItemSheetRepository($client);

        $itemId = $repository->appendItem([
            'sku' => 'SKU-9',
            'name' => 'Switch',
            'category' => 'Netgear',
            'location' => 'Rack C',
            'totalQty' => 12,
            'availableQty' => null,
            'reorderThreshold' => 3,
            'unitPrice' => 200000,
        ]);

        $created = $repository->findItem($itemId);
        $this->assertNotNull($created);
        $this->assertSame('Switch', $created['name']);
        $this->assertSame(12, $created['availableQty'], 'availableQty defaults to totalQty when null');
        $this->assertSame(3, $created['reorderThreshold']);
        $this->assertSame(200000, $created['unitPrice']);
        $this->assertNotSame('', $created['createdAt']);
        $this->assertSame($created['createdAt'], $created['updatedAt'], 'freshly created item has equal timestamps');

        $repository->updateItem($itemId, ['name' => 'Switch v2', 'unitPrice' => 250000]);

        $updated = $repository->findItem($itemId);
        $this->assertSame('Switch v2', $updated['name']);
        $this->assertSame(250000, $updated['unitPrice']);
        $this->assertSame('SKU-9', $updated['sku'], 'unspecified fields are preserved');
        $this->assertSame(12, $updated['availableQty'], 'unspecified fields are preserved');

        $repository->softDeleteItem($itemId);

        $this->assertNull($repository->findItem($itemId), 'soft-deleted item is no longer found');
        $this->assertCount(0, $repository->allItems());
    }

    public function test_writes_stock_quantities_and_refreshes_updated_at(): void
    {
        $client = new InMemorySheetClient([
            'Items' => [
                ['itemId', 'sku', 'name', 'category', 'location', 'totalQty', 'availableQty', 'reorderThreshold', 'unitPrice', 'createdAt', 'updatedAt', 'deleted'],
                ['it-1', 'SKU-1', 'Router', 'Network', 'Rack A', 10, 8, 5, 150000, '2026-01-01 10:00:00', '2026-01-01 10:00:00', false],
            ],
        ]);
        $repository = new ItemSheetRepository($client);

        $this->assertTrue($repository->updateQuantities('it-1', 13, 11));

        $afterAdjust = $repository->findItem('it-1');
        $this->assertSame(13, $afterAdjust['totalQty']);
        $this->assertSame(11, $afterAdjust['availableQty']);
        $this->assertNotSame('2026-01-01 10:00:00', $afterAdjust['updatedAt'], 'updatedAt is refreshed');

        $this->assertTrue($repository->updateAvailableQty('it-1', 7));

        $afterApprove = $repository->findItem('it-1');
        $this->assertSame(13, $afterApprove['totalQty'], 'totalQty untouched when only availableQty changes');
        $this->assertSame(7, $afterApprove['availableQty']);

        $this->assertFalse($repository->updateAvailableQty('missing', 1), 'missing item reports failure');
    }
}
