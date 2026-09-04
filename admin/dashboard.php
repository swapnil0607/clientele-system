<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/client_repository.php';
require_once __DIR__ . '/../includes/uploads.php';

require_login();

$pdo = db();
$message = '';
$error = '';
$currentAdminEmail = current_admin_email();
$isSuperAdmin = employee_is_super_admin($currentAdminEmail);
$previewAdminView = $isSuperAdmin && ($_GET['preview_role'] ?? '') === 'admin';
$canViewSuperAdminTools = $isSuperAdmin && !$previewAdminView;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $superAdminActions = ['delete_client', 'save_employee', 'toggle_employee_admin', 'toggle_employee_super_admin', 'toggle_employee_active'];

    try {
        if (in_array($action, $superAdminActions, true) && !$isSuperAdmin) {
            throw new RuntimeException('Only super admins can manage employee access.');
        }

        if ($action === 'save_category') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($name === '') {
                throw new RuntimeException('Category name is required.');
            }
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT sort_order FROM categories WHERE id = ?');
                $stmt->execute([$id]);
                $sortOrder = (int) $stmt->fetchColumn();
                $stmt = $pdo->prepare('UPDATE categories SET name = ?, slug = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$name, slugify($name), $sortOrder, $isActive, $id]);
                audit_log('category_updated', 'category', $id, $name, ['is_active' => $isActive]);
                $message = 'Category updated.';
            } else {
                $sortOrder = ((int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM categories')->fetchColumn()) + 10;
                $stmt = $pdo->prepare('INSERT INTO categories (name, slug, sort_order, is_active) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, slugify($name), $sortOrder, $isActive]);
                $id = (int) $pdo->lastInsertId();
                audit_log('category_added', 'category', $id, $name, ['is_active' => $isActive]);
                $message = 'Category added.';
            }
        }

        if ($action === 'save_client') {
            $id = (int) ($_POST['id'] ?? 0);
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $logoUrl = trim((string) ($_POST['logo_url'] ?? ''));
            if (isset($_FILES['logo_file'])) {
                $logoUrl = handle_logo_upload($_FILES['logo_file'], $name, $logoUrl);
            }
            $primaryUrl = trim((string) ($_POST['primary_url'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($categoryId <= 0 || $name === '') {
                throw new RuntimeException('Client name and category are required.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT sort_order FROM clients WHERE id = ?');
                $stmt->execute([$id]);
                $sortOrder = (int) $stmt->fetchColumn();
                $stmt = $pdo->prepare('UPDATE clients SET category_id = ?, name = ?, logo_url = ?, primary_url = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$categoryId, $name, $logoUrl, $primaryUrl, $sortOrder, $isActive, $id]);
                audit_log('client_updated', 'client', $id, $name, ['category_id' => $categoryId, 'is_active' => $isActive]);
                $message = 'Client updated.';
            } else {
                $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM clients WHERE category_id = ?');
                $stmt->execute([$categoryId]);
                $sortOrder = ((int) $stmt->fetchColumn()) + 10;
                $stmt = $pdo->prepare('INSERT INTO clients (category_id, name, logo_url, primary_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$categoryId, $name, $logoUrl, $primaryUrl, $sortOrder, $isActive]);
                $id = (int) $pdo->lastInsertId();
                audit_log('client_added', 'client', $id, $name, ['category_id' => $categoryId, 'is_active' => $isActive]);
                $message = 'Client added.';
            }

            $linkTitle = trim((string) ($_POST['link_title'] ?? 'Portal'));
            $linkUrl = trim((string) ($_POST['link_url'] ?? $primaryUrl));
            if ($linkUrl !== '') {
                $pdo->prepare('DELETE FROM client_links WHERE client_id = ?')->execute([$id]);
                $stmt = $pdo->prepare('INSERT INTO client_links (client_id, title, url, sort_order) VALUES (?, ?, ?, 0)');
                $stmt->execute([$id, $linkTitle !== '' ? $linkTitle : 'Portal', $linkUrl]);
                audit_log('client_link_saved', 'client', $id, $name, ['title' => $linkTitle !== '' ? $linkTitle : 'Portal', 'url' => $linkUrl]);
            }
        }

        if ($action === 'delete_client') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT name FROM clients WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $deletedClientName = (string) $stmt->fetchColumn();
            $pdo->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]);
            audit_log('client_deleted', 'client', $id, $deletedClientName);
            $message = 'Client deleted.';
        }

        if ($action === 'save_employee') {
            $name = trim((string) ($_POST['employee_name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['employee_email'] ?? '')));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid employee email is required.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO employees (name, email, can_access_admin, is_super_admin, is_active)
                 VALUES (?, ?, 0, 0, 1)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1'
            );
            $stmt->execute([$name !== '' ? $name : null, $email]);
            audit_log('employee_saved', 'employee', null, $email, ['name' => $name !== '' ? $name : null, 'default_active' => 1]);
            $message = 'Employee saved. Rights can be assigned separately by a super admin.';
        }

        if ($action === 'toggle_employee_admin') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT email, can_access_admin, is_super_admin FROM employees WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $employee = $stmt->fetch();
            if (!$employee) {
                throw new RuntimeException('Employee not found.');
            }
            if ((int) $employee['is_super_admin'] === 1 && (int) $employee['can_access_admin'] === 1) {
                throw new RuntimeException('Super admin access includes admin access.');
            }
            $stmt = $pdo->prepare('UPDATE employees SET can_access_admin = IF(can_access_admin = 1, 0, 1) WHERE id = ? AND is_super_admin = 0');
            $stmt->execute([$id]);
            audit_log('employee_admin_toggled', 'employee', $id, (string) $employee['email']);
            $message = 'Employee admin access updated.';
        }

        if ($action === 'toggle_employee_super_admin') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT email, is_super_admin FROM employees WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $employee = $stmt->fetch();
            if (!$employee) {
                throw new RuntimeException('Employee not found.');
            }
            if (strtolower((string) $employee['email']) === strtolower($currentAdminEmail) && (int) $employee['is_super_admin'] === 1) {
                throw new RuntimeException('You cannot remove your own super admin access.');
            }
            $stmt = $pdo->prepare('UPDATE employees SET is_super_admin = IF(is_super_admin = 1, 0, 1), can_access_admin = 1 WHERE id = ?');
            $stmt->execute([$id]);
            audit_log('employee_super_admin_toggled', 'employee', $id, (string) $employee['email']);
            $message = 'Employee super admin access updated.';
        }

        if ($action === 'toggle_employee_active') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT email, is_active FROM employees WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $employee = $stmt->fetch();
            if (!$employee) {
                throw new RuntimeException('Employee not found.');
            }
            if (strtolower((string) $employee['email']) === strtolower($currentAdminEmail) && (int) $employee['is_active'] === 1) {
                throw new RuntimeException('You cannot deactivate your own employee access.');
            }
            $stmt = $pdo->prepare('UPDATE employees SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$id]);
            audit_log('employee_active_toggled', 'employee', $id, (string) $employee['email']);
            $message = 'Employee status updated.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$categories = get_categories();
$search = trim((string) ($_GET['search'] ?? ''));
$filterCategoryId = (int) ($_GET['category_id'] ?? 0);
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(c.name LIKE ? OR c.primary_url LIKE ? OR cat.name LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($filterCategoryId > 0) {
    $where[] = 'c.category_id = ?';
    $params[] = $filterCategoryId;
}

$sql = 'SELECT c.*, cat.name AS category_name,
               (SELECT COUNT(*) FROM client_links cl WHERE cl.client_id = c.id) AS link_count
        FROM clients c
        INNER JOIN categories cat ON cat.id = c.category_id';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY cat.sort_order ASC, cat.name ASC, c.sort_order ASC, c.name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();
$employees = $canViewSuperAdminTools
    ? $pdo->query('SELECT * FROM employees ORDER BY is_super_admin DESC, can_access_admin DESC, is_active DESC, name ASC, email ASC')->fetchAll()
    : [];

$totalClientCount = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$activeClientCount = (int) $pdo->query('SELECT COUNT(*) FROM clients WHERE is_active = 1')->fetchColumn();
$adminEmployeeCount = (int) $pdo->query('SELECT COUNT(*) FROM employees WHERE can_access_admin = 1 AND is_active = 1')->fetchColumn();
try {
    $superAdminEmployeeCount = (int) $pdo->query('SELECT COUNT(*) FROM employees WHERE is_super_admin = 1 AND is_active = 1')->fetchColumn();
} catch (Throwable $exception) {
    $superAdminEmployeeCount = 0;
}
$auditLogs = [];
$auditPerPage = 50;
$auditPage = max(1, (int) ($_GET['audit_page'] ?? 1));
$auditTotalCount = 0;
$auditTotalPages = 1;
if ($canViewSuperAdminTools) {
    try {
        $auditTotalCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $auditTotalPages = max(1, (int) ceil($auditTotalCount / $auditPerPage));
        $auditPage = min($auditPage, $auditTotalPages);
        $auditOffset = ($auditPage - 1) * $auditPerPage;
        $auditLogs = $pdo->query(
            'SELECT * FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT ' . $auditPerPage . ' OFFSET ' . $auditOffset
        )->fetchAll();
    } catch (Throwable $exception) {
        $auditLogs = [];
    }
}

$auditLogGroups = [];
$auditLogGroupsByDate = [];
foreach ($auditLogs as $log) {
    $timeBucket = substr((string) ($log['created_at'] ?? ''), 0, 16);
    $dateBucket = substr((string) ($log['created_at'] ?? ''), 0, 10);
    $entityKey = implode('|', [
        (string) ($log['actor_email'] ?? ''),
        (string) ($log['entity_type'] ?? ''),
        (string) ($log['entity_id'] ?? ''),
        (string) ($log['entity_name'] ?? ''),
        $timeBucket,
    ]);

    if (!isset($auditLogGroups[$entityKey])) {
        $auditLogGroups[$entityKey] = [
            'date' => $dateBucket,
            'time' => $timeBucket,
            'actor_email' => (string) ($log['actor_email'] ?? ''),
            'entity_type' => (string) ($log['entity_type'] ?? ''),
            'entity_name' => (string) ($log['entity_name'] ?? ''),
            'logs' => [],
        ];
    }

    $auditLogGroups[$entityKey]['logs'][] = $log;
}

foreach ($auditLogGroups as $group) {
    $auditLogGroupsByDate[$group['date']][] = $group;
}

$auditPageUrl = function (int $page): string {
    $params = $_GET;
    $params['audit_page'] = (string) $page;
    return 'dashboard.php?' . http_build_query($params) . '#changeLogs';
};

$totalClients = count($clients);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Eduriser Clientele</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, sans-serif; background: #f5f7fb; }
        .admin-shell { margin: 0 auto; max-width: 88rem; padding: 1.5rem 1.25rem 2rem; }
        .admin-surface {
            border: 1px solid #dbe3ef;
            border-radius: 0.9rem;
            background: #fff;
            box-shadow: 0 18px 42px -34px rgba(15, 23, 42, 0.55);
        }
        .admin-stat {
            position: relative;
            min-height: 5.65rem;
            overflow: hidden;
            border-radius: 0.75rem;
            padding: 1rem 1.15rem;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .admin-stat:hover {
            transform: translateY(-1px);
            border-color: #c8d4e4;
            box-shadow: 0 22px 46px -32px rgba(15, 23, 42, 0.65);
        }
        .admin-stat::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 0.18rem;
            background: #1d4ed8;
        }
        .admin-stat-top {
            display: block;
        }
        .admin-stat-value {
            margin-top: 0.45rem;
            color: #07111f;
            font-size: clamp(1.55rem, 2.15vw, 2.05rem);
            line-height: 1;
        }
        .admin-section-heading {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            border-bottom: 1px solid #dbe3ef;
            background:
                linear-gradient(90deg, rgba(15, 23, 42, 0.045), rgba(37, 99, 235, 0.045) 48%, rgba(255, 255, 255, 0));
            padding: 1.05rem 1.25rem;
        }
        .admin-section-heading::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 0.18rem;
            background: #1d4ed8;
        }
        .admin-section-heading h2 {
            letter-spacing: -0.01em;
        }
        .admin-eyebrow {
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #53657d;
        }
        .admin-tools-column {
            align-self: start;
        }
        @media (min-width: 1280px) {
            .admin-tools-column {
                position: sticky;
                top: 5.75rem;
            }
        }
        .admin-tool-card {
            padding: 1rem;
        }
        .admin-tool-card h2 {
            border-bottom: 1px solid #e7edf5;
            border-radius: 0.75rem 0.75rem 0 0;
            margin: -1rem -1rem 0;
            padding: 0.95rem 1rem;
            background: linear-gradient(90deg, #f7faff, #ffffff);
            color: #0f172a;
        }
        .admin-tool-card + .admin-tool-card {
            margin-top: 1rem;
        }
        .admin-workspace-title {
            margin: 1.4rem 0 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        @media (min-width: 768px) {
            .admin-workspace-title {
                flex-direction: row;
                align-items: end;
                justify-content: space-between;
            }
        }
        .audit-group summary {
            cursor: pointer;
            list-style: none;
        }
        .audit-group summary::-webkit-details-marker {
            display: none;
        }
        .audit-chevron {
            transition: transform 0.18s ease;
        }
        .audit-group[open] .audit-chevron {
            transform: rotate(90deg);
        }
        .admin-input {
            min-height: 2.75rem;
            border-radius: 0.65rem;
            border: 1px solid #c7d2e1;
            background: #fff;
            padding: 0.625rem 0.75rem;
            width: 100%;
        }
        .admin-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px #dbeafe;
            outline: none;
        }
        .admin-table {
            table-layout: fixed;
        }
        .client-column {
            width: 52%;
        }
        .category-column {
            width: 16%;
        }
        .status-column {
            width: 10rem;
        }
        .action-column {
            width: 8.5rem;
        }
        .actions-wrap {
            width: 100%;
        }
        .actions-wrap .action-button,
        .actions-wrap .action-form,
        .actions-wrap .action-form button {
            width: 100%;
        }
        .admin-table-scroll {
            max-height: none;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .client-directory {
            min-height: 0;
        }
        .admin-table-scroll thead {
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .admin-table-scroll thead tr {
            background: #f4f7fb;
        }
        @media (min-width: 1180px) {
            .action-column {
                width: 12.5rem;
            }
            .actions-wrap .action-button,
            .actions-wrap .action-form {
                width: auto;
            }
        }
        @media (max-width: 767px) {
            .admin-shell { padding: 1rem; }
            .admin-header-actions a { flex: 1 1 0; justify-content: center; }
            .admin-table-wrap { overflow: visible; }
            .admin-table-scroll {
                max-height: 32rem;
                overflow-y: auto;
            }
            .client-directory {
                min-height: 0;
            }
            .admin-table { min-width: 0 !important; }
            .admin-table thead { display: none; }
            .admin-table,
            .admin-table tbody,
            .admin-table tr,
            .admin-table td { display: block; width: 100%; }
            .admin-table tr {
                margin: 0.75rem;
                border: 1px solid #e2e8f0;
                border-radius: 0.95rem;
                background: #fff;
                padding: 0.85rem;
                box-shadow: 0 16px 34px -30px rgba(15, 23, 42, 0.45);
            }
            .admin-table td {
                border: 0;
                padding: 0.48rem 0;
            }
            .admin-table td[data-label]::before {
                content: attr(data-label);
                display: block;
                margin-bottom: 0.25rem;
                font-size: 0.68rem;
                font-weight: 900;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #64748b;
            }
            .admin-table .actions-wrap {
                min-width: 0;
                flex-direction: row;
                justify-content: stretch;
                gap: 0.625rem;
            }
            .admin-table .action-button,
            .admin-table .action-form { flex: 1 1 0; }
            .admin-table .action-form button { width: 100%; }
        }
    </style>
</head>
<body class="text-slate-900">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <img src="https://eduriserck.com/eduriser/eduriser-logo.svg" alt="EduRiser Logo" class="h-10 w-auto">
                <div>
                    <h1 class="text-xl font-black">Clientele Admin</h1>
                    <p class="text-sm font-semibold text-slate-500">Signed in as <?= e($_SESSION['admin_username'] ?? 'admin') ?></p>
                </div>
            </div>
            <div class="admin-header-actions flex flex-wrap gap-2">
                <?php if ($isSuperAdmin): ?>
                    <?php if ($previewAdminView): ?>
                        <a href="dashboard.php" class="inline-flex rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-black text-blue-800 hover:bg-blue-100">Exit Admin Preview</a>
                    <?php else: ?>
                        <a href="dashboard.php?preview_role=admin" class="inline-flex rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-black text-blue-800 hover:bg-blue-100">Preview Admin View</a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="../index.php" target="_blank" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-black text-slate-700 hover:bg-blue-50">View Site</a>
                <a href="logout.php" class="inline-flex rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-black text-red-700 hover:bg-red-50">Logout</a>
            </div>
        </div>
    </header>

    <main class="admin-shell">
        <?php if ($message !== ''): ?>
            <div class="mb-5 rounded-lg bg-green-50 px-4 py-3 text-sm font-bold text-green-700"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm font-bold text-red-700"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div class="admin-surface admin-stat">
                <div class="admin-stat-top"><p class="admin-eyebrow">Clients</p></div>
                <p class="admin-stat-value font-black"><?= $totalClientCount ?></p>
            </div>
            <div class="admin-surface admin-stat">
                <div class="admin-stat-top"><p class="admin-eyebrow">Active</p></div>
                <p class="admin-stat-value font-black"><?= $activeClientCount ?></p>
            </div>
            <div class="admin-surface admin-stat">
                <div class="admin-stat-top"><p class="admin-eyebrow">Categories</p></div>
                <p class="admin-stat-value font-black"><?= count($categories) ?></p>
            </div>
            <div class="admin-surface admin-stat">
                <div class="admin-stat-top"><p class="admin-eyebrow">Admin Users</p></div>
                <p class="admin-stat-value font-black"><?= $adminEmployeeCount ?></p>
            </div>
            <div class="admin-surface admin-stat">
                <div class="admin-stat-top"><p class="admin-eyebrow">Super Admins</p></div>
                <p class="admin-stat-value font-black"><?= $superAdminEmployeeCount ?></p>
            </div>
        </section>

        <div class="admin-workspace-title">
            <div>
                <p class="admin-eyebrow">Workspace</p>
                <h2 class="text-xl font-black text-slate-950">Client Management</h2>
            </div>
            <p class="text-sm font-semibold text-slate-500">Showing <?= $totalClients ?> of <?= $totalClientCount ?> records</p>
        </div>

        <section class="grid items-stretch gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="admin-tools-column order-2 xl:order-2">
                <form method="post" enctype="multipart/form-data" class="admin-surface admin-tool-card">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_client">
                    <h2 class="text-lg font-black">Add Client</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Client Name</label>
                            <input name="name" required class="admin-input">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Category</label>
                            <select name="category_id" required class="admin-input">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Logo URL</label>
                            <input name="logo_url" type="text" class="admin-input">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Upload Logo</label>
                            <input name="logo_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="admin-input text-sm">
                            <p class="mt-1 text-xs font-semibold text-slate-500">Optional. PNG, JPG, WEBP, or GIF up to 2 MB. Uploaded logo overrides the URL field.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Portal Link</label>
                            <input name="primary_url" type="url" required class="admin-input">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Link Title</label>
                            <input name="link_title" value="Portal" class="admin-input">
                        </div>
                        <label class="flex items-end gap-2 pb-3 text-sm font-bold text-slate-700">
                            <input name="is_active" type="checkbox" checked class="h-4 w-4 rounded border-slate-300">
                            Active
                        </label>
                    </div>
                    <button class="mt-5 w-full rounded-lg bg-blue-900 px-5 py-3 text-sm font-black text-white hover:bg-blue-800 sm:w-auto">Save Client</button>
                </form>

                <form method="post" class="admin-surface admin-tool-card">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_category">
                    <h2 class="text-lg font-black">Add Category</h2>
                    <div class="mt-4 grid gap-4">
                        <input name="name" required placeholder="Category name" class="admin-input">
                    </div>
                    <label class="mt-4 flex gap-2 text-sm font-bold text-slate-700">
                        <input name="is_active" type="checkbox" checked class="h-4 w-4 rounded border-slate-300">
                        Active
                    </label>
                    <button class="mt-5 w-full rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-black text-slate-700 hover:bg-blue-50 sm:w-auto">Save Category</button>
                </form>

                <?php if ($canViewSuperAdminTools): ?>
                <section class="admin-surface admin-tool-card">
                    <h2 class="text-lg font-black">Employee Admin Access</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">New employees are active by default with no admin rights. Super admins can grant rights from the employee list.</p>
                    <form method="post" class="mt-4 grid gap-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_employee">
                        <input name="employee_name" placeholder="Employee name" class="admin-input">
                        <input name="employee_email" type="email" required placeholder="employee@eduriser.com" class="admin-input">
                        <p class="text-xs font-semibold text-slate-500">The employee will be active immediately. Admin and super admin rights are assigned separately below.</p>
                        <button class="rounded-lg bg-blue-900 px-5 py-3 text-sm font-black text-white hover:bg-blue-800">Save Employee</button>
                    </form>

                    <div class="mt-5 max-h-[30rem] space-y-2 overflow-y-auto pr-1">
                        <?php foreach ($employees as $employee): ?>
                            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                                <p class="font-black text-slate-900"><?= e($employee['name'] ?: $employee['email']) ?></p>
                                <p class="text-xs font-bold text-slate-500"><?= e($employee['email']) ?></p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <?php if ((int) $employee['is_super_admin'] === 1): ?>
                                        <span class="rounded-full bg-purple-50 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-purple-700">Super Admin</span>
                                    <?php endif; ?>
                                    <?php if ((int) $employee['can_access_admin'] === 1): ?>
                                        <span class="rounded-full bg-green-50 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-green-700">Admin</span>
                                    <?php endif; ?>
                                    <?php if ((int) $employee['is_active'] !== 1): ?>
                                        <span class="rounded-full bg-red-50 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-red-700">Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3 grid gap-2 sm:flex sm:flex-wrap">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle_employee_admin">
                                        <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                        <button class="rounded-lg border px-3 py-2 text-xs font-black <?= (int) $employee['can_access_admin'] === 1 ? 'border-green-200 bg-green-50 text-green-700' : 'border-slate-200 bg-white text-slate-600' ?>">
                                            <?= (int) $employee['can_access_admin'] === 1 ? 'Admin Enabled' : 'Admin Disabled' ?>
                                        </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle_employee_super_admin">
                                        <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                        <button class="rounded-lg border px-3 py-2 text-xs font-black <?= (int) $employee['is_super_admin'] === 1 ? 'border-purple-200 bg-purple-50 text-purple-700' : 'border-slate-200 bg-white text-slate-600' ?>">
                                            <?= (int) $employee['is_super_admin'] === 1 ? 'Super Enabled' : 'Super Disabled' ?>
                                        </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle_employee_active">
                                        <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                        <button class="rounded-lg border px-3 py-2 text-xs font-black <?= (int) $employee['is_active'] === 1 ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                                            <?= (int) $employee['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <section class="client-directory admin-surface order-1 flex min-h-[32rem] flex-col overflow-hidden xl:order-1">
                <div class="admin-section-heading">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="admin-eyebrow">Directory</p>
                            <h2 class="text-lg font-black">Clients</h2>
                            <p id="clientVisibleCount" class="text-sm font-semibold text-slate-500">
                                Showing <?= $totalClients ?> of <?= $totalClientCount ?> database records
                            </p>
                        </div>
                        <?php if ($search !== '' || $filterCategoryId > 0): ?>
                            <a href="dashboard.php" class="text-sm font-black text-blue-700 hover:text-blue-900">Clear filters</a>
                        <?php endif; ?>
                    </div>
                    <form method="get" class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_14rem_auto]">
                        <div>
                            <label for="clientSearch" class="mb-1 block text-xs font-black uppercase tracking-wider text-slate-500">Search</label>
                            <input id="clientSearch" name="search" value="<?= e($search) ?>" type="search" placeholder="Search client, category, or URL" class="admin-input text-sm font-semibold">
                        </div>
                        <div>
                            <label for="categoryFilter" class="mb-1 block text-xs font-black uppercase tracking-wider text-slate-500">Category</label>
                            <select id="categoryFilter" name="category_id" class="admin-input text-sm font-semibold">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= (int) $category['id'] === $filterCategoryId ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid gap-2 sm:flex sm:items-end">
                            <button class="h-[42px] rounded-lg bg-blue-900 px-5 text-sm font-black text-white hover:bg-blue-800" type="submit">Apply</button>
                            <a href="dashboard.php" class="inline-flex h-[42px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-black text-slate-700 hover:bg-blue-50">Reset</a>
                        </div>
                    </form>
                </div>
                <div class="admin-table-wrap admin-table-scroll flex-1">
                    <table class="admin-table w-full">
                        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500">
                            <tr><th class="client-column px-4 py-3">Client</th><th class="category-column px-4 py-3">Category</th><th class="status-column px-4 py-3">Status</th><th class="action-column px-4 py-3">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr class="client-row border-t border-slate-100" data-client-category-id="<?= (int) $client['category_id'] ?>" data-client-search="<?= e(strtolower((string) $client['name'] . ' ' . (string) $client['category_name'] . ' ' . (string) $client['primary_url'])) ?>">
                                    <td class="px-4 py-3" data-label="Client">
                                        <p class="font-black"><?= e($client['name']) ?></p>
                                        <a href="<?= e($client['primary_url']) ?>" target="_blank" class="block max-w-full truncate text-xs font-bold text-blue-700"><?= e($client['primary_url']) ?></a>
                                        <?php if ((int) $client['link_count'] > 1): ?>
                                            <span class="mt-1 inline-flex rounded-full bg-blue-50 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-blue-700"><?= (int) $client['link_count'] ?> links</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 font-bold" data-label="Category"><?= e($client['category_name']) ?></td>
                                    <td class="px-4 py-3" data-label="Status"><span class="rounded-full <?= (int) $client['is_active'] === 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?> px-2 py-1 text-xs font-black"><?= (int) $client['is_active'] === 1 ? 'Active' : 'Paused' ?></span></td>
                                    <td class="action-column px-4 py-3" data-label="Action">
                                        <div class="actions-wrap flex flex-col items-stretch justify-end gap-2 min-[1180px]:flex-row min-[1180px]:items-center">
                                            <a href="edit-client.php?id=<?= (int) $client['id'] ?>" class="action-button inline-flex h-9 min-w-16 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-black text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800">Edit</a>
                                            <?php if ($canViewSuperAdminTools): ?>
                                                <form method="post" onsubmit="return confirm('Delete this client?')" class="action-form m-0">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="delete_client">
                                                    <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                                                    <button class="inline-flex h-9 min-w-20 items-center justify-center rounded-lg border border-red-200 bg-white px-3 text-xs font-black text-red-700 transition hover:border-red-300 hover:bg-red-50" type="submit">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="clientNoResultsRow" class="<?= empty($clients) ? '' : 'hidden' ?> border-t border-slate-100">
                                <td colspan="4" class="px-4 py-10 text-center">
                                    <p class="font-black text-slate-800">No clients found</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-500">Try a different search or category filter.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <?php if ($canViewSuperAdminTools): ?>
            <section id="changeLogs" class="admin-surface mt-5 overflow-hidden">
                <div class="admin-section-heading">
                    <p class="admin-eyebrow">Audit</p>
                    <h2 class="text-lg font-black">Change Logs</h2>
                    <p class="text-sm font-semibold text-slate-500">Showing <?= count($auditLogs) ?> of <?= $auditTotalCount ?> audit records. Grouped by date.</p>
                </div>
                <div>
                    <?php foreach ($auditLogGroupsByDate as $date => $dateGroups): ?>
                        <div class="border-t border-slate-100">
                            <div class="bg-slate-50 px-4 py-2 sm:px-5">
                                <p class="text-xs font-black uppercase tracking-wider text-slate-600"><?= e($date) ?></p>
                            </div>
                            <div class="divide-y divide-slate-100">
                                <?php foreach ($dateGroups as $group): ?>
                                    <?php
                                        $groupLogs = $group['logs'];
                                        $entityLabel = trim($group['entity_type'] . (!empty($group['entity_name']) ? ' - ' . $group['entity_name'] : ''));
                                    ?>
                                    <details class="audit-group">
                                        <summary class="grid gap-2 px-4 py-3 text-sm transition hover:bg-slate-50 sm:grid-cols-[9rem_11rem_minmax(0,1fr)_7rem] sm:items-center sm:px-5">
                                            <div class="font-bold text-slate-500"><?= e(substr($group['time'], 11)) ?></div>
                                            <div class="truncate font-black text-blue-900"><?= e($group['actor_email']) ?></div>
                                            <div class="min-w-0">
                                                <p class="truncate font-black text-slate-900"><?= e($entityLabel !== '' ? $entityLabel : 'System change') ?></p>
                                                <p class="text-xs font-semibold text-slate-500">
                                                    <?= count($groupLogs) ?> <?= count($groupLogs) === 1 ? 'change' : 'changes' ?> at this time
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-blue-700">
                                                <span>View</span>
                                                <span class="audit-chevron inline-block">&rsaquo;</span>
                                            </div>
                                        </summary>
                                        <div class="bg-slate-50 px-4 pb-4 sm:px-5">
                                            <div class="space-y-2 border-l-2 border-blue-100 pl-4">
                                                <?php foreach ($groupLogs as $log): ?>
                                                    <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm">
                                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                            <div>
                                                                <p class="font-black text-slate-900"><?= e((string) $log['action']) ?></p>
                                                                <p class="text-xs font-semibold text-slate-500"><?= e((string) $log['created_at']) ?></p>
                                                            </div>
                                                            <p class="text-xs font-semibold text-slate-400"><?= e((string) $log['ip_address']) ?></p>
                                                        </div>
                                                        <?php if (!empty($log['details'])): ?>
                                                            <p class="mt-2 break-words rounded-md bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500"><?= e((string) $log['details']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($auditLogGroupsByDate)): ?>
                        <div class="px-4 py-10 text-center sm:px-5">
                            <p class="font-black text-slate-800">No change logs yet</p>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Logs will appear after the audit table migration is applied and changes are made.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($auditTotalPages > 1): ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:px-5">
                        <p class="text-xs font-bold text-slate-500">Page <?= $auditPage ?> of <?= $auditTotalPages ?></p>
                        <div class="flex flex-wrap gap-2">
                            <?php if ($auditPage > 1): ?>
                                <a href="<?= e($auditPageUrl($auditPage - 1)) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-blue-50">Previous</a>
                            <?php endif; ?>
                            <?php for ($page = 1; $page <= $auditTotalPages; $page++): ?>
                                <a href="<?= e($auditPageUrl($page)) ?>" class="rounded-lg border px-3 py-2 text-xs font-black <?= $page === $auditPage ? 'border-blue-900 bg-blue-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-blue-50' ?>"><?= $page ?></a>
                            <?php endfor; ?>
                            <?php if ($auditPage < $auditTotalPages): ?>
                                <a href="<?= e($auditPageUrl($auditPage + 1)) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-blue-50">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <script>
        const clientSearch = document.getElementById('clientSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        const clientRows = Array.from(document.querySelectorAll('.client-row'));
        const clientNoResultsRow = document.getElementById('clientNoResultsRow');
        const clientVisibleCount = document.getElementById('clientVisibleCount');
        const totalDatabaseClients = <?= $totalClientCount ?>;
        const clientDirectory = document.querySelector('.client-directory');
        const adminToolsColumn = document.querySelector('.admin-tools-column');

        function filterClientRows() {
            const searchTerm = (clientSearch?.value || '').trim().toLowerCase();
            const selectedCategoryId = categoryFilter?.value || '0';
            const filterCategory = selectedCategoryId !== '0';
            let visibleRows = 0;

            clientRows.forEach((row) => {
                const rowText = row.dataset.clientSearch || '';
                const matchesSearch = searchTerm === '' || rowText.includes(searchTerm);
                const matchesCategory = !filterCategory || row.dataset.clientCategoryId === selectedCategoryId;
                const isVisible = matchesSearch && matchesCategory;

                row.classList.toggle('hidden', !isVisible);
                if (isVisible) {
                    visibleRows++;
                }
            });

            clientNoResultsRow?.classList.toggle('hidden', visibleRows !== 0);
            if (clientVisibleCount) {
                clientVisibleCount.textContent = `Showing ${visibleRows} of ${totalDatabaseClients} database records`;
            }
        }

        clientSearch?.addEventListener('input', filterClientRows);
        categoryFilter?.addEventListener('change', filterClientRows);
        filterClientRows();

        function syncClientDirectoryHeight() {
            if (!clientDirectory || !adminToolsColumn) return;

            if (window.innerWidth >= 1280) {
                clientDirectory.style.height = `${adminToolsColumn.offsetHeight}px`;
            } else {
                clientDirectory.style.height = '';
            }
        }

        window.addEventListener('resize', syncClientDirectoryHeight);
        syncClientDirectoryHeight();

        if (window.ResizeObserver && adminToolsColumn) {
            new ResizeObserver(syncClientDirectoryHeight).observe(adminToolsColumn);
        }
    </script>
</body>
</html>
