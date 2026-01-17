<?php
/**
 * Update all pic/ references to use new storage paths
 * This script will update code references systematically
 */

echo "=== Updating pic/ references to storage paths ===\n\n";

$files_to_update = [
    'admin/include/editall.hnt',
    'admin/include/editallx.hnt',
    'admin/include/vieward.hnt',
    'admin/include/vila/addvila.hnt',
    'admin/include/vila/addvilax.hnt',
    'admin/include/vila/editvila.hnt',
    'admin/include/vila/editvilax.hnt',
    'admin/include/vila/updatvila_report.hnt',
    'admin/include/vila/updatvila_reportx.hnt',
    'admin/include/vila/updatvilax.hnt',
    'admin/include/vila/viewvila.hnt',
    'admin/include/emara/addemara.hnt',
    'admin/include/emara/addemarax.hnt',
    'admin/include/emara/editemara.hnt',
    'admin/include/emara/editemarax.hnt',
    'admin/include/emara/updatemara.hnt',
    'admin/include/emara/updatemara_report.hnt',
    'admin/include/emara/viewemara.hnt',
    'admin/include/edit.hnt',
    'admin/include/updateard.hnt',
    'admin/include/original_updateard.hnt',
    'admin/include/updateard_report.hnt',
    'admin/include/originalupdateard_report.hnt',
];

$patterns = [
    // File operations - upload/copy/move
    [
        'from' => '/move_uploaded_file\s*\([^,]+,\s*"pic\/([^"]+)"\s*\)/',
        'to' => 'move_uploaded_file($1, upload_path(\'properties.images\', "$2"))',
        'desc' => 'move_uploaded_file to pic/'
    ],
    [
        'from' => '/copy\s*\([^,]+,\s*"pic\/([^"]+)"\s*\)/',
        'to' => 'copy($1, upload_path(\'properties.images\', "$2"))',
        'desc' => 'copy to pic/'
    ],

    // file_exists checks
    [
        'from' => '/file_exists\s*\(\s*"pic\/([^"]+)"\s*\)/',
        'to' => 'file_exists(upload_path(\'properties.images\', "$1"))',
        'desc' => 'file_exists("pic/...")'
    ],

    // Variable assignments
    [
        'from' => '/\$\w+\s*=\s*"pic\/([^"]+)"/',
        'to' => '$0 = upload_path(\'properties.images\', "$1")',
        'desc' => 'Variable assignment to pic/'
    ],

    // mkdir pic
    [
        'from' => '/@?mkdir\s*\(\s*"pic"\s*\)\s*;?/',
        'to' => '// pic directory now managed by storage system',
        'desc' => 'Remove mkdir("pic")'
    ],
];

$total_replacements = 0;
$updated_files = 0;

foreach ($files_to_update as $file) {
    $full_path = __DIR__ . '/../' . $file;

    if (!file_exists($full_path)) {
        echo "⚠ File not found: {$file}\n";
        continue;
    }

    $content = file_get_contents($full_path);
    $original_content = $content;
    $file_replacements = 0;

    // Apply each pattern
    foreach ($patterns as $pattern) {
        $count = 0;
        $content = preg_replace($pattern['from'], $pattern['to'], $content, -1, $count);
        if ($count > 0) {
            $file_replacements += $count;
            echo "  {$pattern['desc']}: {$count} replacements\n";
        }
    }

    // Check if content changed
    if ($content !== $original_content) {
        // Backup original
        $backup_file = $full_path . '.bak_' . date('Ymd_His');
        copy($full_path, $backup_file);

        // Write updated content
        file_put_contents($full_path, $content);

        echo "✓ Updated: {$file} ({$file_replacements} changes)\n";
        echo "  Backup: " . basename($backup_file) . "\n\n";

        $updated_files++;
        $total_replacements += $file_replacements;
    } else {
        echo "  No changes: {$file}\n";
    }
}

echo "\n=== Summary ===\n";
echo "Files updated: {$updated_files}\n";
echo "Total replacements: {$total_replacements}\n";

echo "\n✅ Automatic updates complete!\n";
echo "\nNote: Some complex references may need manual review.\n";
echo "Check files with 'pic/' in JavaScript/HTML contexts.\n";
