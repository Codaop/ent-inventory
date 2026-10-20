// ─── Sheet column indices ───────────────────────────────────────────────────
// Users: email(0) | displayName(1) | role(2) | active(3)

function getCurrentUser() {
  const email = Session.getActiveUser().getEmail();
  if (!email) return null;

  let sheet;
  try {
    sheet = getDb_().getSheetByName('Users');
  } catch (e) {
    return null;
  }
  if (!sheet) return null;

  const data = sheet.getDataRange().getValues();
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    const isActive = row[3] === true || String(row[3]).toUpperCase() === 'TRUE';
    if (String(row[0]).toLowerCase().trim() === email.toLowerCase().trim() && isActive) {
      return { email: row[0], displayName: row[1] || row[0], role: row[2] };
    }
  }
  return null;
}

function requireAuth_() {
  const user = getCurrentUser();
  if (!user) throw new Error('ACCESS_DENIED');
  return user;
}

function requireAdmin_() {
  const user = requireAuth_();
  if (user.role !== 'ADMIN') throw new Error('ADMIN_REQUIRED');
  return user;
}

// ─── User management (admin only) ───────────────────────────────────────────

function getUsers() {
  requireAdmin_();
  const sheet = getDb_().getSheetByName('Users');
  if (!sheet) return [];
  const data = sheet.getDataRange().getValues();
  const result = [];
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    if (!row[0]) continue;
    result.push({ email: row[0], displayName: row[1], role: row[2], active: row[3] });
  }
  return result;
}

function createUser(payload) {
  requireAdmin_();
  const sheet = getDb_().getSheetByName('Users');
  const data = sheet.getDataRange().getValues();
  for (let i = 1; i < data.length; i++) {
    if (String(data[i][0]).toLowerCase() === payload.email.toLowerCase()) {
      throw new Error('EMAIL_EXISTS');
    }
  }
  sheet.appendRow([
    payload.email,
    payload.displayName || payload.email,
    payload.role || 'BORROWER',
    true
  ]);
  return { ok: true };
}

function updateUser(email, payload) {
  requireAdmin_();
  const sheet = getDb_().getSheetByName('Users');
  const data = sheet.getDataRange().getValues();
  for (let i = 1; i < data.length; i++) {
    if (String(data[i][0]).toLowerCase() === email.toLowerCase()) {
      const row = i + 1;
      if (payload.displayName !== undefined) sheet.getRange(row, 2).setValue(payload.displayName);
      if (payload.role !== undefined) sheet.getRange(row, 3).setValue(payload.role);
      if (payload.active !== undefined) sheet.getRange(row, 4).setValue(payload.active);
      return { ok: true };
    }
  }
  throw new Error('USER_NOT_FOUND');
}

function deleteUser(email) {
  return updateUser(email, { active: false });
}