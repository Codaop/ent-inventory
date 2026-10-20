<?php

namespace Tests\Unit;

use App\Repositories\UserSheetRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemorySheetClient;

class UserSheetRepositoryTest extends TestCase
{
    public function test_finds_active_user_by_email_case_insensitively(): void
    {
        $client = new InMemorySheetClient([
            'Users' => [
                ['email', 'displayName', 'role', 'active'],
                ['admin@example.com', 'Admin User', 'ADMIN', 'TRUE'],
            ],
        ]);

        $repository = new UserSheetRepository($client);

        $user = $repository->findActiveUserByEmail('  ADMIN@example.COM  ');

        $this->assertNotNull($user);
        $this->assertSame('admin@example.com', $user['email']);
        $this->assertSame('Admin User', $user['displayName']);
        $this->assertSame('ADMIN', $user['role']);
    }

    public function test_admin_can_add_update_and_list_users(): void
    {
        $client = new InMemorySheetClient([
            'Users' => [['email', 'displayName', 'role', 'active']],
        ]);
        $repository = new UserSheetRepository($client);

        $repository->appendUser(['email' => 'new@example.com']);

        $found = $repository->findActiveUserByEmail('new@example.com');
        $this->assertNotNull($found);
        $this->assertSame('new@example.com', $found['displayName'], 'displayName falls back to email');
        $this->assertSame('BORROWER', $found['role'], 'default role is BORROWER');
        $this->assertTrue($repository->existsByEmail('NEW@example.com'));

        $repository->updateUserByEmail('new@example.com', [
            'role' => 'ADMIN',
            'displayName' => 'New Admin',
        ]);

        $users = $repository->allUsers();
        $this->assertCount(1, $users);
        $this->assertSame('ADMIN', $users[0]['role']);
        $this->assertSame('New Admin', $users[0]['displayName']);
        $this->assertTrue($users[0]['active']);

        $repository->updateUserByEmail('new@example.com', ['active' => false]);
        $this->assertNull($repository->findActiveUserByEmail('new@example.com'), 'deactivated user is no longer found');
        $this->assertFalse(
            $repository->updateUserByEmail('missing@example.com', ['role' => 'ADMIN']),
            'updating a missing user reports failure'
        );
    }
}
