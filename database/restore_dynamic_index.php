<?php
declare(strict_types=1);

$backupPath = __DIR__ . '/../index-static-backup.html';
$targetPath = __DIR__ . '/../index.php';
$backup = file_get_contents($backupPath);

$start = strpos($backup, '    <main id="clientResults"');
$end = strpos($backup, '    </main>', $start);
if ($start === false || $end === false) {
    fwrite(STDERR, "Could not locate original main content.\n");
    exit(1);
}
$end += strlen('    </main>');

$before = substr($backup, 0, $start);
$after = substr($backup, $end);

$preamble = <<<'PHP'
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/client_repository.php';

$categoryGroups = get_clients_by_category();
$categories = get_categories(true);

function client_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $letters .= strtoupper(substr($word, 0, 1));
    }
    return $letters !== '' ? $letters : 'CL';
}

function client_links_for(int $clientId, ?string $primaryUrl): array
{
    $stmt = db()->prepare('SELECT title, url FROM client_links WHERE client_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$clientId]);
    $links = $stmt->fetchAll();
    if (empty($links) && $primaryUrl) {
        $links[] = ['title' => 'Portal', 'url' => $primaryUrl];
    }
    return $links;
}
?>
PHP;

$before = preg_replace('/^<!DOCTYPE html>/', $preamble . "\n<!DOCTYPE html>", $before, 1);
$select = <<<'HTML'
<select id="categorySelect" onchange="scrollToCategory(this.value)" class="sr-only" tabindex="-1" aria-hidden="true">
                        <option value="">Jump to Category...</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="category-<?= e($category['slug']) ?>"><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
HTML;
$before = preg_replace('/<select id="categorySelect".*?<\/select>/s', $select, $before, 1);

$main = <<<'HTML'
    <!-- Main Content -->
    <main id="clientResults" class="max-w-7xl mx-auto px-6 py-20">
        <div class="space-y-16">
            <?php if (empty($categoryGroups)): ?>
                <div class="category-wrapper">
                    <section class="text-center section-container">
                        <h2 class="category-title">No clients available</h2>
                        <div class="category-underline"></div>
                        <p class="text-sm font-semibold text-slate-500">Add clients from the admin dashboard after database setup.</p>
                    </section>
                </div>
            <?php endif; ?>

            <?php foreach ($categoryGroups as $group): ?>
                <?php $cardCount = count($group['clients']); ?>
                <div class="category-wrapper">
                    <section id="category-<?= e($group['category']['slug']) ?>" class="text-center section-container">
                        <h2 class="category-title"><?= e($group['category']['name']) ?></h2>
                        <div class="category-underline"></div>
                        <div class="client-grid <?= $cardCount >= 8 ? 'sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5' : '' ?>">
                            <?php foreach ($group['clients'] as $client): ?>
                                <?php
                                    $links = client_links_for((int) $client['id'], $client['primary_url'] ?? null);
                                    $linkCount = count($links);
                                    $firstUrl = $links[0]['url'] ?? '#';
                                    $jsonLinks = json_encode($links, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                    $jsonName = json_encode($client['name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                ?>
                                <?php if ($linkCount > 1): ?>
                                    <div class="client-card" onclick='handleClientClick(<?= $jsonName ?>, <?= $jsonLinks ?>)'>
                                        <div class="link-count-bubble"><?= $linkCount ?></div>
                                        <?php if (!empty($client['logo_url'])): ?>
                                            <img src="<?= e($client['logo_url']) ?>" class="client-logo" alt="<?= e($client['name']) ?> logo">
                                        <?php else: ?>
                                            <span class="client-card-initials"><?= e(client_initials($client['name'])) ?></span>
                                        <?php endif; ?>
                                        <h4 class="client-name"><?= e($client['name']) ?></h4>
                                    </div>
                                <?php else: ?>
                                    <a href="<?= e($firstUrl) ?>" target="_blank" rel="noopener noreferrer" class="client-card" onclick='handleClientClick(<?= $jsonName ?>, ["Portal"])'>
                                        <?php if (!empty($client['logo_url'])): ?>
                                            <img src="<?= e($client['logo_url']) ?>" class="client-logo" alt="<?= e($client['name']) ?> logo">
                                        <?php else: ?>
                                            <span class="client-card-initials"><?= e(client_initials($client['name'])) ?></span>
                                        <?php endif; ?>
                                        <h4 class="client-name"><?= e($client['name']) ?></h4>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="noResultsMessage" class="hidden text-center py-16">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <h3 class="text-2xl font-black text-gray-900 tracking-[0.2em] uppercase mb-2">NO RESULT FOUND</h3>
            <p class="text-gray-500">Try adjusting your search terms</p>
        </div>
    </main>
HTML;

file_put_contents($targetPath, $before . $main . $after);
echo "Restored original layout with dynamic client rendering.\n";
