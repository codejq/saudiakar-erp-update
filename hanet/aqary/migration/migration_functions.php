<?php
/**
 * Database Migration Helper Functions
 *
 * Collection of helper functions for database migration from old to new system
 * Handles encoding conversion, table operations, and progress tracking
 */

require_once('migration_config.php');

/**
 * Create database connections
 *
 * @param array $config Database configuration
 * @return mysqli|false Database connection or false on failure
 */
function createDatabaseConnection($config) {
    $port = !empty($config['port']) ? (int)$config['port'] : 3306;

    $conn = new mysqli(
        $config['host'],
        $config['user'],
        $config['password'],
        $config['database'],
        $port
    );

    if ($conn->connect_error) {
        logMessage("Connection failed: " . $conn->connect_error, 'ERROR');
        return false;
    }

    // For old database (latin1), use binary to preserve raw bytes
    // For new database (utf8mb4), use utf8mb4
    if ($config['charset'] === 'latin1') {
        // Read data as raw bytes without conversion
        $conn->set_charset('latin1');
        $conn->query("SET NAMES 'latin1'");
    } else {
        // Use proper charset for new database
        $conn->set_charset($config['charset']);
        $conn->query("SET NAMES '{$config['charset']}'");
    }

    logMessage("Connected to database: {$config['database']} on {$config['host']}:{$port}", 'SUCCESS');

    return $conn;
}

/**
 * Create migration progress tracking table in new database
 *
 * @param mysqli $new_db New database connection
 * @return bool Success status
 */
function createTrackingTable($new_db) {
    $sql = "CREATE TABLE IF NOT EXISTS `migration_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `table_name` VARCHAR(100) NOT NULL UNIQUE,
        `total_rows` INT DEFAULT 0,
        `migrated_rows` INT DEFAULT 0,
        `last_processed_id` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('pending', 'in_progress', 'completed', 'error', 'skipped') DEFAULT 'pending',
        `started_at` DATETIME NULL,
        `completed_at` DATETIME NULL,
        `error_message` TEXT NULL,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($new_db->query($sql)) {
        logMessage("Migration tracking table created/verified", 'INFO');
        return true;
    } else {
        logMessage("Failed to create tracking table: " . $new_db->error, 'ERROR');
        return false;
    }
}

/**
 * Initialize tracking for all tables
 *
 * @param mysqli $new_db New database connection
 * @param array $tables List of tables to migrate
 * @return bool Success status
 */
function initializeTracking($new_db, $tables) {
    foreach ($tables as $table) {
        $table_escaped = $new_db->real_escape_string($table);
        $sql = "INSERT IGNORE INTO migration_progress (table_name, status)
                VALUES ('$table_escaped', 'pending')";
        $new_db->query($sql);
    }
    logMessage("Initialized tracking for " . count($tables) . " tables", 'INFO');
    return true;
}

/**
 * Get table status from migration_progress
 *
 * @param mysqli $new_db New database connection
 * @param string $table_name Table name
 * @return array|null Table status or null if not found
 */
function getTableStatus($new_db, $table_name) {
    $table_escaped = $new_db->real_escape_string($table_name);
    $sql = "SELECT * FROM migration_progress WHERE table_name = '$table_escaped'";
    $result = $new_db->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Update table migration status
 *
 * @param mysqli $new_db New database connection
 * @param string $table_name Table name
 * @param array $data Data to update
 * @return bool Success status
 */
function updateTableStatus($new_db, $table_name, $data) {
    $table_escaped = $new_db->real_escape_string($table_name);
    $updates = [];

    foreach ($data as $key => $value) {
        if ($value === null) {
            $updates[] = "`$key` = NULL";
        } elseif (is_numeric($value)) {
            $updates[] = "`$key` = $value";
        } else {
            $value_escaped = $new_db->real_escape_string($value);
            $updates[] = "`$key` = '$value_escaped'";
        }
    }

    $sql = "UPDATE migration_progress SET " . implode(', ', $updates) .
           " WHERE table_name = '$table_escaped'";

    return $new_db->query($sql);
}

/**
 * Get list of columns for a table
 *
 * @param mysqli $db Database connection
 * @param string $table_name Table name
 * @return array List of column names
 */
function getTableColumns($db, $table_name) {
    $columns = [];
    $sql = "SHOW COLUMNS FROM `$table_name`";
    $result = $db->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    return $columns;
}

/**
 * Get column details for a table
 *
 * @param mysqli $db Database connection
 * @param string $table_name Table name
 * @return array Associative array of column details
 */
function getTableColumnDetails($db, $table_name) {
    $columns = [];
    $sql = "SHOW COLUMNS FROM `$table_name`";
    $result = $db->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = [
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
    }

    return $columns;
}

/**
 * Get primary key column(s) for a table
 *
 * @param mysqli $db Database connection
 * @param string $table_name Table name
 * @return array List of primary key column names
 */
function getTablePrimaryKey($db, $table_name) {
    $pk_columns = [];
    $sql = "SHOW KEYS FROM `$table_name` WHERE Key_name = 'PRIMARY'";
    $result = $db->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pk_columns[] = $row['Column_name'];
        }
    }

    return $pk_columns;
}

