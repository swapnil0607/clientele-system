<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/client_repository.php';

$categoryGroups = get_clients_by_category();
$categories = get_categories(true);
$showAdminLink = is_logged_in() && employee_can_access_admin(current_admin_email());
$categoryLayoutRows = [
    ['bfsi'],
    ['automobile', 'chemical'],
    ['construction', 'hospitality'],
    ['manufacturing'],
    ['communication', 'education', 'media'],
    ['fmcg', 'pharma'],
    ['retail', 'technology'],
    ['oil-gas', 'supply-chain', 'healthcare', 'gamification'],
    ['custom-content'],
];
$universityLogos = range(2, 15);

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

function category_row_class(array $row, array $categoryGroups): string
{
    $count = count($row);
    if ($count === 2) {
        $firstCount = count($categoryGroups[$row[0]]['clients'] ?? []);
        $secondCount = count($categoryGroups[$row[1]]['clients'] ?? []);
        $smallerCount = min($firstCount, $secondCount);
        $largerCount = max($firstCount, $secondCount);

        if ($smallerCount <= 4 && $largerCount >= 8) {
            return 'space-y-16 category-wrapper';
        }

        return 'grid grid-cols-1 md:grid-cols-2 gap-10 category-wrapper';
    }
    if ($count === 3) {
        return 'grid grid-cols-1 lg:grid-cols-3 gap-10 category-wrapper';
    }
    if ($count >= 4) {
        return 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 category-wrapper';
    }
    return 'category-wrapper';
}

