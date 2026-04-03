<?php
/**
 * Force clear ALL PHP caches
 * Access this file first before testing invoices
 */

echo "<h2>Clearing PHP Caches...</h2>";

// 1. Clear OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ OPcache reset complete<br>";
} else {
    echo "⚠ OPcache extension not enabled<br>";
}

// 2. Clear OPcache for specific files
if (function_exists('opcache_invalidate')) {
    $files = [
        __DIR__ . '/phase2/integration/Phase2Manager.php',
        __DIR__ . '/phase2/integration/Phase2Manager.php.bak',
        __DIR__ . '/config/phase2_config.php',
        __DIR__ . '/lib/php-zatca-xml-main/src/Helpers/Certificate.php',
        __DIR__ . '/lib/php-zatca-xml-main/src/Helpers/InvoiceSignatureBuilder.php',
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            opcache_invalidate($file, true);
            echo "✓ Invalidated: " . basename($file) . "<br>";
        }
    }
}

// 3. Clear realpath cache
if (function_exists('realpath_cache_size')) {
    clearstatcache(true);
    echo "✓ Stat cache cleared<br>";
}

// 4. Show OPcache status
echo "<hr><h3>OPcache Status:</h3>";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status) {
        echo "<pre>" . print_r($status, true) . "</pre>";
    } else {
        echo "OPcache is running but status not available<br>";
    }
} else {
    echo "OPcache status function not available<br>";
}

// 5. Instructions
echo "<hr><h3 style='color:red;'>IMPORTANT NEXT STEPS:</h3>";
echo "<ol>";
echo "<li><strong>Restart Apache</strong> (WAMP tray icon → Restart All Services)</li>";
echo "<li><strong>Clear browser cache</strong> (Ctrl+Shift+Delete)</li>";
echo "<li><strong>Test invoice again</strong></li>";
echo "</ol>";

echo "<p><strong>File timestamps:</strong></p>";
$files = [
    __DIR__ . '/phase2/integration/Phase2Manager.php',
    __DIR__ . '/config/phase2_config.php',
];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo basename($file) . ": " . date('Y-m-d H:i:s', filemtime($file)) . "<br>";
    }
}
?>