/**
 * Check if table exists in database
 *
 * @param mysqli $db Database connection
 * @param string $table_name Table name
 * @return bool True if table exists
 */
function tableExists($db, $table_name) {
    $table_escaped = $db->real_escape_string($table_name);
    $sql = "SHOW TABLES LIKE '$table_escaped'";
    $result = $db->query($sql);
    return $result && $result->num_rows > 0;
}

/**
 * Convert text encoding from Windows-1256 to UTF-8
 *
 * @param mixed $value Value to convert
 * @return mixed Converted value
 */
function convertEncoding($value) {
    global $encoding_settings;

    if ($value === null || $value === '') {
        return $value;
    }

    if (!is_string($value)) {
        return $value;
    }

    // Try multiple encodings in order of likelihood
    $encodings_to_try = [
        'windows-1256',
        'cp1256',
        'ISO-8859-6',
        'UTF-8'
    ];

    $target = 'UTF-8//IGNORE';

    foreach ($encodings_to_try as $source_encoding) {
        $converted = @iconv($source_encoding, $target, $value);

        if ($converted !== false && $converted !== '' && !preg_match('/\?{3,}/', $converted)) {
            // Check if conversion produced valid UTF-8 without excessive question marks
            if (mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }
    }

    // If all conversions failed, try mb_convert_encoding as fallback
    $converted = @mb_convert_encoding($value, 'UTF-8', 'windows-1256');
    if ($converted !== false && !preg_match('/\?{3,}/', $converted)) {
        return $converted;
    }

    // Last resort: return original value
    return $value;
}

/**
 * Determine if a column needs encoding conversion
 *
 * @param string $table_name Table name
 * @param string $column_name Column name
 * @return bool True if column needs encoding conversion
 */
function needsEncodingConversion($table_name, $column_name) {
    global $text_columns;

    if (!isset($text_columns[$table_name])) {
        return false;
    }

    return in_array($column_name, $text_columns[$table_name]);
}

/**
 * Count rows in a table
 *
 * @param mysqli $db Database connection
 * @param string $table_name Table name
 * @param string $where WHERE clause (optional)
 * @return int Number of rows
 */
function countTableRows($db, $table_name, $where = '') {
    $sql = "SELECT COUNT(*) as total FROM `$table_name`";
    if ($where) {
        $sql .= " WHERE $where";
    }

    $result = $db->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    }

    return 0;
}

/**
 * Disable foreign key checks
 *
 * @param mysqli $db Database connection
 * @return bool Success status
 */
function disableForeignKeyChecks($db) {
    return $db->query("SET FOREIGN_KEY_CHECKS = 0");
}

/**
 * Enable foreign key checks
 *
 * @param mysqli $db Database connection
 * @return bool Success status
 */
function enableForeignKeyChecks($db) {
    return $db->query("SET FOREIGN_KEY_CHECKS = 1");
}

/**
 * Log message to file and/or output
 *
 * @param string $message Message to log
 * @param string $type Message type (INFO, ERROR, WARNING, SUCCESS)
 * @return void
 */
