<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: ../index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ssoEmail = trim((string) ($_POST['email'] ?? ''));
    if ($ssoEmail !== '') {
        if (login_sso_employee($ssoEmail)) {
            header('Location: ../index.php');
            exit;
        }

        $error = 'This email is not registered as an active Clientele employee.';
    } else {
        verify_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (login_admin($username, $password)) {
            header('Location: ../index.php');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Eduriser Clientele</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, sans-serif; }
        .login-bg {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(0,47,128,.94), rgba(30,85,138,.78)),
                url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&q=80&w=2000') center/cover;
        }
    </style>
</head>
<body>
    <main class="login-bg flex items-center justify-center px-5 py-10">
        <section class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-2xl">
            <div class="mb-8 flex justify-center">
                <img src="https://eduriserck.com/eduriser/eduriser-logo.svg" alt="EduRiser Logo" class="h-12 w-auto">
            </div>
            <h1 class="text-2xl font-black text-slate-950">Clientele Admin</h1>
            <p class="mt-2 text-sm font-semibold leading-6 text-slate-500">Sign in to manage clients and categories.</p>

            <?php if ($error !== ''): ?>
                <div class="mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm font-bold text-red-700"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-7 space-y-5">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div>
                    <label for="username" class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Username</label>
                    <input id="username" name="username" type="text" required autocomplete="username" class="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100">
                </div>
                <div>
                    <label for="password" class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100">
                </div>
                <button type="submit" class="w-full rounded-lg bg-blue-900 px-5 py-3 text-sm font-black text-white transition hover:bg-blue-800">Sign In</button>
            </form>
        </section>
    </main>
</body>
</html>
