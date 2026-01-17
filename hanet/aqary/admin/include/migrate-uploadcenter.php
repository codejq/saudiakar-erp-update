<?php
/**
 * Upload Center Storage Migration Script
 * Migrates files from old location (admin/include/data/) to centralized storage (storage/uploads/uploadcenter/)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include storage helpers
require_once(__DIR__ . '/../../lib/storage/helpers.php');

echo "========================================\n";
echo "Upload Center Storage Migration\n";
echo "========================================\n\n";

// Define old paths
$old_upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
$old_thumb_dir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'thumbnail' . DIRECTORY_SEPARATOR;

// Define new paths using centralized storage
$new_upload_dir = upload_path('uploadcenter') . DIRECTORY_SEPARATOR;
$new_thumb_dir = upload_path('uploadcenter.thumbnails') . DIRECTORY_SEPARATOR;

// Ensure new directories exist
ensure_dir($new_upload_dir);
ensure_dir($new_thumb_dir);

echo "Source Directories:\n";
echo "  Uploads: {$old_upload_dir}\n";
echo "  Thumbnails: {$old_thumb_dir}\n\n";

echo "Destination Directories:\n";
echo "  Uploads: {$new_upload_dir}\n";
echo "  Thumbnails: {$new_thumb_dir}\n\n";

// Statistics
$stats = [
    'uploads_copied' => 0,
    'uploads_skipped' => 0,
    'uploads_failed' => 0,
    'thumbs_copied' => 0,
    'thumbs_skipped' => 0,
    'thumbs_failed' => 0
];

// Migrate uploads
echo "Migrating upload files...\n";
if (is_dir($old_upload_dir)) {
    $files = scandir($old_upload_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || is_dir($old_upload_dir . $file)) {
            continue;
        }

        $old_path = $old_upload_dir . $file;
        $new_path = $new_upload_dir . $file;

        if (file_exists($new_path)) {
            echo "  [SKIP] {$file} (already exists)\n";
            $stats['uploads_skipped']++;
            continue;
        }

        if (copy($old_path, $new_path)) {
            echo "  [OK] {$file}\n";
            $stats['uploads_copied']++;
        } else {
            echo "  [FAIL] {$file}\n";
            $stats['uploads_failed']++;
        }
    }
} else {
    echo "  Old upload directory not found: {$old_upload_dir}\n";
}

echo "\n";

// Migrate thumbnails
echo "Migrating thumbnail files...\n";
if (is_dir($old_thumb_dir)) {
    $files = scandir($old_thumb_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || is_dir($old_thumb_dir . $file)) {
            continue;
        }

        $old_path = $old_thumb_dir . $file;
        $new_path = $new_thumb_dir . $file;

        if (file_exists($new_path)) {
            echo "  [SKIP] {$file} (already exists)\n";
            $stats['thumbs_skipped']++;
            continue;
        }

        if (copy($old_path, $new_path)) {
            echo "  [OK] {$file}\n";
            $stats['thumbs_copied']++;
        } else {
            echo "  [FAIL] {$file}\n";
            $stats['thumbs_failed']++;
        }
    }
} else {
    echo "  Old thumbnail directory not found: {$old_thumb_dir}\n";
}

echo "\n";
echo "========================================\n";
echo "Migration Summary\n";
echo "========================================\n";
echo "Uploads:\n";
echo "  Copied: {$stats['uploads_copied']}\n";
echo "  Skipped: {$stats['uploads_skipped']}\n";
echo "  Failed: {$stats['uploads_failed']}\n";
echo "\n";
echo "Thumbnails:\n";
echo "  Copied: {$stats['thumbs_copied']}\n";
echo "  Skipped: {$stats['thumbs_skipped']}\n";
echo "  Failed: {$stats['thumbs_failed']}\n";
echo "\n";

$total_success = $stats['uploads_copied'] + $stats['thumbs_copied'];
$total_failed = $stats['uploads_failed'] + $stats['thumbs_failed'];

if ($total_failed > 0) {
    echo "Migration completed with {$total_failed} errors.\n";
    exit(1);
} else {
    echo "Migration completed successfully! ({$total_success} files copied)\n";
    echo "\nNOTE: The old files are still in place. After verifying the migration,\n";
    echo "you can manually delete the old directories:\n";
    echo "  - {$old_upload_dir}\n";
    echo "  - {$old_thumb_dir}\n";
    exit(0);
}
?>