function logMessage($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$type] $message\n";

    // Write to log file
    if (defined('LOG_FILE')) {
        file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    }

    // Output to browser if not CLI
    if (php_sapi_name() !== 'cli') {
        $class_map = [
            'ERROR' => 'error',
            'WARNING' => 'warning',
            'SUCCESS' => 'success',
            'INFO' => 'info'
        ];
        $class = $class_map[$type] ?? 'info';

        echo "<div class='log-entry log-$class'>";
        echo "<span class='log-time'>$timestamp</span> ";
        echo "<span class='log-type'>[$type]</span> ";
        echo "<span class='log-message'>" . htmlspecialchars($message) . "</span>";
        echo "</div>";

        // Flush output buffers if they exist
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    } else {
        echo $log_entry;
    }
}

/**
 * Get migration statistics
 *
 * @param mysqli $new_db New database connection
 * @return array Migration statistics
 */
function getMigrationStatistics($new_db) {
    $sql = "SELECT
                COUNT(*) as total_tables,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tables,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tables,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_tables,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tables,
                SUM(total_rows) as total_rows,
                SUM(migrated_rows) as migrated_rows
            FROM migration_progress";

    $result = $new_db->query($sql);
    if ($result) {
        return $result->fetch_assoc();
    }

    return [
        'total_tables' => 0,
        'completed_tables' => 0,
        'in_progress_tables' => 0,
        'error_tables' => 0,
        'pending_tables' => 0,
        'total_rows' => 0,
        'migrated_rows' => 0
    ];
}

/**
 * Build INSERT query with ON DUPLICATE KEY UPDATE
 *
 * @param string $table_name Table name
 * @param array $columns Column names
 * @param array $row Row data
 * @param array $pk_columns Primary key columns
 * @param mysqli $db Database connection for escaping
 * @return string SQL query
 */
function buildInsertQuery($table_name, $columns, $row, $pk_columns, $db) {
    global $text_columns;

    $values = [];
    $updates = [];

    foreach ($columns as $column) {
        $value = $row[$column] ?? null;

        // Convert encoding if needed
        if (needsEncodingConversion($table_name, $column)) {
            $value = convertEncoding($value);
        }

        // Escape and format value
        if ($value === null) {
            $values[] = 'NULL';
        } else {
            $escaped_value = $db->real_escape_string($value);
            $values[] = "'$escaped_value'";
        }

        // Build UPDATE clause (skip primary key columns)
        if (!in_array($column, $pk_columns)) {
            if ($value === null) {
                $updates[] = "`$column` = NULL";
            } else {
                $escaped_value = $db->real_escape_string($value);
                $updates[] = "`$column` = '$escaped_value'";
            }
        }
    }

    $columns_str = '`' . implode('`, `', $columns) . '`';
    $values_str = implode(', ', $values);
    $updates_str = implode(', ', $updates);

    $sql = "INSERT INTO `$table_name` ($columns_str) VALUES ($values_str)";

    if (!empty($updates)) {
        $sql .= " ON DUPLICATE KEY UPDATE $updates_str";
    }

    return $sql;
}

/**
 * Format bytes to human-readable size
 *
 * @param int $bytes Number of bytes
 * @return string Formatted size
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Format duration in seconds to human-readable time
 *
 * @param int $seconds Duration in seconds
 * @return string Formatted duration
 */
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    $parts = [];
    if ($hours > 0) $parts[] = "{$hours}h";
    if ($minutes > 0) $parts[] = "{$minutes}m";
    if ($secs > 0 || empty($parts)) $parts[] = "{$secs}s";

    return implode(' ', $parts);
}

/**
 * Reset table migration status
 *
 * @param mysqli $new_db New database connection
 * @param string $table_name Table name
 * @return bool Success status
 */
function resetTableMigration($new_db, $table_name) {
    $table_escaped = $new_db->real_escape_string($table_name);
    $sql = "UPDATE migration_progress SET
            status = 'pending',
            migrated_rows = 0,
            last_processed_id = NULL,
            started_at = NULL,
            completed_at = NULL,
            error_message = NULL
            WHERE table_name = '$table_escaped'";

    return $new_db->query($sql);
}

?>
