<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateUser;
use App\Repositories\ConfigSheetRepository;
use App\Sheets\SheetClient;
use Tests\Support\InMemorySheetClient;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    private ConfigSheetRepository $config;

    private InMemorySheetClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new InMemorySheetClient([
            'Users' => [
                ['email', 'displayName', 'role', 'active'],
                ['admin@example.com', 'Admin User', 'ADMIN', true],
                ['borrower@example.com', 'Borrower User', 'BORROWER', true],
                ['inactive@example.com', 'Inactive User', 'BORROWER', false],
            ],
            'Config' => [
                ['key', 'value'],
                ['app_name', 'ENT Inventory'],
            ],
            'Items' => [
                ['itemId', 'sku', 'name', 'category', 'location', 'totalQty', 'availableQty', 'reorderThreshold', 'unitPrice', 'createdAt', 'updatedAt', 'deleted'],
            ],
            'Categories' => [['name']],
            'Transactions' => [['txId', 'timestamp', 'userEmail', 'userDisplayName', 'itemId', 'itemName', 'qty', 'type', 'status', 'borrowDate', 'dueDate', 'returnDate', 'approvedBy', 'notes']],
        ]);
        $this->app->instance(SheetClient::class, $this->client);
        $this->config = new ConfigSheetRepository($this->client);
    }

    private function loginAsAdmin(): static
    {
        return $this->withSession([AuthenticateUser::SESSION_KEY => 'admin@example.com']);
    }

    public function test_get_returns_all_config_values(): void
    {
        $this->loginAsAdmin()->getJson('/config')
            ->assertOk()
            ->assertJsonPath('config.app_name', 'ENT Inventory');
    }

    public function test_get_single_key(): void
    {
        $this->loginAsAdmin()->getJson('/config/app_name')
            ->assertOk()
            ->assertJson(['key' => 'app_name', 'value' => 'ENT Inventory']);
    }

    public function test_get_missing_key_returns_404(): void
    {
        $this->loginAsAdmin()->getJson('/config/missing')
            ->assertStatus(404)
            ->assertJson(['error' => 'CONFIG_NOT_FOUND']);
    }

    public function test_set_creates_new_key(): void
    {
        $this->loginAsAdmin()->patchJson('/config/debug', ['value' => 'true'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('true', $this->config->get('debug'));
    }

    public function test_set_updates_existing_key(): void
    {
        $this->loginAsAdmin()->patchJson('/config/app_name', ['value' => 'New Name'])
            ->assertOk();

        $this->assertSame('New Name', $this->config->get('app_name'));
    }

    public function test_delete_removes_key(): void
    {
        $this->loginAsAdmin()->deleteJson('/config/app_name')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNull($this->config->get('app_name'));
    }
}