function client_grid_class(int $cardCount): string
{
    if ($cardCount >= 9) {
        return 'client-grid client-grid--dense';
    }

    if ($cardCount <= 4) {
        return 'client-grid client-grid--compact';
    }

    return 'client-grid';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Clientele | Eduriser</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            scroll-behavior: smooth;
            background-color: #ffffff;
        }
        /* Hero Banner with Background Image */
        .hero-banner {
            position: relative;
            background-image: url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&q=80&w=2000');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: calc(100vh - 5.5rem);
            min-height: calc(100dvh - 5.5rem);
            padding: clamp(1.75rem, 3vh, 3rem) 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 58, 138, 0.8) 100%);
        }
        .hero-content {
            position: relative;
            z-index: 10;
            width: 100%;
        }
        .hero-eyebrow {
            color: rgba(255, 255, 255, 0.92);
        }
        .hero-gradient-title {
            background: linear-gradient(180deg, #f8fbff 0%, #dbeafe 34%, #70B3FF 66%, #0550AB 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 8px 24px rgba(96, 165, 250, 0.18);
        }
        .hero-subtitle {
            font-size: clamp(1rem, 1.15vw, 1.25rem);
            line-height: 1.45;
        }

        /* Value Cards in Hero */
        .value-card {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.85) 0%, rgba(248, 250, 252, 0.85) 100%);
            backdrop-filter: none;
            border: 1px solid rgba(191, 219, 254, 0.8);
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow:
                0 18px 38px -28px rgba(15, 23, 42, 0.55),
                inset 0 1px 0 rgba(255, 255, 255, 0.92);
            transition: box-shadow 0.2s ease;
        }
        .value-card:hover {
            box-shadow:
                0 18px 38px -28px rgba(15, 23, 42, 0.55),
                inset 0 1px 0 rgba(255, 255, 255, 0.92);
        }
        .hero-value-layout {
            margin-top: 2rem;
            width: 100%;
            max-width: 72rem;
            margin-left: auto;
            margin-right: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        .value-card--featured {
            width: 100%;
            text-align: center;
            min-width: 0;
        }
        .featured-value-icon {
            margin: 0 auto 1rem;
        }
        .feature-icon-wrap {
            width: 2.75rem;
            height: 2.75rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.85rem;
            background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid rgba(147, 197, 253, 0.55);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
        }
        .feature-icon {
            color: #2563eb;
        }
        .value-card-title {
            color: #0f172a;
        }
        .value-card-grid {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
            min-width: 0;
        }
        .value-card-copy {
            color: #475569;
            font-size: 0.875rem;
            line-height: 1.7;
        }
        .featured-value-copy {
            max-width: none;
            white-space: nowrap;
            font-size: 0.8rem;
            line-height: 1.4;
        }
        .hero-value-layout .value-card {
            min-width: 0;
        }
        .logo-marquee {
            position: relative;
            width: 100%;
            margin-top: 1rem;
            padding: 0 3.15rem;
        }
        .logo-marquee-stage {
            position: relative;
            overflow: hidden;
            overflow-y: hidden;
            border-radius: 0.9rem;
            padding: 0.8rem 0;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            border: 1px solid rgba(203, 213, 225, 0.75);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.98),
                inset 0 0 0 1px rgba(241, 245, 249, 0.9),
                0 10px 26px -24px rgba(15, 23, 42, 0.28);
            scrollbar-width: none;
            -ms-overflow-style: none;
            cursor: grab;
            user-select: none;
            touch-action: pan-y;
        }
        .logo-marquee-stage.is-dragging {
            cursor: grabbing;
        }
        .logo-marquee-stage::-webkit-scrollbar {
            display: none;
        }
        .logo-marquee-stage::before,
        .logo-marquee-stage::after {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            width: 3.75rem;
            z-index: 2;
            pointer-events: none;
        }
        .logo-marquee-stage::before {
            left: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0) 100%);
        }
        .logo-marquee-stage::after {
            right: 0;
            background: linear-gradient(270deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0) 100%);
        }
        .logo-track {
            display: flex;
            width: max-content;
            will-change: transform;
        }
        .logo-track-group {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            padding: 0 1.75rem;
            flex-shrink: 0;
        }
        .logo-chip {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 6rem;
            height: 4.25rem;
            padding: 0 0.5rem;
            flex-shrink: 0;
        }
        .university-logo {
            display: block;
            width: auto;
            max-width: 8.75rem;
            max-height: 4.35rem;
            object-fit: contain;
            object-position: center;
            filter: saturate(1.02) contrast(1.03);
            opacity: 0.98;
            pointer-events: none;
        }
        .logo-nav {
            position: absolute;
            top: 50%;
            z-index: 4;
            width: 2.55rem;
            height: 2.55rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            border: 1px solid rgba(203, 213, 225, 0.92);
            background: rgba(255, 255, 255, 0.96);
            color: #2563eb;
            box-shadow: 0 14px 28px -20px rgba(15, 23, 42, 0.45);
            transform: translateY(-50%);
            transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease;
        }
        .logo-nav:hover {
            background: #eff6ff;
            border-color: rgba(147, 197, 253, 0.95);
            box-shadow: 0 16px 30px -20px rgba(37, 99, 235, 0.32);
            color: #1d4ed8;
        }
        .logo-nav:focus-visible {
            outline: 2px solid rgba(59, 130, 246, 0.35);
            outline-offset: 2px;
        }
        .logo-nav--prev {
            left: 0;
        }
        .logo-nav--next {
            right: 0;
        }

        /* Standardized Client Cards */
        .client-card {
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1rem;
            height: 110px;
            width: 100%;
            border-radius: 0.75rem;
            background: white;
            cursor: pointer;
        }
        .client-card:hover {
            border-color: #3b82f6;
            background-color: #f8faff;
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .client-logo {
            max-height: 55px;
            max-width: 90%;
            width: auto;
            height: auto;
            object-fit: contain;
            filter: grayscale(10%);
            opacity: 0.8;
            transition: all 0.4s ease;
            margin-bottom: 0.5rem;
        }
        .client-card:hover .client-logo {
            filter: grayscale(0%);
            opacity: 1;
            transform: scale(1.05);
        }
        .client-name {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #4b5563;
            line-height: 1.1;
        }
        .client-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 11.25rem), 1fr));
            gap: 1.25rem;
            align-items: stretch;
        }
        .client-grid--compact {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 13rem), 1fr));
        }
        @media (min-width: 1024px) {
            .client-grid--dense {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        .client-card--text-only {
            gap: 0.65rem;
        }
        .client-card-initials {
            width: 2.75rem;
            height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.85rem;
            background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid rgba(147, 197, 253, 0.55);
            color: #1d4ed8;
            font-size: 0.8rem;
            font-weight: 900;
            letter-spacing: 0.04em;
        }

        /* Count Bubble */
        .link-count-bubble {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #2563eb;
            color: white;
            font-size: 9px;
            font-weight: 900;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10;
        }

        /* Typography */
        .category-title {
            text-transform: uppercase;
            letter-spacing: 0.15em;
            font-weight: 800;
            color: #1e3a8a;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        .category-underline {
            width: 2rem;
            height: 3px;
            background-color: #2563eb;
            margin: 0 auto 1.5rem auto;
            border-radius: 2px;
        }
        .section-container {
            padding: 2.5rem 1.5rem;
            background-color: #FAFAFA;
            border-radius: 1.25rem;
            border: 1px solid #f1f5f9;
            height: auto;
        }
        .category-wrapper {
            align-items: start;
        }

        /* Modal Style */
        #portalModal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white;
            width: 100%;
            max-width: 380px;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        #portalModal.active { display: flex; }
        .portal-btn {
            display: block;
            width: 100%;
            padding: 0.875rem 1rem;
            margin-bottom: 0.5rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            color: #1e293b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            text-align: left;
            transition: all 0.2s;
        }
        .portal-btn:hover {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        /* iOS-Inspired Category Picker */
        .category-picker {
            position: relative;
        }
        .category-picker-trigger {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.9rem;
            padding: 0.85rem 0.95rem 0.85rem 1rem;
            border-radius: 1.2rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            appearance: none;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(241, 245, 249, 0.82) 100%);
            box-shadow:
                0 10px 30px rgba(15, 23, 42, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            cursor: pointer;
            text-align: left;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }
        .category-picker-trigger::before {
            content: '';
            position: absolute;
            inset: 1px;
            border-radius: inherit;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.65), rgba(255, 255, 255, 0.18));
            opacity: 0.9;
            pointer-events: none;
        }
        .category-picker-trigger:hover {
            transform: translateY(-1px);
            box-shadow:
                0 14px 34px rgba(15, 23, 42, 0.12),
                inset 0 1px 0 rgba(255, 255, 255, 0.88);
        }
        .category-picker.open .category-picker-trigger {
            border-color: rgba(59, 130, 246, 0.32);
            box-shadow:
                0 18px 40px rgba(15, 23, 42, 0.16),
                0 0 0 4px rgba(59, 130, 246, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.92);
        }
        .category-picker-copy,
        .category-picker-icon {
            position: relative;
            z-index: 1;
        }
        .category-picker-copy {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .category-picker-caption {
            font-size: 8px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #183F72;
            margin-bottom: 0.3rem;
        }
        .category-picker-value {
            font-size: 14px;
            line-height: 1.2;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .category-picker-icon {
            width: 2rem;
            height: 2rem;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72);
            color: #1e3a8a;
            transition: transform 0.25s ease, background-color 0.25s ease;
        }
        .category-picker.open .category-picker-icon {
            transform: rotate(180deg);
            background: rgba(219, 234, 254, 0.85);
        }
        .category-picker-menu {
            position: fixed;
            top: 0;
            left: 0;
            width: min(22rem, calc(100vw - 1.5rem));
            padding: 0.5rem;
            border-radius: 1.4rem;
            border: 1px solid rgba(255, 255, 255, 0.78);
            background: rgba(255, 255, 255, 0.7);
            box-shadow:
                0 24px 55px rgba(15, 23, 42, 0.18),
                0 8px 18px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(12px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translateY(-8px) scale(0.98);
            transform-origin: top center;
            transition: opacity 0.22s ease, transform 0.22s ease, visibility 0.22s ease;
            z-index: 90;
            overflow: hidden;
        }
        .category-picker-scroll {
            max-height: min(26rem, 70vh);
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.55) transparent;
        }
        .category-picker-scroll::-webkit-scrollbar {
            width: 8px;
        }
        .category-picker-scroll::-webkit-scrollbar-thumb {
            border-radius: 9999px;
            background: rgba(148, 163, 184, 0.55);
        }
        .category-picker-menu.open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }
        .category-picker-option {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.85rem 0.95rem;
            border: 0;
            border-radius: 1rem;
            background: transparent;
            color: #0f172a;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            text-align: left;
            transition: background-color 0.22s ease, transform 0.22s ease, color 0.22s ease, box-shadow 0.22s ease;
        }
        .category-picker-option:hover,
        .category-picker-option:focus-visible {
            background: rgba(59, 130, 246, 0.08);
            color: #1d4ed8;
            transform: translateX(3px);
            outline: none;
        }
        .category-picker-option.active {
            background: linear-gradient(135deg, rgba(219, 234, 254, 0.88), rgba(239, 246, 255, 0.92));
            color: #1d4ed8;
            box-shadow: inset 0 0 0 1px rgba(96, 165, 250, 0.2);
        }
        .category-picker-check {
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
            opacity: 0;
            transform: scale(0.85);
            transition: opacity 0.22s ease, transform 0.22s ease;
        }
        .category-picker-option.active .category-picker-check {
            opacity: 1;
            transform: scale(1);
        }
        @media (max-width: 768px) {
            .value-card-grid {
                grid-template-columns: 1fr;
            }
            .value-card--featured {
                width: 100%;
                text-align: left;
            }
            .featured-value-icon {
                margin-left: 0;
                margin-right: 0;
            }
            .logo-track-group {
                gap: 0.75rem;
                padding: 0.85rem 0.75rem;
            }
            .logo-chip {
                min-width: 6rem;
                height: 3.75rem;
                padding: 0 0.3rem;
            }
            .logo-marquee-stage {
                padding: 0.65rem 0;
            }
            .logo-marquee-stage::before,
            .logo-marquee-stage::after {
                width: 2.3rem;
            }
            .university-logo {
                max-width: 6.9rem;
                max-height: 4rem;
            }
            .logo-marquee {
                padding: 0 2.45rem;
            }
            .logo-nav {
                width: 2.2rem;
                height: 2.2rem;
            }
            .logo-nav--prev {
                left: 0;
            }
            .logo-nav--next {
                right: 0;
            }
            .featured-value-copy {
                white-space: normal;
            }
        }
        @media (max-width: 640px) {
            .site-header-inner {
                padding: 0.8rem 1rem;
                gap: 0.75rem;
            }
            .site-header-logo img {
                height: 2.55rem;
            }
            .site-header-tools {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 0.65rem;
                align-items: stretch;
                width: 100%;
            }
            .site-header-tools .category-picker {
                order: 1;
                grid-column: 1 / -1;
                width: 100% !important;
            }
            .site-header-tools .faculty-link {
                order: 2;
                grid-column: 1;
                width: 100% !important;
            }
            .site-header-tools .admin-link {
                order: 3;
                grid-column: 2;
                width: 100% !important;
            }
            .site-header-tools .client-search {
                order: 4;
                grid-column: 1 / -1;
                width: 100% !important;
            }
            .category-picker-trigger {
                min-height: 3.05rem;
                padding: 0.72rem 0.85rem;
                border-radius: 1rem;
            }
            .category-picker-caption {
                font-size: 7px;
                margin-bottom: 0.2rem;
            }
            .category-picker-value {
                font-size: 13px;
            }
            .category-picker-icon {
                width: 1.8rem;
                height: 1.8rem;
            }
            .mobile-toolbar-link {
                min-height: 2.75rem;
                padding: 0.65rem 0.85rem;
                border-radius: 0.9rem;
                font-size: 0.86rem;
            }
            .client-search input {
                height: 2.9rem;
                border-radius: 0.9rem;
                font-size: 0.95rem;
            }
            .category-picker-scroll {
                max-height: min(22rem, 60vh);
            }
        }

        /* Go to Top Button */
        #goToTopBtn {
            position: fixed;
            right: 1.5rem;
            bottom: 1.5rem;
            width: 3rem;
            height: 3rem;
            border: none;
            border-radius: 9999px;
            background: #002F80;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.28);
            opacity: 0;
            visibility: hidden;
            transform: translateY(12px);
            transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s ease, box-shadow 0.25s ease;
            z-index: 60;
        }
        #goToTopBtn.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        #goToTopBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(30,85,138,0.55);
        }
        #goToTopBtn:focus-visible {
            outline: 3px solid rgba(59, 130, 246, 0.35);
            outline-offset: 3px;
        }
        @media (max-width: 640px) {
            .client-grid,
            .client-grid--compact,
            .client-grid--dense {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 1.25rem;
            }
            #goToTopBtn {
                right: 1rem;
                bottom: 1rem;
                width: 2.75rem;
                height: 2.75rem;
            }
        }
    </style>
