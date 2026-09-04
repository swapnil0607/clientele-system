<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$sourceDir = realpath(__DIR__ . '/../logos');
if ($sourceDir === false) {
    fwrite(STDERR, "Missing logos folder.\n");
    exit(1);
}

if (!is_dir(CLIENT_LOGO_UPLOAD_DIR) && !mkdir(CLIENT_LOGO_UPLOAD_DIR, 0755, true) && !is_dir(CLIENT_LOGO_UPLOAD_DIR)) {
    fwrite(STDERR, "Could not create upload folder.\n");
    exit(1);
}

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $extension = strtolower($fileInfo->getExtension());
    if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
        continue;
    }
    $baseName = pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME);
    $files[normalize_logo_name($baseName)] = $fileInfo->getPathname();
}

$pdo = db();
$clients = $pdo->query('SELECT id, name, logo_url FROM clients ORDER BY id ASC')->fetchAll();
$updated = 0;
$missing = [];

foreach ($clients as $client) {
    $candidates = [
        normalize_logo_name((string) $client['name']),
        normalize_logo_name(pathinfo((string) $client['logo_url'], PATHINFO_FILENAME)),
    ];

    $source = null;
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && isset($files[$candidate])) {
            $source = $files[$candidate];
            break;
        }
    }

    if ($source === null) {
        $missing[] = $client['name'];
        continue;
    }

    $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $safeName = normalize_logo_name((string) $client['name']) ?: 'client-logo';
    $fileName = $safeName . '.' . $extension;
    $destination = CLIENT_LOGO_UPLOAD_DIR . '/' . $fileName;

    copy($source, $destination);
    $publicPath = CLIENT_LOGO_PUBLIC_PATH . '/' . $fileName;

    $stmt = $pdo->prepare('UPDATE clients SET logo_url = ? WHERE id = ?');
    $stmt->execute([$publicPath, (int) $client['id']]);
    $updated++;
}

echo "Updated {$updated} client logos to local upload paths.\n";
if (!empty($missing)) {
    echo "No local logo match for " . count($missing) . " clients:\n";
    foreach ($missing as $name) {
        echo "- {$name}\n";
    }
}

function normalize_logo_name(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    $value = strtolower($value);
    $value = str_replace(['&', '.'], ['and', ''], $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim((string) $value, '-');
}
