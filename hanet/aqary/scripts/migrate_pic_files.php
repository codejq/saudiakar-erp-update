<?php
/**
 * Migrate pic/ directories to centralized storage
 */

echo "=== Migrating pic/ files to storage ===\n\n";

// Load storage library
require_once(__DIR__ . '/../lib/storage/helpers.php');

$source_dirs = [
    'admin/include/pic',
    'admin/include/vila/pic',
    'admin/include/emara/pic',
];

$target_dir = upload_path('properties.images');
$total_moved = 0;
$errors = [];

echo "Target directory: {$target_dir}\n\n";

// Ensure target directory exists
if (!ensure_dir($target_dir)) {
    die("ERROR: Could not create target directory: {$target_dir}\n");
}

foreach ($source_dirs as $source) {
    $full_source = __DIR__ . '/../' . $source;

    if (!is_dir($full_source)) {
        echo "⚠ Directory not found: {$source}\n";
        continue;
    }

    echo "Processing: {$source}\n";

    $files = glob($full_source . '/*');
    $moved = 0;

    foreach ($files as $file) {
        if (!is_file($file)) continue;

        $filename = basename($file);
        $target_file = $target_dir . $filename;

        // Check if file already exists in target
        if (file_exists($target_file)) {
            // Compare file sizes to see if they're the same
            if (filesize($file) === filesize($target_file)) {
                echo "  ✓ {$filename} (already exists, same size)\n";
                continue;
            } else {
                // Different file with same name - keep both
                $info = pathinfo($filename);
                $new_name = $info['filename'] . '_' . md5_file($file) . '.' . $info['extension'];
                $target_file = $target_dir . $new_name;
                echo "  → {$filename} → {$new_name} (name conflict)\n";
            }
        }

        // Copy file to new location
        if (copy($file, $target_file)) {
            echo "  ✓ {$filename}\n";
            $moved++;
            $total_moved++;
        } else {
            $errors[] = "Failed to copy: {$filename}";
            echo "  ✗ {$filename} (copy failed)\n";
        }
    }

    echo "  Moved: {$moved} files\n\n";
}

// Summary
echo "=== Summary ===\n";
echo "Total files moved: {$total_moved}\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

echo "\n✅ All files migrated successfully!\n";
echo "\nNext step: Update code references from 'pic/' to use upload_path('properties.images')\n";
exit(0);
