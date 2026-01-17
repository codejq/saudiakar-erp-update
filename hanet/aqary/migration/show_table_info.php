<?php
/**
 * Table Structure Info Script
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

$old_conn->set_charset('latin1');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Table Info - mostagereen</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #667eea; color: white; }
        .success { color: green; font-weight: bold; }
        .error { color: red; }
        pre { background: #f8f8f8; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .sample-data { background: #e8f4f8; padding: 10px; margin: 10px 0; border-left: 4px solid #667eea; }
    </style>
</head>
<body>

<h1>🔍 Table Info: mostagereen</h1>

<!-- Section 1: Table Columns -->
<div class="section">
    <h2>1. Table Columns</h2>
    <table>
        <tr>
            <th>Column Name</th>
            <th>Data Type</th>
            <th>Null</th>
            <th>Key</th>
            <th>Default</th>
        </tr>
        <?php
        $result = $old_conn->query("SHOW COLUMNS FROM mostagereen");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
            echo "<tr>";
            echo "<td><strong>{$row['Field']}</strong></td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
    <p><strong>Total Columns:</strong> <?php echo count($columns); ?></p>
</div>

<!-- Section 2: Table Info -->
<div class="section">
    <h2>2. Table Information</h2>
    <?php
    $result = $old_conn->query("SHOW TABLE STATUS WHERE Name = 'mostagereen'");
    $table_info = $result->fetch_assoc();
    ?>
    <p><strong>Engine:</strong> <?php echo $table_info['Engine']; ?></p>
    <p><strong>Collation:</strong> <?php echo $table_info['Collation']; ?></p>
    <p><strong>Rows:</strong> <?php echo number_format($table_info['Rows']); ?></p>
    <p><strong>Data Length:</strong> <?php echo number_format($table_info['Data_length']); ?> bytes</p>
</div>

<!-- Section 3: Sample Row with Encoding Tests -->
<div class="section">
    <h2>3. Sample Data (First Row)</h2>
    <?php
    $result = $old_conn->query("SELECT * FROM mostagereen LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        echo "<p><strong>Testing each text column for best encoding...</strong></p>";

        foreach ($row as $col_name => $value) {
            // Skip numeric or empty values
            if ($value === null || $value === '' || is_numeric($value)) {
                continue;
            }

            // Only test columns that likely contain text
            if (strlen($value) < 2) continue;

            echo "<div class='sample-data'>";
            echo "<h4>Column: {$col_name}</h4>";
            echo "<p><strong>Original (as stored):</strong> " . htmlspecialchars($value) . "</p>";

            // Test different encodings
            $encodings = [
                'windows-1256' => 'Windows-1256',
                'cp1256' => 'CP1256',
                'ISO-8859-6' => 'ISO-8859-6',
                'UTF-8' => 'UTF-8'
            ];

            $found_good = false;
            foreach ($encodings as $enc_code => $enc_name) {
                $converted = @iconv($enc_code, 'UTF-8//IGNORE', $value);

                // Check if conversion is good (no ??? and valid UTF-8)
                $is_good = $converted &&
                          $converted !== '' &&
                          !preg_match('/\?{3,}/', $converted) &&
                          mb_check_encoding($converted, 'UTF-8') &&
                          preg_match('/[\x{0600}-\x{06FF}]/u', $converted); // Has Arabic characters

                if ($is_good) {
                    echo "<p class='success'>✓ {$enc_name}: {$converted}</p>";
                    $found_good = true;
                } else {
                    echo "<p class='error'>✗ {$enc_name}: " . htmlspecialchars(substr($converted, 0, 100)) . "...</p>";
                }
            }

            if (!$found_good) {
                echo "<p style='color: orange;'><strong>⚠ No encoding worked perfectly for this column</strong></p>";
                echo "<p><small>Raw bytes (first 100 chars): " . bin2hex(substr($value, 0, 100)) . "</small></p>";
            }

            echo "</div>";
        }
    } else {
        echo "<p>No data found in table</p>";
    }
    ?>
</div>

<!-- Section 4: Current Config -->
<div class="section">
    <h2>4. Current Migration Config for 'mostagereen'</h2>
    <?php
    require_once('migration_config.php');
    if (isset($text_columns['mostagereen'])) {
        echo "<p><strong>Columns configured for encoding conversion:</strong></p>";
        echo "<pre>" . print_r($text_columns['mostagereen'], true) . "</pre>";
    } else {
        echo "<p class='error'>⚠ No text columns configured for mostagereen table!</p>";
    }
    ?>
</div>

</body>
</html>

<?php $old_conn->close(); ?>
