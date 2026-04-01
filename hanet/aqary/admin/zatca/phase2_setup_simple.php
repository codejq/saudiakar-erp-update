<?php
/**
 * ZATCA Phase 2 - Simplified Setup
 *
 * One-page setup for certificates and testing
 *
 * @charset UTF-8
 * @version 3.0
 */

// Start output buffering early to capture any stray output from includes
ob_start();

$sc_title = "🚀 إعداد المرحلة الثانية - ZATCA";
$sc_id = "151";

if ($_SERVER['REQUEST_METHOD'] != 'POST' && !isset($_POST['action'])) {
    include_once "../../nocash.hnt";
    include_once "../../header.hnt";
} else {
    include_once "../../reqloginajax.hnt";
}

require_once '../../connectdb.hnt';
require_once './config/phase2_config.php';
require_once './phase2/certificate/CertificateBuilder.php';
require_once './phase2/certificate/CSRDebugger.php';

/**
 * Encode CSR PEM file for ZATCA API submission
 * ZATCA expects DOUBLE BASE64 ENCODING:
 * 1. The PEM file already contains base64 content between headers
 * 2. We must base64 encode the ENTIRE PEM (headers + content) again
 * 3. This results in "LS0tLS1CRUdJTi..." (base64 of "-----BEGIN...")
 *
 * This matches the working Quantum-Billing implementation:
 * Buffer.from(csr).toString("base64") where csr is the full PEM
 */
function encodeCSRForAPI($csrPemContent) {
    // Validate CSR PEM format
    if (strpos($csrPemContent, '-----BEGIN CERTIFICATE REQUEST-----') === false) {
        throw new Exception('Invalid CSR PEM format: Missing BEGIN marker');
    }
    if (strpos($csrPemContent, '-----END CERTIFICATE REQUEST-----') === false) {
        throw new Exception('Invalid CSR PEM format: Missing END marker');
    }

    // ZATCA requires the full PEM (including headers) to be base64 encoded.
    // Normalize line endings to LF only — CRLF (Windows) causes Invalid-CSR in simulation.
    $normalizedPem = str_replace("\r\n", "\n", $csrPemContent);
    $normalizedPem = str_replace("\r", "\n", $normalizedPem);

    $csrBase64 = base64_encode($normalizedPem);

    return $csrBase64;
}

