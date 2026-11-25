<?php
$pageTitle = 'لوحة تحكم واتساب';
$sc_title = 'لوحة تحكم واتساب';
$sc_id = "81";
include_once "../../nocash.hnt";

$canscroll = 0;
if ($edit3kod) {
    $canscroll = 0;
}
$showdhmltazkeer = 0;
include_once "../../header.hnt";



if ($db->connect_error) {
    die('<div style="padding:20px;background:red;color:white;">Database connection failed: ' . $db->connect_error . '</div>');
}

$db->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['install'])) {
    $sqlFile = __DIR__ . '/database_schema.sql';

    if (!file_exists($sqlFile)) {
        $message = 'SQL file not found: ' . $sqlFile;
        $messageType = 'danger';
    } else {
        // Read SQL file
        $sql = file_get_contents($sqlFile);

        // Remove comments and split by delimiter
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Split into statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function ($stmt) {
                return !empty($stmt);
            }
        );

        $success = true;
        $errors = array();

        foreach ($statements as $statement) {
            // Skip delimiter and use statements
            if (stripos($statement, 'DELIMITER') === 0 || stripos($statement, 'USE ') === 0) {
                continue;
            }

            // Execute statement
            if (!$db->multi_query($statement . ';')) {
                $success = false;
                $errors[] = $db->error;
            } else {
                // Clear results
                while ($db->more_results() && $db->next_result()) {
                    if ($result = $db->store_result()) {
                        $result->free();
                    }
                }
            }
        }

        if ($success) {
            $message = 'Database tables created successfully!';
            $messageType = 'success';
        } else {
            $message = 'Some errors occurred: ' . implode(', ', $errors);
            $messageType = 'warning';
        }
    }
}

// Check if tables exist
$tablesExist = false;
$result = $db->query("SHOW TABLES LIKE 'whatsapp_%'");
if ($result && $result->num_rows > 0) {
    $tablesExist = true;
    $tableCount = $result->num_rows;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Installation - تثبيت قاعدة البيانات</title>

</head>

<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="bi bi-database"></i> تثبيت قاعدة البيانات</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <h4>Database Status</h4>
                        <?php if ($tablesExist): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill"></i>
                                <strong>Tables Exist:</strong> Found <?php echo $tableCount; ?> WhatsApp tables
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                Database is ready! You can now proceed to:
                                <ul class="mt-2">
                                    <li><a href="install.hnt" class="alert-link">Install WhatsApp Service</a></li>
                                    <li><a href="index.hnt" class="alert-link">Go to Dashboard</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <strong>No tables found!</strong> You need to install the database tables.
                            </div>

                            <div class="mt-4">
                                <h5>Installation Instructions:</h5>
                                <ol>
                                    <li>Make sure you have updated the database connection details at the top of this file</li>
                                    <li>Click the button below to create all required tables</li>
                                    <li>Wait for confirmation</li>
                                </ol>

                                <form method="post" onsubmit="return confirm('Are you sure you want to install the database tables?');">
                                    <button type="submit" name="install" class="btn btn-primary btn-lg mt-3">
                                        <i class="bi bi-database-add"></i> Install Database Tables
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <hr class="my-4">

                        <h5>Database Configuration:</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">Host:</th>
                                <td><?php echo $dbHost; ?></td>
                            </tr>
                            <tr>
                                <th>Database:</th>
                                <td><?php echo $dbName; ?></td>
                            </tr>
                            <tr>
                                <th>User:</th>
                                <td><?php echo $dbUser; ?></td>
                            </tr>
                            <tr>
                                <th>Connection:</th>
                                <td><span class="badge bg-success">Connected</span></td>
                            </tr>
                        </table>

                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> If you need to change database settings, edit the variables at the top of this file (database_install.php)
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

   
</body>

</html>
