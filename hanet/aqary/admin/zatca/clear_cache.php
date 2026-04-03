<?php
// Clear PHP opcode cache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully!\n";
} else {
    echo "OPcache not enabled\n";
}

if (function_exists('opcache_invalidate')) {
    $files = [
        __DIR__ . '/phase2/integration/Phase2Manager.php',
        __DIR__ . '/config/phase2_config.php',
    ];
    foreach ($files as $file) {
        opcache_invalidate($file, true);
        echo "Invalidated: $file\n";
    }
}

echo "Cache clear complete. Restart your web server for full effect.\n";
?>
