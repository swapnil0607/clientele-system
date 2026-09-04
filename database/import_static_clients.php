<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/client_repository.php';

$source = __DIR__ . '/../index-static-backup.html';
if (!is_file($source)) {
    fwrite(STDERR, "Missing index-static-backup.html\n");
    exit(1);
}

$html = file_get_contents($source);
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
$pdo = db();

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE client_links');
    $pdo->exec('TRUNCATE TABLE clients');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $sections = $xpath->query('//section[starts-with(@id, "category-")]');
    $sortByCategory = [];

    foreach ($sections as $section) {
        $sectionId = $section->getAttribute('id');
        $slug = preg_replace('/^category-/', '', $sectionId);
        $titleNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " category-title ")]', $section)->item(0);
        $categoryName = trim($titleNode?->textContent ?? ucfirst(str_replace('-', ' ', $slug)));

        $categoryId = ensure_category($pdo, $categoryName, $slug);
        $sortByCategory[$categoryId] = 0;

        $cards = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " client-card ")]', $section);
        foreach ($cards as $card) {
            $nameNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " client-name ")]', $card)->item(0);
            $name = trim($nameNode?->textContent ?? '');
            if ($name === '') {
                continue;
            }

            $logoNode = $xpath->query('.//img[contains(concat(" ", normalize-space(@class), " "), " client-logo ")]', $card)->item(0);
            $logoUrl = trim($logoNode?->getAttribute('src') ?? '');

            $links = extract_links($card);
            if (empty($links)) {
                $links[] = ['title' => 'Portal', 'url' => '#'];
            }

            $sortByCategory[$categoryId] += 10;
            $primaryUrl = $links[0]['url'];

            $stmt = $pdo->prepare('INSERT INTO clients (category_id, name, logo_url, primary_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)');
            $stmt->execute([$categoryId, $name, $logoUrl, $primaryUrl, $sortByCategory[$categoryId]]);
            $clientId = (int) $pdo->lastInsertId();

            $linkSort = 0;
            foreach ($links as $link) {
                if (trim($link['url']) === '') {
                    continue;
                }
                $linkSort += 10;
                $stmt = $pdo->prepare('INSERT INTO client_links (client_id, title, url, sort_order) VALUES (?, ?, ?, ?)');
                $stmt->execute([$clientId, $link['title'] ?: 'Portal', trim($link['url']), $linkSort]);
            }
        }
    }

} catch (Throwable $exception) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    throw $exception;
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
echo "Imported {$count} clients from static backup.\n";

function ensure_category(PDO $pdo, string $name, string $slug): int
{
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare('INSERT INTO categories (name, slug, sort_order, is_active) VALUES (?, ?, 999, 1)');
    $stmt->execute([$name, $slug]);
    return (int) $pdo->lastInsertId();
}

function extract_links(DOMElement $card): array
{
    $links = [];
    if (strtolower($card->tagName) === 'a') {
        $href = trim($card->getAttribute('href'));
        if ($href !== '') {
            $links[] = ['title' => 'Portal', 'url' => $href];
        }
    }

    $onclick = $card->getAttribute('onclick');
    if ($onclick !== '') {
        if (preg_match_all("/\\{\\s*title:\\s*'([^']*)'\\s*,\\s*url:\\s*'([^']*)'\\s*\\}/", $onclick, $matches, PREG_SET_ORDER)) {
            $links = [];
            foreach ($matches as $match) {
                $links[] = ['title' => html_entity_decode($match[1], ENT_QUOTES), 'url' => html_entity_decode($match[2], ENT_QUOTES)];
            }
        }
    }

    return $links;
}
