<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateUser;
use App\Mail\NotificationMail;
use App\Repositories\TransactionSheetRepository;
use App\Repositories\UserSheetRepository;
use App\Sheets\SheetClient;
use Illuminate\Support\Facades\Mail;
use Tests\Support\InMemorySheetClient;
use Tests\TestCase;

class BorrowFlowTest extends TestCase
{
    private InMemorySheetClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new InMemorySheetClient([
            'Users' => [['email', 'displayName', 'role', 'active']],
            'Items' => [
                ['itemId', 'sku', 'name', 'category', 'location', 'totalQty', 'availableQty', 'reorderThreshold', 'unitPrice', 'createdAt', 'updatedAt', 'deleted'],
                ['it-1', 'SKU-1', 'Router', 'Network', 'Shelf A', 10, 8, 5, 25.5, '2026-10-01 10:00:00', '2026-10-02 11:00:00', false],
                ['it-2', 'SKU-2', 'Gone Switch', 'Network', 'Shelf C', 4, 4, 5, 0, '2026-10-01 10:00:00', '2026-10-02 11:00:00', true],
            ],
            'Categories' => [['name'], ['Network']],
            'Transactions' => [['txId', 'timestamp', 'userEmail', 'userDisplayName', 'itemId', 'itemName', 'qty', 'type', 'status', 'borrowDate', 'dueDate', 'returnDate', 'approvedBy', 'notes']],
        ]);
        $this->app->instance(SheetClient::class, $this->client);
    }

    private function loginAs(string $role = 'BORROWER'): static
    {
        $repository = new UserSheetRepository($this->client);
        $email = 'user@example.com';

        if ($repository->existsByEmail($email)) {
            $repository->updateUserByEmail($email, ['role' => $role]);
        } else {
            $repository->appendUser(['email' => $email, 'displayName' => 'User', 'role' => $role]);
        }

        return $this->withSession([AuthenticateUser::SESSION_KEY => $email]);
    }

    private function seedBorrow(array $tx = []): string
    {
        $repository = new TransactionSheetRepository($this->client);
        $tx = array_merge([
            'txId' => 'tx-'.(count($repository->allTransactions()) + 1),
            'timestamp' => '2026-10-19 08:00:00',
            'userEmail' => 'user@example.com',
            'userDisplay' => 'User',
            'itemId' => 'it-1',
            'itemName' => 'Router',
            'qty' => 1,
            'type' => 'BORROW',
            'status' => 'PENDING',
            'borrowDate' => '2026-10-19 08:00:00',
            'dueDate' => '2026-10-25 00:00:00',
            'returnDate' => '',
            'approvedBy' => '',
            'notes' => '',
        ], $tx);

        $repository->appendTransaction($tx);

        return $tx['txId'];
    }

    public function test_request_borrow_requires_auth(): void
    {
        $this->postJson('/borrows', ['itemId' => 'it-1', 'qty' => 1, 'dueDate' => '2026-10-25'])
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);
    }

    public function test_request_borrow_validates_item_qty_and_due_date(): void
    {
        $this->loginAs();

        $this->postJson('/borrows', ['itemId' => 'missing', 'qty' => 1, 'dueDate' => '2026-10-25'])
            ->assertStatus(404)
            ->assertJson(['error' => 'ITEM_NOT_FOUND']);

        $this->postJson('/borrows', ['itemId' => 'it-2', 'qty' => 1, 'dueDate' => '2026-10-25'])
            ->assertStatus(404)
            ->assertJson(['error' => 'ITEM_NOT_FOUND']);

        $this->postJson('/borrows', ['itemId' => 'it-1', 'qty' => 0, 'dueDate' => '2026-10-25'])
            ->assertStatus(422)
            ->assertJson(['error' => 'INVALID_QTY']);

        $this->postJson('/borrows', ['itemId' => 'it-1', 'qty' => 99, 'dueDate' => '2026-10-25'])
            ->assertStatus(422)
            ->assertJson(['error' => 'INSUFFICIENT_STOCK']);

        $this->postJson('/borrows', ['itemId' => 'it-1', 'qty' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dueDate');
    }

    public function test_request_borrow_creates_a_pending_transaction(): void
    {
        $this->loginAs();

        $response = $this->postJson('/borrows', [
            'itemId' => 'it-1',
            'qty' => 2,
            'dueDate' => '2026-10-25',
            'notes' => 'lab session',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertNotNull($response->json('txId'));

        $tx = (new TransactionSheetRepository($this->client))->findTransaction($response->json('txId'));
        $this->assertSame('PENDING', $tx['status']);
        $this->assertSame('BORROW', $tx['type']);
        $this->assertSame(2, $tx['qty']);
        $this->assertSame('user@example.com', $tx['userEmail']);
        $this->assertSame('User', $tx['userDisplay']);
        $this->assertSame('Router', $tx['itemName']);
        $this->assertSame('2026-10-25 00:00:00', $tx['dueDate']);
        $this->assertSame('lab session', $tx['notes']);
        $this->assertSame('', $tx['approvedBy']);
        $this->assertNotSame('', $tx['borrowDate']);

        $this->getJson('/items/it-1')->assertJson(['availableQty' => 8]);
    }

    public function test_my_lists_only_my_borrows_newest_first(): void
    {
        $this->loginAs();

        $this->seedBorrow(['txId' => 'tx-old']);
        $this->seedBorrow(['txId' => 'tx-new']);
        $this->seedBorrow(['txId' => 'tx-other', 'userEmail' => 'other@example.com']);
        $this->seedBorrow(['txId' => 'tx-adjust', 'type' => 'RESTOCK', 'status' => 'COMPLETED']);

        $this->getJson('/borrows/my')
            ->assertOk()
            ->assertJsonPath('transactions.0.txId', 'tx-new')
            ->assertJsonPath('transactions.1.txId', 'tx-old');

        $this->loginAs('ADMIN');
        $this->getJson('/borrows/my')
            ->assertOk()
            ->assertJsonPath('transactions.0.txId', 'tx-new');
    }

    public function test_pending_and_active_listings_require_admin(): void
    {
        $this->loginAs('BORROWER');

        $this->getJson('/borrows/pending')
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);

        $this->getJson('/borrows/active')
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);
    }

    public function test_pending_lists_pending_requests_newest_first(): void
    {
        $this->loginAs('ADMIN');

        $this->seedBorrow(['txId' => 'tx-1']);
        $this->seedBorrow(['txId' => 'tx-2']);
        $this->seedBorrow(['txId' => 'tx-3', 'status' => 'APPROVED']);
        $this->seedBorrow(['txId' => 'tx-4', 'status' => 'REJECTED']);

        $this->getJson('/borrows/pending')
            ->assertOk()
            ->assertJsonPath('transactions.0.txId', 'tx-2')
            ->assertJsonPath('transactions.1.txId', 'tx-1')
            ->assertJsonCount(2, 'transactions');
    }

    public function test_active_lists_active_borrows_newest_first(): void
    {
        $this->loginAs('ADMIN');

        $this->seedBorrow(['txId' => 'tx-1']);
        $this->seedBorrow(['txId' => 'tx-2', 'status' => 'APPROVED']);
        $this->seedBorrow(['txId' => 'tx-3', 'status' => 'OVERDUE']);
        $this->seedBorrow(['txId' => 'tx-4', 'status' => 'RETURNED']);
        $this->seedBorrow(['txId' => 'tx-5', 'status' => 'REJECTED']);
        $this->seedBorrow(['txId' => 'tx-6', 'type' => 'RESTOCK', 'status' => 'COMPLETED']);

        $this->getJson('/borrows/active')
            ->assertOk()
            ->assertJsonPath('transactions.0.txId', 'tx-3')
            ->assertJsonPath('transactions.1.txId', 'tx-2')
            ->assertJsonPath('transactions.2.txId', 'tx-1')
            ->assertJsonCount(3, 'transactions');
    }

    public function test_approve_requires_admin(): void
    {
        $this->loginAs('BORROWER');
        $txId = $this->seedBorrow();

        $this->postJson("/borrows/{$txId}/approve")
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);
    }

    public function test_approve_moves_stock_and_notifies_borrower_and_admins(): void
    {
        Mail::fake();
        $this->loginAs('ADMIN');
        $txId = $this->seedBorrow(['qty' => 3]);

        $this->postJson("/borrows/{$txId}/approve")->assertOk()->assertJson(['ok' => true]);

        $this->getJson('/items/it-1')->assertJson(['availableQty' => 5]);

        $tx = (new TransactionSheetRepository($this->client))->findTransaction($txId);
        $this->assertSame('APPROVED', $tx['status']);
        $this->assertSame('user@example.com', $tx['approvedBy']);

        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo('user@example.com')
            && str_contains($mail->subject, 'Borrow Request Approved')
            && str_contains($mail->subject, 'Router'));
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo('user@example.com')
            && str_contains($mail->subject, 'Low Stock'), 'threshold reached (5 <= 5) triggers the admin alert');
    }

    public function test_approve_below_the_threshold_sends_only_the_confirmation(): void
    {
        Mail::fake();
        $this->loginAs('ADMIN');
        $txId = $this->seedBorrow(['qty' => 1]);

        $this->postJson("/borrows/{$txId}/approve")->assertOk();

        $this->getJson('/items/it-1')->assertJson(['availableQty' => 7]);
        Mail::assertSent(NotificationMail::class, 1);
    }

    public function test_approve_reports_error_codes_without_changing_state(): void
    {
        Mail::fake();
        $this->loginAs('ADMIN');

        $this->postJson('/borrows/missing/approve')
            ->assertStatus(404)
            ->assertJson(['error' => 'TX_NOT_FOUND']);

        $notPending = $this->seedBorrow(['status' => 'APPROVED']);
        $this->postJson("/borrows/{$notPending}/approve")
            ->assertStatus(409)
            ->assertJson(['error' => 'NOT_PENDING']);

        $deletedItem = $this->seedBorrow(['itemId' => 'it-2', 'itemName' => 'Gone Switch']);
        $this->postJson("/borrows/{$deletedItem}/approve")
            ->assertStatus(404)
            ->assertJson(['error' => 'ITEM_NOT_FOUND']);

        $tooBig = $this->seedBorrow(['qty' => 99]);
        $this->postJson("/borrows/{$tooBig}/approve")
            ->assertStatus(422)
            ->assertJson(['error' => 'INSUFFICIENT_STOCK']);

        $repository = new TransactionSheetRepository($this->client);
        $this->assertSame('PENDING', $repository->findTransaction($deletedItem)['status']);
        $this->assertSame('PENDING', $repository->findTransaction($tooBig)['status']);
        $this->getJson('/items/it-1')->assertJson(['availableQty' => 8]);
        Mail::assertNotSent(NotificationMail::class);
    }

    public function test_reject_requires_admin(): void
    {
        $this->loginAs('BORROWER');
        $txId = $this->seedBorrow();

        $this->postJson("/borrows/{$txId}/reject", ['reason' => 'no stock'])
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);
    }

    public function test_reject_marks_the_transaction_and_notifies_with_the_reason(): void
    {
        Mail::fake();
        $this->loginAs('ADMIN');
        $txId = $this->seedBorrow();

        $this->postJson("/borrows/{$txId}/reject", ['reason' => 'units needed for class'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $tx = (new TransactionSheetRepository($this->client))->findTransaction($txId);
        $this->assertSame('REJECTED', $tx['status']);
        $this->assertSame('user@example.com', $tx['approvedBy']);
        $this->assertSame('units needed for class', $tx['notes']);

        $this->getJson('/items/it-1')->assertJson(['availableQty' => 8], 'rejection does not touch stock');

        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo('user@example.com')
            && str_contains($mail->subject, 'Borrow Request Rejected')
            && str_contains($mail->footerHtml, 'units needed for class'));
    }

    public function test_reject_reports_error_codes_and_allows_an_empty_reason(): void
    {
        Mail::fake();
        $this->loginAs('ADMIN');

        $this->postJson('/borrows/missing/reject', ['reason' => 'x'])
            ->assertStatus(404)
            ->assertJson(['error' => 'TX_NOT_FOUND']);

        $notPending = $this->seedBorrow(['status' => 'APPROVED']);
        $this->postJson("/borrows/{$notPending}/reject", ['reason' => 'x'])
            ->assertStatus(409)
            ->assertJson(['error' => 'NOT_PENDING']);

        $txId = $this->seedBorrow();
        $this->postJson("/borrows/{$txId}/reject")->assertOk();

        $tx = (new TransactionSheetRepository($this->client))->findTransaction($txId);
        $this->assertSame('REJECTED', $tx['status']);
        $this->assertSame('', $tx['notes']);

        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail): bool => ! str_contains($mail->footerHtml, '<strong>Reason:</strong>'));
    }

    public function test_return_requires_owner_or_admin(): void
    {
        $this->loginAs('BORROWER');
        $someoneElsesTx = $this->seedBorrow(['userEmail' => 'other@example.com', 'status' => 'APPROVED']);

        $this->postJson("/borrows/{$someoneElsesTx}/return")
            ->assertStatus(403)
            ->assertJson(['error' => 'FORBIDDEN']);
    }

    public function test_return_restores_stock_and_marks_returned(): void
    {
        $this->loginAs('ADMIN');
        $txId = $this->seedBorrow(['status' => 'APPROVED', 'qty' => 3]);

        $this->postJson("/borrows/{$txId}/return")->assertOk()->assertJson(['ok' => true]);

        $this->getJson('/items/it-1')->assertJson(['availableQty' => 11]);

        $tx = (new TransactionSheetRepository($this->client))->findTransaction($txId);
        $this->assertSame('RETURNED', $tx['status']);
        $this->assertSame('user@example.com', $tx['userEmail']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $tx['returnDate']);
    }

    public function test_return_reports_error_codes(): void
    {
        $this->loginAs('ADMIN');

        $this->postJson('/borrows/missing/return')
            ->assertStatus(404)
            ->assertJson(['error' => 'TX_NOT_FOUND']);

        $pending = $this->seedBorrow(['status' => 'PENDING']);
        $this->postJson("/borrows/{$pending}/return")
            ->assertStatus(409)
            ->assertJson(['error' => 'NOT_ACTIVE']);

        $alreadyReturned = $this->seedBorrow(['status' => 'RETURNED']);
        $this->postJson("/borrows/{$alreadyReturned}/return")
            ->assertStatus(409)
            ->assertJson(['error' => 'NOT_ACTIVE']);
    }

    public function test_return_marks_returned_even_when_the_item_is_missing(): void
    {
        $this->loginAs('ADMIN');
        $txId = $this->seedBorrow(['itemId' => 'it-2', 'itemName' => 'Gone Switch', 'status' => 'APPROVED']);

        $this->postJson("/borrows/{$txId}/return")->assertOk();

        $tx = (new TransactionSheetRepository($this->client))->findTransaction($txId);
        $this->assertSame('RETURNED', $tx['status']);
    }
}
