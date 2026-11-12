-- Council Member Registration Portal — Seed Data
-- Run AFTER schema.sql: mysql -u root -p council_portal < database/seed.sql

USE `council_portal`;

-- Council Categories
INSERT INTO `council_categories` (`name`, `code`, `is_active`) VALUES
('Municipal Corporation',   'MC',   1),
('Town Panchayat',          'TP',   1),
('Gram Panchayat',          'GP',   1),
('Nagar Panchayat',         'NP',   1),
('District Council',        'DC',   1),
('Block Development Council','BDC', 1),
('Urban Local Body',        'ULB',  1),
('Special Area Development Authority', 'SADA', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Default Admin account
-- Password: Admin@1234  (MUST BE CHANGED AFTER FIRST LOGIN)
INSERT INTO `admins` (`username`, `email`, `password_hash`, `is_active`) VALUES (
    'admin',
    'admin@councilportal.in',
    -- Run: php -r "echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12]);"
    -- and replace the hash below before importing
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1
) ON DUPLICATE KEY UPDATE `username` = `username`;

-- NOTE: The hash above is a placeholder. Run the following PHP snippet
-- to generate a real hash and UPDATE the admins table:
--   php -r "echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12]);"
