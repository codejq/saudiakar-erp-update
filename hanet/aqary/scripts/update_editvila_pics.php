<?php
/**
 * Update pic/ references in editvila.hnt
 * Handles complex multi-line onclick JavaScript attributes
 */

echo "=== Updating editvila.hnt pic/ references ===\n\n";

$file = __DIR__ . '/../admin/include/vila/editvila.hnt';

if (!file_exists($file)) {
    die("ERROR: File not found: {$file}\n");
}

$content = file_get_contents($file);
$original = $content;

// Pattern 1: file_exists("pic/
$content = preg_replace(
    '/file_exists\("pic\//',
    'file_exists(upload_path(\'properties.images\', "',
    $content,
    -1,
    $count1
);
echo "file_exists(\"pic/...\") → upload_path(): {$count1} replacements\n";

// Pattern 2: href=pic/ (for display links)
$content = preg_replace(
    '/href=pic\//',
    'href=\'.upload_url(\'properties.images\', \'',
    $content,
    -1,
    $count2
);
echo "href=pic/... → upload_url(): {$count2} replacements\n";

// Pattern 3: sfile=vila/pic/ (delete button paths)
$content = preg_replace(
    '/sfile=vila\/pic\//',
    'sfile=\'.upload_path(\'properties.images\', \'',
    $content,
    -1,
    $count3
);
echo "sfile=vila/pic/... → upload_path(): {$count3} replacements\n";

// Fix the closing patterns - need to add closing quote and concatenation
// This handles the cases where we added opening quotes
$content = preg_replace(
    '/upload_url\(\'properties\.images\', \'([^\']+)\'\.(target|onclick)/i',
    'upload_url(\'properties.images\', \'$1\').$2',
    $content,
    -1,
    $count4
);
echo "Fixed closing quotes for upload_url(): {$count4} replacements\n";

$content = preg_replace(
    '/upload_path\(\'properties\.images\', "([^"]+)"\)\);/i',
    'upload_path(\'properties.images\', "$1"));',
    $content,
    -1,
    $count5
);
echo "Fixed closing for upload_path(): {$count5} replacements\n";

if ($content === $original) {
    echo "\nNo changes made.\n";
    exit(0);
}

// Backup original
$backup = $file . '.bak_' . date('Ymd_His');
copy($file, $backup);
echo "\nBackup created: " . basename($backup) . "\n";

// Write updated content
file_put_contents($file, $content);

$total = $count1 + $count2 + $count3;
echo "\n✓ Updated: editvila.hnt\n";
echo "Total replacements: {$total}\n";
exit(0);
