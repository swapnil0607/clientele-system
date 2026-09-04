<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function get_categories(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM categories';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';

    return db()->query($sql)->fetchAll();
}

function get_clients_by_category(): array
{
    $stmt = db()->query(
        'SELECT c.*, cat.name AS category_name, cat.slug AS category_slug
         FROM clients c
         INNER JOIN categories cat ON cat.id = c.category_id
         WHERE c.is_active = 1 AND cat.is_active = 1
         ORDER BY cat.sort_order ASC, cat.name ASC, c.sort_order ASC, c.name ASC'
    );

    $grouped = [];
    foreach ($stmt->fetchAll() as $client) {
        $grouped[$client['category_slug']]['category'] = [
            'name' => $client['category_name'],
            'slug' => $client['category_slug'],
        ];
        $grouped[$client['category_slug']]['clients'][] = $client;
    }

    return $grouped;
}

function slugify(string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    return $slug !== '' ? $slug : 'category-' . time();
}
