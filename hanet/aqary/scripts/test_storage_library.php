<?php
/**
 * Test Storage Library
 * Verifies that all storage library components work correctly
 */

echo "=== Storage Library Test ===\n\n";

// Load the storage library
require_once(__DIR__ . '/../lib/storage/helpers.php');

$tests = [];
$errors = [];

// Test 1: Check constants are defined
echo "1. Testing Constants:\n";
$required_constants = [
    'STORAGE_ROOT',
    'STORAGE_APP',
    'STORAGE_CACHE',
    'STORAGE_LOGS',
    'STORAGE_PROTECTED',
    'STORAGE_UPLOADS',
    'STORAGE_GENERATED',
];

foreach ($required_constants as $const) {
    if (defined($const)) {
        echo "  ✓ {$const} = " . constant($const) . "\n";
        $tests[$const] = true;
    } else {
        echo "  ✗ {$const} NOT DEFINED\n";
        $tests[$const] = false;
        $errors[] = "{$const} not defined";
    }
}

// Test 2: Check helper functions
echo "\n2. Testing Helper Functions:\n";
$helper_functions = [
    'storage_path',
    'upload_path',
    'qr_path',
    'cache_path',
    'log_path',
    'secret',
    'ensure_dir',
    'write_log',
];

foreach ($helper_functions as $func) {
    if (function_exists($func)) {
        echo "  ✓ {$func}()\n";
        $tests["func_{$func}"] = true;
    } else {
        echo "  ✗ {$func}() NOT FOUND\n";
        $tests["func_{$func}"] = false;
        $errors[] = "Function {$func}() not found";
    }
}

// Test 3: Check classes
echo "\n3. Testing Classes:\n";
$required_classes = [
    'StoragePath',
    'SecretsManager',
    'FileValidator',
];

foreach ($required_classes as $class) {
    if (class_exists($class)) {
        echo "  ✓ {$class}\n";
        $tests["class_{$class}"] = true;
    } else {
        echo "  ✗ {$class} NOT FOUND\n";
        $tests["class_{$class}"] = false;
        $errors[] = "Class {$class} not found";
    }
}

// Test 4: Test actual functionality
echo "\n4. Testing Functionality:\n";

try {
    // Test storage_path
    $path = storage_path('test/file.txt');
    echo "  ✓ storage_path('test/file.txt') = {$path}\n";
    $tests['storage_path_works'] = true;
} catch (Exception $e) {
    echo "  ✗ storage_path() error: {$e->getMessage()}\n";
    $tests['storage_path_works'] = false;
    $errors[] = "storage_path() failed: " . $e->getMessage();
}

try {
    // Test upload_path
    $path = upload_path('properties.images');
    echo "  ✓ upload_path('properties.images') = {$path}\n";
    $tests['upload_path_works'] = true;
} catch (Exception $e) {
    echo "  ✗ upload_path() error: {$e->getMessage()}\n";
    $tests['upload_path_works'] = false;
    $errors[] = "upload_path() failed: " . $e->getMessage();
}

try {
    // Test qr_path
    $path = qr_path('vouchers', 'test.png');
    echo "  ✓ qr_path('vouchers', 'test.png') = {$path}\n";
    $tests['qr_path_works'] = true;
} catch (Exception $e) {
    echo "  ✗ qr_path() error: {$e->getMessage()}\n";
    $tests['qr_path_works'] = false;
    $errors[] = "qr_path() failed: " . $e->getMessage();
}

try {
    // Test secrets (should return null for non-existent key)
    $value = secret('test.key', 'default');
    echo "  ✓ secret('test.key', 'default') = {$value}\n";
    $tests['secret_works'] = true;
} catch (Exception $e) {
    echo "  ✗ secret() error: {$e->getMessage()}\n";
    $tests['secret_works'] = false;
    $errors[] = "secret() failed: " . $e->getMessage();
}

try {
    // Test write_log
    write_log('Test log message', 'info', 'test.log');
    $log_file = log_path('test.log');
    if (file_exists($log_file)) {
        echo "  ✓ write_log() - log file created\n";
        $tests['write_log_works'] = true;
        // Clean up
        @unlink($log_file);
    } else {
        echo "  ✗ write_log() - log file not created\n";
        $tests['write_log_works'] = false;
        $errors[] = "write_log() did not create log file";
    }
} catch (Exception $e) {
    echo "  ✗ write_log() error: {$e->getMessage()}\n";
    $tests['write_log_works'] = false;
    $errors[] = "write_log() failed: " . $e->getMessage();
}

// Summary
echo "\n=== Summary ===\n";
$total = count($tests);
$passed = count(array_filter($tests));
$failed = $total - $passed;

echo "Total tests: {$total}\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n\n";

if ($failed === 0) {
    echo "✅ All tests PASSED!\n";
    echo "\nStorage library is ready to use.\n";
    echo "Next step: Add to connectdb.hnt\n";
    exit(0);
} else {
    echo "❌ Some tests FAILED\n\n";
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}