</head>
<body class="text-gray-900">

    <!-- Header Section with Navigation Tools -->
    <header class="bg-white/70 backdrop-blur-md border-b sticky top-0 z-50 transition-all duration-300">
        <div class="site-header-inner max-w-7xl mx-auto px-6 py-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="site-header-logo flex items-center w-full md:w-auto justify-center md:justify-start">
                <img src="https://eduriserck.com/eduriser/eduriser-logo.svg" alt="EduRiser Logo" class="h-10 w-auto">
            </div>
            
            <div class="site-header-tools flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                <!-- Dropdown Navigation -->
                <div id="categoryPicker" class="category-picker w-full sm:w-64">
                    <button id="categoryPickerButton" type="button" class="category-picker-trigger" aria-haspopup="listbox" aria-expanded="false">
                        <span class="category-picker-copy">
                            <span class="category-picker-caption">Browse Categories</span>
                            <span id="categoryPickerLabel" class="category-picker-value">Jump to Category</span>
                        </span>
                        <span class="category-picker-icon" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </span>
                    </button>
                    <select id="categorySelect" onchange="scrollToCategory(this.value)" class="sr-only" tabindex="-1" aria-hidden="true">
                        <option value="">Jump to Category...</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="category-<?= e($category['slug']) ?>"><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <a href="https://eduriserck.com/clientele/ckfaculty/" class="faculty-link mobile-toolbar-link w-full sm:w-auto inline-flex items-center justify-center rounded-xl bg-blue-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-900 hover:outline-none hover:ring-1 hover:ring-blue-300 hover:ring-offset-2" target="_blank">
                    CK Faculties
                </a>

                <!-- Client Search Bar -->
                <div class="client-search relative w-full sm:w-64">
                    <input type="text" id="searchInput" oninput="filterClients()" placeholder="Search specific client..." class="w-full pl-10 pr-12 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-base sm:text-sm font-medium text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button id="clearSearchBtn" type="button" onclick="clearClientSearch()" aria-label="Clear search" class="absolute inset-y-0 right-0 pr-3 flex items-center hidden">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-400 transition-all hover:border-gray-300 hover:text-gray-600">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 6l12 12M18 6l-12 12"></path>
                            </svg>
                        </span>
                    </button>
                </div>

                <?php if ($showAdminLink): ?>
                    <a href="admin/dashboard.php" class="admin-link mobile-toolbar-link w-full sm:w-auto inline-flex items-center justify-center rounded-xl border border-blue-900 bg-white px-5 py-3 text-sm font-semibold text-blue-900 shadow-sm transition-all hover:bg-blue-50 hover:outline-none hover:ring-1 hover:ring-blue-300 hover:ring-offset-2">
                        Admin
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div id="categoryPickerMenu" class="category-picker-menu" role="presentation">
        <div id="categoryPickerScroll" class="category-picker-scroll" role="listbox" aria-label="Category navigation"></div>
    </div>

    <!-- Hero Section -->
    <section class="hero-banner">
        <div class="hero-overlay"></div>
        <div class="max-w-7xl mx-auto px-6 hero-content text-center">
            <div class="mb-8">
                <h2 class="hero-eyebrow font-bold tracking-[0.3em] uppercase text-sm mb-4">The CrossKnowledge Difference</h2>
                <h1 class="hero-gradient-title text-4xl md:text-5xl font-black tracking-tight uppercase mb-4 leading-tight">
                    Our Global Partners
                </h1>
                <p class="hero-subtitle text-blue-100 font-light max-w-3xl mx-auto">
                    We collaborate with leading global institutions to bring you high-impact learning backed by research, expertise, and cutting-edge delivery.
                </p>
            </div>

            <!-- Difference Cards -->
            <div class="hero-value-layout">
                <div class="value-card value-card--featured">
                    <div class="feature-icon-wrap featured-value-icon">
                        <svg class="feature-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </div>
                    <h3 class="value-card-title font-black text-lg mb-2 uppercase tracking-wide">World-Class Content</h3>
                    <p class="value-card-copy featured-value-copy mx-auto">
                        Curated programs from top global institutions, designed to deliver practical insights and industry-relevant knowledge.
                    </p>
                    <div class="logo-marquee" aria-label="Featured university partners">
                        <button id="logoNavPrev" class="logo-nav logo-nav--prev" type="button" aria-label="Scroll logos left">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M15 18l-6-6 6-6"></path>
                            </svg>
                        </button>
                        <div id="universityLogoStage" class="logo-marquee-stage" tabindex="0">
                            <div id="universityLogoTrack" class="logo-track">
                                <?php for ($logoSet = 0; $logoSet < 3; $logoSet++): ?>
                                    <div class="logo-track-group" <?= $logoSet === 0 ? 'id="universityLogoTrackPrimary"' : 'aria-hidden="true"' ?>>
                                        <?php foreach ($universityLogos as $logoNumber): ?>
                                            <div class="logo-chip">
                                                <img src="https://eduriserck.com/eduriser/Clientele/University-logos/Artboard%20<?= $logoNumber ?>.png" alt="University logo <?= $logoNumber ?>" class="university-logo" decoding="async">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <button id="logoNavNext" class="logo-nav logo-nav--next" type="button" aria-label="Scroll logos right">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M9 6l6 6-6 6"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="value-card-grid text-left">
                    <div class="value-card">
                        <div class="feature-icon-wrap">
                            <svg class="feature-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <h3 class="value-card-title font-black text-lg mb-2 uppercase tracking-wide">Cutting-Edge Tech</h3>
                        <p class="value-card-copy text-sm leading-relaxed">
                            Easier and faster to integrate technology featuring exceptional user experience and full mobile accessibility.
                        </p>
                    </div>
                    <div class="value-card">
                        <div class="feature-icon-wrap">
                            <svg class="feature-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h3 class="value-card-title font-black text-lg mb-2 uppercase tracking-wide">Superior Expertise</h3>
                        <p class="value-card-copy text-sm leading-relaxed">
                            A dedicated team of experts committed to delivering measurable outcomes with precision, transparency, and strategic insight.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

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

            <?php $renderedCategories = []; ?>
            <?php foreach ($categoryLayoutRows as $row): ?>
                <?php
                    $visibleRow = array_values(array_filter($row, static fn(string $slug): bool => isset($categoryGroups[$slug])));
                    if (empty($visibleRow)) {
                        continue;
                    }
                ?>
                <div class="<?= e(category_row_class($visibleRow, $categoryGroups)) ?>">
                    <?php foreach ($visibleRow as $slug): ?>
                        <?php
                            $group = $categoryGroups[$slug];
                            $renderedCategories[$slug] = true;
                            $cardCount = count($group['clients']);
                        ?>
                        <section id="category-<?= e($group['category']['slug']) ?>" class="text-center section-container">
                            <h2 class="category-title"><?= e($group['category']['name']) ?></h2>
                            <div class="category-underline"></div>
                            <div class="<?= e(client_grid_class($cardCount)) ?>">
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
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php foreach ($categoryGroups as $slug => $group): ?>
                <?php if (isset($renderedCategories[$slug])) continue; ?>
                <div class="category-wrapper">
                    <section id="category-<?= e($group['category']['slug']) ?>" class="text-center section-container">
                        <h2 class="category-title"><?= e($group['category']['name']) ?></h2>
                        <div class="category-underline"></div>
                        <div class="<?= e(client_grid_class(count($group['clients']))) ?>">
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

    <!-- Modal for Multi-Link -->
    <div id="portalModal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h3 id="modalClientName" class="font-black text-slate-800 uppercase text-xs tracking-widest">Select Portal</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-900 text-2xl leading-none">&times;</button>
            </div>
            <div id="portalLinkContainer"></div>
        </div>
    </div>

    <button id="goToTopBtn" type="button" aria-label="Go to top" title="Go to top">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path>
        </svg>
    </button>

    <footer class="bg-gray-50 border-t py-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <div class="flex items-center justify-center space-x-2 mb-8 opacity-50">
                <img src="https://eduriserck.com/eduriser/eduriser-logo.svg" alt="EduRiser Logo" class="h-8 w-auto">
            </div>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest mb-6">&copy; 2026 Eduriser learning Solutions.</p>
        </div>
    </footer>

    <script>
        const modal = document.getElementById('portalModal');
        const modalName = document.getElementById('modalClientName');
        const linkContainer = document.getElementById('portalLinkContainer');
        const categoryPicker = document.getElementById('categoryPicker');
        const categoryPickerButton = document.getElementById('categoryPickerButton');
        const categoryPickerMenu = document.getElementById('categoryPickerMenu');
        const categoryPickerScroll = document.getElementById('categoryPickerScroll');
        const categoryPickerLabel = document.getElementById('categoryPickerLabel');
        const categorySelect = document.getElementById('categorySelect');
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const clientResults = document.getElementById('clientResults');
        const goToTopBtn = document.getElementById('goToTopBtn');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const universityLogoStage = document.getElementById('universityLogoStage');
        const universityLogoTrackPrimary = document.getElementById('universityLogoTrackPrimary');
        const universityLogoTrack = document.getElementById('universityLogoTrack');
        const logoNavPrev = document.getElementById('logoNavPrev');
        const logoNavNext = document.getElementById('logoNavNext');
        let universityLogoGroupWidth = 0;
        let universityLogoAnimationFrame = 0;
        let universityLogoLastTimestamp = 0;
        let universityLogoOffset = 0;
        let universityLogoDragging = false;
        let universityLogoPointerId = null;
        let universityLogoDragStartX = 0;
        let universityLogoDragStartOffset = 0;
        let universityLogoAutoPausedUntil = 0;

        function positionCategoryPickerMenu() {
            if (!categoryPickerButton || !categoryPickerMenu) return;

            const rect = categoryPickerButton.getBoundingClientRect();
            const viewportPadding = 12;
            const minimumTopOffset = 100;
            const desiredWidth = rect.width;
            const maxWidth = window.innerWidth - (viewportPadding * 2);
            const menuWidth = Math.min(desiredWidth, maxWidth);
            const left = Math.min(Math.max(rect.left, viewportPadding), window.innerWidth - menuWidth - viewportPadding);
            const top = Math.max(rect.bottom + 12, minimumTopOffset);

            categoryPickerMenu.style.width = `${menuWidth}px`;
            categoryPickerMenu.style.left = `${left}px`;
            categoryPickerMenu.style.top = `${top}px`;
        }

        function setCategoryPickerOpen(isOpen) {
            if (!categoryPicker || !categoryPickerButton) return;

            categoryPicker.classList.toggle('open', isOpen);
            categoryPickerButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            if (categoryPickerMenu) {
                categoryPickerMenu.classList.toggle('open', isOpen);
                if (isOpen) {
                    positionCategoryPickerMenu();
                }
            }
        }

        function updateCategoryPickerLabel(categoryId = '') {
            if (!categoryPickerLabel || !categorySelect) return;

            const selectedOption = Array.from(categorySelect.options).find(option => option.value === categoryId);
            categoryPickerLabel.textContent = selectedOption && selectedOption.value ? selectedOption.textContent : 'Jump to Category';
        }

        function syncCategoryPickerSelection() {
            if (!categoryPickerScroll || !categorySelect) return;

            const currentValue = categorySelect.value;

            Array.from(categoryPickerScroll.querySelectorAll('.category-picker-option')).forEach(optionButton => {
                const isActive = optionButton.dataset.value === currentValue;
                optionButton.classList.toggle('active', isActive);
                optionButton.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
        }

        function buildCategoryPickerOptions() {
            if (!categoryPickerScroll || !categorySelect) return;

            const svgNamespace = 'http://www.w3.org/2000/svg';
            categoryPickerScroll.innerHTML = '';

            Array.from(categorySelect.options)
                .filter(option => option.value)
                .forEach(option => {
                    const optionButton = document.createElement('button');
                    const optionLabel = document.createElement('span');
                    const checkIcon = document.createElementNS(svgNamespace, 'svg');
                    const checkPath = document.createElementNS(svgNamespace, 'path');

                    optionButton.type = 'button';
                    optionButton.className = 'category-picker-option';
                    optionButton.dataset.value = option.value;
                    optionButton.setAttribute('role', 'option');
                    optionButton.setAttribute('aria-selected', 'false');

                    optionLabel.textContent = option.textContent;
                    optionButton.appendChild(optionLabel);

                    checkIcon.setAttribute('class', 'category-picker-check');
                    checkIcon.setAttribute('fill', 'none');
                    checkIcon.setAttribute('stroke', 'currentColor');
                    checkIcon.setAttribute('viewBox', '0 0 24 24');
                    checkIcon.setAttribute('aria-hidden', 'true');

                    checkPath.setAttribute('stroke-linecap', 'round');
                    checkPath.setAttribute('stroke-linejoin', 'round');
                    checkPath.setAttribute('stroke-width', '2.5');
                    checkPath.setAttribute('d', 'M5 13l4 4L19 7');
                    checkIcon.appendChild(checkPath);

                    optionButton.appendChild(checkIcon);
                    optionButton.addEventListener('click', () => {
                        setCategoryPickerOpen(false);
                        scrollToCategory(option.value);
                    });

                    categoryPickerScroll.appendChild(optionButton);
                });

            updateCategoryPickerLabel(categorySelect.value);
            syncCategoryPickerSelection();
        }

        function pauseUniversityLogoAutoScroll(duration = 3200) {
            universityLogoAutoPausedUntil = performance.now() + duration;
        }

        function measureUniversityLogoSlider() {
            if (!universityLogoTrackPrimary) return;
            universityLogoGroupWidth = universityLogoTrackPrimary.getBoundingClientRect().width;
            normalizeUniversityLogoOffset();
            renderUniversityLogoSlider();
        }

        function normalizeUniversityLogoOffset() {
            if (!universityLogoGroupWidth) return;

            universityLogoOffset %= universityLogoGroupWidth;
            if (universityLogoOffset < 0) {
                universityLogoOffset += universityLogoGroupWidth;
            }
        }

        function renderUniversityLogoSlider() {
            if (!universityLogoTrack) return;
            universityLogoTrack.style.transform = `translateX(${-universityLogoOffset}px)`;
        }

        function animateUniversityLogoSlider(timestamp) {
            if (!universityLogoTrack || !universityLogoStage) return;

            if (!universityLogoLastTimestamp) {
                universityLogoLastTimestamp = timestamp;
            }

            const delta = timestamp - universityLogoLastTimestamp;
            universityLogoLastTimestamp = timestamp;

            if (
                universityLogoGroupWidth > 0 &&
                !universityLogoDragging &&
                timestamp > universityLogoAutoPausedUntil &&
                document.visibilityState === 'visible'
            ) {
                universityLogoOffset += delta * 0.035;
                normalizeUniversityLogoOffset();
                renderUniversityLogoSlider();
            }

            universityLogoAnimationFrame = window.requestAnimationFrame(animateUniversityLogoSlider);
        }

        function scrollUniversityLogos(direction) {
            if (!universityLogoStage || !universityLogoGroupWidth) return;

            pauseUniversityLogoAutoScroll(4200);
            const distance = Math.max(universityLogoStage.clientWidth * 0.45, 180) * direction;
            universityLogoOffset += distance;
            normalizeUniversityLogoOffset();
            renderUniversityLogoSlider();
        }

        function startUniversityLogoDrag(event) {
            if (!universityLogoStage || !universityLogoGroupWidth) return;
            if (event.pointerType === 'mouse' && event.button !== 0) return;

            universityLogoDragging = true;
            universityLogoPointerId = event.pointerId;
            universityLogoDragStartX = event.clientX;
            universityLogoDragStartOffset = universityLogoOffset;
            universityLogoStage.classList.add('is-dragging');
            pauseUniversityLogoAutoScroll(5000);
            universityLogoStage.setPointerCapture?.(event.pointerId);
        }

        function moveUniversityLogoDrag(event) {
            if (!universityLogoDragging || !universityLogoStage) return;
            if (universityLogoPointerId !== null && event.pointerId !== universityLogoPointerId) return;

            const deltaX = event.clientX - universityLogoDragStartX;
            universityLogoOffset = universityLogoDragStartOffset - deltaX;
            normalizeUniversityLogoOffset();
            renderUniversityLogoSlider();
            event.preventDefault();
        }

        function endUniversityLogoDrag(event) {
            if (!universityLogoDragging || !universityLogoStage) return;
            if (event && universityLogoPointerId !== null && event.pointerId !== universityLogoPointerId) return;

            universityLogoDragging = false;
            universityLogoPointerId = null;
            universityLogoStage.classList.remove('is-dragging');
            pauseUniversityLogoAutoScroll(1800);
        }

        // Navigation Dropdown
        function scrollToCategory(categoryId) {
            if (categorySelect) {
                categorySelect.value = categoryId || '';
            }
            updateCategoryPickerLabel(categoryId || '');
            syncCategoryPickerSelection();
            setCategoryPickerOpen(false);

            if (!categoryId) return;
            
            // Clear search if using dropdown
            const searchInput = document.getElementById('searchInput');
            if(searchInput.value !== "") {
                searchInput.value = "";
                filterClients(); // Reset filter
            }

            const element = document.getElementById(categoryId);
            if (element) {
                // Adjusting header offset so the sticky header doesn't cover the title
                const headerOffset = 100; 
                const elementPosition = element.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                     top: offsetPosition,
                     behavior: "smooth"
                });
            }
        }

        function getHeaderOffset() {
            const header = document.querySelector('header');
            return (header ? header.offsetHeight : 84) + 20;
        }

        function bringSearchResultsIntoView(hasResults) {
            const searchTerm = searchInput?.value.trim();
            if (!searchTerm) return;

            const target = hasResults
                ? document.querySelector('.section-container:not(.hidden)')
                : noResultsMessage || clientResults;

            if (!target) return;

            const headerOffset = getHeaderOffset();
            const rect = target.getBoundingClientRect();
            const isAlreadyVisible = rect.top >= headerOffset && rect.top <= window.innerHeight * 0.45;

            if (!isAlreadyVisible) {
                const offsetPosition = rect.top + window.pageYOffset - headerOffset;
                window.scrollTo({
                    top: Math.max(offsetPosition, 0),
                    behavior: 'smooth'
                });
            }
        }

        function updateSearchClearButton() {
            if (!searchInput || !clearSearchBtn) return;

            clearSearchBtn.classList.toggle('hidden', searchInput.value.trim() === '');
        }

        function clearClientSearch() {
            if (!searchInput) return;

            searchInput.value = '';
            filterClients();
            updateSearchClearButton();
            searchInput.focus();
        }

        // Live Search Filtering
        function filterClients() {
            const searchTerm = searchInput.value.toLowerCase();
            const sections = document.querySelectorAll('.section-container');
            let visibleCardCount = 0;
            
            // Filter standard cards
            sections.forEach(section => {
                const cards = section.querySelectorAll('.client-card');
                let hasVisibleCard = false;

                cards.forEach(card => {
                    const clientName = card.querySelector('.client-name').innerText.toLowerCase();
                    if (clientName.includes(searchTerm)) {
                        card.classList.remove('hidden');
                        card.style.display = 'flex'; // Restore inline style overridden by class
                        hasVisibleCard = true;
                        visibleCardCount++;
                    } else {
                        card.classList.add('hidden');
                        card.style.display = 'none'; // Force hide
                    }
                });

                // Toggle visibility of the inner section
                if (hasVisibleCard) {
                    section.classList.remove('hidden');
                } else {
                    section.classList.add('hidden');
                }
            });

            // Handle outer wrapping containers (to avoid excessive blank space when sections hide)
            const wrappers = document.querySelectorAll('.category-wrapper');
            wrappers.forEach(wrapper => {
                const visibleSections = Array.from(wrapper.querySelectorAll('.section-container')).filter(sec => !sec.classList.contains('hidden'));
                if (visibleSections.length > 0) {
                    wrapper.classList.remove('hidden');
                } else {
                    wrapper.classList.add('hidden');
                }
            });

            // Reset dropdown to default state when searching
            if(searchTerm !== "") {
                if (categorySelect) {
                    categorySelect.value = "";
                }
                updateCategoryPickerLabel('');
                syncCategoryPickerSelection();
            }

            if (noResultsMessage) {
                noResultsMessage.classList.toggle('hidden', !(searchTerm !== "" && visibleCardCount === 0));
            }

            updateSearchClearButton();

            if (searchTerm !== "") {
                bringSearchResultsIntoView(visibleCardCount > 0);
            }
        }

        // Modal Functionality
        function handleClientClick(name, links) {
			if (links && links.length > 1) {
				// Show popup for multiple links
				openModal(name, links);
			} else {
				// Fallback just in case a single link is passed here
				console.log(`Directly opening portal for: ${name}`);
			}
		}

		function openModal(name, links) {
			modalName.innerText = name;
			linkContainer.innerHTML = '';

			links.forEach(linkData => {
				// Create an <a> tag for native link behavior
				const linkEl = document.createElement('a');
				linkEl.className = 'portal-btn block'; // 'block' ensures it spans full width

				// Check if using the new object format {title, url}
				if (typeof linkData === 'object') {
					linkEl.innerText = linkData.title;
					linkEl.href = linkData.url;
					if (linkData.url && linkData.url !== '#') {
						linkEl.target = '_blank';
						linkEl.rel = 'noopener noreferrer';
					}
				} else {
					// Fallback just in case you haven't updated all old string links yet
					linkEl.innerText = linkData;
					linkEl.href = '#'; 
				}

				// Close modal when link is clicked
				linkEl.onclick = () => {
					closeModal();
				};

				linkContainer.appendChild(linkEl);
			});

			modal.classList.add('active');
			document.body.style.overflow = 'hidden';
		}

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        window.onclick = (e) => { if (e.target == modal) closeModal(); };

        function toggleGoToTopButton() {
            if (!goToTopBtn) return;
            goToTopBtn.classList.toggle('visible', window.scrollY > 300);
        }

        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        if (goToTopBtn) {
            goToTopBtn.addEventListener('click', scrollToTop);
            window.addEventListener('scroll', toggleGoToTopButton, { passive: true });
            toggleGoToTopButton();
        }

        if (categoryPickerButton) {
            categoryPickerButton.addEventListener('click', () => {
                setCategoryPickerOpen(!categoryPicker.classList.contains('open'));
            });

            categoryPickerButton.addEventListener('keydown', (event) => {
                if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && categoryPickerMenu) {
                    event.preventDefault();
                    setCategoryPickerOpen(true);

                    const options = categoryPickerScroll?.querySelectorAll('.category-picker-option') || [];
                    const targetOption = event.key === 'ArrowUp' ? options[options.length - 1] : options[0];
                    if (targetOption) {
                        targetOption.focus();
                    }
                }
            });
        }

        if (categoryPickerScroll) {
            categoryPickerScroll.addEventListener('keydown', (event) => {
                const options = Array.from(categoryPickerScroll.querySelectorAll('.category-picker-option'));
                const currentIndex = options.indexOf(document.activeElement);

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    const nextIndex = currentIndex < options.length - 1 ? currentIndex + 1 : 0;
                    options[nextIndex]?.focus();
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    const previousIndex = currentIndex > 0 ? currentIndex - 1 : options.length - 1;
                    options[previousIndex]?.focus();
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    setCategoryPickerOpen(false);
                    categoryPickerButton?.focus();
                }
            });
        }

        document.addEventListener('click', (event) => {
            const clickedInsideTrigger = categoryPicker && categoryPicker.contains(event.target);
            const clickedInsideMenu = categoryPickerMenu && categoryPickerMenu.contains(event.target);

            if (!clickedInsideTrigger && !clickedInsideMenu) {
                setCategoryPickerOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setCategoryPickerOpen(false);
            }
        });

        window.addEventListener('resize', () => {
            if (categoryPicker?.classList.contains('open')) {
                positionCategoryPickerMenu();
            }

            if (universityLogoStage) {
                measureUniversityLogoSlider();
            }
        });

        window.addEventListener('scroll', () => {
            if (categoryPicker?.classList.contains('open')) {
                positionCategoryPickerMenu();
            }
        }, { passive: true });

        if (universityLogoStage && universityLogoTrackPrimary && universityLogoTrack) {
            measureUniversityLogoSlider();

            universityLogoStage.addEventListener('pointerdown', startUniversityLogoDrag);
            universityLogoStage.addEventListener('pointermove', moveUniversityLogoDrag);
            universityLogoStage.addEventListener('pointerup', endUniversityLogoDrag);
            universityLogoStage.addEventListener('pointercancel', endUniversityLogoDrag);
            universityLogoStage.addEventListener('pointerleave', (event) => {
                if (universityLogoDragging && event.pointerType === 'mouse') {
                    endUniversityLogoDrag(event);
                }
            });
            universityLogoStage.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    scrollUniversityLogos(-1);
                }

                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    scrollUniversityLogos(1);
                }
            });

            logoNavPrev?.addEventListener('click', () => scrollUniversityLogos(-1));
            logoNavNext?.addEventListener('click', () => scrollUniversityLogos(1));

            document.addEventListener('visibilitychange', () => {
                universityLogoLastTimestamp = 0;
                if (document.visibilityState === 'visible') {
                    measureUniversityLogoSlider();
                }
            });

            window.addEventListener('load', measureUniversityLogoSlider);
            universityLogoAnimationFrame = window.requestAnimationFrame(animateUniversityLogoSlider);
        }

        buildCategoryPickerOptions();
    </script>

</body>
</html>
