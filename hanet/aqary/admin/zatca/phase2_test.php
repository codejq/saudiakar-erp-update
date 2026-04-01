<?php
/**
 * ZATCA Phase 2 Testing Tool
 *
 * Comprehensive testing interface for Phase 2 components
 *
 * @charset UTF-8
 * @version 2.0
 */
$sc_title = "🧪 أداة اختبار المرحلة الثانية - ZATCA";
$sc_id = "153";

if ($_SERVER['REQUEST_METHOD'] != 'POST' && !isset($_POST['action'])) {
    include_once "../../nocash.hnt";
    include_once "../../header.hnt";
} else {
    include_once "../../reqloginajax.hnt";
}

require_once __DIR__ . '/../../connectdb.hnt';
require_once __DIR__ . '/config/phase2_config.php';
require_once __DIR__ . '/phase2/tools/TestInvoiceGenerator.php';
require_once __DIR__ . '/phase2/tools/XMLValidator.php';
require_once __DIR__ . '/phase2/integration/Phase2Manager.php';

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار المرحلة الثانية - ZATCA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            border-right: 4px solid #3498db;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #2980b9;
        }
        .code {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: right;
        }
        table th {
            background: #3498db;
            color: white;
        }
        .status-ok {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
        }
        .status-error {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 أداة اختبار المرحلة الثانية - ZATCA</h1>

        <div style="margin-bottom: 20px;">
            <a href="index.php" style="background: #059669; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                ← العودة الي الخيارات
            </a>
        </div>

        <div class="info">
            <strong>أداة اختبار شاملة للمرحلة الثانية</strong><br>
            اختبر جميع مكونات المرحلة الثانية: XML، التوقيع الرقمي، API
        </div>

        <?php
        // Test 1: Generate Test Invoice
        if (isset($_GET['test']) && $_GET['test'] === 'generate') {
            echo '<div class="test-section">';
            echo '<h2>اختبار 1: توليد فاتورة تجريبية</h2>';

            $type = $_GET['type'] ?? 'standard';
            $testData = TestInvoiceGenerator::generateByType($type);

            if ($testData) {
                echo '<div class="success">✅ تم توليد فاتورة تجريبية: ' . $testData['invoice_number'] . '</div>';
                echo '<div class="code">' . htmlspecialchars(json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</div>';
            } else {
                echo '<div class="error">❌ فشل في توليد الفاتورة</div>';
            }
            echo '</div>';
        }

        // Test 2: Generate XML
        if (isset($_GET['test']) && $_GET['test'] === 'xml') {
            echo '<div class="test-section">';
            echo '<h2>اختبار 2: توليد XML</h2>';

            require_once __DIR__ . '/phase2/xml/UBLInvoiceGenerator.php';

            $testData = TestInvoiceGenerator::generateStandardInvoice();
            $generator = new UBLInvoiceGenerator();

            try {
                $xml = $generator->generateXML($testData);
                echo '<div class="success">✅ تم توليد XML بنجاح</div>';
                echo '<div class="info">حجم XML: ' . strlen($xml) . ' بايت</div>';
                echo '<div class="code">' . htmlspecialchars($xml) . '</div>';
            } catch (Exception $e) {
                echo '<div class="error">❌ خطأ: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            echo '</div>';
        }

        // Test 3: Validate XML
        if (isset($_GET['test']) && $_GET['test'] === 'validate') {
            echo '<div class="test-section">';
            echo '<h2>اختبار 3: التحقق من صحة XML</h2>';

            require_once __DIR__ . '/phase2/xml/UBLInvoiceGenerator.php';

            $testData = TestInvoiceGenerator::generateStandardInvoice();
            $generator = new UBLInvoiceGenerator();
            $xml = $generator->generateXML($testData);

            $validator = new XMLValidator();
            $result = $validator->validateInvoice($xml);

            if ($result['valid']) {
                echo '<div class="success">✅ XML صحيح - لا توجد أخطاء</div>';
            } else {
                echo '<div class="error">❌ XML يحتوي على أخطاء (' . $result['error_count'] . ')</div>';
                echo '<ul>';
                foreach ($result['errors'] as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul>';
            }

            if (!empty($result['warnings'])) {
                echo '<div class="warning">⚠️ تحذيرات (' . $result['warning_count'] . ')</div>';
                echo '<ul>';
                foreach ($result['warnings'] as $warning) {
                    echo '<li>' . htmlspecialchars($warning) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }

        // Test 4: Sign Invoice
        if (isset($_GET['test']) && $_GET['test'] === 'sign') {
            echo '<div class="test-section">';
            echo '<h2>اختبار 4: التوقيع الرقمي</h2>';

            if (!ZatcaPhase2Config::certificatesExist()) {
                echo '<div class="error">❌ الشهادات غير موجودة. يرجى توليد الشهادات أولاً.</div>';
            } else {
                require_once __DIR__ . '/phase2/xml/UBLInvoiceGenerator.php';
                require_once __DIR__ . '/phase2/signature/InvoiceSigner.php';

                $testData = TestInvoiceGenerator::generateStandardInvoice();
                $generator = new UBLInvoiceGenerator();
                $xml = $generator->generateXML($testData);

                try {
                    $signer = new InvoiceSigner();
                    $result = $signer->signInvoice($xml);

                    if ($result['success']) {
                        echo '<div class="success">✅ تم التوقيع بنجاح</div>';
                        echo '<div class="info">';
                        echo 'Hash: ' . substr($result['hash'], 0, 40) . '...<br>';
                        echo 'حجم التوقيع: ' . strlen($result['signature']) . ' بايت<br>';
                        echo 'حجم XML الموقع: ' . strlen($result['signed_xml']) . ' بايت';
                        echo '</div>';
                    } else {
                        echo '<div class="error">❌ فشل التوقيع: ' . htmlspecialchars($result['error']) . '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">❌ خطأ: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            echo '</div>';
        }

        // Test 5: Complete Workflow
        if (isset($_GET['test']) && $_GET['test'] === 'workflow') {
            echo '<div class="test-section">';
            echo '<h2>اختبار 5: سير العمل الكامل</h2>';

            if (!ZatcaPhase2Config::certificatesExist()) {
                echo '<div class="error">❌ الشهادات غير موجودة</div>';
            } else {
                $manager = new Phase2Manager($link);

                // Create a test sanad
                $sql = "SELECT idsanad FROM sanad WHERE depositeamount > 0 LIMIT 1";
                $result = mysql_query($sql, $link);

                if ($result && mysql_num_rows($result) > 0) {
                    $row = mysql_fetch_array($result);
                    $idsanad = $row['idsanad'];

                    echo '<div class="info">اختبار مع سند رقم: ' . $idsanad . '</div>';

                    try {
                        $processResult = $manager->processSanad($idsanad, false);

                        if ($processResult['success']) {
                            echo '<div class="success">✅ تم معالجة الفاتورة بنجاح</div>';
                            echo '<table>';
                            echo '<tr><th>البيان</th><th>القيمة</th></tr>';
                            echo '<tr><td>رقم الفاتورة</td><td>' . $processResult['invoice_number'] . '</td></tr>';
                            echo '<tr><td>UUID</td><td>' . $processResult['uuid'] . '</td></tr>';
                            echo '<tr><td>العداد</td><td>' . $processResult['counter'] . '</td></tr>';
                            echo '<tr><td>Hash</td><td>' . substr($processResult['hash'], 0, 40) . '...</td></tr>';
                            echo '<tr><td>PIH</td><td>' . substr($processResult['previous_hash'], 0, 40) . '...</td></tr>';
                            echo '</table>';
                        } else {
                            echo '<div class="error">❌ فشلت المعالجة: ' . htmlspecialchars($processResult['error']) . '</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="error">❌ خطأ: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                } else {
                    echo '<div class="warning">⚠️ لا توجد سندات للاختبار</div>';
                }
            }
            echo '</div>';
        }

        // Test 6: Submit to Compliance Endpoint
        if (isset($_GET['test']) && $_GET['test'] === 'compliance') {
            echo '<div class="test-section">';
            echo '<h2>اختبار 6: إرسال إلى Compliance Endpoint</h2>';

            echo '<div class="info">';
            echo '<strong>⚠️ مهم جداً:</strong><br>';
            echo 'هذا الاختبار يرسل الفاتورة إلى <code>/compliance/invoices</code><br>';
            echo 'يجب أن تنجح في هذا الاختبار قبل طلب Production CSID';
            echo '</div>';

            if (!ZatcaPhase2Config::certificatesExist()) {
                echo '<div class="error">❌ الشهادات غير موجودة. يرجى توليد الشهادات أولاً.</div>';
            } else {
                require_once __DIR__ . '/phase2/integration/Phase2Manager.php';
                require_once __DIR__ . '/phase2/api/ZatcaAPIClient.php';
                require_once __DIR__ . '/phase2/signature/HashGenerator.php';

                try {
                    $manager = new Phase2Manager($link);

                    // ALWAYS generate a fresh invoice for compliance testing
                    // Old invoices in DB may have been signed with broken signer
                    $forceNew = true; // Always force new for now

                    if (!$forceNew) {
                        // Try to find an existing signed invoice (disabled by default)
                        $sql = "SELECT idsanad, sanadrakam, zatca_uuid, zatca_invoice_hash, zatca_signed_xml
                                FROM sanad
                                WHERE zatca_signed_xml IS NOT NULL
                                AND zatca_uuid IS NOT NULL
                                AND zatca_invoice_hash IS NOT NULL
                                ORDER BY idsanad DESC LIMIT 1";

                        $result = mysql_query($sql, $link);
                    } else {
                        $result = false; // Force new invoice generation
                    }

                    if ($result && mysql_num_rows($result) > 0) {
                        // Use existing signed invoice
                        $row = mysql_fetch_array($result);
                        $signedXml = $row['zatca_signed_xml'];
                        $uuid = $row['zatca_uuid'];

                        // Extract DigestValue hash from the signed XML signature
                        // This is the correct hash that ZATCA expects (hash of canonical unsigned XML)
                        $hash = $row['zatca_invoice_hash']; // Default to DB hash

                        // Try to extract DigestValue from signed XML
                        // CRITICAL: Must get the DigestValue from Reference with URI="" (invoice hash)
                        // NOT the first DigestValue (which could be SignedProperties hash)
                        $dom = new DOMDocument();
                        if (@$dom->loadXML($signedXml)) {
                            $xpath = new DOMXPath($dom);
                            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

                            // Get the invoice hash from Reference with empty URI
                            $invoiceDigestNode = $xpath->query('//ds:Reference[@URI=""]/ds:DigestValue')->item(0);
                            if ($invoiceDigestNode) {
                                $extractedHash = trim($invoiceDigestNode->textContent);
                                if (!empty($extractedHash)) {
                                    $hash = $extractedHash;
                                    echo '<div class="success">✅ استخراج Hash من DigestValue (Reference URI="") في التوقيع</div>';
                                }
                            } else {
                                // Fallback: try first DigestValue (old behavior)
                                $digestValues = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'DigestValue');
                                if ($digestValues->length > 0) {
                                    $extractedHash = $digestValues->item(0)->nodeValue;
                                    if (!empty($extractedHash)) {
                                        $hash = $extractedHash;
                                        echo '<div class="warning">⚠️ استخراج Hash من أول DigestValue (fallback)</div>';
                                    }
                                }
                            }
                        }

                        echo '<div class="info">';
                        echo 'استخدام فاتورة موقعة موجودة مسبقاً:<br>';
                        echo 'رقم السند: ' . $row['idsanad'] . '<br>';
                        echo 'رقم الفاتورة: ' . htmlspecialchars($row['sanadrakam']) . '<br>';
                        echo 'UUID: ' . $uuid . '<br>';
                        echo '<strong>Hash (DB):</strong> ' . $row['zatca_invoice_hash'] . '<br>';
                        echo '<strong>Hash (DigestValue):</strong> ' . $hash . '<br>';
                        echo '<strong>Match:</strong> ' . ($hash === $row['zatca_invoice_hash'] ? '✅ نعم' : '❌ لا') . '<br>';
                        echo '</div>';

                        // Update database with correct hash if different
                        if ($hash !== $row['zatca_invoice_hash']) {
                            $hashEscaped = mysql_real_escape_string($hash, $link);
                            $updateSql = "UPDATE sanad SET zatca_invoice_hash = '$hashEscaped' WHERE idsanad = " . intval($row['idsanad']);
                            mysql_query($updateSql, $link);
                            echo '<div class="info">✅ تم تحديث Hash في قاعدة البيانات</div>';
                        }
                    } else {
                        // No existing invoice, create a new one
                        echo '<div class="info">لم يتم العثور على فواتير موقعة. جاري إنشاء فاتورة تجريبية...</div>';

                        // Find a sanad to process
                        $sql = "SELECT idsanad FROM sanad WHERE depositeamount > 0 LIMIT 1";
                        $result = mysql_query($sql, $link);

                        if (!$result || mysql_num_rows($result) === 0) {
                            throw new Exception('لا توجد سندات متاحة. أنشئ سند أولاً.');
                        }

                        $row = mysql_fetch_array($result);
                        $idsanad = $row['idsanad'];

                        // Process the sanad to generate signed invoice
                        // CRITICAL: Force compliance certificate for compliance testing
                        $processResult = $manager->processSanad($idsanad, false, true);

                        if (!$processResult['success']) {
                            throw new Exception('فشل معالجة السند: ' . $processResult['error']);
                        }

                        $signedXml = $processResult['signed_xml'];
                        $hash = $processResult['hash'];
                        $uuid = $processResult['uuid'];

                        echo '<div class="success">✅ تم إنشاء وتوقيع الفاتورة بنجاح</div>';
                        echo '<div class="info">';
                        echo 'UUID: ' . $uuid . '<br>';
                        echo 'Hash (from processSanad): ' . $hash . '<br>';
                        echo '</div>';
                    }

                    // DEBUG: Extract DigestValue from XML and compare with hash being sent
                    $debugDom = new DOMDocument();
                    if (@$debugDom->loadXML($signedXml)) {
                        $debugXpath = new DOMXPath($debugDom);
                        $debugXpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

                        // Get invoice hash from Reference with empty URI
                        $invoiceDigestNode = $debugXpath->query('//ds:Reference[@URI=""]/ds:DigestValue')->item(0);
                        $xmlDigestValue = $invoiceDigestNode ? trim($invoiceDigestNode->textContent) : 'NOT FOUND';

                        echo '<div class="warning">';
                        echo '<strong>🔍 Hash Debug:</strong><br>';
                        echo 'Hash being sent to API: <code>' . htmlspecialchars($hash) . '</code><br>';
                        echo 'DigestValue in XML (Reference URI=""): <code>' . htmlspecialchars($xmlDigestValue) . '</code><br>';
                        echo 'Match: ' . ($hash === $xmlDigestValue ? '<span style="color:green;">✓ YES</span>' : '<span style="color:red;">✗ NO - THIS IS THE PROBLEM!</span>');
                        echo '</div>';

                        // If mismatch, use the XML's DigestValue
                        if ($hash !== $xmlDigestValue && $xmlDigestValue !== 'NOT FOUND') {
                            echo '<div class="info">⚠️ Using DigestValue from XML instead of processSanad hash</div>';
                            $hash = $xmlDigestValue;
                        }
                    }

                    // Load compliance certificate and secret
                    // CRITICAL: Must use the SAME certificate that Phase2Manager used for signing
                    $env = ZatcaPhase2Config::$ENVIRONMENT;
                    $complianceCertFile = ZatcaPhase2Config::CERT_DIR . '/' . $env . '_certificate.pem';
                    $complianceSecretFile = ZatcaPhase2Config::CERT_DIR . '/' . $env . '_secret.txt';
                    $productionCertFile = ZatcaPhase2Config::CERT_DIR . '/certificate.pem';
                    $productionSecretFile = ZatcaPhase2Config::CERT_DIR . '/secret.txt';
                    $productionFlagFile = ZatcaPhase2Config::CERT_DIR . '/production_mode.flag';

                    // DEBUG: Show certificate info
                    echo '<div class="warning"><strong>🔍 Certificate Debug:</strong><br>';
                    echo 'Compliance cert: ' . basename($complianceCertFile) . ' - ' . (file_exists($complianceCertFile) ? 'EXISTS (' . filesize($complianceCertFile) . ' bytes)' : 'NOT FOUND') . '<br>';
                    echo 'Production cert: ' . basename($productionCertFile) . ' - ' . (file_exists($productionCertFile) ? 'EXISTS (' . filesize($productionCertFile) . ' bytes)' : 'NOT FOUND') . '<br>';
                    if (file_exists($complianceCertFile) && file_exists($productionCertFile)) {
                        $compMd5 = md5_file($complianceCertFile);
                        $prodMd5 = md5_file($productionCertFile);
                        echo 'Compliance MD5: ' . $compMd5 . '<br>';
                        echo 'Production MD5: ' . $prodMd5 . '<br>';
                        echo 'Same certificate: ' . ($compMd5 === $prodMd5 ? '<span style="color:red;">YES - PROBLEM!</span>' : '<span style="color:green;">NO - OK</span>') . '<br>';
                    }
                    echo 'Production flag: ' . (file_exists($productionFlagFile) ? 'EXISTS' : 'NOT FOUND') . '<br>';
                    echo '</div>';

                    // Detect production mode (same logic as Phase2Manager)
                    $useProductionCert = false;
                    if (file_exists($productionFlagFile) && file_exists($productionCertFile) && file_exists($productionSecretFile)) {
                        $useProductionCert = true;
                    } elseif (file_exists($productionCertFile) && file_exists($productionSecretFile) && file_exists($complianceCertFile)) {
                        if (md5_file($productionCertFile) !== md5_file($complianceCertFile)) {
                            $useProductionCert = true;
                        }
                    }

                    // For compliance testing, we use compliance certificate
                    // The invoice was signed with forceComplianceCert=true, so it uses compliance cert
                    if ($useProductionCert) {
                        echo '<div class="info">ℹ️ Production mode detected in system, but using COMPLIANCE certificate for this test.<br>';
                        echo 'Invoice was signed with compliance certificate (forceComplianceCert=true).</div>';
                    }

                    $certFile = $complianceCertFile;
                    $secretFile = $complianceSecretFile;

                    if (!file_exists($certFile) || !file_exists($secretFile)) {
                        throw new Exception('Compliance certificate or secret not found: ' . $certFile);
                    }

                    // Read certificate - ZATCA API expects the raw base64 content from PEM (double-encoded)
                    // The certificate mismatch warning is expected - XML has decoded cert, API auth uses encoded
                    $certContent = file_get_contents($certFile);
                    $certificate = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r", ' ', "\t"], '', $certContent);

                    // CRITICAL: Remove ALL whitespace from secret (not just trim)
                    $secret = str_replace(["\n", "\r", ' ', "\t"], '', file_get_contents($secretFile));

                    // DEBUG: Show credential lengths
                    echo '<div class="info">Certificate length: ' . strlen($certificate) . ' chars, Secret length: ' . strlen($secret) . ' chars</div>';

                    echo '<div class="info">تم تحميل شهادة Compliance</div>';

                    // Create API client with compliance credentials
                    $apiClient = new ZatcaAPIClient();
                    $apiClient->setCredentials($certificate, $secret);

                    echo '<div class="info">';
                    echo '🔄 إرسال إلى: ' . ZatcaPhase2Config::getAPIBaseURL() . ZatcaPhase2Config::API_PATH_COMPLIANCE_CHECK;
                    echo '</div>';

                    // DEBUG: Recalculate hash using ZATCA's method
                    // ZATCA receives the XML, removes UBLExtensions/Signature/QR, canonicalizes, then hashes
                    $debugDom2 = new DOMDocument();
                    $debugDom2->preserveWhiteSpace = true;  // Match library settings
                    $debugDom2->formatOutput = false;
                    @$debugDom2->loadXML($signedXml);
                    $debugXpath2 = new DOMXPath($debugDom2);

                    // Register namespaces
                    $debugXpath2->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
                    $debugXpath2->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
                    $debugXpath2->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

                    // Remove UBLExtensions, Signature, and QR for hash calculation
                    $nodesToRemove = [];
                    foreach ($debugXpath2->query('//ext:UBLExtensions') as $node) $nodesToRemove[] = $node;
                    foreach ($debugXpath2->query('//cac:Signature') as $node) $nodesToRemove[] = $node;
                    foreach ($debugXpath2->query('//cac:AdditionalDocumentReference[cbc:ID="QR"]') as $node) $nodesToRemove[] = $node;
                    foreach ($nodesToRemove as $node) $node->parentNode->removeChild($node);

                    $canonicalXml = $debugDom2->documentElement->C14N(false, false);
                    $recalcHash = base64_encode(hash('sha256', $canonicalXml, true));

                    echo '<div class="warning"><strong>🔍 Hash Recalculation Debug:</strong><br>';
                    echo 'Library hash: <code>' . htmlspecialchars($hash) . '</code><br>';
                    echo 'Recalculated hash: <code>' . htmlspecialchars($recalcHash) . '</code><br>';
                    echo 'Match: ' . ($hash === $recalcHash ? '<span style="color:green;">✓ YES</span>' : '<span style="color:red;">✗ NO</span>') . '<br>';
                    echo 'Canonical XML length: ' . strlen($canonicalXml) . ' bytes<br>';
                    echo 'Canonical XML SHA256 (hex): <code>' . hash('sha256', $canonicalXml) . '</code>';
                    echo '</div>';

                    // Show first 300 chars of canonical XML for debugging
                    echo '<details><summary>Click to see canonical XML (first 300 chars)</summary>';
                    echo '<pre style="background:#f5f5f5;padding:10px;font-size:10px;overflow:auto;">' . htmlspecialchars(substr($canonicalXml, 0, 300)) . '...</pre>';
                    echo '</details>';

                    // CRITICAL: Use recalculated hash - ZATCA calculates hash from received XML
                    // The library's DigestValue may differ due to XML formatting changes
                    $hashToSend = $recalcHash;

                    echo '<div class="info">';
                    echo '<strong>Hash Strategy:</strong><br>';
                    echo 'DigestValue in XML (library): <code>' . htmlspecialchars($hash) . '</code><br>';
                    echo 'Recalculated from final XML: <code>' . htmlspecialchars($recalcHash) . '</code><br>';
                    echo 'Match: ' . ($hash === $recalcHash ? '<span style="color:green;">✓ YES</span>' : '<span style="color:orange;">✗ Different - using recalculated</span>') . '<br>';
                    echo '<strong>Using RECALCULATED hash for API: <code>' . htmlspecialchars($hashToSend) . '</code></strong>';
                    echo '</div>';

                    // DEBUG: Show XML details for troubleshooting
                    $xmlFirstBytes = bin2hex(substr($signedXml, 0, 20));
                    $hasBOM = (substr($signedXml, 0, 3) === "\xEF\xBB\xBF");
                    $startsWithDecl = (substr(ltrim($signedXml), 0, 5) === '<?xml');

                    echo '<div class="warning"><strong>🔍 XML Analysis:</strong><br>';
                    echo 'First 20 bytes (hex): <code>' . $xmlFirstBytes . '</code><br>';
                    echo 'Has BOM: ' . ($hasBOM ? '<span style="color:red;">YES - PROBLEM!</span>' : '<span style="color:green;">No ✓</span>') . '<br>';
                    echo 'Starts with &lt;?xml: ' . ($startsWithDecl ? '<span style="color:green;">Yes ✓</span>' : '<span style="color:red;">NO</span>') . '<br>';
                    echo 'XML length: ' . strlen($signedXml) . ' bytes<br>';
                    echo 'Base64 length: ' . strlen(base64_encode($signedXml)) . ' chars';
                    echo '</div>';

                    echo '<div class="warning"><strong>🔍 XML Preview (first 500 chars):</strong><br>';
                    echo '<pre style="font-size:10px; max-height:200px; overflow:auto;">' . htmlspecialchars(substr($signedXml, 0, 500)) . '...</pre>';
                    echo '</div>';

                    // DEBUG: Check certificate in XML vs API authentication certificate
                    $xmlCertDom = new DOMDocument();
                    @$xmlCertDom->loadXML($signedXml);
                    $xmlCertXpath = new DOMXPath($xmlCertDom);
                    $xmlCertXpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
                    $x509CertNode = $xmlCertXpath->query('//ds:X509Certificate')->item(0);
                    $xmlCertBase64 = $x509CertNode ? trim($x509CertNode->textContent) : 'NOT FOUND';

                    echo '<div class="warning"><strong>🔍 Certificate Match Debug:</strong><br>';
                    echo 'Certificate in XML (first 50 chars): <code>' . htmlspecialchars(substr($xmlCertBase64, 0, 50)) . '...</code><br>';
                    echo 'API auth certificate (first 50 chars): <code>' . htmlspecialchars(substr($certificate, 0, 50)) . '...</code><br>';
                    echo 'Match: ' . ($xmlCertBase64 === $certificate ? '<span style="color:green;">✓ YES</span>' : '<span style="color:red;">✗ NO - CERTIFICATE MISMATCH!</span>');
                    echo '</div>';

                    // DEBUG: Show secret (first 10 chars only for security)
                    echo '<div class="info"><strong>🔐 Secret Debug:</strong> First 10 chars: <code>' . htmlspecialchars(substr($secret, 0, 10)) . '...</code> (length: ' . strlen($secret) . ')</div>';

                    // DEBUG: Check certificate validity (decode if double-encoded for parsing)
                    $certForParsing = base64_decode($certificate);
                    if (substr($certForParsing, 0, 4) === 'MIIC' || substr($certForParsing, 0, 4) === 'MIID') {
                        // Double-encoded - use decoded version for parsing
                        $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($certForParsing, 64, "\n") . "-----END CERTIFICATE-----";
                    } else {
                        // Single-encoded
                        $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($certificate, 64, "\n") . "-----END CERTIFICATE-----";
                    }
                    $certData = @openssl_x509_read($certPem);
                    if ($certData) {
                        $certInfo = openssl_x509_parse($certData);
                        $validFrom = date('Y-m-d H:i:s', $certInfo['validFrom_time_t']);
                        $validTo = date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
                        $isExpired = time() > $certInfo['validTo_time_t'];
                        echo '<div class="' . ($isExpired ? 'error' : 'success') . '"><strong>📅 Certificate Validity:</strong><br>';
                        echo 'Valid From: ' . $validFrom . '<br>';
                        echo 'Valid To: ' . $validTo . '<br>';
                        echo 'Status: ' . ($isExpired ? '❌ EXPIRED!' : '✓ Valid') . '</div>';
                    } else {
                        echo '<div class="warning">Could not parse certificate for validity check</div>';
                    }

                    // DEBUG: Show Authorization header format
                    $authString = $certificate . ':' . $secret;
                    $authBase64 = base64_encode($authString);
                    echo '<div class="info"><strong>🔑 Auth Debug:</strong><br>';
                    echo 'Auth string length: ' . strlen($authString) . ' chars<br>';
                    echo 'Auth Base64 (first 50): <code>' . htmlspecialchars(substr($authBase64, 0, 50)) . '...</code></div>';

                    // Submit to compliance endpoint
                    $apiResult = $apiClient->validateCompliance(
                        $hashToSend,
                        $uuid,
                        $signedXml
                    );

                    // Display results
                    if ($apiResult['success']) {
                        echo '<div class="success">';
                        echo '✅ نجح الإرسال إلى Compliance Endpoint!<br>';
                        echo 'الحالة: ' . ($apiResult['status'] ?? 'PASSED');
                        echo '</div>';

                        if (!empty($apiResult['validation_results'])) {
                            echo '<div class="info">';
                            echo '<strong>نتائج التحقق:</strong><br>';
                            echo '<div class="code" style="max-height: 300px; overflow-y: auto;">';
                            echo htmlspecialchars(json_encode($apiResult['validation_results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            echo '</div>';
                            echo '</div>';
                        }

                        echo '<div class="success">';
                        echo '<strong>✅ الخطوات التالية:</strong><br>';
                        echo '1. أرسل المزيد من الفواتير التجريبية إذا لزم الأمر<br>';
                        echo '2. بعد نجاح جميع الاختبارات، اطلب Production CSID<br>';
                        echo '3. استخدم Production CSID للفواتير الحقيقية';
                        echo '</div>';

                        echo '<div class="info">';
                        echo '<strong>الاستجابة الكاملة:</strong><br>';
                        echo '<div class="code" style="max-height: 400px; overflow-y: auto;">';
                        echo htmlspecialchars(json_encode($apiResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        echo '</div>';
                        echo '</div>';

                    } else {
                        echo '<div class="error">';
                        echo '❌ فشل الإرسال إلى Compliance Endpoint<br>';
                        echo 'الخطأ: ' . htmlspecialchars($apiResult['error'] ?? 'خطأ غير معروف');
                        echo '</div>';
echo "<textarea>{$signedXml}</textarea>";
                        if (isset($apiResult['http_code'])) {
                            echo '<div class="warning">HTTP Code: ' . $apiResult['http_code'] . '</div>';
                        }

                        if (!empty($apiResult['validation_results'])) {
                            echo '<div class="warning">';
                            echo '<strong>نتائج التحقق:</strong><br>';
                            echo '<div class="code" style="max-height: 300px; overflow-y: auto;">';
                            echo htmlspecialchars(json_encode($apiResult['validation_results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            echo '</div>';
                            echo '</div>';
                        }

                        echo '<div class="info">';
                        echo '<strong>الاستجابة الكاملة:</strong><br>';
                        echo '<div class="code" style="max-height: 400px; overflow-y: auto;">';
                        echo htmlspecialchars(json_encode($apiResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        echo '</div>';
                        echo '</div>';
                    }

                } catch (Exception $e) {
                    echo '<div class="error">❌ خطأ: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            echo '</div>';
        }

        // Test Menu
        echo '<div class="test-section">';
        echo '<h2>🎯 اختبارات متاحة</h2>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">';

        $tests = [
            ['name' => 'توليد فاتورة تجريبية', 'link' => '?test=generate&type=standard', 'icon' => '📄'],
            ['name' => 'توليد XML', 'link' => '?test=xml', 'icon' => '📝'],
            ['name' => 'التحقق من XML', 'link' => '?test=validate', 'icon' => '✅'],
            ['name' => 'التوقيع الرقمي', 'link' => '?test=sign', 'icon' => '🔐'],
            ['name' => 'سير العمل الكامل', 'link' => '?test=workflow', 'icon' => '🔄'],
            ['name' => 'إرسال إلى Compliance (مطلوب قبل Production CSID)', 'link' => '?test=compliance', 'icon' => '🚀']
        ];

        foreach ($tests as $test) {
            echo '<a href="' . $test['link'] . '" class="btn" style="display: block; text-align: center;">';
            echo $test['icon'] . ' ' . $test['name'];
            echo '</a>';
        }

        echo '</div>';
        echo '</div>';

        // Test Invoice Types
        echo '<div class="test-section">';
        echo '<h2>📋 أنواع الفواتير التجريبية</h2>';
        $types = TestInvoiceGenerator::getAllTestTypes();
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">';
        foreach ($types as $key => $name) {
            echo '<a href="?test=generate&type=' . $key . '" class="btn">' . $name . '</a>';
        }
        echo '</div>';
        echo '</div>';
        ?>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;">
            <p style="color: #7f8c8d;">ZATCA Phase 2 Testing Tool v2.0</p>
            <a href="zatca/phase2_setup.php" class="btn">العودة إلى الإعداد</a>
            <a href="zatca/setup.php" class="btn">المرحلة الأولى</a>
        </div>
    </div>
</body>
</html>
