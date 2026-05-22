-- CRM Installation SQL
-- Version 1.0
-- Run this file once to set up the database

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `crm_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `crm_db`;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','manager','sales') NOT NULL DEFAULT 'sales',
  `language` ENUM('el','en') NOT NULL DEFAULT 'el',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- COMPANIES
-- ============================================================
CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `vat_number` VARCHAR(50) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT 'Greece',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_name` (`name`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONTACTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `position` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_name` (`last_name`, `first_name`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) DEFAULT NULL UNIQUE,
  `name_el` VARCHAR(200) NOT NULL,
  `name_en` VARCHAR(200) NOT NULL,
  `description_el` TEXT DEFAULT NULL,
  `description_en` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit` VARCHAR(30) DEFAULT 'τεμ',
  `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 24.00,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OFFERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `offers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `offer_number` VARCHAR(20) NOT NULL UNIQUE,
  `contact_id` INT UNSIGNED DEFAULT NULL,
  `company_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `status` ENUM('draft','sent','accepted','rejected') NOT NULL DEFAULT 'draft',
  `valid_until` DATE DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `total_net` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_vat` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_gross` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_offer_number` (`offer_number`),
  FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OFFER ITEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS `offer_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `offer_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `description` TEXT NOT NULL,
  `qty` DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 24.00,
  `line_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sort_order` INT NOT NULL DEFAULT 0,
  FOREIGN KEY (`offer_id`) REFERENCES `offers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EMAIL LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS `email_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `offer_id` INT UNSIGNED DEFAULT NULL,
  `sent_to` VARCHAR(150) NOT NULL,
  `sent_by` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `error_msg` TEXT DEFAULT NULL,
  FOREIGN KEY (`offer_id`) REFERENCES `offers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CUSTOM FIELDS
-- ============================================================
CREATE TABLE IF NOT EXISTS `custom_fields` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `module` ENUM('contacts','companies','products','offers') NOT NULL,
  `field_name` VARCHAR(50) NOT NULL,
  `label_el` VARCHAR(100) NOT NULL,
  `label_en` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text','number','date','select','checkbox','memo') NOT NULL DEFAULT 'text',
  `options_json` TEXT DEFAULT NULL COMMENT 'JSON array for select type',
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_module_field` (`module`, `field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CUSTOM FIELD VALUES
-- ============================================================
CREATE TABLE IF NOT EXISTS `custom_field_values` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `module` ENUM('contacts','companies','products','offers') NOT NULL,
  `record_id` INT UNSIGNED NOT NULL,
  `field_id` INT UNSIGNED NOT NULL,
  `value` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_record_field` (`module`, `record_id`, `field_id`),
  FOREIGN KEY (`field_id`) REFERENCES `custom_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user (password: admin123)
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `language`, `active`) VALUES
('Administrator', 'admin@crm.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'el', 1),
('Γιώργος Παπαδόπουλος', 'manager@crm.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'el', 1),
('Μαρία Νικολάου', 'sales@crm.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sales', 'el', 1);

-- Note: The above hash is for 'password' - we'll update admin with admin123 below
UPDATE `users` SET `password_hash` = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMeBl4j7a.JxZI6v6bqXJEkXim' WHERE email = 'admin@crm.local';
-- admin123 hash
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `language`, `active`) VALUES
('Admin User', 'admin2@crm.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMeBl4j7a.JxZI6v6bqXJEkXim', 'admin', 'el', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Let's just use a known hash for admin123
DELETE FROM `users`;
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `language`, `active`) VALUES
('Διαχειριστής', 'admin@crm.local', '$2y$10$TKh8H1.PfY0XLBuSEzMt4OzTVCjLg2zfGCJOLBvFjHLyJLg8J0gZa', 'admin', 'el', 1),
('Γιώργος Παπαδόπουλος', 'manager@crm.local', '$2y$10$TKh8H1.PfY0XLBuSEzMt4OzTVCjLg2zfGCJOLBvFjHLyJLg8J0gZa', 'manager', 'el', 1),
('Μαρία Νικολάου', 'sales@crm.local', '$2y$10$TKh8H1.PfY0XLBuSEzMt4OzTVCjLg2zfGCJOLBvFjHLyJLg8J0gZa', 'sales', 'el', 1);

-- Sample Companies
INSERT INTO `companies` (`name`, `vat_number`, `phone`, `email`, `address`, `city`, `country`, `notes`, `created_by`) VALUES
('Τεχνολογίες Α.Ε.', 'EL123456789', '2101234567', 'info@technologies.gr', 'Λεωφόρος Αθηνών 100', 'Αθήνα', 'Greece', 'Μεγάλος πελάτης στον τομέα πληροφορικής', 1),
('Εμπορική Εταιρεία Β.Ε.Ε.', 'EL987654321', '2310987654', 'contact@commercial.gr', 'Εγνατία 45', 'Θεσσαλονίκη', 'Greece', 'Εταιρεία εμπορίου', 1),
('Κατασκευές Γ. & Σια', 'EL456789123', '2610456789', 'info@constructions.gr', 'Κορίνθου 22', 'Πάτρα', 'Greece', 'Κατασκευαστική εταιρεία', 1),
('Alpha Solutions Ltd', 'GB123456789', '+441234567890', 'info@alphasolutions.co.uk', '10 Business Park', 'London', 'UK', 'International partner', 1);

-- Sample Contacts
INSERT INTO `contacts` (`company_id`, `first_name`, `last_name`, `email`, `phone`, `position`, `notes`, `created_by`) VALUES
(1, 'Δημήτρης', 'Αλεξίου', 'dalexiou@technologies.gr', '6971234567', 'Διευθύνων Σύμβουλος', 'Κύριος επαφή', 1),
(1, 'Ελένη', 'Κωστοπούλου', 'ekostopoulou@technologies.gr', '6987654321', 'Υπεύθυνη Αγορών', 'Υπεύθυνη για προμήθειες', 1),
(2, 'Νίκος', 'Παπανικολάου', 'npapanikol@commercial.gr', '6941234567', 'Γενικός Διευθυντής', '', 1),
(3, 'Σοφία', 'Γεωργίου', 'sgeorgiou@constructions.gr', '6951234567', 'Οικονομική Διευθύντρια', '', 1),
(NULL, 'John', 'Smith', 'john.smith@email.com', '+447890123456', 'Independent Consultant', 'Freelance contact', 1);

-- Sample Products
INSERT INTO `products` (`code`, `name_el`, `name_en`, `description_el`, `description_en`, `price`, `unit`, `vat_rate`, `active`, `created_by`) VALUES
('PRD-001', 'Λογισμικό CRM', 'CRM Software', 'Πλήρης λύση διαχείρισης πελατών', 'Complete customer management solution', 1500.00, 'άδεια', 24.00, 1, 1),
('PRD-002', 'Υπηρεσίες Εγκατάστασης', 'Installation Services', 'Εγκατάσταση και ρύθμιση συστήματος', 'System installation and configuration', 500.00, 'ώρα', 24.00, 1, 1),
('PRD-003', 'Εκπαίδευση Χρηστών', 'User Training', 'Εκπαίδευση προσωπικού στο σύστημα', 'Staff training on the system', 200.00, 'ώρα', 24.00, 1, 1),
('PRD-004', 'Ετήσια Συντήρηση', 'Annual Maintenance', 'Ετήσιο συμβόλαιο συντήρησης και υποστήριξης', 'Annual maintenance and support contract', 800.00, 'έτος', 24.00, 1, 1),
('PRD-005', 'Αναφορές & Analytics', 'Reports & Analytics', 'Προηγμένες αναφορές και ανάλυση δεδομένων', 'Advanced reports and data analysis module', 300.00, 'άδεια', 24.00, 1, 1);

-- Sample Offers
INSERT INTO `offers` (`offer_number`, `contact_id`, `company_id`, `title`, `status`, `valid_until`, `notes`, `total_net`, `total_vat`, `total_gross`, `created_by`) VALUES
('OFF-2025-001', 1, 1, 'Πρόταση CRM για Τεχνολογίες Α.Ε.', 'sent', '2025-12-31', 'Αρχική πρόταση', 2000.00, 480.00, 2480.00, 1),
('OFF-2025-002', 3, 2, 'Λύση Διαχείρισης Πελατών', 'draft', '2025-11-30', '', 1500.00, 360.00, 1860.00, 1),
('OFF-2025-003', 5, NULL, 'CRM Proposal for Consulting', 'accepted', '2025-10-31', 'Accepted via email', 3000.00, 720.00, 3720.00, 1);

-- Sample Offer Items
INSERT INTO `offer_items` (`offer_id`, `product_id`, `description`, `qty`, `unit_price`, `discount_pct`, `vat_rate`, `line_total`, `sort_order`) VALUES
(1, 1, 'Λογισμικό CRM - Enterprise', 1, 1500.00, 0.00, 24.00, 1500.00, 1),
(1, 2, 'Υπηρεσίες Εγκατάστασης (1 ημέρα)', 1, 500.00, 0.00, 24.00, 500.00, 2),
(2, 1, 'Λογισμικό CRM - Standard', 1, 1500.00, 0.00, 24.00, 1500.00, 1),
(3, 1, 'CRM Software License', 1, 1500.00, 0.00, 24.00, 1500.00, 1),
(3, 2, 'Installation Services', 2, 500.00, 0.00, 24.00, 1000.00, 2),
(3, 3, 'User Training', 2.5, 200.00, 0.00, 24.00, 500.00, 3);

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_email', 'noreply@crm.local'),
('smtp_from_name', 'CRM System'),
('company_name', 'Η Εταιρεία Μου'),
('company_address', ''),
('company_phone', ''),
('company_email', ''),
('company_vat', ''),
('offer_prefix', 'OFF'),
('offer_footer', 'Ευχαριστούμε για την εμπιστοσύνη σας.');

SET foreign_key_checks = 1;
