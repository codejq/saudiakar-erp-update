<?php
/**
 * File Management System - Configuration
 */

// Include centralized storage helpers
require_once(__DIR__ . '/../../lib/storage/helpers.php');

// System Configuration using centralized storage
// NOTE: Database connection is provided by connectdb.hnt (not defined here)
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
define('UPLOAD_DIR', upload_path('framework.file-management.uploads'));
define('LOGS_DIR', upload_path('framework.file-management.logs'));
define('CACHE_DIR', upload_path('framework.file-management.cache'));

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('SESSION_SECURE', true); // HTTPS only
define('SESSION_HTTPONLY', true); // No JavaScript access

// Application Configuration
define('APP_NAME', 'File Manager');
define('APP_VERSION', '2.0');
define('APP_TIMEZONE', 'UTC');
if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', '/aqary'); // Web base path for URL generation
}

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// NOTE: Session and database connection are handled by legacy system files:
// - Session is started in reqloginajax.hnt (or header.hnt)
// - Database connection ($link) is provided by connectdb.hnt
// This config file only defines storage paths and application constants
?>
