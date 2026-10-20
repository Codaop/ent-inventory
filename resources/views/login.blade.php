<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · ENT Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container" style="max-width: 24rem;">
    <div class="card mt-5 shadow-sm">
        <div class="card-body text-center p-4">
            <h1 class="h4 mb-3">ENT Inventory</h1>

            @if ($error === 'ACCESS_DENIED')
                <div class="alert alert-danger">
                    <strong>ACCESS_DENIED</strong> — your Google account is not registered or is inactive.
                    Contact an administrator.
                </div>
            @endif

            <a class="btn btn-primary w-100" href="/auth/google">Sign in with Google</a>
        </div>
    </div>
</main>
</body>
</html>
