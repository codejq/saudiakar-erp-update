<?php
/**
 * Phase 1 Verification Script
 * Verifies storage structure and file migration
 */

echo "=== Phase 1 Verification ===\n\n";

$checks = [];
$root = 'Y:/';

// Check storage directories
$required_dirs = [
    'storage',
    'storage/app',
    'storage/app/cache',
    'storage/app/logs',
    'storage/protected',
    'storage/uploads',
    'storage/generated',
    'storage/backups',
];

echo "1. Checking Directory Structure:\n";
foreach ($required_dirs as $dir) {
    $path = $root . $dir;
    $exists = is_dir($path);
    echo ($exists ? '  ✓' : '  ✗') . " {$dir}\n";
    $checks["dir_{$dir}"] = $exists;
}

// Check protection files
echo "\n2. Checking Protection Files:\n";
$protection_files = [
    'storage/.htaccess',
    'storage/protected/.htaccess',
    'storage/protected/secrets.php',
];

foreach ($protection_files as $file) {
    $path = $root . $file;
    $exists = file_exists($path);
    echo ($exists ? '  ✓' : '  ✗') . " {$file}\n";
    $checks["file_{$file}"] = $exists;
}

// Check backups
echo "\n3. Checking Backups:\n";
$backup_dir = $root . 'storage/backups/migration';
if (is_dir($backup_dir)) {
    $backup_dirs = glob($backup_dir . '/*', GLOB_ONLYDIR);
    echo "  ✓ Backup directory exists\n";
    echo "  ℹ " . count($backup_dirs) . " directories backed up\n";
    foreach ($backup_dirs as $dir) {
        $name = basename($dir);
        $count = count(glob($dir . '/*'));
        echo "    - {$name}: {$count} items\n";
    }
    $checks['backups'] = true;
} else {
    echo "  ✗ Backup directory missing\n";
    $checks['backups'] = false;
}

// Check moved files
echo "\n4. Checking Moved Files:\n";
$file_locations = [
    'storage/uploads/communications/email' => 'Email attachments',
    'storage/generated/qr-codes/vouchers' => 'QR code vouchers',
    'storage/uploads/media/slides' => 'Slide images',
    'storage/uploads/properties/blueprints' => 'Blueprints',
];

foreach ($file_locations as $dir => $description) {
    $path = $root . $dir;
    if (is_dir($path)) {
        $count = count(glob($path . '/*'));
        echo "  ✓ {$description}: {$count} files\n";
        $checks["files_{$dir}"] = true;
    } else {
        echo "  ✗ {$description}: directory missing\n";
        $checks["files_{$dir}"] = false;
    }
}

// Check symlinks (Windows)
echo "\n5. Checking Symlinks (Windows):\n";
$symlinks = [
    'qr-codes',
    'slidepic',
    'mokhatatpic',
    'pic',
];

foreach ($symlinks as $link) {
    $path = $root . $link;
    // On Windows, check if it's a junction/symlink
    if (is_dir($path) && is_link($path)) {
        $target = readlink($path);
        echo "  ✓ {$link} -> {$target}\n";
        $checks["symlink_{$link}"] = true;
    } elseif (is_dir($path)) {
        echo "  ℹ {$link} exists but is not a symlink (may have files)\n";
        $checks["symlink_{$link}"] = 'exists';
    } else {
        echo "  ⚠ {$link} does not exist (symlink not created)\n";
        $checks["symlink_{$link}"] = false;
    }
}

// Summary
echo "\n=== Summary ===\n";
$total = count($checks);
$passed = count(array_filter($checks, fn($v) => $v === true));
$warned = count(array_filter($checks, fn($v) => $v === 'exists'));
$failed = count(array_filter($checks, fn($v) => $v === false));

echo "Total checks: {$total}\n";
echo "Passed: {$passed}\n";
echo "Warnings: {$warned}\n";
echo "Failed: {$failed}\n\n";

if ($failed === 0) {
    echo "✅ Phase 1 verification PASSED\n";
    echo "\nNote: Symlinks require Administrator privileges on Windows.\n";
    echo "Run create_symlinks.bat as Administrator if needed.\n";
    exit(0);
} else {
    echo "❌ Phase 1 verification has failures\n";
    echo "Please fix the issues above before proceeding to Phase 2.\n";
    exit(1);
}
