<?php
/**
 * Phase 3 Integration Test
 * Verifies storage library is available after including connectdb.hnt
 */

echo "=== Phase 3 Integration Test ===\n\n";

// Test 1: Include connectdb.hnt
echo "1. Including connectdb.hnt...\n";
try {
    require_once(__DIR__ . '/../connectdb.hnt');
    echo "  ✓ connectdb.hnt loaded successfully\n";
} catch (Exception $e) {
    echo "  ✗ Failed to load connectdb.hnt: {$e->getMessage()}\n";
    exit(1);
}

// Test 2: Check if constants are defined
echo "\n2. Checking Storage Constants:\n";
$constants = [
    'STORAGE_ROOT',
    'STORAGE_UPLOADS',
    'STORAGE_PROTECTED',
    'STORAGE_CACHE',
    'STORAGE_LOGS',
];

$all_defined = true;
foreach ($constants as $const) {
    if (defined($const)) {
        echo "  ✓ {$const}\n";
    } else {
        echo "  ✗ {$const} NOT DEFINED\n";
        $all_defined = false;
    }
}

// Test 3: Check if helper functions are available
echo "\n3. Checking Helper Functions:\n";
$functions = [
    'storage_path',
    'upload_path',
    'qr_path',
    'cache_path',
    'log_path',
    'secret',
];

$all_exist = true;
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "  ✓ {$func}()\n";
    } else {
        echo "  ✗ {$func}() NOT FOUND\n";
        $all_exist = false;
    }
}

// Test 4: Test actual functionality
echo "\n4. Testing Functionality:\n";

try {
    $path = upload_path('properties.images');
    echo "  ✓ upload_path() works: {$path}\n";
} catch (Exception $e) {
    echo "  ✗ upload_path() failed: {$e->getMessage()}\n";
    $all_exist = false;
}

try {
    $path = qr_path('vouchers');
    echo "  ✓ qr_path() works: {$path}\n";
} catch (Exception $e) {
    echo "  ✗ qr_path() failed: {$e->getMessage()}\n";
    $all_exist = false;
}

try {
    $value = secret('test.key', 'default_value');
    echo "  ✓ secret() works: {$value}\n";
} catch (Exception $e) {
    echo "  ✗ secret() failed: {$e->getMessage()}\n";
    $all_exist = false;
}

// Test 5: Verify database connection still works
echo "\n5. Checking Database Connection:\n";
if (isset($link) && $link) {
    echo "  ✓ MySQL legacy connection (\$link) active\n";
} else {
    echo "  ✗ MySQL legacy connection failed\n";
    $all_exist = false;
}

if (isset($db) && $db) {
    echo "  ✓ MySQLi connection (\$db) active\n";
} else {
    echo "  ✗ MySQLi connection failed\n";
    $all_exist = false;
}

// Summary
echo "\n=== Summary ===\n";
if ($all_defined && $all_exist) {
    echo "✅ All tests PASSED!\n\n";
    echo "Storage library is now globally available.\n";
    echo "Database connections are working.\n";
    echo "Ready for Phase 4: Module-by-Module Migration\n";
    exit(0);
} else {
    echo "❌ Some tests FAILED\n";
    exit(1);
}
