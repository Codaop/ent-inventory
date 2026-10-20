<?php

namespace Tests\Unit;

use App\Repositories\TransactionSheetRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemorySheetClient;

class TransactionSheetRepositoryTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function newClient(): InMemorySheetClient
    {
        return new InMemorySheetClient([
            'Transactions' => [
                ['txId', 'timestamp', 'userEmail', 'userDisplayName', 'itemId', 'itemName', 'qty', 'type', 'status', 'borrowDate', 'dueDate', 'returnDate', 'approvedBy', 'notes'],
            ],
        ]);
    }

    public function test_appends_and_reads_a_transaction_with_the_sheet_column_layout(): void
    {
        $client = $this->newClient();
        $repository = new TransactionSheetRepository($client);

        $repository->appendTransaction([
            'txId' => 'tx-1',
            'timestamp' => '2026-10-19 08:00:00',
            'userEmail' => 'borrower@example.com',
            'userDisplay' => 'Budi',
            'itemId' => 'it-1',
            'itemName' => 'Router',
            'qty' => 2,
            'type' => 'BORROW',
            'status' => 'PENDING',
            'borrowDate' => '2026-10-19 08:00:00',
            'dueDate' => '2026-10-25',
            'returnDate' => '',
            'approvedBy' => '',
            'notes' => 'for the event',
        ]);

        $stored = $client->sheet('Transactions')[1];
        $this->assertSame('BORROW', $stored[7], 'type lands in column 8 per the Transactions layout');
        $this->assertSame('PENDING', $stored[8], 'status lands in column 9 per the Transactions layout');
        $this->assertSame(2, $stored[6], 'qty lands in column 7 per the Transactions layout');

        $found = $repository->findTransaction('tx-1');
        $this->assertNotNull($found);
        $this->assertSame('borrower@example.com', $found['userEmail']);
        $this->assertSame('Budi', $found['userDisplay']);
        $this->assertSame('Router', $found['itemName']);
        $this->assertSame(2, $found['qty']);
        $this->assertSame('PENDING', $found['status']);
        $this->assertSame('2026-10-25 00:00:00', $found['dueDate'], 'date-only values normalize to midnight');
        $this->assertSame('for the event', $found['notes']);

        $this->assertNull($repository->findTransaction('missing'));

        $all = $repository->allTransactions();
        $this->assertCount(1, $all);
        $this->assertSame('tx-1', $all[0]['txId'], 'sheet order is preserved (oldest first)');
    }

    public function test_updates_transaction_cells_for_approve_reject_and_return(): void
    {
        $client = $this->newClient();
        $repository = new TransactionSheetRepository($client);

        $repository->appendTransaction([
            'txId' => 'tx-9',
            'timestamp' => '2026-10-19 08:00:00',
            'userEmail' => 'borrower@example.com',
            'userDisplay' => 'Budi',
            'itemId' => 'it-1',
            'itemName' => 'Router',
            'qty' => 2,
            'type' => 'BORROW',
            'status' => 'PENDING',
            'borrowDate' => '2026-10-19 08:00:00',
            'dueDate' => '2026-10-25',
            'returnDate' => '',
            'approvedBy' => '',
            'notes' => '',
        ]);

        $this->assertTrue($repository->updateTransaction('tx-9', [
            'status' => 'APPROVED',
            'approvedBy' => 'admin@example.com',
        ]));

        $approved = $repository->findTransaction('tx-9');
        $this->assertSame('APPROVED', $approved['status']);
        $this->assertSame('admin@example.com', $approved['approvedBy']);
        $this->assertSame(2, $approved['qty'], 'untouched fields are preserved');
        $this->assertSame('borrower@example.com', $approved['userEmail'], 'untouched fields are preserved');

        $repository->updateTransaction('tx-9', ['status' => 'REJECTED', 'notes' => 'out of stock']);

        $rejected = $repository->findTransaction('tx-9');
        $this->assertSame('REJECTED', $rejected['status']);
        $this->assertSame('out of stock', $rejected['notes']);

        $repository->updateTransaction('tx-9', [
            'status' => 'RETURNED',
            'returnDate' => '2026-10-26 09:30:00',
        ]);

        $returned = $repository->findTransaction('tx-9');
        $this->assertSame('RETURNED', $returned['status']);
        $this->assertSame('2026-10-26 09:30:00', $returned['returnDate']);

        $this->assertFalse($repository->updateTransaction('missing', ['status' => 'APPROVED']));
    }
}
