<?php
require_once("../../connectdb.hnt");
require_once("../../mysql.php");

$qdb_db_offers = "aqary_offers";
$offers_link = mysql_connect($qdb_server.":".$qdb_port, $qdb_user, $qdb_pass);

if (!$offers_link) {
    echo ("Failed to connect to MySQL server: " . mysql_error());
}

// Expected number of tables (9 tables + 2 views = 11)
$expected_table_count = 11;

// Check if database exists and has all tables
$db_exists = false;
$needs_setup = true;

$result = mysql_query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . mysql_real_escape_string($qdb_db_offers, $offers_link) . "'", $offers_link);

if ($result && mysql_num_rows($result) > 0) {
    $db_exists = true;

    // Check table count
    $tables_query = "SELECT COUNT(*) as table_count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . mysql_real_escape_string($qdb_db_offers, $offers_link) . "'";
    $tables_result = mysql_query($tables_query, $offers_link);

    if ($tables_result) {
        $row = mysql_fetch_assoc($tables_result);
        $table_count = (int)$row['table_count'];

        if ($table_count >= $expected_table_count) {
            $needs_setup = false;
            echo "<!-- Database '{$qdb_db_offers}' already exists with {$table_count} tables. Setup complete.\n --> ";
        } else {
            echo "<! -- Database '{$qdb_db_offers}' exists but has only {$table_count}/{$expected_table_count} tables. Running setup...\n --> ";
        }
    }
} else {
    echo "Database '{$qdb_db_offers}' does not exist. Creating...\n";
}

// Only run setup if needed
if (!$needs_setup) {
    // Database exists with all tables, just select it
    if (!mysql_select_db($qdb_db_offers, $offers_link)) {
        echo ("Error selecting database: " . mysql_error($offers_link));
    }
    echo "<!-- No setup needed. Database is ready.\n -->";

    // Set UTF-8 character set for proper Arabic text handling
    mysql_query("SET NAMES 'utf8mb4'", $offers_link);
    mysql_query("SET CHARACTER SET 'utf8mb4'", $offers_link);
}
else{
echo "<br> جاري انشاء قاعدة باينات العروض '{$qdb_db_offers}'...\n";

$sql_file = __DIR__ . "/schema.sql";

if (!file_exists($sql_file)) {
    echo ("Error: Schema file not found at {$sql_file}\n");
}

// Read SQL file
$sql_content = file_get_contents($sql_file);

if ($sql_content === false) {
    echo("Error: Could not read schema file\n");
}

// If database exists but needs setup, drop it first to remove any existing tablespaces
if ($db_exists) {
    echo "<br> قاعدة البيانات موجودة ولكنها غير مكتملة. جاري حذف قاعدة البيانات القديمة...\n";
    $drop_db_query = "DROP DATABASE IF EXISTS `{$qdb_db_offers}`";
    if (!mysql_query($drop_db_query, $offers_link)) {
        echo("Error dropping database: " . mysql_error($offers_link) . "\n");
    } else {
        echo "<br> تم حذف قاعدة البيانات القديمة.\n";
    }
}

// First, create the database
$create_db_query = "CREATE DATABASE IF NOT EXISTS `{$qdb_db_offers}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!mysql_query($create_db_query, $offers_link)) {
    echo("Error creating database: " . mysql_error($offers_link) . "\n");
}
echo "<br> قاعدة البيانات العروض '{$qdb_db_offers}' تم انشاؤها .\n";

// Select the database (now that it exists)
if (!mysql_select_db($qdb_db_offers, $offers_link)) {
    echo ("Error selecting database: " . mysql_error($offers_link) . "\n");
}
echo "Database '{$qdb_db_offers}' selected.\n";

// Drop any existing tables to remove orphaned tablespaces
// This handles the case where database didn't exist but .ibd files do
echo "<br> جاري فحص وحذف أي جداول موجودة (tablespaces)...\n";
$tables_check = mysql_query("SHOW TABLES", $offers_link);
if ($tables_check) {
    $dropped_count = 0;
    while ($row = mysql_fetch_array($tables_check)) {
        $table_name = $row[0];
        if (mysql_query("DROP TABLE IF EXISTS `{$table_name}`", $offers_link)) {
            $dropped_count++;
        }
    }
    if ($dropped_count > 0) {
        echo "<br> تم حذف {$dropped_count} جدول قديم.\n";
    } else {
        echo "<br> لا توجد جداول قديمة.\n";
    }
}

// Set UTF-8 character set for proper Arabic text handling
mysql_query("SET NAMES 'utf8mb4'", $offers_link);
mysql_query("SET CHARACTER SET 'utf8mb4'", $offers_link);

// Remove CREATE DATABASE and USE statements from the SQL content
$sql_content = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`?aqary_offers`?[^;]*;/i', '', $sql_content);
$sql_content = preg_replace('/USE\s+`?aqary_offers`?;/i', '', $sql_content);

// Remove SQL comments
$sql_content = preg_replace('/^--.*$/m', '', $sql_content); // Remove line comments
$sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content); // Remove block comments

// Remove only standalone SET statements at the beginning (not SET NULL in constraints)
$sql_content = preg_replace('/^\s*SET\s+[^;]+;/mi', '', $sql_content);

// Add IF NOT EXISTS to all CREATE TABLE statements
$sql_content = preg_replace('/CREATE\s+TABLE\s+`/i', 'CREATE TABLE IF NOT EXISTS `', $sql_content);

// Make INSERT statements ignore duplicates
$sql_content = preg_replace('/INSERT\s+INTO\s+`/i', 'INSERT IGNORE INTO `', $sql_content);

// Add IF NOT EXISTS to CREATE INDEX statements (for MySQL 5.7+, will be ignored in older versions)
$sql_content = preg_replace('/CREATE\s+INDEX\s+(\w+)/i', 'CREATE INDEX IF NOT EXISTS $1', $sql_content);

// Split SQL file by semicolons (handles multi-line statements)
$raw_statements = explode(';', $sql_content);

// Clean and filter statements
$statements = array_filter(
    array_map('trim', $raw_statements),
    function($stmt) {
        // Keep only non-empty statements
        return !empty($stmt) && strlen($stmt) > 5; // Skip very short statements
    }
);

echo "<br> وجدنا " . count($raw_statements) . " SQL statements. جاري تنفيذ " . count($statements) . "  .\n";

// Execute each statement
$success_count = 0;
$error_count = 0;

foreach ($statements as $index => $statement) {
    // Show progress
    $stmt_preview = preg_replace('/\s+/', ' ', substr($statement, 0, 60));
    //echo "[" . ($index + 1) . "/" . count($statements) . "] Executing: {$stmt_preview}...\n";

    $result = mysql_query($statement, $offers_link);

    if ($result === false) {
        echo "<br>  ✗ خطأ: " . mysql_error($offers_link) . "\n";
        $error_count++;
    } else {
        echo "<br>  ✓ نجاح\n";
        $success_count++;
    }
}

echo "<br> تم انشاء قاعدة باينات العروض.\n";
echo "<br> Successful statements: {$success_count}\n";
echo "<br> Failed statements: {$error_count}\n";

if ($error_count > 0) {
    echo "<br> Warning: Some statements failed. Please check the errors above.\n";
} else {
    echo "<br> All statements executed successfully!\n";
}

// Close connection
//mysql_close($offers_link);
}
?>
