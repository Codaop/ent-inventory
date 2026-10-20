<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateUser;
use App\Repositories\TransactionSheetRepository;
use App\Repositories\UserSheetRepository;
use App\Sheets\SheetClient;
use Tests\Support\InMemorySheetClient;
use Tests\TestCase;

class ItemApiTest extends TestCase
{
    private InMemorySheetClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new InMemorySheetClient([
            'Users' => [['email', 'displayName', 'role', 'active']],
            'Items' => [
                ['itemId', 'sku', 'name', 'category', 'location', 'totalQty', 'availableQty', 'reorderThreshold', 'unitPrice', 'createdAt', 'updatedAt', 'deleted'],
                ['it-1', 'SKU-1', 'Router', 'Network', 'Shelf A', 10, 3, 5, 25.5, '2026-10-01 10:00:00', '2026-10-02 11:00:00', false],
                ['it-2', 'SKU-2', 'Cable Tester', 'Cable', 'Shelf B', 20, 18, 5, 10, '2026-10-01 10:00:00', '2026-10-02 11:00:00', false],
                ['it-3', 'SKU-3', 'Old Switch', 'Network', 'Shelf C', 5, 5, 5, 0, '2026-10-01 10:00:00', '2026-10-02 11:00:00', true],
            ],
            'Categories' => [['name'], ['Network'], ['Cable']],
            'Transactions' => [['txId', 'timestamp', 'userEmail', 'userDisplayName', 'itemId', 'itemName', 'qty', 'type', 'status', 'borrowDate', 'dueDate', 'returnDate', 'approvedBy', 'notes']],
        ]);
        $this->app->instance(SheetClient::class, $this->client);
    }

    private function loginAs(string $role = 'BORROWER'): static
    {
        $repository = new UserSheetRepository($this->client);

        if ($repository->existsByEmail('user@example.com')) {
            $repository->updateUserByEmail('user@example.com', ['role' => $role]);
        } else {
            $repository->appendUser([
                'email' => 'user@example.com',
                'displayName' => 'User',
                'role' => $role,
            ]);
        }

        return $this->withSession([AuthenticateUser::SESSION_KEY => 'user@example.com']);
    }

    public function test_index_requires_auth(): void
    {
        $this->getJson('/items')
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);
    }

    public function test_index_lists_non_deleted_items_with_the_legacy_shape(): void
    {
        $items = $this->loginAs()->getJson('/items')->assertOk()->json('items');

        $this->assertCount(2, $items);
        $this->assertSame(
            ['itemId', 'sku', 'name', 'category', 'location', 'totalQty', 'availableQty', 'reorderThreshold', 'unitPrice', 'createdAt', 'updatedAt'],
            array_keys($items[0]),
        );
        $this->assertSame('it-1', $items[0]['itemId']);
        $this->assertSame('SKU-1', $items[0]['sku']);
        $this->assertSame('Router', $items[0]['name']);
        $this->assertSame('Network', $items[0]['category']);
        $this->assertSame('Shelf A', $items[0]['location']);
        $this->assertSame(10, $items[0]['totalQty']);
        $this->assertSame(3, $items[0]['availableQty']);
        $this->assertSame(5, $items[0]['reorderThreshold']);
        $this->assertSame(25.5, $items[0]['unitPrice']);
        $this->assertSame('2026-10-01 10:00:00', $items[0]['createdAt']);
        $this->assertSame('2026-10-02 11:00:00', $items[0]['updatedAt']);
        $this->assertSame(['it-1', 'it-2'], array_column($items, 'itemId'));
    }

    public function test_index_applies_the_legacy_filters(): void
    {
        $this->loginAs();

        $this->assertSame(['it-1'], $this->getJson('/items?category=Network')->json('items.*.itemId'));
        $this->assertSame(['it-2'], $this->getJson('/items?location=Shelf%20B')->json('items.*.itemId'));
        $this->assertSame(['it-2'], $this->getJson('/items?search=CABLE')->json('items.*.itemId'));
        $this->assertSame(['it-1'], $this->getJson('/items?lowStock=1')->json('items.*.itemId'));
    }

    public function test_show_returns_an_item_and_item_not_found(): void
    {
        $this->loginAs();

        $this->getJson('/items/it-2')
            ->assertOk()
            ->assertJson(['itemId' => 'it-2', 'name' => 'Cable Tester', 'availableQty' => 18]);

        $this->getJson('/items/missing')
            ->assertStatus(404)
            ->assertJson(['error' => 'ITEM_NOT_FOUND']);

        $this->getJson('/items/it-3')
            ->assertStatus(404)
            ->assertJson(['error' => 'ITEM_NOT_FOUND']);
    }

    public function test_store_requires_admin(): void
    {
        $this->loginAs('BORROWER')
            ->postJson('/items', ['name' => 'Switch'])
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);
    }

    public function test_store_creates_an_item_with_defaults_and_ensures_the_category(): void
    {
        $response = $this->loginAs('ADMIN')->postJson('/items', [
            'sku' => 'SKU-9',
            'name' => 'Patch Panel',
            'category' => 'NewCat',
            'location' => 'Shelf D',
            'totalQty' => 4,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $itemId = $response->json('itemId');
        $this->assertNotNull($itemId);

        $this->getJson('/items/'.$itemId)
            ->assertOk()
            ->assertJson([
                'sku' => 'SKU-9',
                'name' => 'Patch Panel',
                'category' => 'NewCat',
                'location' => 'Shelf D',
                'totalQty' => 4,
                'availableQty' => 4,
                'reorderThreshold' => 5,
                'unitPrice' => 0,
            ]);

        $this->assertContains('NewCat', $this->getJson('/categories')->json('categories'));

        $this->postJson('/items', [])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_update_patches_an_item_and_requires_admin(): void
    {
        $this->loginAs('BORROWER')
            ->patchJson('/items/it-1', ['name' => 'Hacked'])
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);

        $this->loginAs('ADMIN')
            ->patchJson('/items/it-1', ['location' => 'Shelf Z', 'reorderThreshold' => 2])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->getJson('/items/it-1')->assertOk()->assertJson([
            'name' => 'Router',
            'location' => 'Shelf Z',
            'reorderThreshold' => 2,
            'totalQty' => 10,
            'availableQty' => 3,
        ]);
    }

    public function test_destroy_soft_deletes_an_item_and_requires_admin(): void
    {
        $this->loginAs('BORROWER')
            ->deleteJson('/items/it-2')
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);

        $this->loginAs('ADMIN')->deleteJson('/items/it-2')->assertOk()->assertJson(['ok' => true]);

        $this->getJson('/items/it-2')->assertStatus(404);
        $this->assertSame(['it-1'], $this->getJson('/items')->json('items.*.itemId'));
    }

    public function test_update_and_destroy_report_item_not_found(): void
    {
        $this->loginAs('ADMIN');

        $this->patchJson('/items/missing', ['name' => 'x'])
            ->assertStatus(404)
            ->assertJson(['error' => 'ITEM_NOT_FOUND']);

        $this->deleteJson('/items/missing')
            ->assertStatus(404)
            ->assertJson(['error' => 'ITEM_NOT_FOUND']);
    }

    public function test_adjust_requires_admin(): void
    {
        $this->loginAs('BORROWER')
            ->postJson('/items/it-1/adjust', ['delta' => 1])
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);
    }

    public function test_adjust_changes_stock_and_logs_a_completed_transaction(): void
    {
        $this->loginAs('ADMIN');

        $this->postJson('/items/it-1/adjust', ['delta' => 5, 'reason' => 'new shipment'])
            ->assertOk()
            ->assertJson(['ok' => true, 'newTotal' => 15, 'newAvail' => 8]);

        $this->postJson('/items/it-1/adjust', ['delta' => -3, 'reason' => 'damaged units'])
            ->assertOk()
            ->assertJson(['ok' => true, 'newTotal' => 12, 'newAvail' => 5]);

        $this->getJson('/items/it-1')->assertJson(['totalQty' => 12, 'availableQty' => 5]);

        $transactions = (new TransactionSheetRepository($this->client))->allTransactions();
        $this->assertCount(2, $transactions);
        $this->assertSame('RESTOCK', $transactions[0]['type']);
        $this->assertSame('COMPLETED', $transactions[0]['status']);
        $this->assertSame(5, $transactions[0]['qty']);
        $this->assertSame('user@example.com', $transactions[0]['userEmail']);
        $this->assertSame('Router', $transactions[0]['itemName']);
        $this->assertSame('user@example.com', $transactions[0]['approvedBy']);
        $this->assertSame('new shipment', $transactions[0]['notes']);
        $this->assertSame('ADJUST', $transactions[1]['type']);
        $this->assertSame(3, $transactions[1]['qty']);
        $this->assertSame('damaged units', $transactions[1]['notes']);
    }

    public function test_adjust_rejects_insufficient_stock_invalid_quantities_and_missing_items(): void
    {
        $this->loginAs('ADMIN');

        $this->postJson('/items/it-1/adjust', ['delta' => -5])
            ->assertStatus(422)
            ->assertJson(['error' => 'INSUFFICIENT_STOCK']);

        $this->getJson('/items/it-1')->assertJson(['totalQty' => 10, 'availableQty' => 3]);

        $this->postJson('/items/it-1/adjust', ['delta' => 0])
            ->assertStatus(422)
            ->assertJson(['error' => 'INVALID_QTY']);

        $this->postJson('/items/it-1/adjust', ['delta' => 'many'])
            ->assertStatus(422)
            ->assertJson(['error' => 'INVALID_QTY']);

        $this->postJson('/items/missing/adjust', ['delta' => 1])
            ->assertStatus(404)
            ->assertJson(['error' => 'ITEM_NOT_FOUND']);

        $this->assertEmpty((new TransactionSheetRepository($this->client))->allTransactions());
    }

    public function test_categories_require_auth_and_list_names(): void
    {
        $this->getJson('/categories')
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);

        $this->loginAs()->getJson('/categories')
            ->assertOk()
            ->assertJson(['categories' => ['Network', 'Cable']]);
    }
}
