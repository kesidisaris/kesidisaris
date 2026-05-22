-- CRM Database Schema + Seed Data
-- Run this file once to initialize the database
-- Default admin: admin@crm.local / admin123

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS crm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_db;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','sales') NOT NULL DEFAULT 'sales',
    language ENUM('el','en') NOT NULL DEFAULT 'el',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- COMPANIES
-- ============================================================
CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    vat_number VARCHAR(30) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT 'Greece',
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONTACTS
-- ============================================================
CREATE TABLE IF NOT EXISTS contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED DEFAULT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) DEFAULT NULL,
    name_el VARCHAR(200) NOT NULL,
    name_en VARCHAR(200) NOT NULL,
    description_el TEXT DEFAULT NULL,
    description_en TEXT DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(30) DEFAULT 'τεμ.',
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 24.00,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OFFERS
-- ============================================================
CREATE TABLE IF NOT EXISTS offers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offer_number VARCHAR(20) NOT NULL UNIQUE,
    contact_id INT UNSIGNED DEFAULT NULL,
    company_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    status ENUM('draft','sent','accepted','rejected') NOT NULL DEFAULT 'draft',
    valid_until DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    total_net DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_vat DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_gross DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OFFER ITEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS offer_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED DEFAULT NULL,
    description VARCHAR(500) NOT NULL,
    qty DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 24.00,
    line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EMAIL LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS email_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offer_id INT UNSIGNED DEFAULT NULL,
    sent_to VARCHAR(255) NOT NULL,
    sent_by INT UNSIGNED DEFAULT NULL,
    subject VARCHAR(500) DEFAULT NULL,
    body TEXT DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) NOT NULL DEFAULT 0,
    error_msg TEXT DEFAULT NULL,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE SET NULL,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CUSTOM FIELDS
