<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Repositories\UserSheetRepository;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email notifications from Notify.gs. Like the original, every send is
 * wrapped in try/catch so a mail failure never breaks the borrow flow.
 */
class NotificationService
{
    public function __construct(private readonly UserSheetRepository $users) {}

    /**
     * sendBorrowConfirm() from Notify.gs.
     *
     * @param  array<string, mixed>  $tx
     */
    public function borrowConfirmed(array $tx): void
    {
        $this->send(
            $tx['userEmail'],
            '[ENT Inventory] Borrow Request Approved — '.$tx['itemName'],
            'Borrow Request Approved',
            '<p>Hi <strong>'.$tx['userDisplay'].'</strong>,</p>'
            .'<p>Your borrow request has been <strong style="color:#28a745">approved</strong>.</p>',
            $this->txTable($tx),
            '<p>Please return the item by <strong>'.$this->formatDate($tx['dueDate']).'</strong>.</p>',
        );
    }

    /**
     * sendBorrowRejected() from Notify.gs.
     *
     * @param  array<string, mixed>  $tx
     */
    public function borrowRejected(array $tx, string $reason): void
    {
        $this->send(
            $tx['userEmail'],
            '[ENT Inventory] Borrow Request Rejected — '.$tx['itemName'],
            'Borrow Request Rejected',
            '<p>Hi <strong>'.$tx['userDisplay'].'</strong>,</p>'
            .'<p>Your borrow request has been <strong style="color:#dc3545">rejected</strong>.</p>',
            $this->txTable($tx),
            ($reason !== '' ? '<p><strong>Reason:</strong> '.$reason.'</p>' : '')
            .'<p>Contact your administrator if you have questions.</p>',
        );
    }

    /**
     * sendLowStockAlert() from Notify.gs: to every active admin.
     *
     * @param  array<string, mixed>  $item
     */
    public function lowStockAlert(array $item): void
    {
        foreach ($this->adminEmails() as $email) {
            $this->send(
                $email,
                '[ENT Inventory] Low Stock — '.$item['name'],
                'Low Stock Alert',
                '<p>The following item has fallen below its reorder threshold.</p>',
                '<table style="border-collapse:collapse;width:100%;margin:16px 0">'
                .'<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Item</strong></td><td style="padding:8px;border:1px solid #dee2e6">'.e($item['name']).'</td></tr>'
                .'<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>SKU</strong></td><td style="padding:8px;border:1px solid #dee2e6">'.e($item['sku']).'</td></tr>'
                .'<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Available</strong></td><td style="padding:8px;border:1px solid #dee2e6;color:#dc3545"><strong>'.$item['availableQty'].'</strong></td></tr>'
                .'<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Threshold</strong></td><td style="padding:8px;border:1px solid #dee2e6">'.$item['reorderThreshold'].'</td></tr>'
                .'</table>',
                '<p>Please restock this item soon.</p>',
            );
        }
    }

    /**
     * sendDueReminder() from Notify.gs: to the borrower only.
     *
     * @param  array<string, mixed>  $tx
     */
    public function sendDueReminder(array $tx): void
    {
        $this->send(
            $tx['userEmail'],
            '[ENT Inventory] Return Reminder — '.$tx['itemName'],
            'Return Reminder',
            '<p>Hi <strong>'.$tx['userDisplay'].'</strong>,</p>'
            .'<p>This is a reminder that the following item is due <strong>tomorrow</strong>.</p>'
            .$this->txTable($tx)
            .'<p>Please return it on time to avoid overdue status.</p>',
        );
    }

    /**
     * sendOverdueAlert() from Notify.gs: to the borrower and all active admins.
     *
     * @param  array<string, mixed>  $tx
     */
    public function sendOverdueAlert(array $tx): void
    {
        $recipients = array_merge([$tx['userEmail']], $this->adminEmails());
        $this->send(
            implode(',', $recipients),
            '[ENT Inventory] OVERDUE — '.$tx['itemName'],
            '⚠ Overdue Item',
            '<p>The following item is <strong style="color:#dc3545">overdue</strong>.</p>'
            .$this->txTable($tx)
            .'<p>Borrower: <strong>'.$tx['userDisplay'].'</strong> ('.$tx['userEmail'].')</p>'
            .'<p>Was due: <strong>'.$this->formatDate($tx['dueDate']).'</strong></p>',
        );
    }

    /**
     * getAdminEmails_() from Notify.gs: active ADMIN rows of the Users sheet.
     *
     * @return list<string>
     */
    public function adminEmails(): array
    {
        $emails = [];

        foreach ($this->users->allUsers() as $user) {
            if ($user['role'] === 'ADMIN' && $user['active']) {
                $emails[] = $user['email'];
            }
        }

        return $emails;
    }

    private function send(string $to, string $mailSubject, string $heading, string $introHtml, string $tableHtml, string $footerHtml): void
    {
        if ($to === '') {
            return;
        }

        try {
            Mail::to($to)->send(new NotificationMail($mailSubject, $heading, $introHtml, $tableHtml, $footerHtml));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * txTable_() from Notify.gs.
     *
     * @param  array<string, mixed>  $tx
     */
    private function txTable(array $tx): string
    {
        $row = fn (string $label, string $value): string => '<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>'.$label.'</strong></td><td style="padding:8px;border:1px solid #dee2e6">'.$value.'</td></tr>';

        return '<table style="border-collapse:collapse;width:100%;margin:16px 0">'
            .$row('Item', e($tx['itemName']))
            .$row('Qty', (string) $tx['qty'])
            .$row('Borrow Date', $this->formatDate($tx['borrowDate']))
            .$row('Due Date', $this->formatDate($tx['dueDate']))
            .'</table>';
    }

    /**
     * formatDate_() from Notify.gs: "dd MMM yyyy", falling back to the raw value.
     */
    private function formatDate(string $dateStr): string
    {
        if ($dateStr === '') {
            return '-';
        }

        $timestamp = strtotime($dateStr);

        return $timestamp === false ? $dateStr : date('d M Y', $timestamp);
    }
}
