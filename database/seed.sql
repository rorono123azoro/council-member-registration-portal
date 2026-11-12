-- Council Member Registration Portal — Seed Data
-- Run AFTER schema.sql: mysql -u root -p council_portal < database/seed.sql

USE `council_portal`;

-- Council Categories (8 active + 1 inactive for archive/validation testing)
INSERT INTO `categories` (`name`, `code`, `description`, `is_active`) VALUES
('Fellow Member',                    'FM',   'Senior council fellow with 10+ years experience', 1),
('Ordinary Member',                  'OM',   'Standard voting council member', 1),
('Associate Member',                 'AM',   'Early-career or affiliated professional member', 1),
('Institutional Representative',     'IR',   'Nominated representative from an accredited institution', 1),
('Municipal / Civic Delegate',       'MCD',  'Elected or appointed civic body delegate', 1),
('Technical & Advisory Member',      'TAM',  'Subject matter expert serving on advisory panels', 1),
('Life Member',                      'LM',   'Lifetime council membership category', 1),
('Student / Trainee Observer',       'STO',  'Non-voting student or trainee council observer', 1),
('Legacy Honorary (Archived)',       'LHA',  'Retired category kept for historical records', 0)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `is_active` = VALUES(`is_active`);

-- Default Super Admin account
-- Username: admin | Email: admin@councilportal.local
-- Temporary Password: Admin@1234 (MUST BE CHANGED ON FIRST LOGIN)
INSERT INTO `admins` (`name`, `username`, `email`, `password_hash`, `role`, `is_active`, `must_change_password`) VALUES (
    'System Administrator',
    'admin',
    'admin@councilportal.local',
    '$2y$12$jLnYmlV.2q8Di8BaU0UFEurBIPRwst2XXw9EsJrriHs2WsnMVuBBm',
    'super_admin',
    1,
    1
) ON DUPLICATE KEY UPDATE `username` = `username`;
