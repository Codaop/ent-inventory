<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · ENT Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #1e293b;
        }
        .login-card {
            background: #ffffff;
            border-radius: 1.25rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(226, 232, 240, 0.8);
            width: 100%;
            max-width: 25rem;
            overflow: hidden;
            transition: transform 0.2s ease;
        }
        .card-header-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #e0f2fe;
            color: #0369a1;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            background-color: #ffffff;
            color: #1f2937;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            padding: 0.85rem 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
            width: 100%;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-google:hover {
            background-color: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-1px);
        }
        .btn-google:active {
            transform: translateY(0);
            background-color: #f1f5f9;
        }
        .brand-icon {
            width: 3.25rem;
            height: 3.25rem;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            color: white;
            box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.3);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="p-4 p-sm-5 text-center">
        <!-- Logo / Brand Icon -->
        <div class="brand-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                <path d="m3.3 7 8.7 5 8.7-5"/>
                <path d="M12 22V12"/>
            </svg>
        </div>

        <span class="card-header-badge">ENT Inventory</span>
        <h1 class="h4 fw-bold text-slate-900 mb-1">Masuk ke Sistem</h1>
        <p class="text-muted small mb-4">Sistem Inventaris Laboratorium & Departemen THT</p>

        <!-- Error Handling -->
        @if ($error === 'ACCESS_DENIED')
            <div class="alert alert-danger text-start p-3 rounded-3 mb-4 border-0 bg-danger-subtle text-danger-emphasis">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <strong>Akses Ditolak</strong>
                </div>
                <div class="small">
                    Akun Google/Gmail Anda belum terdaftar di database spreadsheet atau berstatus non-aktif.
                </div>
                @if (!empty($reason))
                    <div class="mt-2 pt-2 border-top border-danger-subtle small font-monospace" style="font-size: 0.75rem;">
                        <strong>Info Teknis:</strong> {{ $reason }}
                    </div>
                @endif
            </div>
        @endif

        <!-- Google / Gmail Login Button -->
        <a class="btn-google" href="/auth/google">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.65v3h3.88c2.27-2.09 3.66-5.17 3.66-9.09z"/>
                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.27v3.09C3.29 21.43 7.37 24 12 24z"/>
                <path fill="#FBBC05" d="M5.28 14.32c-.25-.72-.38-1.49-.38-2.32s.14-1.6.38-2.32V6.59H1.27C.46 8.21 0 10.05 0 12s.46 3.79 1.27 5.41l4.01-3.09z"/>
                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.37 0 3.29 2.57 1.27 6.59l4.01 3.09c.95-2.83 3.6-4.93 6.72-4.93z"/>
            </svg>
            <span>Masuk dengan Google / Gmail</span>
        </a>

        <div class="mt-4 pt-3 border-top text-muted" style="font-size: 0.78rem;">
            Pastikan email Gmail Anda telah didaftarkan oleh Administrator pada tab <strong>Users</strong> spreadsheet.
        </div>
    </div>
</div>

</body>
</html>
