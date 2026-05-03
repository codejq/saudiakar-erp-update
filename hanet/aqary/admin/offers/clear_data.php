<?php
/**
 * Clear all synced data from the database
 * Run this before re-syncing to get fresh data with proper UTF-8 encoding
 */

header('Content-Type: text/plain; charset=utf-8');

echo "===============================================\n";
echo "  Clear Synced Data  \n";
echo "===============================================\n\n";

require_once("../../connectdb.hnt");

// Database setup
$offers_link = mysql_connect($qdb_server.":".$qdb_port, $qdb_user, $qdb_pass, true);

if (!$offers_link) {
    die("Failed to connect to MySQL server: " . mysql_error());
}

if (!mysql_select_db($qdb_db_offers, $offers_link)) {
    die("Failed to select database '{$qdb_db_offers}': " . mysql_error($offers_link));
}

// Set UTF-8 character set
mysql_query("SET NAMES 'utf8mb4'", $offers_link);
mysql_query("SET CHARACTER SET 'utf8mb4'", $offers_link);

echo "Connected to database '{$qdb_db_offers}'.\n\n";

// Disable foreign key checks temporarily
mysql_query("SET FOREIGN_KEY_CHECKS=0", $offers_link);
echo "Foreign key checks disabled.\n\n";

// Clear data from tables (in reverse order to avoid foreign key issues)
$tables_to_clear = [
    'offer_images' => 'Offer images',
    'messages' => 'Messages',
    'offers' => 'Offers',
    'users' => 'Users (synced only)'
];

foreach ($tables_to_clear as $table => $description) {
    echo "Clearing {$description}...\n";

    if ($table === 'users') {
        // Only delete synced users (those with SYNC- API keys)
        $query = "DELETE FROM users WHERE api_key LIKE 'SYNC-%'";
    } else {
        // Clear entire table
        $query = "TRUNCATE TABLE {$table}";
    }

    $result = mysql_query($query, $offers_link);

    if ($result) {
        $affected = mysql_affected_rows($offers_link);
        echo "  ✓ Cleared {$affected} records from {$table}\n";
    } else {
        echo "  ✗ Error clearing {$table}: " . mysql_error($offers_link) . "\n";
    }
}

// Re-enable foreign key checks
mysql_query("SET FOREIGN_KEY_CHECKS=1", $offers_link);
echo "\nForeign key checks re-enabled.\n";

echo "\n===============================================\n";
echo "  Data Cleared Successfully!  \n";
echo "===============================================\n\n";

echo "Next step: Run the sync to get fresh data with proper UTF-8 encoding.\n";
echo "Visit: run_sync.php\n";

?>
