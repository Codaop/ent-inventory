var BRAND_COLOR = '#134679';
var BRAND_NAME  = 'ENT Inventory';

function sendBorrowConfirm(tx) {
  try {
    MailApp.sendEmail({
      to: tx.userEmail,
      subject: '[' + BRAND_NAME + '] Borrow Request Approved — ' + tx.itemName,
      htmlBody: emailWrap_(
        'Borrow Request Approved',
        '<p>Hi <strong>' + tx.userDisplay + '</strong>,</p>' +
        '<p>Your borrow request has been <strong style="color:#28a745">approved</strong>.</p>' +
        txTable_(tx) +
        '<p>Please return the item by <strong>' + formatDate_(tx.dueDate) + '</strong>.</p>'
      )
    });
  } catch (e) {
    Logger.log('sendBorrowConfirm error: ' + e.message);
  }
}

function sendBorrowRejected(tx, reason) {
  try {
    MailApp.sendEmail({
      to: tx.userEmail,
      subject: '[' + BRAND_NAME + '] Borrow Request Rejected — ' + tx.itemName,
      htmlBody: emailWrap_(
        'Borrow Request Rejected',
        '<p>Hi <strong>' + tx.userDisplay + '</strong>,</p>' +
        '<p>Your borrow request has been <strong style="color:#dc3545">rejected</strong>.</p>' +
        txTable_(tx) +
        (reason ? '<p><strong>Reason:</strong> ' + reason + '</p>' : '') +
        '<p>Contact your administrator if you have questions.</p>'
      )
    });
  } catch (e) {
    Logger.log('sendBorrowRejected error: ' + e.message);
  }
}

function sendDueReminder(tx) {
  try {
    MailApp.sendEmail({
      to: tx.userEmail,
      subject: '[' + BRAND_NAME + '] Return Reminder — ' + tx.itemName + ' due tomorrow',
      htmlBody: emailWrap_(
        'Return Reminder',
        '<p>Hi <strong>' + tx.userDisplay + '</strong>,</p>' +
        '<p>This is a reminder that the following item is due <strong>tomorrow</strong>.</p>' +
        txTable_(tx) +
        '<p>Please return it on time to avoid overdue status.</p>'
      )
    });
  } catch (e) {
    Logger.log('sendDueReminder error: ' + e.message);
  }
}

function sendOverdueAlert(tx) {
  try {
    const admins = getAdminEmails_();
    const recipients = [tx.userEmail].concat(admins).join(',');
    MailApp.sendEmail({
      to: recipients,
      subject: '[' + BRAND_NAME + '] OVERDUE — ' + tx.itemName,
      htmlBody: emailWrap_(
        '⚠ Overdue Item',
        '<p>The following item is <strong style="color:#dc3545">overdue</strong>.</p>' +
        txTable_(tx) +
        '<p>Borrower: <strong>' + tx.userDisplay + '</strong> (' + tx.userEmail + ')</p>' +
        '<p>Was due: <strong>' + formatDate_(tx.dueDate) + '</strong></p>'
      )
    });
  } catch (e) {
    Logger.log('sendOverdueAlert error: ' + e.message);
  }
}

function sendLowStockAlert(item) {
  try {
    const admins = getAdminEmails_();
    if (!admins.length) return;
    MailApp.sendEmail({
      to: admins.join(','),
      subject: '[' + BRAND_NAME + '] Low Stock — ' + item.name,
      htmlBody: emailWrap_(
        'Low Stock Alert',
        '<p>The following item has fallen below its reorder threshold.</p>' +
        '<table style="border-collapse:collapse;width:100%;margin:16px 0">' +
        '<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Item</strong></td><td style="padding:8px;border:1px solid #dee2e6">' + item.name + '</td></tr>' +
        '<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>SKU</strong></td><td style="padding:8px;border:1px solid #dee2e6">' + item.sku + '</td></tr>' +
        '<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Available</strong></td><td style="padding:8px;border:1px solid #dee2e6;color:#dc3545"><strong>' + item.availableQty + '</strong></td></tr>' +
        '<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Threshold</strong></td><td style="padding:8px;border:1px solid #dee2e6">' + item.reorderThreshold + '</td></tr>' +
        '</table>' +
        '<p>Please restock this item soon.</p>'
      )
    });
  } catch (e) {
    Logger.log('sendLowStockAlert error: ' + e.message);
  }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getAdminEmails_() {
  const sheet = getDb_().getSheetByName('Users');
  if (!sheet) return [];
  const data = sheet.getDataRange().getValues();
  const emails = [];
  for (let i = 1; i < data.length; i++) {
    if (data[i][2] === 'ADMIN' && data[i][3] === true) emails.push(data[i][0]);
  }
  return emails;
}

function txTable_(tx) {
  return '<table style="border-collapse:collapse;width:100%;margin:16px 0">' +
    '<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Item</strong></td><td style="padding:8px;border:1px solid #dee2e6">' + tx.itemName + '</td></tr>' +
    '<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Qty</strong></td><td style="padding:8px;border:1px solid #dee2e6">' + tx.qty + '</td></tr>' +
    '<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Borrow Date</strong></td><td style="padding:8px;border:1px solid #dee2e6">' + formatDate_(tx.borrowDate) + '</td></tr>' +
    '<tr><td style="padding:8px;border:1px solid #dee2e6"><strong>Due Date</strong></td><td style="padding:8px;border:1px solid #dee2e6">' + formatDate_(tx.dueDate) + '</td></tr>' +
    '</table>';
}

function formatDate_(dateStr) {
  if (!dateStr) return '-';
  try {
    return Utilities.formatDate(new Date(dateStr), Session.getScriptTimeZone(), 'dd MMM yyyy');
  } catch (e) {
    return dateStr;
  }
}

function emailWrap_(heading, body) {
  return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f0f4f8;margin:0;padding:20px">' +
    '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">' +
    '<div style="background:' + BRAND_COLOR + ';padding:20px 28px">' +
    '<h2 style="color:#fff;margin:0;font-size:1.1rem">' + BRAND_NAME + '</h2>' +
    '</div>' +
    '<div style="padding:28px">' +
    '<h3 style="color:' + BRAND_COLOR + ';margin-top:0">' + heading + '</h3>' +
    body +
    '</div>' +
    '<div style="background:#f8f9fa;padding:14px 28px;font-size:12px;color:#6c757d;text-align:center">' +
    'This is an automated message from ' + BRAND_NAME + '.</div>' +
    '</div></body></html>';
}