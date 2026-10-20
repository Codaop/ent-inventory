// ─── Sheet column indices ────────────────────────────────────────────────────
// Items: itemId(0) | sku(1) | name(2) | category(3) | location(4)
//        totalQty(5) | availableQty(6) | reorderThreshold(7) | unitPrice(8)
//        createdAt(9) | updatedAt(10) | deleted(11)

function listItems(filter) {
  requireAuth_();
  const sheet = getDb_().getSheetByName('Items');
  if (!sheet) return [];

  const data = sheet.getDataRange().getValues();
  const results = [];

  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    if (!row[0] || row[11] === true) continue;

    const item = rowToItem_(row);
    if (filter) {
      if (filter.category && item.category !== filter.category) continue;
      if (filter.location && item.location !== filter.location) continue;
      if (filter.lowStock && item.availableQty > item.reorderThreshold) continue;
      if (filter.search) {
        const q = filter.search.toLowerCase();
        const match = item.name.toLowerCase().includes(q) ||
                      item.sku.toLowerCase().includes(q) ||
                      item.category.toLowerCase().includes(q);
        if (!match) continue;
      }
    }
    results.push(item);
  }
  return results;
}

function getItem(itemId) {
  requireAuth_();
  const row = findItemRow_(itemId);
  if (!row) throw new Error('ITEM_NOT_FOUND');
  return rowToItem_(row.values);
}

function createItem(payload) {
  requireAdmin_();
  const sheet = getDb_().getSheetByName('Items');
  const now = new Date();
  const itemId = Utilities.getUuid();

  sheet.appendRow([
    itemId,
    payload.sku || '',
    payload.name,
    payload.category || '',
    payload.location || '',
    Number(payload.totalQty) || 0,
    Number(payload.availableQty) !== undefined ? Number(payload.availableQty) : Number(payload.totalQty) || 0,
    Number(payload.reorderThreshold) || 5,
    Number(payload.unitPrice) || 0,
    now,
    now,
    false
  ]);

  ensureCategory_(payload.category);
  return { ok: true, itemId };
}

function updateItem(itemId, payload) {
  requireAdmin_();
  const found = findItemRow_(itemId);
  if (!found) throw new Error('ITEM_NOT_FOUND');

  const sheet = getDb_().getSheetByName('Items');
  const rowNum = found.rowNum;
  const row = found.values;

  const updated = [
    row[0],
    payload.sku !== undefined ? payload.sku : row[1],
    payload.name !== undefined ? payload.name : row[2],
    payload.category !== undefined ? payload.category : row[3],
    payload.location !== undefined ? payload.location : row[4],
    payload.totalQty !== undefined ? Number(payload.totalQty) : row[5],
    payload.availableQty !== undefined ? Number(payload.availableQty) : row[6],
    payload.reorderThreshold !== undefined ? Number(payload.reorderThreshold) : row[7],
    payload.unitPrice !== undefined ? Number(payload.unitPrice) : row[8],
    row[9],
    new Date(),
    row[11]
  ];

  sheet.getRange(rowNum, 1, 1, updated.length).setValues([updated]);
  if (payload.category) ensureCategory_(payload.category);
  return { ok: true };
}

function deleteItem(itemId) {
  requireAdmin_();
  const found = findItemRow_(itemId);
  if (!found) throw new Error('ITEM_NOT_FOUND');
  const sheet = getDb_().getSheetByName('Items');
  sheet.getRange(found.rowNum, 12).setValue(true);
  return { ok: true };
}

function adjustStock(itemId, delta, reason) {
  requireAdmin_();
  const found = findItemRow_(itemId);
  if (!found) throw new Error('ITEM_NOT_FOUND');

  const sheet = getDb_().getSheetByName('Items');
  const row = found.values;
  const newTotal = row[5] + Number(delta);
  const newAvail = row[6] + Number(delta);

  if (newAvail < 0) throw new Error('INSUFFICIENT_STOCK');

  sheet.getRange(found.rowNum, 6).setValue(newTotal);
  sheet.getRange(found.rowNum, 7).setValue(newAvail);
  sheet.getRange(found.rowNum, 11).setValue(new Date());

  const txSheet = getDb_().getSheetByName('Transactions');
  const admin = getCurrentUser();
  txSheet.appendRow([
    Utilities.getUuid(),
    new Date(),
    admin.email,
    admin.displayName,
    itemId,
    row[2],
    Math.abs(Number(delta)),
    delta > 0 ? 'RESTOCK' : 'ADJUST',
    'COMPLETED',
    '', '', '', admin.email,
    reason || ''
  ]);

  return { ok: true, newTotal, newAvail };
}

function getCategories() {
  requireAuth_();
  const sheet = getDb_().getSheetByName('Categories');
  if (!sheet) return [];
  const data = sheet.getDataRange().getValues();
  return data.slice(1).map(r => r[0]).filter(Boolean);
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function rowToItem_(row) {
  return {
    itemId: row[0],
    sku: row[1],
    name: row[2],
    category: row[3],
    location: row[4],
    totalQty: row[5],
    availableQty: row[6],
    reorderThreshold: row[7],
    unitPrice: row[8],
    createdAt: row[9] ? row[9].toString() : '',
    updatedAt: row[10] ? row[10].toString() : ''
  };
}

function findItemRow_(itemId) {
  const sheet = getDb_().getSheetByName('Items');
  if (!sheet) return null;
  const data = sheet.getDataRange().getValues();
  for (let i = 1; i < data.length; i++) {
    if (data[i][0] === itemId && data[i][11] !== true) {
      return { rowNum: i + 1, values: data[i] };
    }
  }
  return null;
}

function ensureCategory_(name) {
  if (!name) return;
  const sheet = getDb_().getSheetByName('Categories');
  if (!sheet) return;
  const data = sheet.getDataRange().getValues();
  for (let i = 0; i < data.length; i++) {
    if (String(data[i][0]).toLowerCase() === name.toLowerCase()) return;
  }
  sheet.appendRow([name]);
}