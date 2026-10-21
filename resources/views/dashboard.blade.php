<!DOCTYPE html>
<html lang="id">
<head>
  <base target="_top">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ENT Inventory</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:        #134679;
      --primary-hover:  #1a5da0;
      --primary-light:  #e8f1fc;
      --sidebar-width:  240px;
      --topbar-height:  56px;
    }

    /* ── Reset / Base ──────────────────────────────── */
    body {
      background: #f0f4f8;
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: .9rem;
    }

    /* ── Top Navbar ─────────────────────────────────── */
    .topbar {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: var(--topbar-height);
      background: var(--primary);
      display: flex;
      align-items: center;
      padding: 0 16px;
      z-index: 1040;
      gap: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,.18);
    }
    .topbar-brand {
      color: #fff;
      font-weight: 700;
      font-size: 1.05rem;
      letter-spacing: .3px;
      flex: 1;
    }
    .topbar-user { color: rgba(255,255,255,.9); font-size: .82rem; }
    .badge-role {
      background: rgba(255,255,255,.2);
      color: #fff;
      font-size: .7rem;
      padding: 2px 8px;
      border-radius: 20px;
      font-weight: 600;
      letter-spacing: .5px;
    }
    .logout-btn {
      background: rgba(255,255,255,.15);
      border: 1px solid rgba(255,255,255,.3);
      color: #fff;
      font-size: .78rem;
      padding: 4px 12px;
      border-radius: 20px;
      cursor: pointer;
      transition: background .15s;
      text-decoration: none;
    }
    .logout-btn:hover { background: rgba(255,255,255,.28); color: #fff; }

    /* ── Sidebar ────────────────────────────────────── */
    .sidebar {
      position: fixed;
      top: var(--topbar-height);
      left: 0;
      bottom: 0;
      width: var(--sidebar-width);
      background: #fff;
      border-right: 1px solid #e2e8f0;
      overflow-y: auto;
      z-index: 1030;
      transition: transform .25s ease;
    }
    .sidebar-section {
      padding: 12px 8px 4px;
      font-size: .7rem;
      font-weight: 700;
      color: #94a3b8;
      letter-spacing: .8px;
      text-transform: uppercase;
    }
    .nav-link-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 16px;
      border-radius: 8px;
      margin: 1px 8px;
      color: #475569;
      cursor: pointer;
      font-size: .875rem;
      transition: background .15s, color .15s;
      text-decoration: none;
    }
    .nav-link-item:hover { background: var(--primary-light); color: var(--primary); }
    .nav-link-item.active { background: var(--primary-light); color: var(--primary); font-weight: 600; }
    .nav-link-item .bi { font-size: 1rem; }

    /* ── Main Content ───────────────────────────────── */
    .main-content {
      margin-left: var(--sidebar-width);
      margin-top: var(--topbar-height);
      padding: 24px;
      min-height: calc(100vh - var(--topbar-height));
    }
    .page { display: none; }
    .page.active { display: block; }

    /* ── KPI Cards ──────────────────────────────────── */
    .kpi-card {
      background: #fff;
      border-radius: 12px;
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }
    .kpi-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      background: var(--primary-light);
      color: var(--primary);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem;
      flex-shrink: 0;
    }
    .kpi-value { font-size: 1.6rem; font-weight: 700; color: #1e293b; line-height: 1; }
    .kpi-label { font-size: .75rem; color: #64748b; margin-top: 2px; }

    /* ── Cards ──────────────────────────────────────── */
    .card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }
    .card-header {
      background: #fff;
      border-bottom: 1px solid #f1f5f9;
      font-weight: 600;
      color: #1e293b;
      border-radius: 12px 12px 0 0 !important;
      padding: 14px 20px;
    }

    /* ── Tables ─────────────────────────────────────── */
    .table thead th {
      background: #f8fafc;
      color: #64748b;
      font-weight: 600;
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .5px;
      border-bottom: 1px solid #e2e8f0;
    }
    .table td { vertical-align: middle; color: #334155; font-size: .875rem; }
    .table-hover tbody tr:hover { background: var(--primary-light); }

    /* ── Buttons ────────────────────────────────────── */
    .btn-primary { background: var(--primary); border-color: var(--primary); }
    .btn-primary:hover, .btn-primary:focus { background: var(--primary-hover); border-color: var(--primary-hover); }
    .btn-outline-primary { color: var(--primary); border-color: var(--primary); }
    .btn-outline-primary:hover { background: var(--primary); border-color: var(--primary); }

    /* ── Badges ─────────────────────────────────────── */
    .badge-pending  { background: #fff3cd; color: #856404; }
    .badge-approved { background: #d1e7dd; color: #155724; }
    .badge-rejected { background: #f8d7da; color: #842029; }
    .badge-returned { background: #e2e8f0; color: #475569; }
    .badge-overdue  { background: #f8d7da; color: #842029; }
    .badge-completed{ background: #d1e7dd; color: #155724; }
    .status-badge {
      padding: 3px 10px;
      border-radius: 20px;
      font-size: .75rem;
      font-weight: 600;
      display: inline-block;
    }

    /* ── Toast ──────────────────────────────────────── */
    .toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; }
    .toast { min-width: 280px; border-radius: 10px; border: none; box-shadow: 0 4px 16px rgba(0,0,0,.12); }

    /* ── Loading overlay ────────────────────────────── */
    #loading-overlay {
      position: fixed; inset: 0;
      background: rgba(255,255,255,.7);
      display: flex; align-items: center; justify-content: center;
      z-index: 9998;
      backdrop-filter: blur(2px);
    }
    .spinner-ring {
      width: 44px; height: 44px;
      border: 4px solid #e2e8f0;
      border-top-color: var(--primary);
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Search bar ─────────────────────────────────── */
    .search-box { position: relative; max-width: 280px; }
    .search-box input { padding-left: 36px; border-radius: 8px; border: 1px solid #e2e8f0; }
    .search-box .bi-search {
      position: absolute; left: 10px; top: 50%;
      transform: translateY(-50%); color: #94a3b8; font-size: .9rem;
    }

    /* ── Low-stock badge in qty cell ─────────────────── */
    .low-stock { color: #dc3545; font-weight: 600; }

    /* ── Mobile ─────────────────────────────────────── */
    @media (max-width: 767px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); box-shadow: 4px 0 24px rgba(0,0,0,.15); }
      .main-content { margin-left: 0; padding: 16px; }
      .sidebar-backdrop {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.35); z-index: 1025;
      }
      .sidebar-backdrop.show { display: block; }
      .kpi-value { font-size: 1.3rem; }
    }
  </style>
</head>
<body>

<!-- Loading overlay -->
<div id="loading-overlay" style="display:none">
  <div class="spinner-ring"></div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toast-container"></div>

<!-- Sidebar backdrop (mobile) -->
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="closeSidebar()"></div>

<!-- ── Top navbar ─────────────────────────────────── -->
<header class="topbar">
  <button class="btn btn-link text-white p-0 d-md-none me-2" onclick="toggleSidebar()" style="font-size:1.3rem">
    <i class="bi bi-list"></i>
  </button>
  <div class="topbar-brand"><i class="bi bi-boxes me-2"></i>ENT Inventory</div>
  <div class="topbar-user d-none d-sm-block">{{ $currentUser['displayName'] }}</div>
  <span class="badge-role">{{ $currentUser['role'] }}</span>
  <form method="POST" action="{{ route('auth.logout') }}" class="mb-0">
    @csrf
    <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
  </form>
</header>

<!-- ── Sidebar ────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="pt-2 pb-3">
    <div class="sidebar-section">Menu</div>

    <a class="nav-link-item" data-page="dashboard" onclick="navigate('dashboard')">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a class="nav-link-item" data-page="inventory" onclick="navigate('inventory')">
      <i class="bi bi-boxes"></i> Inventory
    </a>
    <a class="nav-link-item" data-page="my-borrows" onclick="navigate('my-borrows')">
      <i class="bi bi-bag-check"></i> My Borrows
    </a>

    @if($currentUser['role'] === 'ADMIN')
    <div class="sidebar-section" style="margin-top:8px">Admin</div>

    <a class="nav-link-item" data-page="admin-requests" onclick="navigate('admin-requests')">
      <i class="bi bi-clipboard-check"></i> Borrow Requests
      <span class="ms-auto badge bg-danger rounded-pill" id="badge-pending" style="display:none"></span>
    </a>
    <a class="nav-link-item" data-page="admin-users" onclick="navigate('admin-users')">
      <i class="bi bi-people"></i> Users
    </a>
    <a class="nav-link-item" data-page="reports" onclick="navigate('reports')">
      <i class="bi bi-bar-chart-line"></i> Reports
    </a>
    @endif
  </div>
</aside>

<!-- ── Main content ───────────────────────────────── -->
<main class="main-content">

  <!-- ─── Dashboard ───────────────────────────────── -->
  <div class="page" id="page-dashboard">
    <h5 class="fw-bold text-dark mb-4">Dashboard</h5>
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="kpi-card">
          <div class="kpi-icon"><i class="bi bi-boxes"></i></div>
          <div><div class="kpi-value" id="kpi-total">—</div><div class="kpi-label">Total Items</div></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#fff3cd;color:#856404"><i class="bi bi-exclamation-triangle"></i></div>
          <div><div class="kpi-value" id="kpi-lowstock">—</div><div class="kpi-label">Low Stock</div></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#d1e7dd;color:#155724"><i class="bi bi-bag-check"></i></div>
          <div><div class="kpi-value" id="kpi-borrows">—</div><div class="kpi-label">My Active Borrows</div></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#e8f1fc;color:#134679"><i class="bi bi-currency-dollar"></i></div>
          <div><div class="kpi-value" id="kpi-value" style="font-size:1.1rem">—</div><div class="kpi-label">Inventory Value</div></div>
        </div>
      </div>
      @if($currentUser['role'] === 'ADMIN')
      <div class="col-6 col-lg-3" id="kpi-pending-wrap">
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#f8d7da;color:#842029"><i class="bi bi-hourglass-split"></i></div>
          <div><div class="kpi-value" id="kpi-pending">—</div><div class="kpi-label">Pending Approvals</div></div>
        </div>
      </div>
      @endif
    </div>

    <div class="card">
      <div class="card-header">Recent Activity</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th>Date</th><th>User</th><th>Item</th><th>Qty</th><th>Type</th><th>Status</th>
          </tr></thead>
          <tbody id="tbl-recent-body">
            <tr><td colspan="6" class="text-center text-muted py-3">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ─── Inventory ────────────────────────────────── -->
  <div class="page" id="page-inventory">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h5 class="fw-bold text-dark mb-0">Inventory</h5>
      @if($currentUser['role'] === 'ADMIN')
      <button class="btn btn-primary" onclick="openAddItemModal()">
        <i class="bi bi-plus-lg me-1"></i>Add Item
      </button>
      @endif
    </div>

    <div class="card mb-3 p-3">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input type="text" id="inv-search" class="form-control" placeholder="Search name / SKU…" oninput="filterInventory()">
        </div>
        <select id="inv-filter-cat" class="form-select" style="width:auto" onchange="filterInventory()">
          <option value="">All Categories</option>
        </select>
        <div class="form-check mb-0 ms-2">
          <input class="form-check-input" type="checkbox" id="inv-filter-low" onchange="filterInventory()">
          <label class="form-check-label" for="inv-filter-low">Low Stock Only</label>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th>SKU</th><th>Name / Category</th><th>Location</th>
            <th>Available</th><th>Total</th><th>Unit Price</th><th>Actions</th>
          </tr></thead>
          <tbody id="tbl-inventory-body">
            <tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ─── My Borrows ───────────────────────────────── -->
  <div class="page" id="page-my-borrows">
    <h5 class="fw-bold text-dark mb-4">My Borrows</h5>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th>Item</th><th>Qty</th><th>Borrowed</th><th>Due Date</th><th>Returned</th><th>Status</th><th>Action</th>
          </tr></thead>
          <tbody id="tbl-my-borrows-body">
            <tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @if($currentUser['role'] === 'ADMIN')
  <!-- ─── Admin: Borrow Requests ───────────────────── -->
  <div class="page" id="page-admin-requests">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold text-dark mb-0">Borrow Requests</h5>
      <button class="btn btn-outline-secondary btn-sm" onclick="loadPendingRequests()">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
    </div>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th>Borrower</th><th>Item</th><th>Qty</th><th>Borrow Date</th><th>Due Date</th><th>Notes</th><th>Actions</th>
          </tr></thead>
          <tbody id="tbl-pending-body">
            <tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ─── Admin: Users ─────────────────────────────── -->
  <div class="page" id="page-admin-users">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold text-dark mb-0">User Management</h5>
      <button class="btn btn-primary" onclick="openAddUserModal()">
        <i class="bi bi-person-plus me-1"></i>Add User
      </button>
    </div>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th>Email</th><th>Display Name</th><th>Role</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody id="tbl-users-body">
            <tr><td colspan="5" class="text-center text-muted py-3">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ─── Reports ──────────────────────────────────── -->
  <div class="page" id="page-reports">
    <h5 class="fw-bold text-dark mb-3">Reports</h5>
    <div class="card mb-3 p-3">
      <div class="d-flex flex-wrap gap-2 align-items-end">
        <div>
          <label class="form-label small mb-1">From</label>
          <input type="date" id="rpt-from" class="form-control" style="width:160px">
        </div>
        <div>
          <label class="form-label small mb-1">To</label>
          <input type="date" id="rpt-to" class="form-control" style="width:160px">
        </div>
        <div>
          <label class="form-label small mb-1">Type</label>
          <select id="rpt-type" class="form-select" style="width:140px">
            <option value="">All Types</option>
            <option>BORROW</option>
            <option>RETURN</option>
            <option>RESTOCK</option>
            <option>ADJUST</option>
          </select>
        </div>
        <button class="btn btn-primary" onclick="loadReports()">
          <i class="bi bi-search me-1"></i>Filter
        </button>
      </div>
    </div>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th>Date</th><th>User</th><th>Item</th><th>Qty</th><th>Type</th><th>Status</th><th>Due Date</th><th>Returned</th>
          </tr></thead>
          <tbody id="tbl-reports-body">
            <tr><td colspan="8" class="text-center text-muted py-3">Select filters and click Filter.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

</main>

<!-- ── Modals ─────────────────────────────────────── -->

@if($currentUser['role'] === 'ADMIN')
<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary);color:#fff">
        <h5 class="modal-title" id="item-modal-title">Add Item</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="form-item">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" id="item-name" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label">SKU</label>
              <input type="text" id="item-sku" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label">Category</label>
              <input type="text" id="item-category" class="form-control" list="category-list">
              <datalist id="category-list"></datalist>
            </div>
            <div class="col-12">
              <label class="form-label">Location</label>
              <input type="text" id="item-location" class="form-control" placeholder="e.g. Room A, Cabinet 2">
            </div>
            <div class="col-4">
              <label class="form-label">Total Qty</label>
              <input type="number" id="item-total-qty" class="form-control" min="0" value="0">
            </div>
            <div class="col-4">
              <label class="form-label">Available</label>
              <input type="number" id="item-avail-qty" class="form-control" min="0" value="0">
            </div>
            <div class="col-4">
              <label class="form-label">Reorder At</label>
              <input type="number" id="item-threshold" class="form-control" min="0" value="5">
            </div>
            <div class="col-6">
              <label class="form-label">Unit Price (Rp)</label>
              <input type="number" id="item-price" class="form-control" min="0" value="0">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveItem()">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary);color:#fff">
        <h5 class="modal-title">Adjust Stock</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="fw-semibold" id="adjust-item-name"></p>
        <div class="mb-3">
          <label class="form-label">Delta (+ restock / − remove)</label>
          <input type="number" id="adjust-delta" class="form-control" placeholder="e.g. 10 or -3">
        </div>
        <div class="mb-3">
          <label class="form-label">Reason</label>
          <input type="text" id="adjust-reason" class="form-control" placeholder="Restock, damaged, etc.">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveAdjust()">Apply</button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center py-4">
        <i class="bi bi-trash3 text-danger" style="font-size:2.5rem"></i>
        <h5 class="mt-2 mb-1">Delete Item?</h5>
        <p class="text-muted small mb-3">This action cannot be undone.</p>
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="doDeleteItem()">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- Reject Reason Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reject Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Reason (optional)</label>
        <textarea id="reject-reason" class="form-control" rows="3" placeholder="Explain why the request is rejected…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="doReject()">Reject</button>
      </div>
    </div>
  </div>
</div>

<!-- Add / Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary);color:#fff">
        <h5 class="modal-title">Add User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="form-user">
          <div class="mb-3">
            <label class="form-label">Google Email <span class="text-danger">*</span></label>
            <input type="email" id="user-email" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Display Name</label>
            <input type="text" id="user-displayname" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select id="user-role" class="form-select">
              <option value="BORROWER">Borrower</option>
              <option value="ADMIN">Admin</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveUser()">Add</button>
      </div>
    </div>
  </div>
</div>
@endif

<!-- Borrow Modal (all users) -->
<div class="modal fade" id="borrowModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary);color:#fff">
        <h5 class="modal-title">Borrow Request</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3" id="borrow-item-name"></p>
        <div class="mb-3">
          <label class="form-label">Quantity <span class="text-danger">*</span></label>
          <input type="number" id="borrow-qty" class="form-control" min="1" value="1">
        </div>
        <div class="mb-3">
          <label class="form-label">Return Due Date <span class="text-danger">*</span></label>
          <input type="date" id="borrow-due" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea id="borrow-notes" class="form-control" rows="2" placeholder="Purpose, event, etc."></textarea>
        </div>
        <div class="alert alert-info small py-2 mb-0">
          <i class="bi bi-info-circle me-1"></i>
          Your request will be reviewed by an admin before the item is reserved.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitBorrow()">Submit Request</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Current user injected from server -->
<script>
  var CURRENT_USER = {
    email:       '{{ addslashes($currentUser['email']) }}',
    displayName: '{{ addslashes($currentUser['displayName']) }}',
    role:        '{{ $currentUser['role'] }}'
  };
  var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
</script>

<script>
// ─── API wrapper ─────────────────────────────────────────────────────────────
function api(method, url, body) {
  var opts = {
    method: method,
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': CSRF_TOKEN
    }
  };
  if (body !== undefined && body !== null) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  return fetch(url, opts).then(function(res) {
    if (res.status === 401 || res.status === 403) {
      window.location.href = '/login';
      return Promise.reject(new Error('Unauthenticated'));
    }
    return res.json().then(function(data) {
      if (!res.ok) {
        var msg = (data && data.message) ? data.message : ('HTTP ' + res.status);
        return Promise.reject(new Error(msg));
      }
      return data;
    });
  });
}

// ─── State ───────────────────────────────────────────────────────────────────
var state = {
  items: [],
  categories: [],
  currentItemId: null,
  currentTxId: null
};

// ─── Utilities ───────────────────────────────────────────────────────────────
function fmtDate(str) {
  if (!str) { return '—'; }
  var d = new Date(str);
  return isNaN(d) ? str : d.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
}

function fmtCurrency(n) {
  return 'Rp\u00a0' + Number(n || 0).toLocaleString('id-ID');
}

function statusBadge(s) {
  var cls = { PENDING:'pending', APPROVED:'approved', REJECTED:'rejected',
              RETURNED:'returned', OVERDUE:'overdue', COMPLETED:'completed' };
  return '<span class="status-badge badge-' + (cls[s] || 'returned') + '">' + (s || '—') + '</span>';
}

function escHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Loading overlay ─────────────────────────────────────────────────────────
function showLoading() { document.getElementById('loading-overlay').style.display = 'flex'; }
function hideLoading() { document.getElementById('loading-overlay').style.display = 'none'; }

// ─── Toast notifications ─────────────────────────────────────────────────────
function showToast(msg, type) {
  var bg = type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#198754';
  var tc = type === 'warning' ? '#000' : '#fff';
  var el = document.createElement('div');
  el.className = 'toast show';
  el.style.background = bg;
  el.style.color = tc;
  el.innerHTML = '<div class="toast-body d-flex align-items-center gap-2">' +
    '<i class="bi bi-' + (type === 'error' ? 'x-circle' : 'check-circle') + '"></i>' +
    '<span>' + escHtml(msg) + '</span>' +
    '</div>';
  document.getElementById('toast-container').appendChild(el);
  setTimeout(function() { el.remove(); }, 3500);
}

// ─── Navigation ──────────────────────────────────────────────────────────────
function navigate(page) {
  document.querySelectorAll('.page').forEach(function(p) { p.classList.remove('active'); });
  document.querySelectorAll('.nav-link-item').forEach(function(l) { l.classList.remove('active'); });

  var pageEl = document.getElementById('page-' + page);
  if (pageEl) { pageEl.classList.add('active'); }

  var linkEl = document.querySelector('[data-page="' + page + '"]');
  if (linkEl) { linkEl.classList.add('active'); }

  closeSidebar();

  if (page === 'dashboard')      { loadDashboard(); }
  if (page === 'inventory')      { loadInventory(); }
  if (page === 'my-borrows')     { loadMyBorrows(); }
  if (page === 'admin-requests') { loadPendingRequests(); }
  if (page === 'admin-users')    { loadUsers(); }
  if (page === 'reports')        { /* user clicks Filter to load */ }
}

// ─── Sidebar (mobile) ────────────────────────────────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-backdrop').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-backdrop').classList.remove('show');
}

// ─── Dashboard ───────────────────────────────────────────────────────────────
function loadDashboard() {
  showLoading();

  var itemsP   = api('GET', '/items');
  var borrowsP = api('GET', '/borrows/my');
  var pendingP = CURRENT_USER.role === 'ADMIN' ? api('GET', '/borrows/pending') : Promise.resolve([]);

  Promise.all([itemsP, borrowsP, pendingP]).then(function(results) {
    hideLoading();
    var items   = results[0] || [];
    var myBorrows  = results[1] || [];
    var pending = results[2] || [];

    var totalItems  = items.length;
    var lowStock    = items.filter(function(i) { return i.availableQty <= i.reorderThreshold; }).length;
    var activeBorrows = myBorrows.filter(function(t) { return t.status === 'APPROVED' || t.status === 'OVERDUE'; }).length;
    var totalValue  = items.reduce(function(sum, i) { return sum + (Number(i.unitPrice) * Number(i.totalQty)); }, 0);

    document.getElementById('kpi-total').textContent    = totalItems;
    document.getElementById('kpi-lowstock').textContent = lowStock;
    document.getElementById('kpi-borrows').textContent  = activeBorrows;
    document.getElementById('kpi-value').textContent    = fmtCurrency(totalValue);

    var kpiPending = document.getElementById('kpi-pending');
    if (kpiPending) { kpiPending.textContent = pending.length; }

    var badgePending = document.getElementById('badge-pending');
    if (badgePending) {
      badgePending.textContent = pending.length;
      badgePending.style.display = pending.length > 0 ? '' : 'none';
    }

    // Recent: combine borrows (sorted by borrowDate desc, take 10)
    var recent = myBorrows.slice().sort(function(a, b) {
      return new Date(b.borrowDate || 0) - new Date(a.borrowDate || 0);
    }).slice(0, 10);

    var tbody = document.getElementById('tbl-recent-body');
    tbody.innerHTML = recent.length ? '' : '<tr><td colspan="6" class="text-center text-muted py-3">No activity yet.</td></tr>';
    recent.forEach(function(tx) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td>' + fmtDate(tx.borrowDate) + '</td>' +
        '<td>' + escHtml(tx.userDisplay || tx.userEmail || '') + '</td>' +
        '<td>' + escHtml(tx.itemName || '') + '</td>' +
        '<td>' + (tx.qty || '') + '</td>' +
        '<td>BORROW</td>' +
        '<td>' + statusBadge(tx.status) + '</td>';
      tbody.appendChild(tr);
    });
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

// ─── Inventory ───────────────────────────────────────────────────────────────
function loadInventory(filter) {
  showLoading();

  var url = '/items';
  var params = [];
  if (filter) {
    if (filter.search)   { params.push('search='   + encodeURIComponent(filter.search)); }
    if (filter.category) { params.push('category=' + encodeURIComponent(filter.category)); }
    if (filter.lowStock) { params.push('lowStock=1'); }
  }
  if (params.length) { url += '?' + params.join('&'); }

  Promise.all([api('GET', '/categories'), api('GET', url)]).then(function(results) {
    hideLoading();
    state.categories = results[0] || [];
    state.items      = results[1] || [];

    var sel = document.getElementById('inv-filter-cat');
    if (sel) {
      var current = sel.value;
      sel.innerHTML = '<option value="">All Categories</option>';
      state.categories.forEach(function(c) {
        var opt = document.createElement('option');
        opt.value = c; opt.textContent = c;
        if (c === current) { opt.selected = true; }
        sel.appendChild(opt);
      });
    }

    renderInventoryTable(state.items);
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

function renderInventoryTable(items) {
  var tbody = document.getElementById('tbl-inventory-body');
  tbody.innerHTML = '';
  if (!items.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No items found.</td></tr>';
    return;
  }
  items.forEach(function(item) {
    var lowStock = item.availableQty <= item.reorderThreshold;
    var qtyClass = lowStock ? 'low-stock' : '';
    var isAdmin  = CURRENT_USER.role === 'ADMIN';
    var actions;

    if (isAdmin) {
      actions = '<button class="btn btn-sm btn-outline-primary me-1" onclick="openEditItemModal(\'' + escHtml(item.itemId) + '\')" title="Edit"><i class="bi bi-pencil"></i></button>' +
        '<button class="btn btn-sm btn-outline-secondary me-1" onclick="openAdjustModal(\'' + escHtml(item.itemId) + '\')" title="Adjust Stock"><i class="bi bi-plus-slash-minus"></i></button>' +
        '<button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteItem(\'' + escHtml(item.itemId) + '\')" title="Delete"><i class="bi bi-trash"></i></button>';
    } else {
      actions = '<button class="btn btn-sm btn-primary" onclick="openBorrowModal(\'' + escHtml(item.itemId) + '\')" ' +
        (item.availableQty < 1 ? 'disabled' : '') + '>Borrow</button>';
    }

    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td>' + escHtml(item.sku) + '</td>' +
      '<td><strong>' + escHtml(item.name) + '</strong><div class="text-muted small">' + escHtml(item.category) + '</div></td>' +
      '<td>' + escHtml(item.location) + '</td>' +
      '<td class="' + qtyClass + '">' + item.availableQty + (lowStock ? ' <i class="bi bi-exclamation-triangle-fill text-danger small"></i>' : '') + '</td>' +
      '<td>' + item.totalQty + '</td>' +
      '<td>' + fmtCurrency(item.unitPrice) + '</td>' +
      '<td>' + actions + '</td>';
    tbody.appendChild(tr);
  });
}

function filterInventory() {
  var q   = document.getElementById('inv-search').value.trim();
  var cat = document.getElementById('inv-filter-cat').value;
  var low = document.getElementById('inv-filter-low').checked;

  // Client-side filter on cached items for speed
  var filtered = state.items.filter(function(item) {
    var matchQ   = !q   || item.name.toLowerCase().includes(q.toLowerCase()) || (item.sku || '').toLowerCase().includes(q.toLowerCase());
    var matchCat = !cat || item.category === cat;
    var matchLow = !low || item.availableQty <= item.reorderThreshold;
    return matchQ && matchCat && matchLow;
  });
  renderInventoryTable(filtered);
}

// ── Item Modal (Add / Edit) ───────────────────────────────────────────────────
function openAddItemModal() {
  state.currentItemId = null;
  document.getElementById('item-modal-title').textContent = 'Add Item';
  document.getElementById('form-item').reset();
  populateCategoryDatalist();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('itemModal')).show();
}

function openEditItemModal(itemId) {
  var item = state.items.find(function(i) { return i.itemId === itemId; });
  if (!item) { return; }
  state.currentItemId = itemId;
  document.getElementById('item-modal-title').textContent = 'Edit Item';
  document.getElementById('item-name').value       = item.name;
  document.getElementById('item-sku').value        = item.sku;
  document.getElementById('item-category').value   = item.category;
  document.getElementById('item-location').value   = item.location;
  document.getElementById('item-total-qty').value  = item.totalQty;
  document.getElementById('item-avail-qty').value  = item.availableQty;
  document.getElementById('item-threshold').value  = item.reorderThreshold;
  document.getElementById('item-price').value      = item.unitPrice;
  populateCategoryDatalist();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('itemModal')).show();
}

function populateCategoryDatalist() {
  var dl = document.getElementById('category-list');
  dl.innerHTML = '';
  state.categories.forEach(function(c) {
    var opt = document.createElement('option');
    opt.value = c;
    dl.appendChild(opt);
  });
}

function saveItem() {
  var payload = {
    name:             document.getElementById('item-name').value.trim(),
    sku:              document.getElementById('item-sku').value.trim(),
    category:         document.getElementById('item-category').value.trim(),
    location:         document.getElementById('item-location').value.trim(),
    totalQty:         Number(document.getElementById('item-total-qty').value),
    availableQty:     Number(document.getElementById('item-avail-qty').value),
    reorderThreshold: Number(document.getElementById('item-threshold').value),
    unitPrice:        Number(document.getElementById('item-price').value)
  };
  if (!payload.name) { showToast('Item name required.', 'error'); return; }

  showLoading();
  var promise = state.currentItemId
    ? api('PATCH', '/items/' + state.currentItemId, payload)
    : api('POST', '/items', payload);

  promise.then(function() {
    hideLoading();
    bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
    showToast(state.currentItemId ? 'Item updated.' : 'Item created.');
    loadInventory();
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

// ── Delete Confirm ────────────────────────────────────────────────────────────
function confirmDeleteItem(itemId) {
  state.currentItemId = itemId;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}

function doDeleteItem() {
  showLoading();
  api('DELETE', '/items/' + state.currentItemId).then(function() {
    hideLoading();
    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
    showToast('Item deleted.');
    loadInventory();
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

// ── Adjust Stock ──────────────────────────────────────────────────────────────
function openAdjustModal(itemId) {
  state.currentItemId = itemId;
  var item = state.items.find(function(i) { return i.itemId === itemId; });
  document.getElementById('adjust-item-name').textContent = item ? item.name : '';
  document.getElementById('adjust-delta').value = '';
  document.getElementById('adjust-reason').value = '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('adjustModal')).show();
}

function saveAdjust() {
  var delta  = Number(document.getElementById('adjust-delta').value);
  var reason = document.getElementById('adjust-reason').value.trim();
  if (!delta) { showToast('Enter a non-zero quantity.', 'error'); return; }

  showLoading();
  api('POST', '/items/' + state.currentItemId + '/adjust', { delta: delta, reason: reason }).then(function() {
    hideLoading();
    bootstrap.Modal.getInstance(document.getElementById('adjustModal')).hide();
    showToast('Stock adjusted.');
    loadInventory();
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

// ─── Borrow Modal ─────────────────────────────────────────────────────────────
function openBorrowModal(itemId) {
  var item = state.items.find(function(i) { return i.itemId === itemId; });
  if (!item) { return; }
  state.currentItemId = itemId;

  document.getElementById('borrow-item-name').textContent = item.name + ' (available: ' + item.availableQty + ')';
  document.getElementById('borrow-qty').value   = 1;
  document.getElementById('borrow-qty').max     = item.availableQty;

  var tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  document.getElementById('borrow-due').min   = tomorrow.toISOString().split('T')[0];
  document.getElementById('borrow-due').value = '';
  document.getElementById('borrow-notes').value = '';

  bootstrap.Modal.getOrCreateInstance(document.getElementById('borrowModal')).show();
}

function submitBorrow() {
  var qty   = Number(document.getElementById('borrow-qty').value);
  var due   = document.getElementById('borrow-due').value;
  var notes = document.getElementById('borrow-notes').value.trim();

  if (!qty || qty < 1) { showToast('Quantity must be at least 1.', 'error'); return; }
  if (!due)            { showToast('Due date is required.', 'error');  return; }

  showLoading();
  api('POST', '/borrows', { itemId: state.currentItemId, qty: qty, dueDate: due, notes: notes })
    .then(function() {
      hideLoading();
      bootstrap.Modal.getInstance(document.getElementById('borrowModal')).hide();
      showToast('Borrow request submitted. Waiting for admin approval.');
      loadInventory();
    }).catch(function(e) {
      hideLoading();
      showToast(e.message, 'error');
    });
}

// ─── My Borrows ───────────────────────────────────────────────────────────────
function loadMyBorrows() {
  showLoading();
  api('GET', '/borrows/my').then(function(txs) {
    hideLoading();
    var tbody = document.getElementById('tbl-my-borrows-body');
    tbody.innerHTML = '';
    if (!txs.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No borrow history.</td></tr>';
      return;
    }
    txs.forEach(function(tx) {
      var canReturn = tx.status === 'APPROVED' || tx.status === 'OVERDUE';
      var returnBtn = canReturn
        ? '<button class="btn btn-sm btn-success" onclick="doReturn(\'' + tx.txId + '\')"><i class="bi bi-arrow-return-left"></i> Return</button>'
        : '';
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td><strong>' + escHtml(tx.itemName) + '</strong></td>' +
        '<td>' + tx.qty + '</td>' +
        '<td>' + fmtDate(tx.borrowDate) + '</td>' +
        '<td>' + fmtDate(tx.dueDate) + '</td>' +
        '<td>' + (tx.returnDate ? fmtDate(tx.returnDate) : '—') + '</td>' +
        '<td>' + statusBadge(tx.status) + '</td>' +
        '<td>' + returnBtn + '</td>';
      tbody.appendChild(tr);
    });
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

function doReturn(txId) {
  if (!confirm('Confirm return of this item?')) { return; }
  showLoading();
  api('POST', '/borrows/' + txId + '/return').then(function() {
    hideLoading();
    showToast('Item returned successfully.');
    loadMyBorrows();
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

// ─── Admin: Pending Requests ──────────────────────────────────────────────────
function loadPendingRequests() {
  showLoading();
  api('GET', '/borrows/pending').then(function(txs) {
    hideLoading();
    var tbody = document.getElementById('tbl-pending-body');
    tbody.innerHTML = '';
    if (!txs.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No pending requests.</td></tr>';
      return;
    }
    txs.forEach(function(tx) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + escHtml(tx.userDisplay || tx.userEmail || '') + '<div class="text-muted small">' + escHtml(tx.userEmail || '') + '</div></td>' +
        '<td><strong>' + escHtml(tx.itemName) + '</strong></td>' +
        '<td>' + tx.qty + '</td>' +
        '<td>' + fmtDate(tx.borrowDate) + '</td>' +
        '<td>' + fmtDate(tx.dueDate) + '</td>' +
        '<td>' + escHtml(tx.notes || '—') + '</td>' +
        '<td>' +
          '<button class="btn btn-sm btn-success me-1" onclick="doApprove(\'' + tx.txId + '\')"><i class="bi bi-check-lg"></i> Approve</button>' +
          '<button class="btn btn-sm btn-outline-danger" onclick="openRejectModal(\'' + tx.txId + '\')"><i class="bi bi-x-lg"></i> Reject</button>' +
        '</td>';
      tbody.appendChild(tr);
    });
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

function doApprove(txId) {
  showLoading();
  api('POST', '/borrows/' + txId + '/approve').then(function() {
    hideLoading();
    showToast('Request approved.');
    loadPendingRequests();
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

function openRejectModal(txId) {
  state.currentTxId = txId;
  document.getElementById('reject-reason').value = '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('rejectModal')).show();
}

function doReject() {
  var reason = document.getElementById('reject-reason').value.trim();
  showLoading();
  api('POST', '/borrows/' + state.currentTxId + '/reject', { reason: reason }).then(function() {
    hideLoading();
    bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
    showToast('Request rejected.');
    loadPendingRequests();
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

// ─── Admin: Users ─────────────────────────────────────────────────────────────
function loadUsers() {
  showLoading();
  api('GET', '/users').then(function(users) {
    hideLoading();
    var tbody = document.getElementById('tbl-users-body');
    tbody.innerHTML = '';
    if (!users.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No users.</td></tr>';
      return;
    }
    users.forEach(function(u) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + escHtml(u.email) + '</td>' +
        '<td>' + escHtml(u.displayName) + '</td>' +
        '<td><span class="badge ' + (u.role === 'ADMIN' ? 'bg-primary' : 'bg-secondary') + '">' + u.role + '</span></td>' +
        '<td>' + (u.active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>') + '</td>' +
        '<td>' +
          '<button class="btn btn-sm btn-outline-secondary" onclick="toggleUserActive(\'' + escHtml(u.email) + '\',' + (!u.active) + ')">' +
          (u.active ? '<i class="bi bi-person-x"></i> Deactivate' : '<i class="bi bi-person-check"></i> Activate') + '</button>' +
        '</td>';
      tbody.appendChild(tr);
    });
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

function openAddUserModal() {
  document.getElementById('form-user').reset();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).show();
}

function saveUser() {
  var email       = document.getElementById('user-email').value.trim();
  var displayName = document.getElementById('user-displayname').value.trim();
  var role        = document.getElementById('user-role').value;
  if (!email) { showToast('Email required.', 'error'); return; }

  showLoading();
  api('POST', '/users', { email: email, displayName: displayName, role: role }).then(function() {
    hideLoading();
    bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
    showToast('User added.');
    loadUsers();
  }).catch(function(e) {
    hideLoading();
    showToast(e.message === 'EMAIL_EXISTS' ? 'Email already exists.' : e.message, 'error');
  });
}

function toggleUserActive(email, active) {
  showLoading();
  api('PATCH', '/users/' + encodeURIComponent(email), { active: active }).then(function() {
    hideLoading();
    showToast('User updated.');
    loadUsers();
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

// ─── Reports ─────────────────────────────────────────────────────────────────
function loadReports() {
  var params = [];
  var from  = document.getElementById('rpt-from').value;
  var to    = document.getElementById('rpt-to').value;
  var type  = document.getElementById('rpt-type').value;
  if (from) { params.push('fromDate=' + encodeURIComponent(from)); }
  if (to)   { params.push('toDate='   + encodeURIComponent(to)); }
  if (type) { params.push('type='     + encodeURIComponent(type)); }

  var url = '/borrows/active' + (params.length ? '?' + params.join('&') : '');

  showLoading();
  api('GET', url).then(function(txs) {
    hideLoading();
    var tbody = document.getElementById('tbl-reports-body');
    tbody.innerHTML = '';
    if (!txs.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>';
      return;
    }
    txs.forEach(function(tx) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + fmtDate(tx.borrowDate || tx.timestamp) + '</td>' +
        '<td>' + escHtml(tx.userDisplay || tx.userEmail || '') + '</td>' +
        '<td>' + escHtml(tx.itemName) + '</td>' +
        '<td>' + tx.qty + '</td>' +
        '<td><span class="badge bg-secondary">BORROW</span></td>' +
        '<td>' + statusBadge(tx.status) + '</td>' +
        '<td>' + fmtDate(tx.dueDate) + '</td>' +
        '<td>' + (tx.returnDate ? fmtDate(tx.returnDate) : '—') + '</td>';
      tbody.appendChild(tr);
    });
  }).catch(function(e) {
    hideLoading();
    showToast(e.message, 'error');
  });
}

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  navigate('dashboard');
});
</script>
</body>
</html>
