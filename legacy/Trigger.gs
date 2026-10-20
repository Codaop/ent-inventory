function installTriggers() {
  // Remove existing daily scan triggers to avoid duplicates
  ScriptApp.getProjectTriggers().forEach(function(t) {
    if (t.getHandlerFunction() === 'dailyScan') {
      ScriptApp.deleteTrigger(t);
    }
  });

  ScriptApp.newTrigger('dailyScan')
    .timeBased()
    .everyDays(1)
    .atHour(7)
    .create();

  Logger.log('Trigger installed: dailyScan runs daily at 07:00.');
}

function dailyScan() {
  const sheet = getDb_().getSheetByName('Transactions');
  if (!sheet) return;

  const data = sheet.getDataRange().getValues();
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);

  const lowStockAlerted = {}; // dedupe per item

  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    if (!row[0] || row[7] !== 'BORROW') continue;

    const status = row[8];
    if (status !== 'APPROVED' && status !== 'OVERDUE') continue;

    const dueDate = row[10] ? new Date(row[10]) : null;
    if (!dueDate) continue;
    dueDate.setHours(0, 0, 0, 0);

    const tx = rowToTx_(row);

    if (dueDate.getTime() === tomorrow.getTime()) {
      sendDueReminder(tx);
    }

    if (dueDate < today && status !== 'OVERDUE') {
      sheet.getRange(i + 1, 9).setValue('OVERDUE');
      tx.status = 'OVERDUE';
      sendOverdueAlert(tx);
    } else if (dueDate < today && status === 'OVERDUE') {
      sendOverdueAlert(tx);
    }
  }

  // Low stock scan
  const itemSheet = getDb_().getSheetByName('Items');
  if (itemSheet) {
    const items = itemSheet.getDataRange().getValues();
    for (let i = 1; i < items.length; i++) {
      const row = items[i];
      if (!row[0] || row[11] === true) continue;
      if (row[6] <= row[7] && !lowStockAlerted[row[0]]) {
        lowStockAlerted[row[0]] = true;
        sendLowStockAlert(rowToItem_(row));
      }
    }
  }

  Logger.log('dailyScan complete: ' + new Date());
}

function setupSheets() {
  const db = getDb_();
  const sheets = {
    'Items': [
      'itemId','sku','name','category','location',
      'totalQty','availableQty','reorderThreshold','unitPrice',
      'createdAt','updatedAt','deleted'
    ],
    'Transactions': [
      'txId','timestamp','userEmail','userDisplayName',
      'itemId','itemName','qty','type','status',
      'borrowDate','dueDate','returnDate','approvedBy','notes'
    ],
    'Users': ['email','displayName','role','active'],
    'Categories': ['name'],
    'Config': ['key','value']
  };

  Object.entries(sheets).forEach(function([name, headers]) {
    let sheet = db.getSheetByName(name);
    if (!sheet) {
      sheet = db.insertSheet(name);
    }
    if (sheet.getLastRow() === 0) {
      sheet.appendRow(headers);
      sheet.getRange(1, 1, 1, headers.length)
        .setBackground('#134679')
        .setFontColor('#ffffff')
        .setFontWeight('bold');
    }
  });

  Logger.log('Sheets setup complete.');
}