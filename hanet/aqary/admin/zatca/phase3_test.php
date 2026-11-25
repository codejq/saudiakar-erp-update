<?php
/**
 * Phase 3 - ZATCA API Submission & Clearance Testing
 *
 * Submit signed invoices to ZATCA and handle responses
 * Open: http://localhost/admin/zatca/phase3_test.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/config/phase2_config.php';
require_once __DIR__ . '/phase2/api/ZatcaAPIClient.php';
require_once __DIR__ . '/phase2/integration/Phase2Manager.php';
require_once __DIR__ . '/lib/InvoiceHelper.php';

// Get database connection - using credentials from root system configuration
$qdb_server = "localhost";
$qdb_port = "3329";  // Default development port
$qdb_user = "root";
$qdb_pass = "1";
$qdb_db = "aqary_utf";  // Default database

// Try different configurations
$db = null;

// Try 1: Port 3329 with aqary_utf
$db = @mysqli_connect($qdb_server, $qdb_user, $qdb_pass, $qdb_db, $qdb_port);

// Try 2: Port 3329 with accountingdb
if (!$db) {
    $db = @mysqli_connect($qdb_server, $qdb_user, $qdb_pass, "accountingdb", $qdb_port);
}

// Try 3: Port 3306 with no password, aqary_utf
if (!$db) {
    $qdb_port = "3306";
    $qdb_pass = "";
    $db = @mysqli_connect($qdb_server, $qdb_user, $qdb_pass, "aqary_utf", $qdb_port);
}

// Try 4: Port 3306 with no password, accountingdb
if (!$db) {
    $db = @mysqli_connect($qdb_server, $qdb_user, $qdb_pass, "accountingdb", $qdb_port);
}

if (!$db) {
    die('<div style="color: red; padding: 20px; font-family: Arial;">
    <h2>❌ Database Connection Failed</h2>
    <p>Could not connect to database. Error: ' . htmlspecialchars(mysqli_connect_error()) . '</p>
    </div>');
}

// Determine test type
$test = isset($_GET['test']) ? $_GET['test'] : 'menu';
$idsanad = isset($_GET['idsanad']) ? intval($_GET['idsanad']) : 0;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Phase 3 - ZATCA API Submission Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 2px solid #2196F3; padding-bottom: 10px; }
        h2 { color: #2196F3; margin-top: 30px; }
        .test { margin: 20px 0; padding: 15px; border-left: 4px solid #ddd; }
        .test.success { border-left-color: #4CAF50; background: #f1f8e9; }
        .test.error { border-left-color: #f44336; background: #ffebee; }
        .test.info { border-left-color: #2196F3; background: #e3f2fd; }
        .test.warning { border-left-color: #ff9800; background: #fff3e0; }
        .button { display: inline-block; padding: 10px 20px; margin: 5px 0; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .button:hover { background: #1976d2; }
        .button.success { background: #4CAF50; }
        .button.success:hover { background: #45a049; }
        .button.danger { background: #f44336; }
        .button.danger:hover { background: #da190b; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; border: 1px solid #ddd; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New'; }
        .invoice-select { padding: 8px; margin: 10px 0; }
        .details { margin-top: 10px; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 3px; font-size: 14px; }
        .response-data { background: #f0f0f0; padding: 10px; border-left: 4px solid #2196F3; margin: 10px 0; border-radius: 3px; font-family: 'Courier New'; font-size: 12px; max-height: 400px; overflow-y: auto; }
        .error-msg { color: #d32f2f; font-weight: bold; }
        .success-msg { color: #388e3c; font-weight: bold; }
        .info-msg { color: #1976d2; font-weight: bold; }
        .table-data { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .table-data th, .table-data td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .table-data th { background: #f0f0f0; font-weight: bold; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .menu-item { padding: 20px; background: #e3f2fd; border: 1px solid #2196F3; border-radius: 5px; text-align: center; }
        .menu-item h3 { color: #2196F3; margin-top: 0; }
        .menu-item a { display: inline-block; margin-top: 10px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 Phase 3 - ZATCA API Submission & Clearance</h1>
    <p>Submit signed invoices to ZATCA API and handle clearance responses.</p>

    <?php

    if ($test === 'menu') {
        // Main menu
        echo '<h2>📋 Select Test Option</h2>';
        echo '<div class="menu-grid">';

        echo '<div class="menu-item">';
        echo '<h3>1️⃣ View Phase 2 Data</h3>';
        echo '<p>Check which invoices are ready for Phase 3</p>';
        echo '<a class="button" href="?test=list">View Invoices</a>';
        echo '</div>';

        echo '<div class="menu-item">';
        echo '<h3>2️⃣ Compliance Validation</h3>';
        echo '<p>Submit invoice to /compliance/invoices (Required before Production CSID)</p>';
        echo '<a class="button success" href="?test=compliance">Validate Compliance</a>';
        echo '</div>';

        echo '<div class="menu-item">';
        echo '<h3>3️⃣ Submit to ZATCA</h3>';
        echo '<p>Submit signed invoice to ZATCA Production API</p>';
        echo '<a class="button success" href="?test=submit">Submit Invoice</a>';
        echo '</div>';

        echo '<div class="menu-item">';
        echo '<h3>4️⃣ API Connectivity Test</h3>';
        echo '<p>Test ZATCA API connection status</p>';
        echo '<a class="button info" href="?test=api_test">Test Connection</a>';
        echo '</div>';

        echo '<div class="menu-item">';
        echo '<h3>5️⃣ Environment Info</h3>';
        echo '<p>Check Phase 3 configuration</p>';
        echo '<a class="button info" href="?test=config">View Config</a>';
        echo '</div>';

        echo '</div>';

    } elseif ($test === 'list') {
        // List signed invoices ready for submission
        echo '<h2>📋 Phase 2 Invoices Ready for Phase 3</h2>';

        try {
            $sql = "SELECT idsanad, sanadrakam, sanaddate, zatca_uuid, zatca_invoice_hash
                    FROM sanad
                    WHERE zatca_signed_xml IS NOT NULL
                    AND zatca_uuid IS NOT NULL
                    ORDER BY idsanad DESC LIMIT 10";

            $result = mysqli_query($db, $sql);

            if (!$result || mysqli_num_rows($result) === 0) {
                echo '<div class="test warning">';
                echo '<p><span class="warning">⚠️ No signed invoices found</span></p>';
                echo '<p>First run Phase 2 to generate and sign invoices</p>';
                echo '</div>';
            } else {
                echo '<div class="test success">';
                echo '<p><span class="success-msg">✅ Found ' . mysqli_num_rows($result) . ' signed invoices</span></p>';
                echo '</div>';

                echo '<table class="table-data">';
                echo '<thead><tr>';
                echo '<th>Invoice ID</th>';
                echo '<th>Invoice #</th>';
                echo '<th>Date</th>';
                echo '<th>UUID</th>';
                echo '<th>Action</th>';
                echo '</tr></thead><tbody>';

                while ($row = mysqli_fetch_array($result)) {
                    echo '<tr>';
                    echo '<td>' . $row['idsanad'] . '</td>';
                    echo '<td>' . htmlspecialchars($row['sanadrakam'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($row['sanaddate'] ?? 'N/A') . '</td>';
                    echo '<td><code>' . substr($row['zatca_uuid'] ?? '', 0, 20) . '...</code></td>';
                    echo '<td><a class="button success" href="?test=submit&idsanad=' . $row['idsanad'] . '">Submit</a></td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            }
        } catch (Exception $e) {
            echo '<div class="test error">';
            echo '<p><span class="error-msg">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
            echo '</div>';
        }

        echo '<p><a class="button" href="?test=menu">← Back to Menu</a></p>';

    } elseif ($test === 'compliance') {
        // Validate invoice compliance
        echo '<h2>✅ Compliance Validation</h2>';
        echo '<div class="test info">';
        echo '<p><strong>⚠️ Important:</strong> This test submits invoices to <code>/compliance/invoices</code> endpoint.</p>';
        echo '<p>You MUST pass compliance validation before requesting Production CSID.</p>';
        echo '</div>';

        // If no idsanad provided, show selection
        if (!$idsanad) {
            echo '<div class="section">';
            echo '<p>Select an invoice to validate:</p>';

            $sql = "SELECT idsanad, sanadrakam FROM sanad
                    WHERE zatca_signed_xml IS NOT NULL
                    AND zatca_uuid IS NOT NULL
                    ORDER BY idsanad DESC LIMIT 20";

            $result = mysqli_query($db, $sql);

            if ($result && mysqli_num_rows($result) > 0) {
                echo '<select class="invoice-select" onchange="window.location=\'?test=compliance&idsanad=\'+this.value">';
                echo '<option value="">-- Select Invoice --</option>';

                while ($row = mysqli_fetch_array($result)) {
                    echo '<option value="' . $row['idsanad'] . '">';
                    echo 'ID: ' . $row['idsanad'] . ' | Invoice: ' . htmlspecialchars($row['sanadrakam']) . '</option>';
                }

                echo '</select>';
            } else {
                echo '<div class="test warning">';
                echo '<p><span class="warning">⚠️ No invoices available for validation</span></p>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            // Perform compliance validation
            try {
                echo '<div class="test info">';
                echo '<p>🔄 Processing invoice for compliance: ' . $idsanad . '</p>';
                echo '</div>';

                // Get signed invoice data
                $sql = "SELECT zatca_signed_xml, zatca_invoice_hash, zatca_uuid, sanadrakam FROM sanad
                        WHERE idsanad = " . intval($idsanad);

                $result = mysqli_query($db, $sql);

                if (!$result || mysqli_num_rows($result) === 0) {
                    throw new Exception("Invoice not found: $idsanad");
                }

                $row = mysqli_fetch_array($result);
                $signedXml = $row['zatca_signed_xml'];
                $hash = $row['zatca_invoice_hash'];
                $uuid = $row['zatca_uuid'];

                if (!$signedXml || !$hash || !$uuid) {
                    throw new Exception("Incomplete Phase 2 data for invoice $idsanad");
                }

                echo '<div class="test">';
                echo '<p><strong>Invoice #:</strong> ' . htmlspecialchars($row['sanadrakam']) . '</p>';
                echo '<p><strong>UUID:</strong> <code>' . htmlspecialchars($uuid) . '</code></p>';
                echo '<p><strong>Hash:</strong> <code>' . substr(htmlspecialchars($hash), 0, 50) . '...</code></p>';
                echo '<p><strong>XML Size:</strong> ' . strlen($signedXml) . ' bytes</p>';
                echo '</div>';

                // Check if we have compliance credentials
                echo '<div class="test">';
                echo '<p>🔐 Loading Compliance Certificate...</p>';

                // Load sandbox certificate and secret
                $certFile = ZatcaPhase2Config::CERT_DIR . '/sandbox_certificate.pem';
                $secretFile = ZatcaPhase2Config::CERT_DIR . '/sandbox_secret.txt';

                if (!file_exists($certFile) || !file_exists($secretFile)) {
                    throw new Exception("Sandbox certificate or secret not found. Generate certificates first.");
                }

                $certificate = base64_encode(file_get_contents($certFile));
                $secret = trim(file_get_contents($secretFile));

                echo '<p><span class="success-msg">✅ Compliance credentials loaded</span></p>';
                echo '<p><strong>Certificate:</strong> ' . substr($certificate, 0, 40) . '...</p>';
                echo '<p><strong>Secret:</strong> ' . substr($secret, 0, 20) . '...</p>';
                echo '</div>';

                // Submission button
                echo '<form method="POST">';
                echo '<input type="hidden" name="action" value="do_compliance">';
                echo '<input type="hidden" name="idsanad" value="' . $idsanad . '">';
                echo '<button class="button success" type="submit">▶️ Submit to Compliance Endpoint</button>';
                echo '</form>';

            } catch (Exception $e) {
                echo '<div class="test error">';
                echo '<p><span class="error-msg">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
                echo '</div>';
            }
        }

        // Handle actual compliance submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'do_compliance') {
            $submitId = intval($_POST['idsanad']);

            try {
                echo '<h3>🔄 Validating Compliance...</h3>';

                // Get invoice data
                $sql = "SELECT zatca_signed_xml, zatca_invoice_hash, zatca_uuid, sanadrakam FROM sanad
                        WHERE idsanad = " . $submitId;

                $result = mysqli_query($db, $sql);
                if (!$result || mysqli_num_rows($result) === 0) {
                    throw new Exception("Invoice not found");
                }

                $row = mysqli_fetch_array($result);
                $signedXml = $row['zatca_signed_xml'];
                $hash = $row['zatca_invoice_hash'];
                $uuid = $row['zatca_uuid'];

                // Load sandbox certificate and secret
                $certFile = ZatcaPhase2Config::CERT_DIR . '/sandbox_certificate.pem';
                $secretFile = ZatcaPhase2Config::CERT_DIR . '/sandbox_secret.txt';

                $certificate = base64_encode(file_get_contents($certFile));
                $secret = trim(file_get_contents($secretFile));

                // Create API client with compliance credentials
                $apiClient = new ZatcaAPIClient();
                $apiClient->setCredentials($certificate, $secret);

                // Submit to compliance endpoint
                echo '<div class="test info">';
                echo '<p>📤 Submitting to: <code>' . ZatcaPhase2Config::getAPIBaseURL() . ZatcaPhase2Config::API_PATH_COMPLIANCE_CHECK . '</code></p>';
                echo '</div>';

                $apiResult = $apiClient->validateCompliance($hash, $uuid, $signedXml);

                // Display result
                if ($apiResult['success']) {
                    echo '<div class="test success">';
                    echo '<p><span class="success-msg">✅ Compliance validation passed!</span></p>';
                    echo '<div class="details">';
                    echo '<p><strong>Status:</strong> ' . htmlspecialchars($apiResult['status'] ?? 'PASSED') . '</p>';

                    if (isset($apiResult['validation_results'])) {
                        echo '<p><strong>Validation Results:</strong></p>';
                        echo '<div class="response-data">';
                        echo htmlspecialchars(json_encode($apiResult['validation_results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        echo '</div>';
                    }

                    echo '</div>';
                    echo '</div>';

                    echo '<div class="test info">';
                    echo '<p><strong>✅ Next Steps:</strong></p>';
                    echo '<ol>';
                    echo '<li>Submit more sample invoices if required (check ZATCA requirements)</li>';
                    echo '<li>Once all compliance checks pass, request Production CSID</li>';
                    echo '<li>Use Production CSID for actual invoice submissions</li>';
                    echo '</ol>';
                    echo '</div>';

                    // Show full response for debugging
                    echo '<h3>📊 Full API Response</h3>';
                    echo '<div class="response-data">';
                    echo htmlspecialchars(json_encode($apiResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    echo '</div>';

                } else {
                    echo '<div class="test error">';
                    echo '<p><span class="error-msg">❌ Compliance validation failed</span></p>';
                    echo '<p><strong>Error:</strong> ' . htmlspecialchars($apiResult['error'] ?? 'Unknown error') . '</p>';

                    if (isset($apiResult['http_code'])) {
                        echo '<p><strong>HTTP Code:</strong> ' . $apiResult['http_code'] . '</p>';
                    }

                    if (isset($apiResult['validation_results'])) {
                        echo '<p><strong>Validation Results:</strong></p>';
                        echo '<div class="response-data">';
                        echo htmlspecialchars(json_encode($apiResult['validation_results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        echo '</div>';
                    }

                    echo '</div>';

                    // Show full response for debugging
                    echo '<h3>📊 Full API Response</h3>';
                    echo '<div class="response-data">';
                    echo htmlspecialchars(json_encode($apiResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    echo '</div>';
                }

            } catch (Exception $e) {
                echo '<div class="test error">';
                echo '<p><span class="error-msg">❌ Exception: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
                echo '</div>';
            }
        }

        echo '<p><a class="button" href="?test=menu">← Back to Menu</a></p>';

    } elseif ($test === 'submit') {
        // Submit invoice to ZATCA
        echo '<h2>📤 Submit Invoice to ZATCA</h2>';

        // If no idsanad provided, show selection
        if (!$idsanad) {
            echo '<div class="section">';
            echo '<p>Select an invoice to submit:</p>';

            $sql = "SELECT idsanad, sanadrakam FROM sanad
                    WHERE zatca_signed_xml IS NOT NULL
                    AND zatca_uuid IS NOT NULL
                    ORDER BY idsanad DESC LIMIT 20";

            $result = mysqli_query($db, $sql);

            if ($result && mysqli_num_rows($result) > 0) {
                echo '<select class="invoice-select" onchange="window.location=\'?test=submit&idsanad=\'+this.value">';
                echo '<option value="">-- Select Invoice --</option>';

                while ($row = mysqli_fetch_array($result)) {
                    echo '<option value="' . $row['idsanad'] . '">';
                    echo 'ID: ' . $row['idsanad'] . ' | Invoice: ' . htmlspecialchars($row['sanadrakam']) . '</option>';
                }

                echo '</select>';
            } else {
                echo '<div class="test warning">';
                echo '<p><span class="warning">⚠️ No invoices available for submission</span></p>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            // Perform submission
            try {
                echo '<div class="test info">';
                echo '<p>🔄 Processing invoice: ' . $idsanad . '</p>';
                echo '</div>';

                // Get Phase2Manager
                $manager = new Phase2Manager($db);

                // Get signed invoice data
                $sql = "SELECT zatca_signed_xml, zatca_invoice_hash, zatca_uuid, sanadrakam FROM sanad
                        WHERE idsanad = " . intval($idsanad);

                $result = mysqli_query($db, $sql);

                if (!$result || mysqli_num_rows($result) === 0) {
                    throw new Exception("Invoice not found: $idsanad");
                }

                $row = mysqli_fetch_array($result);
                $signedXml = $row['zatca_signed_xml'];
                $hash = $row['zatca_invoice_hash'];
                $uuid = $row['zatca_uuid'];

                if (!$signedXml || !$hash || !$uuid) {
                    throw new Exception("Incomplete Phase 2 data for invoice $idsanad");
                }

                echo '<div class="test">';
                echo '<p><strong>Invoice #:</strong> ' . htmlspecialchars($row['sanadrakam']) . '</p>';
                echo '<p><strong>UUID:</strong> <code>' . htmlspecialchars($uuid) . '</code></p>';
                echo '<p><strong>Hash:</strong> <code>' . substr(htmlspecialchars($hash), 0, 50) . '...</code></p>';
                echo '<p><strong>XML Size:</strong> ' . strlen($signedXml) . ' bytes</p>';
                echo '</div>';

                // Test API connectivity first
                echo '<div class="test">';
                echo '<p>🔗 Testing API connectivity...</p>';

                $apiClient = new ZatcaAPIClient();
                $connected = $apiClient->testConnection();

                if (!$connected) {
                    echo '<div class="test warning">';
                    echo '<p><span class="warning">⚠️ Cannot reach ZATCA API</span></p>';
                    echo '<p>Environment: ' . ZatcaPhase2Config::$ENVIRONMENT . '</p>';
                    echo '<p>Base URL: ' . ZatcaPhase2Config::getAPIBaseURL() . '</p>';
                    echo '</div>';
                } else {
                    echo '<p><span class="success-msg">✅ API is reachable</span></p>';
                }

                echo '</div>';

                // Check if we have production credentials
                echo '<div class="test">';
                echo '<p>🔐 Checking credentials...</p>';

                if (!ZatcaPhase2Config::PRODUCTION_CERTIFICATE || !ZatcaPhase2Config::PRODUCTION_SECRET) {
                    echo '<div class="test warning">';
                    echo '<p><span class="warning">⚠️ Production credentials not configured</span></p>';
                    echo '<p>For production submission, configure:</p>';
                    echo '<ul>';
                    echo '<li>PRODUCTION_CERTIFICATE</li>';
                    echo '<li>PRODUCTION_SECRET</li>';
                    echo '</ul>';
                    echo '<p>You can test with compliance credentials first.</p>';
                    echo '</div>';
                } else {
                    echo '<p><span class="success-msg">✅ Production credentials available</span></p>';
                }

                echo '</div>';

                // Prepare submission
                echo '<div class="test info">';
                echo '<p>📋 Ready to submit. Here\'s what will happen:</p>';
                echo '<ol>';
                echo '<li>XML will be base64 encoded</li>';
                echo '<li>Hash will be sent as invoiceHash</li>';
                echo '<li>UUID will be included</li>';
                echo '<li>Request will be sent to ZATCA API</li>';
                echo '<li>Response will be parsed and displayed</li>';
                echo '</ol>';
                echo '</div>';

                // Submission button
                echo '<form method="POST">';
                echo '<input type="hidden" name="action" value="do_submit">';
                echo '<input type="hidden" name="idsanad" value="' . $idsanad . '">';
                echo '<button class="button success" type="submit">▶️ Submit to ZATCA</button>';
                echo '</form>';

            } catch (Exception $e) {
                echo '<div class="test error">';
                echo '<p><span class="error-msg">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
                echo '</div>';
            }
        }

        // Handle actual submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'do_submit') {
            $submitId = intval($_POST['idsanad']);

            try {
                echo '<h3>🔄 Submitting Invoice...</h3>';

                // Get invoice data
                $sql = "SELECT zatca_signed_xml, zatca_invoice_hash, zatca_uuid, sanadrakam FROM sanad
                        WHERE idsanad = " . $submitId;

                $result = mysqli_query($db, $sql);
                if (!$result || mysqli_num_rows($result) === 0) {
                    throw new Exception("Invoice not found");
                }

                $row = mysqli_fetch_array($result);
                $signedXml = $row['zatca_signed_xml'];
                $hash = $row['zatca_invoice_hash'];
                $uuid = $row['zatca_uuid'];

                // Create API client with production credentials
                $apiClient = new ZatcaAPIClient();
                if (ZatcaPhase2Config::PRODUCTION_CERTIFICATE && ZatcaPhase2Config::PRODUCTION_SECRET) {
                    $apiClient->setCredentials(
                        ZatcaPhase2Config::PRODUCTION_CERTIFICATE,
                        ZatcaPhase2Config::PRODUCTION_SECRET
                    );
                }

                // Submit to ZATCA
                $apiResult = $apiClient->clearInvoice($hash, $uuid, $signedXml);

                // Display result
                if ($apiResult['success']) {
                    echo '<div class="test success">';
                    echo '<p><span class="success-msg">✅ Invoice submitted successfully!</span></p>';
                    echo '<div class="details">';
                    echo '<p><strong>Response HTTP Code:</strong> ' . $apiResult['http_code'] . '</p>';
                    echo '<p><strong>Status:</strong> ' . htmlspecialchars($apiResult['cleared_invoice'] ?? 'CLEARED') . '</p>';

                    if (isset($apiResult['validation_results'])) {
                        echo '<p><strong>Validation Results:</strong></p>';
                        echo '<div class="response-data">';
                        echo htmlspecialchars(json_encode($apiResult['validation_results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        echo '</div>';
                    }

                    echo '</div>';
                    echo '</div>';

                    // Show full response for debugging
                    echo '<h3>📊 Full API Response</h3>';
                    echo '<div class="response-data">';
                    echo htmlspecialchars(json_encode($apiResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    echo '</div>';

                } else {
                    echo '<div class="test error">';
                    echo '<p><span class="error-msg">❌ Submission failed</span></p>';
                    echo '<p><strong>Error:</strong> ' . htmlspecialchars($apiResult['error'] ?? 'Unknown error') . '</p>';

                    if (isset($apiResult['http_code'])) {
                        echo '<p><strong>HTTP Code:</strong> ' . $apiResult['http_code'] . '</p>';
                    }

                    if (isset($apiResult['validation_results'])) {
                        echo '<p><strong>Validation Results:</strong></p>';
                        echo '<div class="response-data">';
                        echo htmlspecialchars(json_encode($apiResult['validation_results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        echo '</div>';
                    }

                    echo '</div>';

                    // Show full response for debugging
                    echo '<h3>📊 Full API Response</h3>';
                    echo '<div class="response-data">';
                    echo htmlspecialchars(json_encode($apiResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    echo '</div>';
                }

            } catch (Exception $e) {
                echo '<div class="test error">';
                echo '<p><span class="error-msg">❌ Exception: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
                echo '</div>';
            }
        }

        echo '<p><a class="button" href="?test=menu">← Back to Menu</a></p>';

    } elseif ($test === 'api_test') {
        // Test API connectivity
        echo '<h2>🔗 API Connectivity Test</h2>';

        try {
            $apiClient = new ZatcaAPIClient();

            echo '<div class="section">';
            echo '<h3>Configuration</h3>';
            echo '<p><strong>Environment:</strong> ' . ZatcaPhase2Config::$ENVIRONMENT . '</p>';
            echo '<p><strong>Base URL:</strong> ' . ZatcaPhase2Config::getAPIBaseURL() . '</p>';
            echo '<p><strong>API Timeout:</strong> ' . ZatcaPhase2Config::API_TIMEOUT . 'ms</p>';
            echo '</div>';

            echo '<div class="test">';
            echo '<p>🔄 Testing connection to ZATCA API...</p>';

            $connected = $apiClient->testConnection();

            if ($connected) {
                echo '<div class="test success">';
                echo '<p><span class="success-msg">✅ ZATCA API is reachable</span></p>';
                echo '<p>Connection successful! API is ready for submissions.</p>';
                echo '</div>';
            } else {
                echo '<div class="test error">';
                echo '<p><span class="error-msg">❌ Cannot reach ZATCA API</span></p>';
                echo '<p>Check your network and environment configuration.</p>';
                echo '</div>';
            }

            echo '</div>';

            // Show endpoints
            echo '<div class="section">';
            echo '<h3>API Endpoints</h3>';
            echo '<table class="table-data">';
            echo '<tr><th>Endpoint</th><th>Path</th></tr>';
            echo '<tr><td>Compliance CSID</td><td><code>' . ZatcaPhase2Config::API_PATH_COMPLIANCE_CSID . '</code></td></tr>';
            echo '<tr><td>Production CSID</td><td><code>' . ZatcaPhase2Config::API_PATH_PRODUCTION_CSID . '</code></td></tr>';
            echo '<tr><td>Clearance</td><td><code>' . ZatcaPhase2Config::API_PATH_CLEARANCE . '</code></td></tr>';
            echo '<tr><td>Reporting</td><td><code>' . ZatcaPhase2Config::API_PATH_REPORTING . '</code></td></tr>';
            echo '</table>';
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="test error">';
            echo '<p><span class="error-msg">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
            echo '</div>';
        }

        echo '<p><a class="button" href="?test=menu">← Back to Menu</a></p>';

    } elseif ($test === 'config') {
        // Show configuration
        echo '<h2>⚙️ Phase 3 Configuration</h2>';

        $config = [
            'Environment' => ZatcaPhase2Config::$ENVIRONMENT,
            'Base URL' => ZatcaPhase2Config::getAPIBaseURL(),
            'API Enabled' => ZatcaPhase2Config::API_INTEGRATION_ENABLED ? 'YES' : 'NO',
            'API Timeout' => ZatcaPhase2Config::API_TIMEOUT . 'ms',
            'Log Directory' => ZatcaPhase2Config::LOG_DIR,
            'Has Production Cert' => !empty(ZatcaPhase2Config::PRODUCTION_CERTIFICATE) ? 'YES' : 'NO',
            'Has Production Secret' => !empty(ZatcaPhase2Config::PRODUCTION_SECRET) ? 'YES' : 'NO'
        ];

        echo '<div class="section">';
        echo '<table class="table-data">';
        echo '<tr><th>Setting</th><th>Value</th></tr>';

        foreach ($config as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($key) . '</strong></td>';
            echo '<td>' . htmlspecialchars($value) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';

        // Check logs
        echo '<h3>📝 Recent Logs</h3>';
        $logFile = ZatcaPhase2Config::LOG_DIR . '/zatca_logs.txt';

        if (file_exists($logFile)) {
            echo '<div class="section">';

            $lines = file($logFile);
            $recent = array_slice($lines, -20); // Last 20 lines

            echo '<p><strong>Last 20 log entries:</strong></p>';
            echo '<div class="response-data">';

            foreach ($recent as $line) {
                echo htmlspecialchars($line);
            }

            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="test info">';
            echo '<p>No logs found yet</p>';
            echo '</div>';
        }

        echo '<p><a class="button" href="?test=menu">← Back to Menu</a></p>';
    }

    ?>

</div>

</body>
</html>
