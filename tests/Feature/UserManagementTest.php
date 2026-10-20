<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateUser;
use App\Repositories\UserSheetRepository;
use App\Sheets\SheetClient;
use Tests\Support\InMemorySheetClient;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
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
            'Items' => [
                ['itemId', 'sku', 'name', 'category', 'location', 'totalQty', 'availableQty', 'reorderThreshold', 'unitPrice', 'createdAt', 'updatedAt', 'deleted'],
            ],
            'Categories' => [['name']],
            'Transactions' => [['txId', 'timestamp', 'userEmail', 'userDisplayName', 'itemId', 'itemName', 'qty', 'type', 'status', 'borrowDate', 'dueDate', 'returnDate', 'approvedBy', 'notes']],
        ]);
        $this->app->instance(SheetClient::class, $this->client);
    }

    private function loginAsAdmin(): static
    {
        return $this->withSession([AuthenticateUser::SESSION_KEY => 'admin@example.com']);
    }

    private function loginAsBorrower(): static
    {
        return $this->withSession([AuthenticateUser::SESSION_KEY => 'borrower@example.com']);
    }

    // ─── List Users ──────────────────────────────────────────────────────────

    public function test_list_users_requires_admin(): void
    {
        $this->loginAsBorrower();
        $this->getJson('/users')
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);
    }

    public function test_list_users_requires_auth(): void
    {
        $this->getJson('/users')
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);
    }

    public function test_list_users_returns_all_users_including_inactive(): void
    {
        $this->loginAsAdmin();

        $this->getJson('/users')
            ->assertOk()
            ->assertJsonPath('users.0.email', 'admin@example.com')
            ->assertJsonPath('users.1.email', 'borrower@example.com')
            ->assertJsonPath('users.2.email', 'inactive@example.com')
            ->assertJsonCount(3, 'users');
    }

    // ─── Create User ──────────────────────────────────────────────────────────

    public function test_create_user_requires_admin(): void
    {
        $this->loginAsBorrower();
        $this->postJson('/users', ['email' => 'new@example.com'])
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);
    }

    public function test_create_user_requires_auth(): void
    {
        $this->postJson('/users', ['email' => 'new@example.com'])
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);
    }

    public function test_create_user_validates_email(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/users', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_create_user_reports_email_exists(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/users', ['email' => 'admin@example.com'])
            ->assertStatus(422)
            ->assertJson(['error' => 'EMAIL_EXISTS']);
    }

    public function test_create_user_uses_defaults_for_optional_fields(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/users', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $user = (new UserSheetRepository($this->client))->findActiveUserByEmail('new@example.com');
        $this->assertNotNull($user);
        $this->assertSame('new@example.com', $user['email']);
        $this->assertSame('new@example.com', $user['displayName']); // falls back to email
        $this->assertSame('BORROWER', $user['role']);
        $this->assertTrue($user['active']);
    }

    public function test_create_user_accepts_custom_display_name_and_role(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/users', [
            'email' => 'custom@example.com',
            'displayName' => 'Custom Name',
            'role' => 'ADMIN',
        ])->assertOk();

        $user = (new UserSheetRepository($this->client))->findActiveUserByEmail('custom@example.com');
        $this->assertSame('Custom Name', $user['displayName']);
        $this->assertSame('ADMIN', $user['role']);
    }

    // ─── Update User ──────────────────────────────────────────────────────────

    public function test_update_user_requires_admin(): void
    {
        $this->loginAsBorrower();
        $this->patchJson('/users/borrower@example.com', ['displayName' => 'New Name'])
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);
    }

    public function test_update_user_requires_auth(): void
    {
        $this->patchJson('/users/borrower@example.com', ['displayName' => 'New Name'])
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);
    }

    public function test_update_user_reports_user_not_found(): void
    {
        $this->loginAsAdmin();

        $this->patchJson('/users/missing@example.com', ['displayName' => 'X'])
            ->assertStatus(404)
            ->assertJson(['error' => 'USER_NOT_FOUND']);
    }

    public function test_update_user_works_case_insensitive_on_email(): void
    {
        $this->loginAsAdmin();

        $this->patchJson('/users/ADMIN@EXAMPLE.COM', ['displayName' => 'Updated'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $user = (new UserSheetRepository($this->client))->findActiveUserByEmail('admin@example.com');
        $this->assertSame('Updated', $user['displayName']);
    }

    public function test_update_user_allows_partial_updates(): void
    {
        $this->loginAsAdmin();

        $this->patchJson('/users/borrower@example.com', ['role' => 'ADMIN'])
            ->assertOk();

        $user = (new UserSheetRepository($this->client))->findActiveUserByEmail('borrower@example.com');
        $this->assertSame('ADMIN', $user['role']);
        $this->assertSame('Borrower User', $user['displayName']); // unchanged
    }

    public function test_update_user_can_deactivate(): void
    {
        $this->loginAsAdmin();

        $this->patchJson('/users/borrower@example.com', ['active' => false])
            ->assertOk();

        $repo = new UserSheetRepository($this->client);
        $this->assertNull($repo->findActiveUserByEmail('borrower@example.com')); // no longer active
        $this->assertFalse($repo->allUsers()[1]['active']); // but still in allUsers
    }

    // ─── Delete User (soft delete = set active=false) ─────────────────────────

    public function test_delete_user_requires_admin(): void
    {
        $this->loginAsBorrower();
        $this->deleteJson('/users/borrower@example.com')
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);
    }

    public function test_delete_user_requires_auth(): void
    {
        $this->deleteJson('/users/borrower@example.com')
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);
    }

    public function test_delete_user_reports_user_not_found(): void
    {
        $this->loginAsAdmin();

        $this->deleteJson('/users/missing@example.com')
            ->assertStatus(404)
            ->assertJson(['error' => 'USER_NOT_FOUND']);
    }

    public function test_delete_user_soft_deletes_by_setting_active_false(): void
    {
        $this->loginAsAdmin();

        $this->deleteJson('/users/borrower@example.com')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $repo = new UserSheetRepository($this->client);
        $this->assertNull($repo->findActiveUserByEmail('borrower@example.com'));
        $this->assertFalse($repo->allUsers()[1]['active']);
    }
}
