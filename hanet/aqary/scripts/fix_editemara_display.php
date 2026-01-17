<?php
/**
 * Fix editemara.hnt display section
 * Properly update all file type references with upload_path() and upload_url()
 */

$file = __DIR__ . '/../admin/include/emara/editemara.hnt';
$content = file_get_contents($file);

// Backup
copy($file, $file . '.bak_' . date('YmdHis'));

// Pattern to match and fix each file type check
// Current broken pattern: if($file_path = upload_path('properties.images', $emaraid."_".$i."_.EXT")){echo"<tr><td><a href=pic/".$emaraid."_".$i."_.EXT ...
// Target pattern: $file_path = upload_path(...); $file_url = upload_url(...); if(file_exists($file_path)){echo"<tr><td><a href={$file_url} ...

$extensions = ['jpg', 'gif', 'png', 'bmp', 'pdf', 'tif', 'doc', 'ppt', 'pps', 'avi', 'mepg', 'wmv', 'swf', 'xls', 'mdi'];

foreach ($extensions as $ext) {
    // Pattern 1: Fix the if statement assignment issue and add upload_url
    $pattern = '/if\(\$file_path = upload_path\(\'properties\.images\', \$emaraid\."_"\.\$i\."_\.' . $ext . '"\)\)\{echo"<tr><td([^>]*)><a href=pic\/"\.\$emaraid\."_"\.\$i\."_\.' . $ext . '"/';

    $replacement = '// ' . strtoupper($ext) . "\n" .
                   '$file_path = upload_path(\'properties.images\', $emaraid."_".$i."_.' . $ext . '");' . "\n" .
                   '$file_url = upload_url(\'properties.images\', $emaraid."_".$i."_.' . $ext . '");' . "\n" .
                   'if(file_exists($file_path)){echo"<tr><td$1><a href={$file_url}';

    $content = preg_replace($pattern, $replacement, $content);
}

// Fix sfile paths in JavaScript (emara/pic/ → direct path)
$content = preg_replace(
    '/sfile=emara\/pic\/"\.\$emaraid/',
    'sfile=\'.\$file_path.\'',
    $content
);

// Fix "go" blueprint references if any
$content = preg_replace(
    '/if\(\$file_path = upload_path\(\'properties\.images\', "go"\.\$emaraid/',
    '$file_path = upload_path(\'properties.images\', "go".$emaraid',
    $content
);

file_put_contents($file, $content);

echo "✓ Fixed editemara.hnt display section\n";
echo "Backup: " . basename($file . '.bak_' . date('YmdHis')) . "\n";
