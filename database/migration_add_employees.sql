CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) DEFAULT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    can_access_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO employees (name, email, can_access_admin, is_super_admin, is_active)
VALUES ('Amit Singh Lamba', 'amits@eduriser.com', 1, 1, 1)
ON DUPLICATE KEY UPDATE can_access_admin = VALUES(can_access_admin), is_super_admin = VALUES(is_super_admin), is_active = VALUES(is_active);
