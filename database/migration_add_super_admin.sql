ALTER TABLE employees
    ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER can_access_admin;

INSERT INTO employees (name, email, can_access_admin, is_super_admin, is_active)
VALUES
    ('Swapnil Gaonkar', 'swapnilg@eduriser.com', 1, 1, 1),
    ('Amit Singh Lamba', 'amits@eduriser.com', 1, 1, 1)
ON DUPLICATE KEY UPDATE
    can_access_admin = 1,
    is_super_admin = 1,
    is_active = 1;
