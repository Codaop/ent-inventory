// ─── Sheet column indices ────────────────────────────────────────────────────
// Transactions: txId(0) | timestamp(1) | userEmail(2) | userDisplay(3)
//   itemId(4) | itemName(5) | qty(6) | type(7) | status(8)
//   borrowDate(9) | dueDate(10) | returnDate(11) | approvedBy(12) | notes(13)

function requestBorrow(payload) {
  const user = requireAuth_();

  const found = findItemRow_(payload.itemId);
  if (!found) throw new Error('ITEM_NOT_FOUND');
  const item = rowToItem_(found.values);

  if (item.availableQty < Number(payload.qty)) throw new Error('INSUFFICIENT_STOCK');
  if (Number(payload.qty) < 1) throw new Error('INVALID_QTY');

  const sheet = getDb_().getSheetByName('Transactions');
  const txId = Utilities.getUuid();
  const now = new Date();

  sheet.appendRow([
    txId,
    now,
    user.email,
    user.displayName,
    payload.itemId,
    item.name,
    Number(payload.qty),
    'BORROW',
    'PENDING',
    now,
    new Date(payload.dueDate),
    '',
    '',
    payload.notes || ''
  ]);

  return { ok: true, txId };
}

function getPendingRequests() {
  requireAdmin_();
  const sheet = getDb_().getSheetByName('Transactions');
  if (!sheet) return [];
  const data = sheet.getDataRange().getValues();
  const results = [];
  for (let i = 1; i < data.length; i++) {
    if (data[i][0] && data[i][8] === 'PENDING') {
      results.push(rowToTx_(data[i]));
    }
  }
  return results.reverse();
}

function approveRequest(txId) {
  const admin = requireAdmin_();
  const lock = LockService.getScriptLock();
  lock.waitLock(10000);

  try {
    const txFound = findTxRow_(txId);
    if (!txFound) throw new Error('TX_NOT_FOUND');
    if (txFound.values[8] !== 'PENDING') throw new Error('NOT_PENDING');

    const itemId = txFound.values[4];
    const qty    = Number(txFound.values[6]);

    const itemFound = findItemRow_(itemId);
    if (!itemFound) throw new Error('ITEM_NOT_FOUND');
    if (itemFound.values[6] < qty) throw new Error('INSUFFICIENT_STOCK');

    const itemSheet = getDb_().getSheetByName('Items');
    itemSheet.getRange(itemFound.rowNum, 7).setValue(itemFound.values[6] - qty);
    itemSheet.getRange(itemFound.rowNum, 11).setValue(new Date());

    const txSheet = getDb_().getSheetByName('Transactions');
    txSheet.getRange(txFound.rowNum, 9).setValue('APPROVED');
    txSheet.getRange(txFound.rowNum, 13).setValue(admin.email);

    const tx = rowToTx_(txFound.values);
    tx.status = 'APPROVED';
    tx.approvedBy = admin.email;
    sendBorrowConfirm(tx);

    const updatedItem = rowToItem_(itemFound.values);
    updatedItem.availableQty -= qty;
    if (updatedItem.availableQty <= updatedItem.reorderThreshold) {
      sendLowStockAlert(updatedItem);
    }

    return { ok: true };
  } finally {
    lock.releaseLock();
  }
}

function rejectRequest(txId, reason) {
  const admin = requireAdmin_();
  const txFound = findTxRow_(txId);
  if (!txFound) throw new Error('TX_NOT_FOUND');
  if (txFound.values[8] !== 'PENDING') throw new Error('NOT_PENDING');

  const txSheet = getDb_().getSheetByName('Transactions');
  txSheet.getRange(txFound.rowNum, 9).setValue('REJECTED');
  txSheet.getRange(txFound.rowNum, 13).setValue(admin.email);
  txSheet.getRange(txFound.rowNum, 14).setValue(reason || '');

  const tx = rowToTx_(txFound.values);
  tx.status = 'REJECTED';
  tx.notes = reason || '';
  sendBorrowRejected(tx, reason);

  return { ok: true };
}

function returnItem(txId) {
  const user = requireAuth_();
  const txFound = findTxRow_(txId);
  if (!txFound) throw new Error('TX_NOT_FOUND');

  const status = txFound.values[8];
  if (status !== 'APPROVED' && status !== 'OVERDUE') throw new Error('NOT_ACTIVE');

  if (user.role !== 'ADMIN' && txFound.values[2] !== user.email) {
    throw new Error('FORBIDDEN');
  }

  const qty    = Number(txFound.values[6]);
  const itemId = txFound.values[4];

  const itemFound = findItemRow_(itemId);
  if (itemFound) {
    const itemSheet = getDb_().getSheetByName('Items');
    itemSheet.getRange(itemFound.rowNum, 7).setValue(itemFound.values[6] + qty);
    itemSheet.getRange(itemFound.rowNum, 11).setValue(new Date());
  }

  const txSheet = getDb_().getSheetByName('Transactions');
  txSheet.getRange(txFound.rowNum, 9).setValue('RETURNED');
  txSheet.getRange(txFound.rowNum, 12).setValue(new Date());

  return { ok: true };
}

function getMyBorrows() {
  const user = requireAuth_();
  const sheet = getDb_().getSheetByName('Transactions');
  if (!sheet) return [];
  const data = sheet.getDataRange().getValues();
  const results = [];
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    if (!row[0] || row[2] !== user.email || row[7] !== 'BORROW') continue;
    results.push(rowToTx_(row));
  }
  return results.reverse();
}

function getActiveBorrows() {
  requireAdmin_();
  const sheet = getDb_().getSheetByName('Transactions');
  if (!sheet) return [];
  const data = sheet.getDataRange().getValues();
  const results = [];
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    if (!row[0] || row[7] !== 'BORROW') continue;
    const status = row[8];
    if (status === 'APPROVED' || status === 'OVERDUE' || status === 'PENDING') {
      results.push(rowToTx_(row));
    }
  }
  return results.reverse();
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function findTxRow_(txId) {
  const sheet = getDb_().getSheetByName('Transactions');
  if (!sheet) return null;
  const data = sheet.getDataRange().getValues();
  for (let i = 1; i < data.length; i++) {
    if (data[i][0] === txId) return { rowNum: i + 1, values: data[i] };
  }
  return null;
}