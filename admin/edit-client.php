<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/client_repository.php';
require_once __DIR__ . '/../includes/uploads.php';

require_login();

$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: dashboard.php');
    exit;
}

$linkStmt = $pdo->prepare('SELECT title, url FROM client_links WHERE client_id = ? ORDER BY sort_order ASC, id ASC');
$linkStmt->execute([$id]);
$links = $linkStmt->fetchAll();
if (empty($links)) {
    $links[] = ['title' => 'Portal', 'url' => $client['primary_url']];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $logoUrl = trim((string) ($_POST['logo_url'] ?? ''));
        if (isset($_FILES['logo_file'])) {
            $logoUrl = handle_logo_upload($_FILES['logo_file'], $name, $logoUrl);
        }
        $primaryUrl = trim((string) ($_POST['primary_url'] ?? ''));
        $stmt = $pdo->prepare('SELECT sort_order FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        $sortOrder = (int) $stmt->fetchColumn();
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $linkTitles = $_POST['link_titles'] ?? [];
        $linkUrls = $_POST['link_urls'] ?? [];

        if ($categoryId <= 0 || $name === '') {
            throw new RuntimeException('Client name and category are required.');
        }

        $cleanLinks = [];
        foreach ((array) $linkUrls as $index => $url) {
            $url = trim((string) $url);
            $title = trim((string) ($linkTitles[$index] ?? 'Portal'));
            if ($url === '') {
                continue;
            }
            $cleanLinks[] = [
                'title' => $title !== '' ? $title : 'Portal',
                'url' => $url,
            ];
        }

        if (empty($cleanLinks) && $primaryUrl !== '') {
            $cleanLinks[] = ['title' => 'Portal', 'url' => $primaryUrl];
        }

        if (!empty($cleanLinks)) {
            $primaryUrl = $cleanLinks[0]['url'];
        }

        $stmt = $pdo->prepare('UPDATE clients SET category_id = ?, name = ?, logo_url = ?, primary_url = ?, sort_order = ?, is_active = ? WHERE id = ?');
        $stmt->execute([$categoryId, $name, $logoUrl, $primaryUrl, $sortOrder, $isActive, $id]);
        audit_log('client_updated', 'client', $id, $name, ['category_id' => $categoryId, 'is_active' => $isActive, 'link_count' => count($cleanLinks)]);

        $pdo->prepare('DELETE FROM client_links WHERE client_id = ?')->execute([$id]);
        $linkSort = 0;
        foreach ($cleanLinks as $link) {
            $linkSort += 10;
            $stmt = $pdo->prepare('INSERT INTO client_links (client_id, title, url, sort_order) VALUES (?, ?, ?, ?)');
            $stmt->execute([$id, $link['title'], $link['url'], $linkSort]);
        }
        audit_log('client_links_updated', 'client', $id, $name, ['links' => $cleanLinks]);

        header('Location: dashboard.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$categories = get_categories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Client | Eduriser Clientele</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, sans-serif; background: #f6f8fb; }
        .admin-surface {
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 18px 40px -34px rgba(15, 23, 42, 0.55);
        }
        .admin-input {
            min-height: 2.75rem;
            border-radius: 0.75rem;
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 0.625rem 0.75rem;
            width: 100%;
        }
        .admin-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px #dbeafe;
            outline: none;
        }
    </style>
</head>
<body class="text-slate-900">
    <main class="mx-auto max-w-3xl px-4 py-5 sm:px-5 sm:py-8">
        <a href="dashboard.php" class="text-sm font-black text-blue-700">Back to dashboard</a>
        <section class="admin-surface mt-5 p-4 sm:p-6">
            <h1 class="text-xl font-black sm:text-2xl">Edit Client</h1>
            <?php if ($error !== ''): ?>
                <div class="mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm font-bold text-red-700"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="mt-6 grid gap-4 sm:grid-cols-2">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                <input type="hidden" name="primary_url" value="<?= e($client['primary_url']) ?>">
                <div class="sm:col-span-2">
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Client Name</label>
                    <input name="name" required value="<?= e($client['name']) ?>" class="admin-input">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Category</label>
                    <select name="category_id" required class="admin-input">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) $category['id'] === (int) $client['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Logo URL</label>
                    <input name="logo_url" type="text" value="<?= e($client['logo_url']) ?>" class="admin-input">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Upload Replacement Logo</label>
                    <input name="logo_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="admin-input text-sm">
                    <p class="mt-1 text-xs font-semibold text-slate-500">Optional. PNG, JPG, WEBP, or GIF up to 2 MB. Leave empty to keep the current logo path.</p>
                </div>
                <div class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-base font-black text-slate-900">Client Links / Group Pages</h2>
                            <p class="text-xs font-semibold text-slate-500">Add multiple links to turn this client card into a grouped popup.</p>
                        </div>
                        <span class="text-xs font-black uppercase tracking-wider text-blue-700"><?= count($links) ?> saved</span>
                    </div>
                    <div id="clientLinksList" class="space-y-3">
                        <?php
                            $linkRows = array_values($links);
                            while (count($linkRows) < 6) {
                                $linkRows[] = ['title' => '', 'url' => ''];
                            }
                        ?>
                        <?php foreach ($linkRows as $index => $link): ?>
                            <div data-link-row="true" class="grid gap-3 sm:grid-cols-[12rem_minmax(0,1fr)]">
                                <input name="link_titles[]" value="<?= e($link['title']) ?>" placeholder="Title <?= $index + 1 ?>" class="admin-input">
                                <input name="link_urls[]" type="url" value="<?= e($link['url']) ?>" placeholder="https://..." class="admin-input">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button id="addClientLink" type="button" class="mt-3 rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-black text-slate-700 hover:bg-blue-50">Add Link</button>
                </div>
                <label class="flex gap-2 text-sm font-bold text-slate-700">
                    <input name="is_active" type="checkbox" <?= (int) $client['is_active'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300">
                    Active
                </label>
                <div class="grid gap-2 sm:col-span-2 sm:flex sm:justify-end">
                    <a href="dashboard.php" class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-black text-slate-700">Cancel</a>
                    <button class="rounded-lg bg-blue-900 px-5 py-3 text-sm font-black text-white hover:bg-blue-800">Save Changes</button>
                </div>
            </form>
        </section>
    </main>
    <script>
        const clientLinksList = document.getElementById('clientLinksList');
        const addClientLink = document.getElementById('addClientLink');

        addClientLink?.addEventListener('click', () => {
            const rowNumber = clientLinksList.querySelectorAll('[data-link-row]').length + 1;
            const row = document.createElement('div');
            row.className = 'grid gap-3 sm:grid-cols-[12rem_minmax(0,1fr)]';
            row.dataset.linkRow = 'true';
            row.innerHTML = `
                <input name="link_titles[]" placeholder="Title ${rowNumber}" class="admin-input">
                <input name="link_urls[]" type="url" placeholder="https://..." class="admin-input">
            `;
            clientLinksList.appendChild(row);
        });
    </script>
</body>
</html>
