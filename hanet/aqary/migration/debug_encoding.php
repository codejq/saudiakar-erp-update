<?php
/**
 * Encoding Debug Script
 *
 * This script helps diagnose encoding issues by showing:
 * - Column names in mostagereen table
 * - Sample data in different encodings
 * - Raw bytes
 */

require_once('config.php');

// Connect to old database
$old_conn = new mysqli(
    $old_mysql_server,
    $old_mysql_user,
    $old_mysql_password,
    $old_mysql_database,
    $old_mysql_port
);

if ($old_conn->connect_error) {
    die("Connection failed: " . $old_conn->connect_error);
}

// Set charset to latin1 to read raw bytes
$old_conn->set_charset('latin1');
$old_conn->query("SET NAMES 'latin1'");

echo "<html><head><meta charset='UTF-8'><title>Encoding Debug</title></head><body>";
echo "<h1>Mostagereen Table Encoding Debug</h1>";

// 1. Show column names
echo "<h2>1. Column Names</h2>";
$result = $old_conn->query("SHOW COLUMNS FROM mostagereen");
echo "<table border='1' style='border-collapse:collapse; margin:20px;'>";
echo "<tr><th>Column Name</th><th>Type</th><th>Collation</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td><strong>{$row['Field']}</strong></td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>" . ($row['Collation'] ?? 'N/A') . "</td>";
    echo "</tr>";
}
echo "</table>";

// 2. Get first 3 rows of data
echo "<h2>2. Sample Data (First 3 Rows)</h2>";
$result = $old_conn->query("SELECT * FROM mostagereen LIMIT 3");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div style='border:1px solid #ccc; margin:20px; padding:20px; background:#f5f5f5;'>";
        echo "<h3>Row:</h3>";

        foreach ($row as $col_name => $value) {
            if ($value === null || $value === '') continue;

            echo "<div style='margin:10px 0; padding:10px; background:white;'>";
            echo "<strong>Column: {$col_name}</strong><br>";
            echo "Raw value: " . htmlspecialchars($value) . "<br>";

            // Try different encodings
            $encodings = ['windows-1256', 'cp1256', 'ISO-8859-6', 'UTF-8'];

            foreach ($encodings as $encoding) {
                $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
                if ($converted && !preg_match('/\?{3,}/', $converted)) {
                    echo "<span style='color:green;'>✓ {$encoding}: {$converted}</span><br>";
                } else {
                    echo "<span style='color:red;'>✗ {$encoding}: failed or produced ?????</span><br>";
                }
            }

            // Show raw bytes (first 50 chars)
            $hex = bin2hex(substr($value, 0, 50));
            echo "<small style='color:#666;'>Hex: {$hex}</small><br>";
            echo "</div>";
        }

        echo "</div>";
        break; // Only show first row in detail
    }
} else {
    echo "<p>No data found in mostagereen table</p>";
}

echo "</body></html>";

$old_conn->close();
?>
