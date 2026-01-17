<?php
/**
 * Database Migration Script
 *
 * Migrates data from old database (aqary) to new database (aqary_utf8)
 * - Converts encoding from Windows-1256 to UTF-8/UTF8MB4
 * - Handles schema differences
 * - Tracks progress and is resumable
 * - Prevents duplicate data
 */

// Password Protection
session_start();

// Check if user is already authenticated
if (!isset($_SESSION['migration_authenticated']) || $_SESSION['migration_authenticated'] !== true) {
    // Check if password is submitted
    if (isset($_POST['migration_password'])) {
        if ($_POST['migration_password'] === '2255') {
            $_SESSION['migration_authenticated'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $login_error = 'Invalid password. Please try again.';
        }
    }

    // Show login form
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Migration Script - Authentication Required</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .login-container {
                background: white;
                border-radius: 15px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                padding: 40px;
                max-width: 400px;
                width: 100%;
                text-align: center;
            }

            .login-container h1 {
                color: #667eea;
                margin-bottom: 10px;
                font-size: 2em;
            }

            .login-container p {
                color: #666;
                margin-bottom: 30px;
            }

            .form-group {
                margin-bottom: 20px;
                text-align: left;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: #333;
                font-weight: 600;
            }

            .form-group input {
                width: 100%;
                padding: 12px 15px;
                border: 2px solid #ddd;
                border-radius: 8px;
                font-size: 16px;
                transition: border-color 0.3s;
            }

            .form-group input:focus {
                outline: none;
                border-color: #667eea;
            }

            .btn-login {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }

            .btn-login:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            }

            .error {
                background: #fee;
                color: #c00;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                border: 1px solid #fcc;
            }

            .lock-icon {
                font-size: 4em;
                color: #667eea;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="lock-icon">🔒</div>
            <h1>Authentication Required</h1>
            <p>Please enter the password to access the migration script</p>

            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="migration_password">Password:</label>
                    <input
                        type="password"
                        id="migration_password"
                        name="migration_password"
                        required
                        autofocus
                        placeholder="Enter password"
                    >
                </div>
                <button type="submit" class="btn-login">Access Migration Script</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Configuration
set_time_limit(0);
ini_set('memory_limit', '2048M');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('migration_functions.php');

// Start output buffering for real-time display
ob_implicit_flush(true);
ob_end_flush();

// Initialize variables
$old_db = null;
$new_db = null;
$migration_start_time = time();

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - Old System to New System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .content {
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: #667eea;
            font-size: 2em;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #666;
            font-size: 0.9em;
        }

        .progress-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .progress-bar-container {
            background: #e0e0e0;
            height: 40px;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            margin: 15px 0;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .controls {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #a8a8a8 0%, #7f7f7f 100%);
            color: white;
        }

        .table-status {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: right;
            font-weight: bold;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
            display: inline-block;
        }

        .status-pending {
            background: #ffeaa7;
            color: #d63031;
        }

        .status-in_progress {
            background: #74b9ff;
            color: #0984e3;
        }

        .status-completed {
            background: #55efc4;
            color: #00b894;
        }

        .status-error {
            background: #ff7675;
            color: #d63031;
        }

        .log-section {
            background: #2d3436;
            color: #dfe6e9;
            padding: 20px;
            border-radius: 10px;
            max-height: 500px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        .log-entry {
            padding: 5px 0;
            border-bottom: 1px solid #636e72;
        }

        .log-time {
            color: #74b9ff;
        }

        .log-type {
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            margin: 0 5px;
        }

        .log-info .log-type {
            background: #0984e3;
            color: white;
        }

        .log-success .log-type {
            background: #00b894;
            color: white;
        }

        .log-warning .log-type {
            background: #fdcb6e;
            color: #2d3436;
        }

        .log-error .log-type {
            background: #d63031;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .mini-progress {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }

        .mini-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Database Migration Tool</h1>
            <p>Migration from Old System (aqary) to New System (aqary_utf8)</p>
            <p style="font-size: 0.9em; margin-top: 10px;">
                Windows-1256 → UTF-8/UTF8MB4 | MyISAM → InnoDB
            </p>
        </div>

        <div class="content">
            <?php
            // Connect to databases
            $old_db = createDatabaseConnection($old_db_config);
            $new_db = createDatabaseConnection($new_db_config);

            if (!$old_db || !$new_db) {
                echo '<div class="alert alert-error">';
                echo '<strong>Connection Error:</strong> Unable to connect to one or both databases. ';
                echo 'Please check your configuration in config.php';
                echo '</div>';
                exit;
            }

            // Create tracking table
            createTrackingTable($new_db);

            // Initialize tracking for all tables
            initializeTracking($new_db, $migration_tables);

            // Get migration statistics
            $stats = getMigrationStatistics($new_db);

            // Calculate overall progress
            $overall_progress = $stats['total_tables'] > 0
                ? round(($stats['completed_tables'] / $stats['total_tables']) * 100, 2)
                : 0;

            $row_progress = $stats['total_rows'] > 0
                ? round(($stats['migrated_rows'] / $stats['total_rows']) * 100, 2)
                : 0;
            ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $stats['total_tables']; ?></h3>
                    <p>Total Tables</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['completed_tables']; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['in_progress_tables']; ?></h3>
                    <p>In Progress</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['pending_tables']; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['error_tables']; ?></h3>
                    <p>Errors</p>
                </div>
            </div>

            <!-- Overall Progress -->
            <div class="progress-section">
                <h3>Overall Progress</h3>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: <?php echo $overall_progress; ?>%">
                        <?php echo $overall_progress; ?>%
                    </div>
                </div>
                <p style="text-align: center; color: #666; margin-top: 10px;">
                    <?php echo number_format($stats['migrated_rows']); ?> / <?php echo number_format($stats['total_rows']); ?> rows migrated
                    (<?php echo $row_progress; ?>%)
                </p>
            </div>

            <!-- Controls -->
            <div class="controls">
                <?php if (!isset($_GET['start'])): ?>
                    <a href="?start=1" class="btn btn-primary">▶️ Start Migration</a>
                    <a href="?start=1&resume=1" class="btn btn-success">🔄 Resume Migration</a>
                <?php endif; ?>
                <a href="?" class="btn btn-secondary">🔄 Refresh Status</a>
                <?php if (isset($_GET['start'])): ?>
                    <a href="?" class="btn btn-warning">⏸️ Pause</a>
                <?php endif; ?>
            </div>

            <?php if (!isset($_GET['start']) && !isset($_GET['remigrate'])): ?>
                <!-- Table Status Grid -->
                <div class="table-status">
                    <table>
                        <thead>
                            <tr>
                                <th>Table Name</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Rows</th>
                                <th>Started</th>
                                <th>Completed</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM migration_progress ORDER BY
                                    CASE status
                                        WHEN 'in_progress' THEN 1
                                        WHEN 'error' THEN 2
                                        WHEN 'pending' THEN 3
                                        WHEN 'completed' THEN 4
                                        ELSE 5
                                    END, table_name";
                            $result = $new_db->query($sql);

                            while ($row = $result->fetch_assoc()):
                                $progress_pct = $row['total_rows'] > 0
                                    ? round(($row['migrated_rows'] / $row['total_rows']) * 100, 2)
                                    : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['table_name']); ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $row['status']; ?>">
                                            <?php echo strtoupper($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="mini-progress">
                                            <div class="mini-progress-bar" style="width: <?php echo $progress_pct; ?>%"></div>
                                        </div>
                                        <?php echo $progress_pct; ?>%
                                    </td>
                                    <td>
                                        <?php echo number_format($row['migrated_rows']); ?> /
                                        <?php echo number_format($row['total_rows']); ?>
                                    </td>
                                    <td><?php echo $row['started_at'] ?? '-'; ?></td>
                                    <td><?php echo $row['completed_at'] ?? '-'; ?></td>
                                    <td>
                                        <a href="?remigrate=<?php echo urlencode($row['table_name']); ?>"
                                           class="btn btn-warning"
                                           style="padding: 8px 15px; font-size: 12px;"
                                           onclick="return confirm('Re-migrate table <?php echo htmlspecialchars($row['table_name']); ?>? This will delete existing data in this table first.');">
                                            Re-migrate
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif (isset($_GET['remigrate'])): ?>
                <!-- Single Table Re-migration -->
                <?php
                $remigrate_table = $_GET['remigrate'];

                // Validate table name is in migration list
                if (!in_array($remigrate_table, $migration_tables)) {
                    echo '<div class="alert alert-error"><strong>Error:</strong> Invalid table name.</div>';
                } else {
                ?>
                <div class="alert alert-info">
                    <strong>Re-migrating Table: <?php echo htmlspecialchars($remigrate_table); ?></strong>
                    <div class="spinner"></div>
                    Please wait while the table is re-migrated. Do not close this window.
                </div>

                <div class="log-section" id="migration-logs">
                    <?php
                    // Reset the table status first
                    $reset_sql = "UPDATE migration_progress SET
                                  status = 'pending',
                                  migrated_rows = 0,
                                  started_at = NULL,
                                  completed_at = NULL,
                                  error_message = NULL
                                  WHERE table_name = ?";
                    $stmt = $new_db->prepare($reset_sql);
                    $stmt->bind_param('s', $remigrate_table);
                    $stmt->execute();
                    $stmt->close();

                    // Delete existing data from the table in new database
                    logMessage("Clearing existing data from table: $remigrate_table", 'INFO');
                    $new_db->query("SET FOREIGN_KEY_CHECKS = 0");
                    $delete_result = $new_db->query("DELETE FROM `$remigrate_table`");
                    if ($delete_result) {
                        $deleted_rows = $new_db->affected_rows;
                        logMessage("Deleted $deleted_rows existing rows from $remigrate_table", 'INFO');
                    } else {
                        logMessage("Warning: Could not delete existing data: " . $new_db->error, 'WARNING');
                    }
                    $new_db->query("SET FOREIGN_KEY_CHECKS = 1");

                    // Re-migrate the single table
                    performSingleTableMigration($old_db, $new_db, $remigrate_table);
                    ?>
                </div>

                <div class="alert alert-success" style="margin-top: 20px;">
                    <strong>Table Re-migration Completed!</strong><br>
                    Please review the logs above and verify the migrated data.
                    <br><br>
                    <a href="?" class="btn btn-primary">View Migration Status</a>
                </div>
                <?php } ?>
            <?php else: ?>
                <!-- Migration in Progress -->
                <div class="alert alert-info">
                    <strong>Migration in Progress...</strong>
                    <div class="spinner"></div>
                    Please wait while the migration completes. Do not close this window.
                </div>

                <div class="log-section" id="migration-logs">
                    <?php
                    // Start migration
                    performMigration($old_db, $new_db, $migration_tables);
                    ?>
                </div>

                <div class="alert alert-success" style="margin-top: 20px;">
                    <strong>✅ Migration Process Completed!</strong><br>
                    Please review the logs above and verify the migrated data.
                    <br><br>
                    <a href="?" class="btn btn-primary">View Migration Status</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-scroll logs to bottom
        const logsContainer = document.getElementById('migration-logs');
        if (logsContainer) {
            logsContainer.scrollTop = logsContainer.scrollHeight;

            // Keep scrolling as new content is added
            const observer = new MutationObserver(() => {
                logsContainer.scrollTop = logsContainer.scrollHeight;
            });

            observer.observe(logsContainer, {
                childList: true,
                subtree: true
            });
        }
    </script>
</body>
</html>

<?php
/**
 * Perform the actual migration
 *
 * @param mysqli $old_db Old database connection
 * @param mysqli $new_db New database connection
 * @param array $tables List of tables to migrate
 */
function performMigration($old_db, $new_db, $tables) {
    global $performance_settings, $special_handling, $skip_tables;

    $start_time = time();

    logMessage("=== MIGRATION STARTED ===", 'INFO');
    logMessage("PHP Version: " . PHP_VERSION, 'INFO');
    logMessage("Memory Limit: " . ini_get('memory_limit'), 'INFO');
    logMessage("Batch Size: " . BATCH_SIZE . " rows", 'INFO');

    // Disable foreign key checks if configured
    if ($performance_settings['disable_foreign_keys']) {
        disableForeignKeyChecks($new_db);
        logMessage("Foreign key checks disabled", 'INFO');
    }

    $migrated_tables_count = 0;
    $skipped_tables_count = 0;
    $error_tables_count = 0;

    foreach ($tables as $table_name) {
        // Check if table should be skipped
        if (in_array($table_name, $skip_tables)) {
            logMessage("Skipping table: $table_name (in skip list)", 'INFO');
            $skipped_tables_count++;
            continue;
        }

        // Check if table exists in both databases
        if (!tableExists($old_db, $table_name)) {
            logMessage("Table $table_name does not exist in old database, skipping", 'WARNING');
            updateTableStatus($new_db, $table_name, ['status' => 'skipped']);
            $skipped_tables_count++;
            continue;
        }

        if (!tableExists($new_db, $table_name)) {
            logMessage("Table $table_name does not exist in new database, skipping", 'WARNING');
            updateTableStatus($new_db, $table_name, ['status' => 'skipped']);
            $skipped_tables_count++;
            continue;
        }

        // Get table status
        $status = getTableStatus($new_db, $table_name);

        // Skip if already completed (unless resuming)
        if ($status['status'] === 'completed' && !isset($_GET['resume'])) {
            logMessage("Table $table_name already completed, skipping", 'INFO');
            continue;
        }

        // Migrate the table
        $result = migrateTable($old_db, $new_db, $table_name);

        if ($result) {
            $migrated_tables_count++;
        } else {
            $error_tables_count++;
        }

        // Free memory
        gc_collect_cycles();
    }

    // Re-enable foreign key checks if configured
    if ($performance_settings['disable_foreign_keys']) {
        enableForeignKeyChecks($new_db);
        logMessage("Foreign key checks re-enabled", 'INFO');
    }

    $end_time = time();
    $duration = $end_time - $start_time;

    logMessage("=== MIGRATION COMPLETED ===", 'SUCCESS');
    logMessage("Total time: " . formatDuration($duration), 'INFO');
    logMessage("Tables migrated: $migrated_tables_count", 'SUCCESS');
    logMessage("Tables skipped: $skipped_tables_count", 'INFO');
    logMessage("Tables with errors: $error_tables_count", $error_tables_count > 0 ? 'WARNING' : 'INFO');
    logMessage("Peak memory usage: " . formatBytes(memory_get_peak_usage(true)), 'INFO');
}

/**
 * Migrate a single table
 *
 * @param mysqli $old_db Old database connection
 * @param mysqli $new_db New database connection
 * @param string $table_name Table name
 * @return bool Success status
 */
function migrateTable($old_db, $new_db, $table_name) {
    global $special_handling, $excluded_columns;

    $table_start_time = time();

    logMessage("", 'INFO');
    logMessage("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'INFO');
    logMessage("Migrating table: $table_name", 'INFO');
    logMessage("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'INFO');

    try {
        // Update status to in_progress
        updateTableStatus($new_db, $table_name, [
            'status' => 'in_progress',
            'started_at' => date('Y-m-d H:i:s')
        ]);

        // Get column information
        $old_columns = getTableColumns($old_db, $table_name);
        $new_columns = getTableColumns($new_db, $table_name);

        // Find common columns
        $common_columns = array_intersect($old_columns, $new_columns);

        // Remove excluded columns
        if (isset($excluded_columns[$table_name])) {
            $common_columns = array_diff($common_columns, $excluded_columns[$table_name]);
        }

        $common_columns = array_values($common_columns); // Re-index

        logMessage("Old table has " . count($old_columns) . " columns", 'INFO');
        logMessage("New table has " . count($new_columns) . " columns", 'INFO');
        logMessage("Common columns: " . count($common_columns), 'INFO');

        if (empty($common_columns)) {
            throw new Exception("No common columns found between old and new table");
        }

        // Get primary key
        $pk_columns = getTablePrimaryKey($new_db, $table_name);
        if (empty($pk_columns)) {
            logMessage("Warning: No primary key found for $table_name", 'WARNING');
        } else {
            logMessage("Primary key: " . implode(', ', $pk_columns), 'INFO');
        }

        // Count total rows
        $total_rows = countTableRows($old_db, $table_name);
        logMessage("Total rows to migrate: " . number_format($total_rows), 'INFO');

        // Update total rows in tracking
        updateTableStatus($new_db, $table_name, ['total_rows' => $total_rows]);

        if ($total_rows == 0) {
            logMessage("Table is empty, marking as completed", 'INFO');
            updateTableStatus($new_db, $table_name, [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        }

        // Migrate data in batches
        $migrated_rows = 0;
        $batch_size = BATCH_SIZE;

        for ($offset = 0; $offset < $total_rows; $offset += $batch_size) {
            $batch_start_time = microtime(true);

            // Fetch batch from old database
            $columns_str = '`' . implode('`, `', $common_columns) . '`';
            $sql = "SELECT $columns_str FROM `$table_name` LIMIT $offset, $batch_size";
            $result = $old_db->query($sql);

            if (!$result) {
                throw new Exception("Failed to fetch data: " . $old_db->error);
            }

            // Start transaction
            if ($performance_settings['use_transactions'] ?? true) {
                $new_db->begin_transaction();
            }

            $batch_count = 0;

            // Process each row
            while ($row = $result->fetch_assoc()) {
                // Build and execute INSERT query
                $insert_sql = buildInsertQuery($table_name, $common_columns, $row, $pk_columns, $new_db);

                if (!DRY_RUN) {
                    if (!$new_db->query($insert_sql)) {
                        // Log error but continue
                        logMessage("Insert error: " . $new_db->error, 'WARNING');
                    }
                }

                $batch_count++;
                $migrated_rows++;
            }

            // Commit transaction
            if ($performance_settings['use_transactions'] ?? true) {
                $new_db->commit();
            }

            // Update progress
            updateTableStatus($new_db, $table_name, [
                'migrated_rows' => $migrated_rows
            ]);

            $batch_end_time = microtime(true);
            $batch_duration = $batch_end_time - $batch_start_time;
            $rows_per_second = $batch_count / $batch_duration;

            $progress_pct = round(($migrated_rows / $total_rows) * 100, 2);
            logMessage(
                "Progress: $migrated_rows / $total_rows ($progress_pct%) - " .
                round($rows_per_second, 2) . " rows/sec",
                'INFO'
            );

            // Free result
            $result->free();
        }

        // Mark as completed
        $table_end_time = time();
        $table_duration = $table_end_time - $table_start_time;

        updateTableStatus($new_db, $table_name, [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'error_message' => null
        ]);

        logMessage("✅ Table $table_name completed in " . formatDuration($table_duration), 'SUCCESS');
        logMessage("Total rows migrated: " . number_format($migrated_rows), 'SUCCESS');

        return true;

    } catch (Exception $e) {
        $error_msg = "Error migrating $table_name: " . $e->getMessage();
        logMessage($error_msg, 'ERROR');

        updateTableStatus($new_db, $table_name, [
            'status' => 'error',
            'error_message' => $error_msg
        ]);

        return false;
    }
}

/**
 * Perform migration for a single table (used for re-migration)
 *
 * @param mysqli $old_db Old database connection
 * @param mysqli $new_db New database connection
 * @param string $table_name Table name to migrate
 */
function performSingleTableMigration($old_db, $new_db, $table_name) {
    global $performance_settings, $skip_tables;

    $start_time = time();

    logMessage("=== SINGLE TABLE RE-MIGRATION STARTED ===", 'INFO');
    logMessage("Table: $table_name", 'INFO');
    logMessage("PHP Version: " . PHP_VERSION, 'INFO');

    // Disable foreign key checks if configured
    if ($performance_settings['disable_foreign_keys']) {
        disableForeignKeyChecks($new_db);
        logMessage("Foreign key checks disabled", 'INFO');
    }

    // Check if table should be skipped
    if (in_array($table_name, $skip_tables)) {
        logMessage("Table $table_name is in skip list", 'WARNING');
        return;
    }

    // Check if table exists in both databases
    if (!tableExists($old_db, $table_name)) {
        logMessage("Table $table_name does not exist in old database", 'ERROR');
        return;
    }

    if (!tableExists($new_db, $table_name)) {
        logMessage("Table $table_name does not exist in new database", 'ERROR');
        return;
    }

    // Migrate the table
    $result = migrateTable($old_db, $new_db, $table_name);

    // Re-enable foreign key checks if configured
    if ($performance_settings['disable_foreign_keys']) {
        enableForeignKeyChecks($new_db);
        logMessage("Foreign key checks re-enabled", 'INFO');
    }

    $end_time = time();
    $duration = $end_time - $start_time;

    logMessage("=== SINGLE TABLE RE-MIGRATION COMPLETED ===", 'SUCCESS');
    logMessage("Total time: " . formatDuration($duration), 'INFO');
    logMessage("Result: " . ($result ? "SUCCESS" : "FAILED"), $result ? 'SUCCESS' : 'ERROR');
    logMessage("Peak memory usage: " . formatBytes(memory_get_peak_usage(true)), 'INFO');
}

// Close database connections when script ends
if ($old_db) $old_db->close();
if ($new_db) $new_db->close();
?>