-- ============================================================
CREATE TABLE IF NOT EXISTS custom_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module ENUM('contacts','companies','products','offers') NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    label_el VARCHAR(100) NOT NULL,
    label_en VARCHAR(100) NOT NULL,
    field_type ENUM('text','number','date','select','checkbox','memo') NOT NULL DEFAULT 'text',
    options_json TEXT DEFAULT NULL COMMENT 'JSON array for select type',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_module_field (module, field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_field_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module ENUM('contacts','companies','products','offers') NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_record_field (module, record_id, field_id),
    FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user (password: admin123)
-- Hash generated with: password_hash('admin123', PASSWORD_BCRYPT, ['cost'=>12])
INSERT INTO users (name, email, password_hash, role, language, active) VALUES
('Administrator', 'admin@crm.local', '$2y$12$R1/3GjfvUSFqvs6BVfYcK.1LeI5sxt9S40x789rbmo1ceurtK7F1.', 'admin', 'el', 1),
('Γιώργος Παπαδόπουλος', 'manager@crm.local', '$2y$12$R1/3GjfvUSFqvs6BVfYcK.1LeI5sxt9S40x789rbmo1ceurtK7F1.', 'manager', 'el', 1),
('Μαρία Κωνσταντίνου', 'sales@crm.local', '$2y$12$R1/3GjfvUSFqvs6BVfYcK.1LeI5sxt9S40x789rbmo1ceurtK7F1.', 'sales', 'el', 1);

-- Sample companies
INSERT INTO companies (name, vat_number, phone, email, address, city, country, notes, created_by) VALUES
('Alpha Τεχνολογίες Α.Ε.', '123456789', '210-1234567', 'info@alpha-tech.gr', 'Λεωφόρος Κηφισίας 10', 'Αθήνα', 'Greece', 'Μεγάλος πελάτης τεχνολογίας', 1),
('Beta Solutions Ε.Π.Ε.', '987654321', '2310-987654', 'contact@betasol.gr', 'Εγνατία 150', 'Θεσσαλονίκη', 'Greece', 'Εταιρεία λογισμικού', 1),
('Gamma Imports Ltd', 'EL112233445', '2610-111222', 'info@gammaimports.gr', 'Πατρών-Αθηνών 55', 'Πάτρα', 'Greece', 'Εισαγωγές εξαγωγές', 1),
('Delta Services', '556677889', '2410-333444', 'hello@deltaservices.gr', 'Παπαναστασίου 30', 'Λάρισα', 'Greece', NULL, 1);

-- Sample contacts
INSERT INTO contacts (company_id, first_name, last_name, email, phone, position, notes, created_by) VALUES
(1, 'Νίκος', 'Παπαδάκης', 'nikos@alpha-tech.gr', '6944-123456', 'Διευθύνων Σύμβουλος', 'Κύρια επαφή', 1),
(1, 'Ελένη', 'Σταυρίδου', 'eleni@alpha-tech.gr', '6944-234567', 'Οικονομική Διευθύντρια', NULL, 1),
(2, 'Κώστας', 'Αναγνώστου', 'kostas@betasol.gr', '6955-345678', 'Τεχνικός Διευθυντής', NULL, 1),
(3, 'Σοφία', 'Λαμπρίδου', 'sofia@gammaimports.gr', '6966-456789', 'Υπεύθυνη Πωλήσεων', NULL, 1),
(NULL, 'Δημήτρης', 'Χατζηγεωργίου', 'dimitris@personal.gr', '6977-567890', NULL, 'Ανεξάρτητος σύμβουλος', 1);

-- Sample products
INSERT INTO products (code, name_el, name_en, description_el, description_en, price, unit, vat_rate, active, created_by) VALUES
('SOFT-001', 'Λογισμικό CRM - Άδεια χρήσης', 'CRM Software - License', 'Ετήσια άδεια χρήσης λογισμικού CRM', 'Annual CRM software license', 1200.00, 'άδεια', 24.00, 1, 1),
('SOFT-002', 'Υποστήριξη & Συντήρηση', 'Support & Maintenance', 'Ετήσιο πρόγραμμα υποστήριξης και συντήρησης', 'Annual support and maintenance plan', 300.00, 'έτος', 24.00, 1, 1),
('TRAIN-001', 'Εκπαίδευση Χρηστών', 'User Training', 'Εκπαίδευση ομάδας έως 10 άτομα, 2 ημέρες', 'Team training up to 10 people, 2 days', 800.00, 'ημέρα', 24.00, 1, 1),
('IMPL-001', 'Υπηρεσίες Εγκατάστασης', 'Implementation Services', 'Εγκατάσταση και διαμόρφωση συστήματος', 'System installation and configuration', 1500.00, 'project', 24.00, 1, 1),
('CONS-001', 'Ώρες Συμβουλευτικής', 'Consulting Hours', 'Ώρες συμβουλευτικής υπηρεσίας', 'Consulting service hours', 90.00, 'ώρα', 24.00, 1, 1);

-- Sample offers
INSERT INTO offers (offer_number, contact_id, company_id, title, status, valid_until, notes, total_net, total_vat, total_gross, created_by) VALUES
('OFF-2025-001', 1, 1, 'Προσφορά Λογισμικού CRM', 'sent', '2025-02-28', 'Αρχική προσφορά για CRM', 2000.00, 480.00, 2480.00, 1),
('OFF-2025-002', 3, 2, 'Πακέτο Εκπαίδευσης', 'draft', '2025-03-15', NULL, 1600.00, 384.00, 1984.00, 2),
('OFF-2025-003', 4, 3, 'Ολοκληρωμένο Πακέτο', 'accepted', '2025-01-31', 'Αποδεκτό!', 3800.00, 912.00, 4712.00, 1);

-- Offer items
INSERT INTO offer_items (offer_id, product_id, description, qty, unit_price, discount_pct, vat_rate, line_total, sort_order) VALUES
(1, 1, 'Λογισμικό CRM - Άδεια χρήσης', 1.000, 1200.00, 0.00, 24.00, 1200.00, 1),
(1, 2, 'Υποστήριξη & Συντήρηση', 1.000, 300.00, 0.00, 24.00, 300.00, 2),
(1, 3, 'Εκπαίδευση Χρηστών', 1.000, 800.00, 37.50, 24.00, 500.00, 3),
(2, 3, 'Εκπαίδευση Χρηστών', 2.000, 800.00, 0.00, 24.00, 1600.00, 1),
(3, 1, 'Λογισμικό CRM - Άδεια χρήσης', 1.000, 1200.00, 0.00, 24.00, 1200.00, 1),
(3, 4, 'Υπηρεσίες Εγκατάστασης', 1.000, 1500.00, 0.00, 24.00, 1500.00, 2),
(3, 5, 'Ώρες Συμβουλευτικής', 12.000, 90.00, 0.00, 24.00, 1080.00, 3);

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_email', 'crm@mycompany.gr'),
('smtp_from_name', 'My Company CRM'),
('company_name', 'My Company'),
('company_phone', '210-0000000'),
('company_email', 'info@mycompany.gr'),
('company_address', 'Οδός Παραδείγματος 1, Αθήνα'),
('currency_symbol', '€'),
('items_per_page', '20');
