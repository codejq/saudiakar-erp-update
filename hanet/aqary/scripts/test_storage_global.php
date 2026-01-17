<?php
/**
 * Storage Library Global Test
 * Tests storage library directly (without database dependency)
 */

echo "=== Storage Library Global Availability Test ===\n\n";

// Test 1: Simulate what connectdb.hnt does
echo "1. Loading storage library (same as connectdb.hnt):\n";
if (!defined('STORAGE_ROOT') && file_exists(__DIR__ . '/../lib/storage/helpers.php')) {
    require_once(__DIR__ . '/../lib/storage/helpers.php');
    echo "  ✓ Storage library loaded\n";
} else if (defined('STORAGE_ROOT')) {
    echo "  ✓ Storage library already loaded\n";
} else {
    echo "  ✗ Storage library file not found\n";
    exit(1);
}

// Test 2: Verify constants
echo "\n2. Storage Constants:\n";
$constants = [
    'STORAGE_ROOT' => 'Root storage directory',
    'STORAGE_UPLOADS' => 'Uploads directory',
    'STORAGE_PROTECTED' => 'Protected secrets directory',
    'STORAGE_CACHE' => 'Cache directory',
    'STORAGE_LOGS' => 'Logs directory',
    'STORAGE_QR_CODES' => 'QR codes directory',
];

$passed = 0;
$failed = 0;

foreach ($constants as $const => $desc) {
    if (defined($const)) {
        echo "  ✓ {$const}\n";
        echo "    → " . constant($const) . "\n";
        $passed++;
    } else {
        echo "  ✗ {$const} NOT DEFINED\n";
        $failed++;
    }
}

// Test 3: Verify helper functions
echo "\n3. Helper Functions:\n";
$functions = [
    'storage_path' => 'Get storage path',
    'upload_path' => 'Get upload path for category',
    'qr_path' => 'Get QR code path',
    'cache_path' => 'Get cache path',
    'log_path' => 'Get log path',
    'secret' => 'Get secret value',
    'ensure_dir' => 'Ensure directory exists',
    'write_log' => 'Write to log file',
];

foreach ($functions as $func => $desc) {
    if (function_exists($func)) {
        echo "  ✓ {$func}() - {$desc}\n";
        $passed++;
    } else {
        echo "  ✗ {$func}() NOT FOUND\n";
        $failed++;
    }
}

// Test 4: Verify classes
echo "\n4. Storage Classes:\n";
$classes = [
    'StoragePath' => 'Path management class',
    'SecretsManager' => 'Secrets management',
    'FileValidator' => 'File upload validator',
];

foreach ($classes as $class => $desc) {
    if (class_exists($class)) {
        echo "  ✓ {$class} - {$desc}\n";
        $passed++;
    } else {
        echo "  ✗ {$class} NOT FOUND\n";
        $failed++;
    }
}

// Test 5: Test actual usage
echo "\n5. Functional Tests:\n";

try {
    $path = storage_path('test/file.txt');
    echo "  ✓ storage_path('test/file.txt')\n";
    echo "    → {$path}\n";
    $passed++;
} catch (Exception $e) {
    echo "  ✗ storage_path() failed: {$e->getMessage()}\n";
    $failed++;
}

try {
    $path = upload_path('properties.images');
    echo "  ✓ upload_path('properties.images')\n";
    echo "    → {$path}\n";
    $passed++;
} catch (Exception $e) {
    echo "  ✗ upload_path() failed: {$e->getMessage()}\n";
    $failed++;
}

try {
    $path = qr_path('vouchers', 'test.png');
    echo "  ✓ qr_path('vouchers', 'test.png')\n";
    echo "    → {$path}\n";
    $passed++;
} catch (Exception $e) {
    echo "  ✗ qr_path() failed: {$e->getMessage()}\n";
    $failed++;
}

try {
    $path = log_path('test.log');
    echo "  ✓ log_path('test.log')\n";
    echo "    → {$path}\n";
    $passed++;
} catch (Exception $e) {
    echo "  ✗ log_path() failed: {$e->getMessage()}\n";
    $failed++;
}

try {
    $value = secret('nonexistent.key', 'default');
    echo "  ✓ secret('nonexistent.key', 'default')\n";
    echo "    → {$value}\n";
    $passed++;
} catch (Exception $e) {
    echo "  ✗ secret() failed: {$e->getMessage()}\n";
    $failed++;
}

// Summary
echo "\n=== Summary ===\n";
echo "Total tests: " . ($passed + $failed) . "\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n\n";

if ($failed === 0) {
    echo "✅ All tests PASSED!\n\n";
    echo "The storage library is properly integrated and will be\n";
    echo "globally available in all files that include connectdb.hnt\n\n";
    echo "Phase 3 Complete!\n";
    echo "Next: Phase 4 - Module-by-Module Migration\n";
    exit(0);
} else {
    echo "❌ {$failed} test(s) FAILED\n";
    exit(1);
}
