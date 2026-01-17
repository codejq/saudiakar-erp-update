<?php
/**
 * Storage Helper Functions
 * Convenience functions for storage management
 * Location: Y:\lib\storage\helpers.php
 */

// Load required classes
require_once(__DIR__ . '/StorageConfig.php');
require_once(__DIR__ . '/StoragePath.php');
require_once(__DIR__ . '/SecretsManager.php');
require_once(__DIR__ . '/FileValidator.php');

/**
 * Get storage path
 */
function storage_path(string $path = ''): string
{
    return StoragePath::get($path);
}

/**
 * Get upload path for specific category
 */
function upload_path(string $category, string $filename = ''): string
{
    return StoragePath::upload($category, $filename);
}

/**
 * Get QR code path
 */
function qr_path(string $type, string $filename = ''): string
{
    return StoragePath::qrCode($type, $filename);
}

/**
 * Get QR code path (alias for consistency with qr_code_url)
 */
function qr_code_path(string $type, string $filename = ''): string
{
    return qr_path($type, $filename);
}

/**
 * Get cache path
 */
function cache_path(string $category = '', string $filename = ''): string
{
    if ($category) {
        return StoragePath::cache($category, $filename);
    }
    return STORAGE_CACHE . $filename;
}

/**
 * Get log path
 */
function log_path(string $filename = 'application.log'): string
{
    return StoragePath::log($filename);
}

/**
 * Get secret value
 */
function secret(string $key, mixed $default = null): mixed
{
    return SecretsManager::get($key, $default);
}

/**
 * Ensure directory exists
 */
function ensure_dir(string $path, int $permissions = 0755): bool
{
    return StoragePath::ensure($path, $permissions);
}

/**
 * Write to log file
 */
function write_log(string $message, string $level = 'info', string $file = 'application.log'): void
{
    $log_file = log_path($file);
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$level}: {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Validate uploaded file
 */
function validate_upload(array $file, array $allowed_extensions = []): FileValidator
{
    $validator = new FileValidator($file);
    $validator->validate($allowed_extensions);
    return $validator;
}

/**
 * Get web URL for upload file
 * Converts server path to web-accessible URL
 */
function upload_url(string $category, string $filename = ''): string
{
    // Get full filesystem path
    $path = upload_path($category, $filename);

    // Get document root (assumes script is in subdirectory of webroot)
    // For Y:\admin\... -> Y:\ is the webroot
    $doc_root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

    // Normalize paths to handle drive mappings
    $normalized_path = str_replace('\\', '/', realpath($path) ?: $path);
    $normalized_root = str_replace('\\', '/', realpath($doc_root) ?: $doc_root);

    // Remove document root from path
    if (stripos($normalized_path, $normalized_root) === 0) {
        $relative_path = substr($normalized_path, strlen($normalized_root));
    } else {
        // Fallback: just get the storage portion
        $storage_pos = stripos($normalized_path, '/storage/');
        if ($storage_pos !== false) {
            $relative_path = substr($normalized_path, $storage_pos + 1);
        } else {
            $relative_path = $normalized_path;
        }
    }

    // Add application base path if defined
    $base_path = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';

    // Return path relative to webroot
    return $base_path . '/' . ltrim($relative_path, '/');
}

/**
 * Get web URL for QR code file
 * Converts server path to web-accessible URL
 */
function qr_code_url(string $type, string $filename = ''): string
{
    // Get full filesystem path
    $path = qr_path($type, $filename);

    // Get document root
    $doc_root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

    // Normalize paths to handle drive mappings
    $normalized_path = str_replace('\\', '/', realpath($path) ?: $path);
    $normalized_root = str_replace('\\', '/', realpath($doc_root) ?: $doc_root);

    // Remove document root from path
    if (stripos($normalized_path, $normalized_root) === 0) {
        $relative_path = substr($normalized_path, strlen($normalized_root));
    } else {
        // Fallback: just get the storage portion
        $storage_pos = stripos($normalized_path, '/storage/');
        if ($storage_pos !== false) {
            $relative_path = substr($normalized_path, $storage_pos + 1);
        } else {
            $relative_path = $normalized_path;
        }
    }

    // Add application base path if defined
    $base_path = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';

    // Return path relative to webroot
    return $base_path . '/' . ltrim($relative_path, '/');
}