// Helper: clean ALL output buffers and send pure JSON
function outputJSON($data) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Handle AJAX requests for certificate operations
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete_csr':
            // Delete existing CSR files to force fresh generation
            try {
                $csrFile = ZatcaPhase2Config::CSR_FILE;
                $keyFile = ZatcaPhase2Config::PRIVATE_KEY_FILE;
                $certFile = ZatcaPhase2Config::CERTIFICATE_FILE;
                $environment = ZatcaPhase2Config::$ENVIRONMENT ?? 'sandbox';
                $envCertFile = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_certificate.pem';

                $deleted = [];

                // Delete CSR
                if (file_exists($csrFile)) {
                    unlink($csrFile);
                    $deleted[] = 'CSR';
                    ZatcaPhase2Config::log('Deleted old CSR file: ' . $csrFile, 'INFO');
                }

                // Delete private key
                if (file_exists($keyFile)) {
                    unlink($keyFile);
                    $deleted[] = 'Private Key';
                    ZatcaPhase2Config::log('Deleted old key file: ' . $keyFile, 'INFO');
                }

                // Delete certificate
                if (file_exists($certFile)) {
                    unlink($certFile);
                    $deleted[] = 'Certificate';
                    ZatcaPhase2Config::log('Deleted old certificate: ' . $certFile, 'INFO');
                }

                // Delete environment certificate
                if (file_exists($envCertFile)) {
                    unlink($envCertFile);
                    $deleted[] = 'Environment Certificate';
                    ZatcaPhase2Config::log('Deleted old environment certificate: ' . $envCertFile, 'INFO');
                }

                outputJSON([
                    'success' => true,
                    'message' => 'Deleted: ' . (empty($deleted) ? 'No files found' : implode(', ', $deleted)) . '. Ready for new ECDSA CSR generation.',
                    'deleted' => $deleted
                ]);
            } catch (Exception $e) {
                outputJSON([
                    'success' => false,
                    'error' => 'Failed to delete files: ' . $e->getMessage()
                ]);
            }

        case 'generate_csr':
            $builder = new CertificateBuilder();
            $result = $builder->saveToFiles();

            if ($result) {
                try {
                    $csrContent = file_get_contents(ZatcaPhase2Config::CSR_FILE);

                    // Base64 encode the ENTIRE PEM (headers + content)
                    // This double-encoding is what ZATCA API expects
                    $csrBase64 = encodeCSRForAPI($csrContent);

                    ZatcaPhase2Config::log('CSR encoded for API (double base64)', 'INFO');
                    ZatcaPhase2Config::log('Encoded CSR length: ' . strlen($csrBase64), 'DEBUG');

                    // Run CSR debugger to analyze structure
                    try {
                        $debugger = new CSRDebugger();
                        $debuggerResults = $debugger->checkRequiredAttributes();

                        // Log the results
                        ZatcaPhase2Config::log('', 'INFO');
                        ZatcaPhase2Config::log('=== CSR STRUCTURE ANALYSIS ===', 'INFO');
                        ZatcaPhase2Config::log('Required Attributes:', 'INFO');
                        foreach ($debuggerResults['required'] as $code => $attr) {
                            $status = $attr['present'] ? '✅' : '❌';
                            ZatcaPhase2Config::log("  $status $code ({$attr['name']}): {$attr['value']}", 'INFO');
                        }

                        if (!empty($debuggerResults['missing_required'])) {
                            ZatcaPhase2Config::log('❌ MISSING REQUIRED: ' . implode(', ', $debuggerResults['missing_required']), 'WARNING');
                        }

                        ZatcaPhase2Config::log('ZATCA-Specific Attributes:', 'INFO');
                        foreach ($debuggerResults['zatca_specific'] as $oid => $attr) {
                            ZatcaPhase2Config::log("  {$attr['status']}", 'INFO');
                        }

                        if (!empty($debuggerResults['missing_zatca'])) {
                            ZatcaPhase2Config::log('❌ CRITICAL - MISSING ZATCA ATTRIBUTES: ' . implode(', ', $debuggerResults['missing_zatca']), 'ERROR');
                        }
                        ZatcaPhase2Config::log('=== END CSR ANALYSIS ===', 'INFO');
                        ZatcaPhase2Config::log('', 'INFO');

                        // Write full debug report
                        $debugger->writeReportToLog();
                    } catch (Exception $debugEx) {
                        ZatcaPhase2Config::log('CSR Debugger Error: ' . $debugEx->getMessage(), 'WARNING');
                    }

                    outputJSON([
                        'success' => true,
                        'csr' => $csrBase64,
                        'message' => 'CSR generated successfully. Check logs for structure analysis.'
                    ]);
                } catch (Exception $e) {
                    outputJSON([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                outputJSON([
                    'success' => false,
                    'error' => 'Failed to generate CSR'
                ]);
            }

        case 'call_zatca_api':
            $csr = $_POST['csr'] ?? '';
            $endpoint = $_POST['endpoint'] ?? 'compliance';
            $otp = $_POST['otp'] ?? '';

            // Determine API URL based on environment from config
            $environment = $_POST['environment'] ?? 'sandbox';
            switch ($environment) {
                case 'production':
                    $apiUrl = ZatcaPhase2Config::API_PRODUCTION;
                    break;
                case 'simulation':
                    $apiUrl = ZatcaPhase2Config::API_SIMULATION;
                    break;
                case 'sandbox':
                default:
                    $apiUrl = ZatcaPhase2Config::API_SANDBOX;
                    break;
            }


            // Prepare base headers
            $headers = [
                'accept: application/json',
                'Accept-Version: V2',
                'Content-Type: application/json'
            ];

            if ($endpoint === 'production/csids') {
                // Production CSID endpoint
                $apiUrl .= '/production/csids';

                // Validate Request ID
                $requestId = $_POST['request_id'] ?? '';
                if (empty($requestId)) {
                    ZatcaPhase2Config::log('Production CSID Error: No Request ID provided', 'ERROR');
                    outputJSON([
                        'success' => false,
                        'error' => 'يجب توفير Request ID من شهادة Compliance',
                        'details' => [
                            'hint' => 'تأكد من توليد شهادة Compliance أولاً والحصول على Request ID'
                        ]
                    ]);
                }

                // Log warning if using test Request ID (common in sandbox)
                if ($requestId === '1234567890123') {
                    ZatcaPhase2Config::log('WARNING: Using test Request ID (1234567890123) - This is expected in Sandbox environment', 'WARNING');
                } else {
                    ZatcaPhase2Config::log('Using Request ID: ' . $requestId, 'INFO');
                }

                $requestData = ['compliance_request_id' => $requestId];

                // Production requires Basic Authentication with Compliance certificate
                // Use the selected environment's Compliance certificate for authentication
                $certFile = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_certificate.pem';
                $secretFile = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_secret.txt';

                if (!file_exists($certFile) || !file_exists($secretFile)) {
                    ZatcaPhase2Config::log('Production endpoint requires Compliance certificate first', 'ERROR');
                    outputJSON([
                        'success' => false,
                        'error' => 'يجب الحصول على شهادة Compliance أولاً قبل طلب شهادة Production',
                        'details' => [
                            'certificate_file' => $certFile,
                            'secret_file' => $secretFile
                        ]
                    ]);
                }

                $certificate = trim(file_get_contents($certFile));
                $secret = trim(file_get_contents($secretFile));

                // Extract certificate content (remove PEM headers if present)
                $certContent = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"], '', $certificate);

                // Create Basic Auth header: base64(certificate:secret)
                $authString = base64_encode($certContent . ':' . $secret);
                $headers[] = 'Authorization: Basic ' . $authString;

                ZatcaPhase2Config::log('Using Compliance certificate for Production authentication', 'INFO');
                ZatcaPhase2Config::log('Compliance Request ID: ' . $requestId, 'INFO');
                ZatcaPhase2Config::log('Certificate file: ' . $certFile, 'INFO');
            } else {
                // Compliance CSID endpoint
                $apiUrl .= '/compliance';

                if (empty($csr)) {
                    ZatcaPhase2Config::log('ZATCA API Error: CSR is required for Compliance endpoint', 'ERROR');
                    outputJSON(['success' => false, 'error' => 'CSR is required for Compliance endpoint']);
                }

                $requestData = ['csr' => $csr];

                // Add OTP header for compliance endpoint
                if (!empty($otp)) {
                    $headers[] = 'OTP: ' . $otp;
                    ZatcaPhase2Config::log('OTP added to request headers: ' . $otp, 'DEBUG');
                } else {
                    $headers[] = 'OTP: 123345'; // Default for sandbox
                    ZatcaPhase2Config::log('Using default OTP: 123345', 'WARNING');
                }
            }

            // Prepare JSON payload first
            $jsonPayload = json_encode($requestData);

            // Log the detailed raw request
            ZatcaPhase2Config::log('', 'INFO');
            ZatcaPhase2Config::log('================================', 'INFO');
            ZatcaPhase2Config::log('=== RAW HTTP REQUEST START ===', 'INFO');
            ZatcaPhase2Config::log('================================', 'INFO');
            ZatcaPhase2Config::log('', 'INFO');
            ZatcaPhase2Config::log('POST ' . parse_url($apiUrl, PHP_URL_PATH) . ($endpoint === 'production/csids' ? '/production/csids' : '/compliance') . ' HTTP/1.1', 'INFO');
            ZatcaPhase2Config::log('Host: gw-fatoora.zatca.gov.sa', 'INFO');
            foreach ($headers as $header) {
                ZatcaPhase2Config::log($header, 'INFO');
            }
            ZatcaPhase2Config::log('Content-Length: ' . strlen($jsonPayload), 'INFO');
            ZatcaPhase2Config::log('Connection: close', 'INFO');
            ZatcaPhase2Config::log('', 'INFO');
            ZatcaPhase2Config::log($jsonPayload, 'INFO');
            ZatcaPhase2Config::log('', 'INFO');
            ZatcaPhase2Config::log('================================', 'INFO');
            ZatcaPhase2Config::log('=== RAW HTTP REQUEST END ===', 'INFO');
            ZatcaPhase2Config::log('================================', 'INFO');
            ZatcaPhase2Config::log('', 'INFO');

            // Call ZATCA API
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // SSL Certificate handling
            // For sandbox/testing, we can disable SSL verification
            // For production, you should configure proper CA bundle
            if (defined('ZATCA_DISABLE_SSL_VERIFY') && ZATCA_DISABLE_SSL_VERIFY === true) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                ZatcaPhase2Config::log('WARNING: SSL verification disabled', 'WARNING');
            } else {
                // Try to use system CA bundle
                $caBundlePaths = [
                    __DIR__ . '/certificates/cacert.pem',
                    'C:/wamp/bin/php/php8.0.2/extras/ssl/cacert.pem',
                    'C:/xampp/php/extras/ssl/cacert.pem',
                    ini_get('curl.cainfo'),
                    ini_get('openssl.cafile')
                ];

                $caBundleFound = false;
                foreach ($caBundlePaths as $path) {
                    if ($path && file_exists($path)) {
                        curl_setopt($ch, CURLOPT_CAINFO, $path);
                        $caBundleFound = true;
                        ZatcaPhase2Config::log('Using CA bundle: ' . $path, 'INFO');
                        break;
                    }
                }

                if (!$caBundleFound) {
                    // Fallback: disable SSL verification with warning
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    ZatcaPhase2Config::log('WARNING: No CA bundle found, SSL verification disabled', 'WARNING');
                } else {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                }
            }

            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);

            // Log the detailed raw response
            ZatcaPhase2Config::log('', 'INFO');
            ZatcaPhase2Config::log('================================', 'INFO');
            ZatcaPhase2Config::log('=== RAW HTTP RESPONSE START ===', 'INFO');
            ZatcaPhase2Config::log('================================', 'INFO');
            ZatcaPhase2Config::log('', 'INFO');
            ZatcaPhase2Config::log('HTTP/1.1 ' . $httpCode, 'INFO');
            ZatcaPhase2Config::log('Server: ZATCA API', 'INFO');
            ZatcaPhase2Config::log('Content-Type: application/json', 'INFO');
            ZatcaPhase2Config::log('Content-Length: ' . strlen($response), 'INFO');
            ZatcaPhase2Config::log('', 'INFO');
            ZatcaPhase2Config::log($response, 'INFO');
            ZatcaPhase2Config::log('', 'INFO');
            if ($curlErrno !== 0) {
                ZatcaPhase2Config::log('cURL Error (' . $curlErrno . '): ' . $curlError, 'ERROR');
            }
            ZatcaPhase2Config::log('================================', 'INFO');
            ZatcaPhase2Config::log('=== RAW HTTP RESPONSE END ===', 'INFO');
            ZatcaPhase2Config::log('================================', 'INFO');
            ZatcaPhase2Config::log('', 'INFO');

            // Check for cURL errors
            if ($curlErrno !== 0) {
                $errorMsg = 'cURL Error (' . $curlErrno . '): ' . $curlError;
                ZatcaPhase2Config::log('ZATCA API Failed: ' . $errorMsg, 'ERROR');
                outputJSON([
                    'success' => false,
                    'error' => $errorMsg,
                    'details' => [
                        'curl_errno' => $curlErrno,
                        'curl_error' => $curlError,
                        'http_code' => $httpCode
                    ]
                ]);
            }

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errorMsg = 'Failed to parse JSON response: ' . json_last_error_msg();
                    ZatcaPhase2Config::log('ZATCA API Error: ' . $errorMsg, 'ERROR');
                    ZatcaPhase2Config::log('Raw Response: ' . $response, 'ERROR');
                    outputJSON([
                        'success' => false,
                        'error' => $errorMsg,
                        'raw_response' => substr($response, 0, 500)
                    ]);
                }

                ZatcaPhase2Config::log('ZATCA API Success: Certificate received', 'INFO');
                outputJSON([
                    'success' => true,
                    'data' => $data
                ]);
            } else {
                // Parse error response if JSON
                $errorData = json_decode($response, true);
                $errorMsg = 'HTTP ' . $httpCode;

                if (json_last_error() === JSON_ERROR_NONE && isset($errorData['message'])) {
                    $errorMsg .= ': ' . $errorData['message'];
                } elseif (json_last_error() === JSON_ERROR_NONE && isset($errorData['error'])) {
                    $errorMsg .= ': ' . $errorData['error'];
                } else {
                    $errorMsg .= ': ' . substr($response, 0, 200);
                }

                ZatcaPhase2Config::log('ZATCA API Failed: ' . $errorMsg, 'ERROR');
                ZatcaPhase2Config::log('Full Response: ' . $response, 'ERROR');

                outputJSON([
                    'success' => false,
                    'error' => $errorMsg,
                    'details' => [
                        'http_code' => $httpCode,
                        'response' => $response,
                        'parsed_error' => $errorData
                    ]
                ]);
            }

        case 'save_certificate':
            $binaryToken = $_POST['binary_token'] ?? '';
            $secret = $_POST['secret'] ?? '';
            $environment = $_POST['environment'] ?? 'sandbox';
            $requestId = $_POST['request_id'] ?? ''; // Get requestID from API response
            $isProduction = ($_POST['is_production'] ?? '') === 'true'; // Flag for production certificate

            if (empty($binaryToken) || empty($secret)) {
                outputJSON(['success' => false, 'error' => 'Certificate and secret are required']);
            }

            try {
                // Decode certificate
                $certificate = base64_decode($binaryToken);

                // Add PEM headers
                if (strpos($certificate, '-----BEGIN CERTIFICATE-----') === false) {
                    $certificate = "-----BEGIN CERTIFICATE-----\n" .
                                 chunk_split(base64_encode(base64_decode($binaryToken)), 64, "\n") .
                                 "-----END CERTIFICATE-----\n";
                }

                $productionFlagFile = ZatcaPhase2Config::CERT_DIR . '/production_mode.flag';

                // Determine file names based on certificate type
                if ($isProduction) {
                    // Production certificate - save to certificate.pem and secret.txt
                    $certFile = ZatcaPhase2Config::CERTIFICATE_FILE; // certificate.pem
                    $secretFile = ZatcaPhase2Config::CERT_DIR . '/secret.txt';
                    $requestIdFile = ZatcaPhase2Config::CERT_DIR . '/production_request_id.txt';

                    // Create production mode flag file
                    file_put_contents($productionFlagFile, date('Y-m-d H:i:s') . "\nProduction CSID obtained");
                    chmod($productionFlagFile, 0600);
                    ZatcaPhase2Config::log('Production mode flag created - certificate.pem is now protected', 'INFO');
                } else {
                    // Compliance certificate - save to {env}_certificate.pem
                    $certFile = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_certificate.pem';
                    $secretFile = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_secret.txt';
                    $requestIdFile = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_request_id.txt';
                }

                file_put_contents($certFile, $certificate);
                chmod($certFile, 0600);

                file_put_contents($secretFile, $secret);
                chmod($secretFile, 0600);

                // Save requestID if provided (from Compliance response)
                if (!empty($requestId)) {
                    file_put_contents($requestIdFile, $requestId);
                    chmod($requestIdFile, 0600);
                    ZatcaPhase2Config::log("Request ID saved: $requestId for " . ($isProduction ? 'production' : $environment), 'INFO');
                }

                // For compliance certificates, also copy to certificate.pem if NOT in production mode
                if (!$isProduction && $environment === 'sandbox' && !file_exists($productionFlagFile)) {
                    copy($certFile, ZatcaPhase2Config::CERTIFICATE_FILE);
                    ZatcaPhase2Config::log('Copied compliance cert to certificate.pem', 'INFO');
                } elseif (!$isProduction && file_exists($productionFlagFile)) {
                    ZatcaPhase2Config::log('Production mode active - NOT overwriting certificate.pem', 'WARNING');
                }

                ZatcaPhase2Config::log("Certificate saved: $certFile", 'INFO');

                $response = [
                    'success' => true,
                    'message' => 'Certificate saved successfully',
                    'files' => [
                        'certificate' => $certFile,
                        'secret' => $secretFile
                    ]
                ];

                if (!empty($requestId)) {
                    $response['files']['request_id'] = $requestIdFile;
                    $response['request_id'] = $requestId;
                }

                outputJSON($response);
            } catch (Exception $e) {
                outputJSON([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }

        case 'get_compliance_request_id':
            // Get the saved compliance request ID for production certificate
            $environment = $_POST['environment'] ?? 'sandbox';
            $requestIdFile = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_request_id.txt';

            if (file_exists($requestIdFile)) {
                $requestId = trim(file_get_contents($requestIdFile));
                if (!empty($requestId)) {
                    outputJSON([
                        'success' => true,
                        'request_id' => $requestId,
                        'message' => 'Request ID تم تحميله تلقائياً من شهادة Compliance'
                    ]);
                } else {
                    outputJSON([
                        'success' => false,
                        'error' => 'Request ID ملف فارغ'
                    ]);
                }
            } else {
                outputJSON([
                    'success' => false,
                    'error' => 'لم يتم العثور على Request ID. يجب الحصول على شهادة Compliance أولاً'
                ]);
            }
    }
}

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد المرحلة الثانية - مبسط</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }
        .header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 40px;
        }
        .wizard {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .wizard::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }
        .wizard-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .wizard-step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 20px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .wizard-step.active .wizard-step-circle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .wizard-step.completed .wizard-step-circle {
            background: #4caf50;
            color: white;
        }
        .wizard-step-title {
            font-size: 14px;
            color: #666;
        }
        .wizard-step.active .wizard-step-title {
            color: #667eea;
            font-weight: bold;
        }
        .step-content {
            display: none;
            animation: fadeIn 0.5s;
        }
        .step-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .card h3 {
            margin: 0 0 20px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        textarea, input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: monospace;
        }
        textarea:focus, input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        label {
            display: block;
            margin: 15px 0 5px 0;
            font-weight: bold;
            color: #333;
        }
        .code-box {
            background: #2d3748;
            color: #68d391;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin: 15px 0;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .hidden {
            display: none !important;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .quick-action {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s;
        }
        .quick-action:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        .quick-action h4 {
            margin: 0 0 10px 0;
            color: #667eea;
        }
        .quick-action p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 إعداد المرحلة الثانية - ZATCA</h1>
            <p>عملية مبسطة من 3 خطوات فقط</p>
        </div>

        <div class="content">
            <div style="margin-bottom: 20px;">
                <a href="index.php" class="btn" style="background: #059669; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
                    <i class="bi bi-arrow-right"></i> العودة الي الخيارات
                </a>
            </div>
            <!-- Progress Bar -->
            <div class="progress-bar">
                <div class="progress-bar-fill" id="progressBar" style="width: 33%"></div>
            </div>

            <!-- Wizard Steps -->
            <div class="wizard">
                <div class="wizard-step active" data-step="1">
                    <div class="wizard-step-circle">1</div>
                    <div class="wizard-step-title">توليد CSR</div>
                </div>
                <div class="wizard-step" data-step="2">
                    <div class="wizard-step-circle">2</div>
                    <div class="wizard-step-title">الحصول على الشهادة</div>
                </div>
                <div class="wizard-step" data-step="3">
                    <div class="wizard-step-circle">3</div>
                    <div class="wizard-step-title">الاختبار</div>
                </div>
            </div>

            <!-- Step 1: Generate CSR -->
            <div class="step-content active" id="step1">
                <div class="card">
                    <h3>📝 الخطوة 1: توليد شهادة CSR</h3>

                    <?php
                    $csrExists = file_exists(ZatcaPhase2Config::CSR_FILE);
                    if ($csrExists):
                        try {
                            $csrContent = file_get_contents(ZatcaPhase2Config::CSR_FILE);
                            // Base64 encode the ENTIRE PEM (headers + content)
                            // ZATCA expects double base64 encoding
                            $csrBase64 = encodeCSRForAPI($csrContent);
                        } catch (Exception $e) {
                            $csrBase64 = 'Error: ' . $e->getMessage();
                        }
                    ?>
                        <div class="alert alert-success">
                            ✅ CSR موجود بالفعل
                        </div>

                        <label>CSR Base64 (للاستخدام مع API):</label>
                        <textarea id="csrBase64" readonly><?php echo $csrBase64; ?></textarea>

                        <div class="btn-group">
                            <button class="btn" onclick="copyCSR()">📋 نسخ CSR</button>
                            <button class="btn btn-outline" onclick="regenerateCSR()">🔄 إعادة توليد</button>
                            <button class="btn btn-danger" style="background: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer;" onclick="deleteCSR()">🗑️ حذف</button>
                            <button class="btn btn-success" onclick="goToStep(2)">التالي ←</button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            ℹ️ لم يتم توليد CSR بعد. اضغط الزر أدناه للبدء.
                        </div>

                        <div class="alert" style="background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; margin-bottom: 15px;">
                            🔐 <strong>ECDSA secp256k1</strong> - سيتم توليد مفتاح ECDSA المطلوب من ZATCA (وليس RSA)
                        </div>

                        <button class="btn" id="generateCSRBtn" onclick="generateCSR()">
                            🔑 توليد CSR الآن (ECDSA)
                        </button>

                        <div id="csrResult" class="hidden"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Step 2: Get Certificate -->
            <div class="step-content" id="step2">
                <div class="card">
                    <h3>🎫 الخطوة 2: الحصول على الشهادة</h3>

                    <div class="alert alert-info">
                        اختر طريقة الحصول على الشهادة
                    </div>

                    <!-- Method 1: Automatic API Call -->
                    <div class="quick-action active" onclick="toggleMethod('auto')">
                        <h4>⚡ طريقة 1: تلقائي (مستحسن)</h4>
                        <p>يقوم النظام بالاتصال بـ ZATCA تلقائياً</p>
                    </div>

                    <div id="autoMethod" style="margin: 20px 0;">
                                                <label>البيئة:</label>
                        <select id="environment">
                            <option value="sandbox">Sandbox (تجريبي)</option>
                            <option value="simulation">Simulation</option>
                            <option value="production">Production (إنتاج)</option>
                        </select>

                        <label>نوع الشهادة:</label>
                        <select id="certType">
                            <option value="compliance">Compliance (التوافق)</option>
                            <option value="production/csids">csids (شهادة)</option>
                        </select>

                        <div id="complianceFields" style="margin-top: 15px;">
                            <label>OTP (كلمة المرور المؤقتة):</label>
                            <input type="text" id="otp" value="123345" placeholder="OTP للتجربة: 123345">
                            <small style="color: #666; display: block; margin-top: 5px;">
                                💡 القيمة الافتراضية للبيئة التجريبية: <strong>123345</strong>
                            </small>
                        </div>

                        <div id="productionFields" class="hidden">
                            <label>Request ID (من Compliance): <small style="color: #28a745;">سيتم التحميل تلقائياً ✨</small></label>
                            <input type="text" id="requestId" placeholder="سيتم تحميله تلقائياً من شهادة Compliance...">
                            <small style="color: #666; display: block; margin-top: 5px;">
                                💡 سيتم تحميل Request ID تلقائياً من شهادة Compliance المحفوظة
                            </small>
                        </div>

                        <div class="btn-group">
                            <button class="btn btn-success" onclick="callZATCAAPI()">
                                🚀 الحصول على الشهادة
                            </button>
                        </div>

                        <div id="apiResult" class="hidden"></div>
                    </div>

                    <!-- Method 2: Manual Paste -->
                    <div class="quick-action" onclick="toggleMethod('manual')">
                        <h4>📋 طريقة 2: يدوي</h4>
                        <p>الصق الشهادة من ZATCA Portal</p>
                    </div>

                    <div id="manualMethod" class="hidden" style="margin: 20px 0;">
                        <label>Binary Security Token:</label>
                        <textarea id="binaryToken" placeholder="الصق binarySecurityToken هنا..."></textarea>

                        <label>Secret:</label>
                        <input type="password" id="secret" placeholder="الصق secret هنا...">

                        <label>البيئة:</label>
                        <select id="environment">
                            <option value="sandbox">Sandbox (تجريبي)</option>
                            <option value="simulation">Simulation</option>
                            <option value="production">Production (إنتاج)</option>
                        </select>

                        <div class="btn-group">
                            <button class="btn btn-success" onclick="saveCertificateManual()">
                                💾 حفظ الشهادة
                            </button>
                        </div>
                    </div>

                    <div id="saveResult" class="hidden"></div>

                    <div class="btn-group" style="margin-top: 30px;">
                        <button class="btn btn-outline" onclick="goToStep(1)">→ رجوع</button>
                        <button class="btn btn-success" onclick="goToStep(3)" id="step2NextBtn" disabled>التالي ←</button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Test -->
            <div class="step-content" id="step3">
                <div class="card">
                    <h3>✅ الخطوة 3: الاختبار</h3>

                    <div class="alert alert-success">
                        🎉 تم الإعداد بنجاح! الآن يمكنك اختبار النظام
                    </div>

                    <?php
                    $certExists = file_exists(ZatcaPhase2Config::CERT_DIR . '/sandbox_certificate.pem');
                    $secretExists = file_exists(ZatcaPhase2Config::CERT_DIR . '/sandbox_secret.txt');
                    ?>

                    <h4>حالة الملفات:</h4>
                    <div style="margin: 15px 0;">
                        <div class="status-badge <?php echo file_exists(ZatcaPhase2Config::PRIVATE_KEY_FILE) ? 'status-ok' : 'status-error'; ?>">
                            Private Key: <?php echo file_exists(ZatcaPhase2Config::PRIVATE_KEY_FILE) ? '✓' : '✗'; ?>
                        </div>
                        <div class="status-badge <?php echo file_exists(ZatcaPhase2Config::CSR_FILE) ? 'status-ok' : 'status-error'; ?>">
                            CSR: <?php echo file_exists(ZatcaPhase2Config::CSR_FILE) ? '✓' : '✗'; ?>
                        </div>
                        <div class="status-badge <?php echo $certExists ? 'status-ok' : 'status-pending'; ?>">
                            Certificate: <?php echo $certExists ? '✓' : '⏳'; ?>
                        </div>
                        <div class="status-badge <?php echo $secretExists ? 'status-ok' : 'status-pending'; ?>">
                            Secret: <?php echo $secretExists ? '✓' : '⏳'; ?>
                        </div>
                    </div>

                    <h4>الخطوات التالية:</h4>
                    <div style="margin: 20px 0;">
                        <a href="phase2_test.php" class="btn" style="display: inline-block; margin: 5px;">
                            🧪 اختبار توليد الفواتير
                        </a>
                        <a href="phase2_setup.php?test_components=1" class="btn btn-outline" style="display: inline-block; margin: 5px;">
                            🔍 فحص المكونات
                        </a>
                    </div>

                    <div class="btn-group">
                        <button class="btn btn-outline" onclick="goToStep(2)">→ رجوع</button>
                        <button class="btn btn-success" onclick="location.reload()">🔄 إعادة البدء</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        let currentMethod = 'auto'; // Default to automatic method

        function goToStep(step) {
            // Hide all steps
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active', 'completed'));

            // Show target step
            document.getElementById('step' + step).classList.add('active');
            document.querySelector(`.wizard-step[data-step="${step}"]`).classList.add('active');

            // Mark previous steps as completed
            for (let i = 1; i < step; i++) {
                document.querySelector(`.wizard-step[data-step="${i}"]`).classList.add('completed');
            }

            // Update progress bar
            const progress = (step / 3) * 100;
            document.getElementById('progressBar').style.width = progress + '%';

            currentStep = step;
        }

        function toggleMethod(method) {
            if (currentMethod === method) {
                document.getElementById(method + 'Method').classList.add('hidden');
                currentMethod = null;
            } else {
                document.getElementById('autoMethod').classList.add('hidden');
                document.getElementById('manualMethod').classList.add('hidden');
                document.getElementById(method + 'Method').classList.remove('hidden');
                currentMethod = method;
            }
        }

        // Certificate type change handler - for Select2 (Bootstrap select)
        $(document).ready(function() {
            console.log('Initializing certType handler for Select2...');

            const $certTypeSelect = $('#certType');
            const $productionFields = $('#productionFields');
            const $complianceFields = $('#complianceFields');

            console.log('- certType element:', $certTypeSelect.length ? 'Found' : 'NOT FOUND');
            console.log('- productionFields element:', $productionFields.length ? 'Found' : 'NOT FOUND');
            console.log('- complianceFields element:', $complianceFields.length ? 'Found' : 'NOT FOUND');

            if ($certTypeSelect.length === 0) {
                console.error('ERROR: certType select not found!');
                return;
            }

            // Use jQuery's .on() for Select2 compatibility
            $certTypeSelect.on('change', async function(e) {
                console.log('=== certType CHANGED (Select2) ===');
                console.log('New value:', $(this).val());
                console.log('Event:', e);

                const selectedValue = $(this).val();

                if (selectedValue === '/production/csids') {
                    console.log('→ Switching to PRODUCTION mode');
                    $productionFields.removeClass('hidden');
                    $complianceFields.addClass('hidden');
                    console.log('  ✓ Production fields shown, Compliance fields hidden');

                    // Auto-load the compliance request ID for production
                    console.log('  → Loading compliance request ID...');
                    await loadComplianceRequestId();
                } else {
                    console.log('→ Switching to COMPLIANCE mode');
                    $productionFields.addClass('hidden');
                    $complianceFields.removeClass('hidden');
                    console.log('  ✓ Compliance fields shown, Production fields hidden');
                }
                console.log('=== certType change complete ===');
            });

            console.log('✓ certType Select2 change event listener attached successfully');
        });

        // Function to auto-load compliance request ID for production certificate
        async function loadComplianceRequestId() {
            const requestIdField = document.getElementById('requestId');
            if (!requestIdField) return;

            try {
                // Read environment from the select box
                const selectedEnvironment = document.getElementById('environment').value;

                const formData = new FormData();
                formData.append('action', 'get_compliance_request_id');
                formData.append('environment', selectedEnvironment); // Use selected environment

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    requestIdField.value = result.request_id;
                    requestIdField.style.background = '#e7f5e7';
                    requestIdField.title = result.message;

                    // Show success message
                    const successMsg = document.createElement('small');
                    successMsg.style.cssText = 'display: block; color: #28a745; margin-top: 5px;';
                    successMsg.innerHTML = `✅ ${result.message}`;
                    requestIdField.parentElement.appendChild(successMsg);
                } else {
                    // Show warning that compliance certificate is needed first
                    requestIdField.placeholder = result.error;
                    requestIdField.style.background = '#fff3cd';

                    const warningMsg = document.createElement('small');
                    warningMsg.style.cssText = 'display: block; color: #856404; margin-top: 5px;';
                    warningMsg.innerHTML = `⚠️ ${result.error}`;
                    requestIdField.parentElement.appendChild(warningMsg);
                }
            } catch (error) {
                console.error('Error loading compliance request ID:', error);
            }
        }

        async function generateCSR() {
            const btn = document.getElementById('generateCSRBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span> جاري التوليد...';

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=generate_csr'
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('csrResult').innerHTML = `
                        <div class="alert alert-success">✅ تم توليد CSR بنجاح! (ECDSA secp256k1)</div>
                        <label>CSR Base64:</label>
                        <textarea id="csrBase64" readonly>${result.csr}</textarea>
                        <div class="btn-group">
                            <button class="btn" onclick="copyCSR()">📋 نسخ CSR</button>
                            <button class="btn btn-success" onclick="goToStep(2)">التالي ←</button>
                        </div>
                    `;
                    document.getElementById('csrResult').classList.remove('hidden');
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                document.getElementById('csrResult').innerHTML = `
                    <div class="alert alert-error">❌ خطأ: ${error.message}</div>
                `;
                document.getElementById('csrResult').classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '🔑 توليد CSR الآن';
            }
        }

        async function regenerateCSR() {
            // Use custom confirm with callback pattern
            confirm('هل أنت متأكد من إعادة توليد CSR؟ سيتم حذف CSR الحالي وإنشاء واحد جديد تماماً.', 'تأكيد إعادة التوليد', async function() {
                // User confirmed, proceed with regeneration
                await performRegenerateCSR();
            }, function() {
                // User cancelled
                console.log('User cancelled CSR regeneration');
            });
        }

        async function performRegenerateCSR() {
            // Get the textarea element
            const csrTextarea = document.getElementById('csrBase64');

            // Check if textarea exists
            if (!csrTextarea) {
                alert('❌ خطأ: لم يتم العثور على حقل CSR. حاول تحديث الصفحة.');
                console.error('CSR textarea element not found');
                return;
            }

            // Show loading state on the textarea
            const originalValue = csrTextarea.value;
            csrTextarea.value = 'جاري حذف الملفات القديمة...';
            csrTextarea.disabled = true;

            try {
                // First, delete the old CSR files
                console.log('Step 1: Deleting old CSR files...');
                const deleteResponse = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=delete_csr',
                    cache: 'no-store'
                });

                const deleteResult = await deleteResponse.json();
                console.log('Delete result:', deleteResult);

                if (!deleteResult.success) {
                    throw new Error('Failed to delete old CSR files: ' + deleteResult.error);
                }

                // Now generate the new CSR
                console.log('Step 2: Generating new CSR...');
                csrTextarea.value = 'جاري توليد CSR جديد...';

                // Add cache-buster to ensure fresh response
                const timestamp = new Date().getTime();
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=generate_csr&_ts=${timestamp}`,
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }

                const result = await response.json();
                console.log('CSR generation response:', result);

                if (result.success && result.csr) {
                    // Verify we got a new CSR
                    if (result.csr === originalValue) {
                        console.warn('WARNING: Received same CSR as before!');
                    }

                    // Update the textarea with new CSR
                    csrTextarea.value = result.csr;
                    csrTextarea.disabled = false;

                    // Show success message with CSR length info
                    const successMsg = document.createElement('div');
                    successMsg.className = 'alert alert-success';
                    successMsg.style.marginTop = '10px';
                    successMsg.innerHTML = `✅ تم إعادة توليد CSR بنجاح! (${result.csr.length} حرف)`;
                    csrTextarea.parentElement.insertBefore(successMsg, csrTextarea.nextSibling);

                    // Remove success message after 4 seconds
                    setTimeout(() => successMsg.remove(), 4000);
                } else {
                    throw new Error(result.error || 'No CSR returned from server');
                }
            } catch (error) {
                console.error('regenerateCSR error:', error);

                // Restore original value on error
                if (csrTextarea) {
                    csrTextarea.value = originalValue;
                    csrTextarea.disabled = false;

                    // Show error message next to textarea
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'alert alert-error';
                    errorMsg.style.marginTop = '10px';
                    errorMsg.innerHTML = `❌ خطأ: ${error.message}<br><br><small>💡 تحقق من ملف السجل: admin/zatca/logs/zatca_logs.txt</small>`;
                    csrTextarea.parentElement.insertBefore(errorMsg, csrTextarea.nextSibling);

                    // Remove error message after 5 seconds
                    setTimeout(() => errorMsg.remove(), 5000);
                } else {
                    // If textarea doesn't exist, show alert instead
                    alert(`❌ خطأ في إعادة توليد CSR:\n\n${error.message}`);
                }
            }
        }

        async function deleteCSR() {
            // Use custom confirm with callback pattern
            confirm('هل أنت متأكد من حذف ملفات CSR؟\n\nسيتم حذف:\n• ملف CSR\n• مفتاح الخصوصية\n\nيمكنك إعادة توليدها لاحقاً.', 'تأكيد الحذف', async function() {
                // User confirmed, proceed with deletion
                await performDeleteCSR();
            }, function() {
                // User cancelled
                console.log('User cancelled CSR deletion');
            });
        }

        async function performDeleteCSR() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=delete_csr',
                    cache: 'no-store'
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ تم حذف ملفات CSR بنجاح!\n\nستحتاج إلى إعادة تحميل الصفحة لرؤية التغييرات.');
                    location.reload();
                } else {
                    alert('❌ خطأ: ' + result.error);
                }
            } catch (error) {
                console.error('deleteCSR error:', error);
                alert('❌ خطأ في حذف ملفات CSR: ' + error.message);
            }
        }

        async function callZATCAAPI() {
            const csr = document.getElementById('csrBase64')?.value;
            const endpoint = document.getElementById('certType').value;
            const requestId = document.getElementById('requestId')?.value;

            // Validate based on endpoint type
            if (endpoint === 'production/csids') {
                // Production requires Request ID
                if (!requestId) {
                    alert('الرجاء التأكد من تحميل Request ID من شهادة Compliance');
                    return;
                }
            } else {
                // Compliance requires CSR and OTP
                if (!csr) {
                    alert('الرجاء توليد CSR أولاً');
                    return;
                }

                const otpField = document.getElementById('otp');
                const otp = otpField?.value;

                console.log('OTP Field exists:', !!otpField);
                console.log('OTP Value:', otp);

                if (!otp) {
                    alert('الرجاء إدخال OTP (القيمة الافتراضية: 123345)');
                    return;
                }
            }

            const resultDiv = document.getElementById('apiResult');
            resultDiv.innerHTML = '<div class="alert alert-info"><span class="loading"></span> جاري الاتصال بـ ZATCA...</div>';
            resultDiv.classList.remove('hidden');

            try {
                const formData = new FormData();
                formData.append('action', 'call_zatca_api');
                formData.append('endpoint', endpoint);
                formData.append('environment', document.getElementById('environment').value);

                // Add CSR for compliance endpoint only
                if (endpoint === 'compliance' && csr) {
                    formData.append('csr', csr);
                }

                // Add Request ID for production endpoint
                if (endpoint === 'production/csids' && requestId) {
                    formData.append('request_id', requestId);
                }

                // Add OTP for compliance endpoint
                const otp = document.getElementById('otp')?.value;
                if (otp && endpoint === 'compliance') {
                    formData.append('otp', otp);
                    console.log('OTP added to request:', otp);
                }

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success && result.data) {
                    // Automatically save the certificate (pass requestID from API response)
                    // Read environment from the select box
                    const selectedEnvironment = document.getElementById('environment').value;

                    // Detect if this is a production certificate
                    const isProduction = (endpoint === 'production/csids');

                    await saveCertificateAuto(
                        result.data.binarySecurityToken,
                        result.data.secret,
                        selectedEnvironment, // Use selected environment instead of hardcoding
                        result.data.requestID || '', // Pass requestID from ZATCA response
                        isProduction // Pass production flag
                    );
                } else {
                    // Show detailed error
                    let errorHTML = `<div class="alert alert-error">❌ خطأ: ${result.error || 'فشل الاتصال بـ ZATCA'}`;

                    // Add details if available
                    if (result.details) {
                        errorHTML += '<br><br><strong>تفاصيل الخطأ:</strong><br>';
                        if (result.details.http_code) {
                            errorHTML += `• HTTP Code: ${result.details.http_code}<br>`;
                        }
                        if (result.details.curl_errno) {
                            errorHTML += `• cURL Error: ${result.details.curl_errno} - ${result.details.curl_error}<br>`;

                            // Special handling for SSL certificate errors (error 60)
                            if (result.details.curl_errno === 60) {
                                errorHTML += `<br><div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;">
                                    <strong>🔒 مشكلة SSL Certificate</strong><br>
                                    هذا خطأ شائع يمكن حله بسهولة!<br><br>
                                    <a href="fix_ssl_certificate.php" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;">
                                        🔧 حل المشكلة الآن
                                    </a>
                                </div>`;
                            }
                        }
                        if (result.details.response) {
                            errorHTML += `<br><small><strong>الاستجابة:</strong><br><pre style="background: #2c3e50; color: #ecf0f1; padding: 10px; border-radius: 5px; overflow-x: auto; max-height: 200px;">${result.details.response}</pre></small>`;
                        }
                        errorHTML += '<br><small style="color: #856404;">💡 تحقق من ملف السجل: admin/zatca/logs/zatca_logs.txt</small>';
                    }

                    errorHTML += '</div>';
                    throw new Error(errorHTML);
                }
            } catch (error) {
                // Check if error.message contains HTML (our detailed error)
                if (error.message.includes('<div')) {
                    resultDiv.innerHTML = error.message;
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-error">❌ خطأ: ${error.message}<br><br><small>💡 تحقق من ملف السجل: admin/zatca/logs/zatca_logs.txt</small></div>`;
                }
            }
        }

        async function saveCertificateAuto(binaryToken, secret, environment, requestId = '', isProduction = false) {
            const formData = new FormData();
            formData.append('action', 'save_certificate');
            formData.append('binary_token', binaryToken);
            formData.append('secret', secret);
            formData.append('environment', environment);

            // Add requestID if provided (from ZATCA API response)
            if (requestId) {
                formData.append('request_id', requestId);
            }

            // Add production flag - this tells PHP to save to certificate.pem + secret.txt
            // and create production_mode.flag to protect from overwriting
            if (isProduction) {
                formData.append('is_production', 'true');
            }

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    let filesHTML = `• ${result.files.certificate}<br>• ${result.files.secret}`;
                    if (result.request_id) {
                        filesHTML += `<br>• Request ID: ${result.request_id} (محفوظ للاستخدام مع Production)`;
                    }

                    document.getElementById('apiResult').innerHTML = `
                        <div class="alert alert-success">
                            ✅ تم الحصول على الشهادة وحفظها تلقائياً!<br>
                            <small>الملفات المحفوظة:<br>
                            ${filesHTML}</small>
                        </div>
                    `;
                    document.getElementById('step2NextBtn').disabled = false;

                    setTimeout(() => goToStep(3), 2000);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                document.getElementById('apiResult').innerHTML = `
                    <div class="alert alert-error">❌ خطأ في الحفظ: ${error.message}</div>
                `;
            }
        }

        async function saveCertificateManual() {
            const binaryToken = document.getElementById('binaryToken').value;
            const secret = document.getElementById('secret').value;
            const environment = document.getElementById('environment').value;

            if (!binaryToken || !secret) {
                alert('الرجاء إدخال الشهادة والسر');
                return;
            }

            await saveCertificateAuto(binaryToken, secret, environment);

            document.getElementById('saveResult').innerHTML = `
                <div class="alert alert-success">✅ تم حفظ الشهادة بنجاح!</div>
            `;
            document.getElementById('saveResult').classList.remove('hidden');
            document.getElementById('step2NextBtn').disabled = false;
        }

        function copyCSR() {
            const csr = document.getElementById('csrBase64');
            csr.select();
            document.execCommand('copy');
            alert('✅ تم نسخ CSR');
        }
    </script>
</body>
</html>
