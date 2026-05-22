<?php
// CRM Configuration File
// Edit these settings to match your environment

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'crm_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'CRM System');
define('APP_URL', 'http://localhost/crm'); // No trailing slash
define('APP_VERSION', '1.0.0');

// Default Language
define('DEFAULT_LANG', 'el');

// Session configuration
define('SESSION_NAME', 'crm_session');
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

// Timezone
date_default_timezone_set('Europe/Athens');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
