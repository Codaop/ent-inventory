<?php

namespace App\Http\Controllers;

use App\Repositories\ItemSheetRepository;
use App\Repositories\TransactionSheetRepository;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Borrow lifecycle from Borrow.gs: request (PENDING), admin approve/reject,
 * owner or admin return, plus the three transaction listings.
 */
class BorrowController extends Controller
{
    public function __construct(
        private readonly TransactionSheetRepository $transactions,
        private readonly ItemSheetRepository $items,
        private readonly NotificationService $notifications,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->attributes->get('currentUser');

        $payload = $request->validate([
            'itemId' => ['required', 'string'],
            'dueDate' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = $this->items->findItem($payload['itemId']);

        if ($item === null) {
            return $this->error('ITEM_NOT_FOUND', 404);
        }

        $qty = $request->input('qty');

        if (! is_numeric($qty) || (int) $qty < 1) {
            return $this->error('INVALID_QTY', 422);
        }

        $qty = (int) $qty;

        if ($item['availableQty'] < $qty) {
            return $this->error('INSUFFICIENT_STOCK', 422);
        }

        $now = now()->format('Y-m-d H:i:s');
        $txId = (string) Str::uuid();

        $this->transactions->appendTransaction([
            'txId' => $txId,
            'timestamp' => $now,
            'userEmail' => $user['email'],
            'userDisplay' => $user['displayName'],
            'itemId' => $item['itemId'],
            'itemName' => $item['name'],
            'qty' => $qty,
            'type' => 'BORROW',
            'status' => 'PENDING',
            'borrowDate' => $now,
            'dueDate' => date('Y-m-d H:i:s', strtotime((string) $payload['dueDate'])),
            'returnDate' => '',
            'approvedBy' => '',
            'notes' => (string) $request->input('notes', ''),
        ]);

        return response()->json(['ok' => true, 'txId' => $txId]);
    }

    /**
     * getMyBorrows(): the signed-in user's own BORROW rows, newest first.
     */
    public function my(Request $request): JsonResponse
    {
        $user = $request->attributes->get('currentUser');

        $borrows = array_filter(
            $this->transactions->allTransactions(),
            fn (array $tx): bool => $tx['userEmail'] === $user['email'] && $tx['type'] === 'BORROW',
        );

        return response()->json(array_values(array_reverse($borrows)));
    }

    /**
     * getPendingRequests(): every PENDING transaction, newest first.
     */
    public function pending(): JsonResponse
    {
        $pending = array_filter(
            $this->transactions->allTransactions(),
            fn (array $tx): bool => $tx['status'] === 'PENDING',
        );

        return response()->json(array_values(array_reverse($pending)));
    }

    /**
     * getActiveBorrows(): BORROW rows still PENDING/APPROVED/OVERDUE, newest first.
     */
    public function active(): JsonResponse
    {
        $active = array_filter(
            $this->transactions->allTransactions(),
            fn (array $tx): bool => $tx['type'] === 'BORROW'
                && in_array($tx['status'], ['PENDING', 'APPROVED', 'OVERDUE'], true),
        );

        return response()->json(array_values(array_reverse($active)));
    }

    /**
     * approveRequest() from Borrow.gs: reserve stock, mark APPROVED with
     * the admin as approver, then notify the borrower and — when the item
     * falls to or below its threshold — the admins.
     */
    public function approve(Request $request, string $txId): JsonResponse
    {
        $admin = $request->attributes->get('currentUser');

        $tx = $this->transactions->findTransaction($txId);

        if ($tx === null) {
            return $this->error('TX_NOT_FOUND', 404);
        }

        if ($tx['status'] !== 'PENDING') {
            return $this->error('NOT_PENDING', 409);
        }

        $item = $this->items->findItem($tx['itemId']);

        if ($item === null) {
            return $this->error('ITEM_NOT_FOUND', 404);
        }

        $newAvail = $item['availableQty'] - (int) $tx['qty'];

        if ($newAvail < 0) {
            return $this->error('INSUFFICIENT_STOCK', 422);
        }

        $this->items->updateAvailableQty($tx['itemId'], $newAvail);
        $this->transactions->updateTransaction($txId, [
            'status' => 'APPROVED',
            'approvedBy' => $admin['email'],
        ]);

        $tx['status'] = 'APPROVED';
        $tx['approvedBy'] = $admin['email'];
        $this->notifications->borrowConfirmed($tx);

        if ($newAvail <= $item['reorderThreshold']) {
            $this->notifications->lowStockAlert(array_merge($item, ['availableQty' => $newAvail]));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * rejectRequest() from Borrow.gs: mark REJECTED with the admin as
     * approver and the reason as notes, then notify the borrower. Stock
     * is untouched.
     */
    public function reject(Request $request, string $txId): JsonResponse
    {
        $admin = $request->attributes->get('currentUser');

        $request->validate(['reason' => ['nullable', 'string']]);

        $tx = $this->transactions->findTransaction($txId);

        if ($tx === null) {
            return $this->error('TX_NOT_FOUND', 404);
        }

        if ($tx['status'] !== 'PENDING') {
            return $this->error('NOT_PENDING', 409);
        }

        $reason = (string) $request->input('reason', '');

        $this->transactions->updateTransaction($txId, [
            'status' => 'REJECTED',
            'approvedBy' => $admin['email'],
            'notes' => $reason,
        ]);

        $tx['status'] = 'REJECTED';
        $tx['notes'] = $reason;
        $this->notifications->borrowRejected($tx, $reason);

        return response()->json(['ok' => true]);
    }

    /**
     * returnBorrow() from Return.gs: owner or admin can call, stock is
     * restored (if item still exists), status marked RETURNED with now as
     * returnDate. A PENDING or already RETURNED transaction is not ACTIVE.
     */
    public function return(Request $request, string $txId): JsonResponse
    {
        $user = $request->attributes->get('currentUser');

        $tx = $this->transactions->findTransaction($txId);

        if ($tx === null) {
            return $this->error('TX_NOT_FOUND', 404);
        }

        if (! in_array($tx['status'], ['APPROVED', 'OVERDUE'], true)) {
            return $this->error('NOT_ACTIVE', 409);
        }

        if ($user['role'] !== 'ADMIN' && $tx['userEmail'] !== $user['email']) {
            return $this->error('FORBIDDEN', 403);
        }

        $item = $this->items->findItem($tx['itemId']);

        if ($item !== null) {
            $this->items->updateAvailableQty($tx['itemId'], $item['availableQty'] + (int) $tx['qty']);
        }

        $this->transactions->updateTransaction($txId, [
            'status' => 'RETURNED',
            'returnDate' => now()->format('Y-m-d H:i:s'),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * dailyScan() from Trigger.gs: daily scan that runs at 07:00.
     * - Due reminders for BORROW tx due tomorrow
     * - Overdue detection + status update
     * - Low stock alerts
     */
    public function dailyScan(): JsonResponse
    {
        $admin = $request->attributes->get('currentUser');

        // ─── 1. Daily scan of BORROW transactions ────────────────────────
        $now = new \DateTime('now');
        $now->setTime(0, 0, 0);
        $tomorrow = clone $now;
        $tomorrow->add(new \DateInterval('P1D'));

        $borrows = $this->transactions->allTransactions();

        $dueReminders = 0;
        $overdueMarked = 0;
        $overdueAlerted = 0;
        $lowStockAlerted = [];

        foreach ($borrows as $tx) {
            if ($tx['type'] !== 'BORROW') {
                continue;
            }

            $status = $tx['status'];
            if ($status !== 'APPROVED' && $status !== 'OVERDUE') {
                continue;
            }

            $dueDateStr = $tx['dueDate'];
            if ($dueDateStr === '') {
                continue;
            }

            $dueDate = \DateTime::createFromFormat('Y-m-d H:i:s', $dueDateStr);
            if (! $dueDate) {
                continue;
            }
            $dueDate->setTime(0, 0, 0);

            // Due reminder: dueDate == tomorrow
            if ($dueDate->getTimestamp() === $tomorrow->getTimestamp()) {
                $this->notifications->sendDueReminder($tx);
                $dueReminders++;

                continue;
            }

            // Overdue logic: dueDate < today
            $today = clone $now;
            if ($dueDate->getTimestamp() < $today->getTimestamp()) {
                if ($status !== 'OVERDUE') {
                    // Mark as OVERDUE in the sheet
                    $this->transactions->updateTransaction($tx['txId'], [
                        'status' => 'OVERDUE',
                    ]);
                    $overdueMarked++;

                    // Send overdue alert (borrower + admins)
                    $this->notifications->sendOverdueAlert($tx);
                    $overdueAlerted++;
                } else {
                    // Already overdue, re-send alert
                    $this->notifications->sendOverdueAlert($tx);
                    $overdueAlerted++;
                }
            }
        }

        // ─── 2. Low stock scan ───────────────────────────────────────────
        $items = $this->items->allItems();

        foreach ($items as $item) {
            if (! $item['availableQty'] || $item['availableQty'] > $item['reorderThreshold']) {
                continue;
            }
            // Dedup: only alert once per item (by itemId)
            if (isset($lowStockAlerted[$item['itemId']])) {
                continue;
            }
            $lowStockAlerted[$item['itemId']] = true;

            $this->notifications->lowStockAlert([
                'itemId' => $item['itemId'],
                'sku' => $item['sku'],
                'name' => $item['name'],
                'availableQty' => $item['availableQty'],
                'reorderThreshold' => $item['reorderThreshold'],
            ]);
        }

        return response()->json([
            'dueReminders' => $dueReminders,
            'overdueMarked' => $overdueMarked,
            'overdueAlerted' => $overdueAlerted,
            'lowStockAlerted' => count($lowStockAlerted),
        ]);
    }

    protected function error(string $code, int $status): JsonResponse
    {
        return response()->json(['error' => $code], $status);
    }
}
