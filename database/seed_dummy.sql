-- ==============================================================================
-- Clientele Online Pre-filled Dummy Data (All passwords: Demo@2026!)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Clean existing tables
TRUNCATE TABLE `audit_logs`;
TRUNCATE TABLE `client_links`;
TRUNCATE TABLE `clients`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `employees`;
TRUNCATE TABLE `admin_users`;

-- 1. Admin Users (Password: Demo@2026!)
INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 1, NOW()),
(2, 'swapnil', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 1, NOW());

-- 2. Employees (SSO & Admin access)
INSERT INTO `employees` (`id`, `name`, `email`, `can_access_admin`, `is_super_admin`, `is_active`) VALUES
(1, 'Swapnil Gaonkar', 'admin@demo.com', 1, 1, 1),
(2, 'Sarah Jenkins', 'manager@demo.com', 1, 0, 1),
(3, 'Alex Rivera', 'alex@demo.com', 1, 0, 1),
(4, 'Elena Vance', 'employee@demo.com', 0, 0, 1);

-- 3. Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `sort_order`, `is_active`) VALUES
(1, 'Banking & Financial Services', 'bfsi', 1, 1),
(2, 'Automobile & Mobility', 'automobile', 2, 1),
(3, 'Technology & Cloud', 'technology', 3, 1),
(4, 'Healthcare & Life Sciences', 'healthcare', 4, 1),
(5, 'Retail & Consumer Goods', 'retail', 5, 1),
(6, 'Media & Entertainment', 'media', 6, 1),
(7, 'Education & Learning Platforms', 'education', 7, 1),
(8, 'Industrial & Manufacturing', 'manufacturing', 8, 1);

-- 4. Demo Enterprise Clients
INSERT INTO `clients` (`id`, `category_id`, `name`, `logo_url`, `primary_url`, `sort_order`, `is_active`) VALUES
-- BFSI
(1, 1, 'Aetheria Capital & Banking', '', 'https://aetheria-capital.demo/portal', 1, 1),
(2, 1, 'Horizon Mutual Assurance', '', 'https://horizon-assurance.demo', 2, 1),
(3, 1, 'Vanguard Wealth Management', '', 'https://vanguard-wealth.demo', 3, 1),
-- Automobile
(4, 2, 'Apex Mobility & Motors', '', 'https://apex-motors.demo/learning', 1, 1),
(5, 2, 'Velocity Electric Vehicles', '', 'https://velocity-ev.demo', 2, 1),
(6, 2, 'Quantum Automotive Systems', '', 'https://quantum-auto.demo', 3, 1),
-- Technology
(7, 3, 'NovaTech Cloud Infrastructure', '', 'https://novatech-cloud.demo/training', 1, 1),
(8, 3, 'Synergy AI Solutions', '', 'https://synergy-ai.demo', 2, 1),
(9, 3, 'CyberShield Security Labs', '', 'https://cybershield.demo', 3, 1),
-- Healthcare
(10, 4, 'Lumina Health Care Systems', '', 'https://lumina-health.demo', 1, 1),
(11, 4, 'Spectra Pharmaceuticals', '', 'https://spectra-pharma.demo/portal', 2, 1),
(12, 4, 'BioGenix Diagnostics', '', 'https://biogenix.demo', 3, 1),
-- Retail & FMCG
(13, 5, 'Meridian Retail Group', '', 'https://meridian-retail.demo/onboarding', 1, 1),
(14, 5, 'PureLife Consumer Brands', '', 'https://purelife-fmcg.demo', 2, 1),
(15, 5, 'FreshEats Global Markets', '', 'https://fresheats.demo', 3, 1),
-- Media
(16, 6, 'Starlight Broadcasting Network', '', 'https://starlight-media.demo', 1, 1),
(17, 6, 'OmniVision Studios', '', 'https://omnivision.demo/campaigns', 2, 1),
-- Education
(18, 7, 'Athena Global Learning Academy', '', 'https://athena-academy.demo/learn', 1, 1),
(19, 7, 'NextGen Learning Systems', '', 'https://nextgen-lms.demo', 2, 1),
-- Manufacturing
(20, 8, 'Titan Steel & Alloys', '', 'https://titan-steel.demo/safety', 1, 1),
(21, 8, 'Precision Precision Dynamics', '', 'https://precision-dynamics.demo', 2, 1);

-- 5. Client Direct Links
INSERT INTO `client_links` (`client_id`, `title`, `url`, `sort_order`) VALUES
(1, 'Corporate LMS Portal', 'https://aetheria-capital.demo/portal', 1),
(1, 'Leadership Journey', 'https://aetheria-capital.demo/leadership', 2),
(4, 'Dealer Training Academy', 'https://apex-motors.demo/learning', 1),
(4, 'Service Tech Certification', 'https://apex-motors.demo/certification', 2),
(7, 'Cloud Engineer Onboarding', 'https://novatech-cloud.demo/training', 1),
(10, 'Clinical Guidelines Hub', 'https://lumina-health.demo/guidelines', 1),
(13, 'Store Manager Portal', 'https://meridian-retail.demo/onboarding', 1),
(18, 'Interactive Student LMS', 'https://athena-academy.demo/learn', 1),
(20, 'Plant Safety Simulation', 'https://titan-steel.demo/safety', 1);

-- 6. Sample Audit Logs
INSERT INTO `audit_logs` (`actor_email`, `action`, `entity_type`, `entity_id`, `entity_name`, `details`, `ip_address`, `created_at`) VALUES
('admin@demo.com', 'create', 'client', 1, 'Aetheria Capital & Banking', 'Created initial demo client record with 2 active portal links.', '127.0.0.1', NOW() - INTERVAL 5 DAY),
('admin@demo.com', 'create', 'client', 4, 'Apex Mobility & Motors', 'Configured automotive portal links.', '127.0.0.1', NOW() - INTERVAL 3 DAY),
('manager@demo.com', 'update', 'category', 1, 'Banking & Financial Services', 'Updated display sort order.', '127.0.0.1', NOW() - INTERVAL 1 DAY);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;