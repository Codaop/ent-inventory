// ─── Entry point ────────────────────────────────────────────────────────────

function doGet(e) {
  const detectedEmail = Session.getActiveUser().getEmail();
  const user = getCurrentUser();

  if (!user) {
    const denied = HtmlService.createHtmlOutput(accessDeniedHtml_(detectedEmail))
      .setTitle('Access Denied');
    denied.setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
    return denied;
  }

  const template = HtmlService.createTemplateFromFile('Index');
  template.userEmail = user.email;
  template.userDisplay = user.displayName;
  template.userRole = user.role;

  const output = template.evaluate()
    .setTitle('ENT Inventory')
    .addMetaTag('viewport', 'width=device-width, initial-scale=1');
  output.setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
  return output;
}

function include(filename) {
  return HtmlService.createHtmlOutputFromFile(filename).getContent();
}

function getDb_() {
  const id = PropertiesService.getScriptProperties().getProperty('SHEET_ID');
  if (!id) throw new Error('SHEET_ID not set. Run setupSheetId("your-sheet-id") in the Apps Script editor.');
  return SpreadsheetApp.openById(id);
}

function setupSheetId(sheetId) {
  PropertiesService.getScriptProperties().setProperty('SHEET_ID', sheetId);
  Logger.log('SHEET_ID saved: ' + sheetId);
}

// ─── Dashboard ───────────────────────────────────────────────────────────────

function getDashboardStats() {
  requireAuth_();
  const user = getCurrentUser();
  const db = getDb_();

  const itemSheet = db.getSheetByName('Items');
  const txSheet   = db.getSheetByName('Transactions');

  let totalItems = 0, lowStockCount = 0, totalValue = 0;
  if (itemSheet) {
    const data = itemSheet.getDataRange().getValues();
    for (let i = 1; i < data.length; i++) {
      const row = data[i];
      if (!row[0] || row[11] === true) continue;
      totalItems++;
      totalValue += (row[6] * row[8]);
      if (row[6] <= row[7]) lowStockCount++;
    }
  }

  let myActiveBorrows = 0, pendingApprovals = 0;
  const recentActivity = [];
  if (txSheet) {
    const data = txSheet.getDataRange().getValues();
    for (let i = data.length - 1; i >= 1; i--) {
      const row = data[i];
      if (!row[0]) continue;
      const status = row[8];
      const userEmail = row[2];
      if (userEmail === user.email && (status === 'APPROVED' || status === 'OVERDUE')) myActiveBorrows++;
      if (status === 'PENDING') pendingApprovals++;
      if (recentActivity.length < 10) {
        recentActivity.push(rowToTx_(row));
      }
    }
  }

  return { totalItems, lowStockCount, totalValue, myActiveBorrows, pendingApprovals, recentActivity };
}

// ─── Reports ─────────────────────────────────────────────────────────────────

function getTransactions(filter) {
  requireAuth_();
  const user = getCurrentUser();
  const sheet = getDb_().getSheetByName('Transactions');
  if (!sheet) return [];

  const data = sheet.getDataRange().getValues();
  const results = [];
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    if (!row[0]) continue;
    const tx = rowToTx_(row);

    if (filter) {
      if (filter.myOnly && tx.userEmail !== user.email) continue;
      if (filter.status && tx.status !== filter.status) continue;
      if (filter.type && tx.type !== filter.type) continue;
      if (filter.fromDate) {
        const from = new Date(filter.fromDate);
        if (new Date(tx.timestamp) < from) continue;
      }
      if (filter.toDate) {
        const to = new Date(filter.toDate);
        to.setHours(23, 59, 59);
        if (new Date(tx.timestamp) > to) continue;
      }
    }
    results.push(tx);
  }
  return results.reverse();
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function rowToTx_(row) {
  // Transactions: txId(0) | timestamp(1) | userEmail(2) | userDisplay(3)
  //   itemId(4) | itemName(5) | qty(6) | type(7) | status(8)
  //   borrowDate(9) | dueDate(10) | returnDate(11) | approvedBy(12) | notes(13)
  return {
    txId:        row[0],
    timestamp:   row[1] ? row[1].toString() : '',
    userEmail:   row[2],
    userDisplay: row[3],
    itemId:      row[4],
    itemName:    row[5],
    qty:         row[6],
    type:        row[7],
    status:      row[8],
    borrowDate:  row[9]  ? row[9].toString()  : '',
    dueDate:     row[10] ? row[10].toString()  : '',
    returnDate:  row[11] ? row[11].toString()  : '',
    approvedBy:  row[12],
    notes:       row[13]
  };
}

function accessDeniedHtml_(detectedEmail) {
  const emailMsg = detectedEmail
    ? 'Detected email: <code>' + detectedEmail + '</code>'
    : 'No email detected — <strong>not signed in</strong> or session scope missing.';
  return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Access Denied</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .card { border: none; box-shadow: 0 4px 24px rgba(0,0,0,.08); border-radius: 16px; }
    .icon { font-size: 3rem; color: #134679; }
  </style>
</head>
<body>
  <div class="card p-5 text-center" style="max-width:460px">
    <div class="icon mb-3">🔒</div>
    <h4 class="fw-bold text-dark mb-2">Access Denied</h4>
    <p class="text-muted mb-2">Your account is not authorised to use this app.<br>Contact your administrator to request access.</p>
    <p class="small mb-4">${emailMsg}</p>
    <a href="https://accounts.google.com/signout" class="btn btn-outline-secondary btn-sm">Sign out of Google</a>
  </div>
</body>
</html>`;
}