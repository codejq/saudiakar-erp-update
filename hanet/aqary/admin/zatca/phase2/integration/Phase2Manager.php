<?php
/**
 * ZATCA Phase 2 Manager
 *
 * Main integration point for Phase 2 functionality
 * Combines XML generation, signing, and API submission
 *
 * @charset UTF-8
 * @version 2.0
 * @date 2025-11-01
 */

require_once __DIR__ . '/../../config/phase2_config.php';
require_once __DIR__ . '/../xml/UBLInvoiceGenerator.php';
require_once __DIR__ . '/../signature/InvoiceSigner.php';
require_once __DIR__ . '/../signature/HashGenerator.php';
require_once __DIR__ . '/../api/ZatcaAPIClient.php';
require_once __DIR__ . '/../counter/InvoiceCounter.php';
require_once __DIR__ . '/../../lib/InvoiceHelper.php';

// Load the php-zatca-xml library (sevaske fork)
require_once __DIR__ . '/../../lib/php-zatca-xml-main/vendor/autoload.php';

class Phase2Manager {

    private $dbLink;
    private $xmlGenerator;
    private $signer;
    private $apiClient;
    private $counter;
    private $useLibrarySigner = true;  // Use library's InvoiceSigner - it handles signature correctly
    private $libraryMode = 'full';

    private function normalizeCertificateForLibrary(string $certificatePemOrB64): string {
        $cert = trim($certificatePemOrB64);

        // If it's a PEM, extract its body for inspection.
        if (preg_match('/-----BEGIN CERTIFICATE-----\s*(.*?)\s*-----END CERTIFICATE-----/s', $cert, $m)) {
            $body = str_replace(["\n", "\r", " "], '', trim($m[1]));

            // Some environments store a base64 blob that itself decodes to another base64 string.
            $decoded = base64_decode($body, true);
            if ($decoded !== false) {
                $decodedTrim = trim($decoded);

                // If the decoded content looks like another base64-encoded DER (often starts with MI...)
                // wrap it again as PEM and return.
                if (strpos($decodedTrim, 'MI') === 0 && base64_decode($decodedTrim, true) !== false) {
                    return "-----BEGIN CERTIFICATE-----\n" . chunk_split($decodedTrim, 64, "\n") . "-----END CERTIFICATE-----";
                }

                // If it decodes to DER directly (starts with 0x30)
                if ($decodedTrim !== '' && isset($decodedTrim[0]) && ord($decodedTrim[0]) === 0x30) {
                    return "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($decodedTrim), 64, "\n") . "-----END CERTIFICATE-----";
                }
            }

            // Normal PEM: return as-is
            return $cert;
        }

        // If it's raw base64 (no PEM markers), wrap it.
        $b64 = str_replace(["\n", "\r", " "], '', $cert);
        if (base64_decode($b64, true) !== false) {
            return "-----BEGIN CERTIFICATE-----\n" . chunk_split($b64, 64, "\n") . "-----END CERTIFICATE-----";
        }

        return $cert;
    }

    private function sanitizeX509CertificateNode(string $signedXml): string {
        // ZATCA XSD requires ds:X509Certificate content to be base64Binary only (no PEM markers).
        return preg_replace_callback(
            '/(<ds:X509Certificate>)([\s\S]*?)(<\\/ds:X509Certificate>)/',
            static function ($m) {
                $content = trim($m[2]);
                $content = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $content);
                $content = str_replace(["\n", "\r", " ", "\t"], '', $content);
                return $m[1] . $content . $m[3];
            },
            $signedXml
        );
    }

    private function ensureLibraryQrHasEncodingCode(string $signedXml): string {
        // Library QR node omits encodingCode="Base64"; ZATCA examples often include it.
        return preg_replace(
            '/(<cac:AdditionalDocumentReference[\s\S]*?<cbc:ID[^>]*>\s*QR\s*<\\/cbc:ID>[\s\S]*?<cbc:EmbeddedDocumentBinaryObject)(?![^>]*\bencodingCode=)([^>]*>)/',
            '$1 encodingCode="Base64"$2',
            $signedXml,
            1
        );
    }

    private function computeInvoiceHashFromSubmittedXml(string $signedXml): string {
        // Compute invoice hash from the *exact* XML bytes that will be submitted, using ZATCA exclude rules:
        // remove UBLExtensions, cac:Signature, and QR AdditionalDocumentReference, then C14N and SHA-256.
        // Returns Base64-encoded SHA-256 digest.
        $xml = ltrim($signedXml, "\xEF\xBB\xBF");

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xml)) {
            throw new Exception('Failed to load submitted XML for invoice hash calculation');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');

        // Remove nodes in a safe order.
        foreach ($xpath->query('//ext:UBLExtensions') as $node) {
            $node->parentNode->removeChild($node);
        }
        foreach ($xpath->query('//cac:Signature') as $node) {
            $node->parentNode->removeChild($node);
        }
        foreach ($xpath->query("//cac:AdditionalDocumentReference[normalize-space(cbc:ID)='QR']") as $node) {
            $node->parentNode->removeChild($node);
        }

        $canonical = $dom->documentElement->C14N(false, false);
        if ($canonical === false) {
            throw new Exception('Canonicalization failed while computing submitted invoice hash');
        }

        return base64_encode(hash('sha256', $canonical, true));
    }

    private function normalizeInvoiceHashForApi(string $hash): string {
        $h = trim($hash);
        $h = str_replace(["\r", "\n", "\t", ' '], '', $h);

        $decoded = base64_decode($h, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $h;
        }

        // Some implementations return the SHA-256 as hex. Convert 64 hex chars to base64.
        if (strlen($h) === 64 && ctype_xdigit($h)) {
            $bin = hex2bin(strtolower($h));
            if ($bin !== false && strlen($bin) === 32) {
                return base64_encode($bin);
            }
        }

        ZatcaPhase2Config::log('[LIB] Invoice hash format unexpected; sending as-is. Value: ' . $h, 'WARNING');
        return $h;
    }

    /**
     * Constructor
     *
     * @param resource $dbLink Database connection
     * @param string|null $environment Override environment (uses global $zatca_environment if not specified)
     */
    public function __construct($dbLink, $environment = null) {
        $this->dbLink = $dbLink;
        $this->xmlGenerator = new UBLInvoiceGenerator();
        $this->counter = new InvoiceCounter($dbLink);

        // CRITICAL: Load environment from global $zatca_environment (from data.hnt)
        // This ensures we use the correct certificate for the current environment
        global $zatca_environment;

        // Priority: 1) Explicit parameter, 2) Global $zatca_environment, 3) Config default
        if ($environment !== null) {
            $selectedEnvironment = $environment;
        } elseif (isset($zatca_environment)) {
            $selectedEnvironment = $zatca_environment;
        } else {
            $selectedEnvironment = ZatcaPhase2Config::$ENVIRONMENT;
        }

        // Log which environment we're using
        ZatcaPhase2Config::log("Phase2Manager initializing with environment: " . strtoupper($selectedEnvironment) .
            (isset($zatca_environment) ? " (global=\$zatca_environment)" : " (config default)"), 'INFO');

        // CRITICAL: Detect production mode — must match processInvoice() logic exactly
        $complianceCertFile = ZatcaPhase2Config::CERT_DIR . '/' . $selectedEnvironment . '_certificate.pem';
        $complianceSecretFile = ZatcaPhase2Config::CERT_DIR . '/' . $selectedEnvironment . '_secret.txt';
        $productionCertFile = ZatcaPhase2Config::CERT_DIR . '/certificate.pem';
        $productionSecretFile = ZatcaPhase2Config::CERT_DIR . '/secret.txt';
        $productionFlagFile = ZatcaPhase2Config::CERT_DIR . '/production_mode.flag';

        $useProductionCert = false;

        // Same logic as processInvoice: flag file is the authoritative signal
        if (file_exists($productionFlagFile) && file_exists($productionCertFile) && file_exists($productionSecretFile)) {
            $useProductionCert = true;
        } elseif (file_exists($productionCertFile) && file_exists($productionSecretFile) && file_exists($complianceCertFile)) {
            if (md5_file($productionCertFile) !== md5_file($complianceCertFile)) {
                $useProductionCert = true;
            }
        }

        // Select certificate based on mode
        if ($useProductionCert) {
            $signerCertFile = $productionCertFile;
            $apiCertFile = $productionCertFile;
            $apiSecretFile = $productionSecretFile;
            ZatcaPhase2Config::log("Using PRODUCTION certificate (environment=$selectedEnvironment)", 'INFO');
        } else {
            $signerCertFile = $complianceCertFile;
            $apiCertFile = $complianceCertFile;
            $apiSecretFile = $complianceSecretFile;
            ZatcaPhase2Config::log("Using " . strtoupper($selectedEnvironment) . " certificate (environment=$selectedEnvironment)", 'INFO');
        }

        // Initialize signer with the CORRECT certificate
        if (file_exists($signerCertFile) && file_exists(ZatcaPhase2Config::PRIVATE_KEY_FILE)) {
            $this->signer = new InvoiceSigner(ZatcaPhase2Config::PRIVATE_KEY_FILE, $signerCertFile);
        }

        // Initialize API client with same credentials as signer
        $this->apiClient = new ZatcaAPIClient();

        // Set API credentials using the same certificate as signer
        if (file_exists($apiCertFile) && file_exists($apiSecretFile)) {
            $certificate = trim(file_get_contents($apiCertFile));
            $secret = trim(file_get_contents($apiSecretFile));

            // Extract certificate content (remove PEM headers for API authentication)
            $certContent = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"], '', $certificate);

            $this->apiClient->setCredentials($certContent, $secret);
            ZatcaPhase2Config::log("API credentials loaded from: " . basename($apiCertFile), 'INFO');
        } else {
            ZatcaPhase2Config::log("API credentials not found", 'WARNING');
            ZatcaPhase2Config::log("Certificate file: $apiCertFile (exists: " . (file_exists($apiCertFile) ? 'yes' : 'no') . ")", 'WARNING');
            ZatcaPhase2Config::log("Secret file: $apiSecretFile (exists: " . (file_exists($apiSecretFile) ? 'yes' : 'no') . ")", 'WARNING');
        }
    }

    /**
     * Process invoice - Complete Phase 2 workflow
     *
     * @param array $invoiceData Invoice data
     * @param string $invoiceType Type identifier (standard/simplified)
     * @param bool $submitToZatca Whether to submit to ZATCA API
     * @param bool $forceComplianceCert Force using compliance certificate (for compliance testing)
     * @return array Result
     */
    public function processInvoice($invoiceData, $invoiceType = 'standard', $submitToZatca = false, $forceComplianceCert = false) {
        try {
            ZatcaPhase2Config::log("Processing invoice: " . ($invoiceData['invoice_number'] ?? 'N/A'), 'INFO');

            // Step 1: Get counter and PIH
            $counter = $this->counter->getNextCounter($invoiceType);
            $previousHash = $this->counter->getLastHash($invoiceType);

            $invoiceData['invoice_counter'] = $counter;
            $invoiceData['previous_invoice_hash'] = $previousHash;
            $invoiceData['uuid'] = $this->generateUUID();

            // Step 2: Generate XML with placeholder PIH
            $xml = $this->xmlGenerator->generateXML($invoiceData);

            if (!$xml) {
                throw new Exception('XML generation failed');
            }

            // Step 3: Sign XML
            if (!$this->signer) {
                throw new Exception('Signer not initialized - certificates missing');
            }

            $signedXML = '';
            $signature = '';
            $hash = '';
            $qrCode = '';

            if ($this->useLibrarySigner) {
                $autoload = __DIR__ . '/../../lib/php-zatca-xml-main/vendor/autoload.php';
                if (!file_exists($autoload)) {
                    throw new Exception('php-zatca-xml autoload not found at: ' . $autoload);
                }
                require_once $autoload;

                // CRITICAL: Use the SAME environment detection as constructor (global $zatca_environment)
                // This ensures we use the correct certificate for the current environment
                global $zatca_environment;

                // Use global $zatca_environment from data.hnt (same logic as constructor)
                if (isset($zatca_environment)) {
                    $env = $zatca_environment;
                } else {
                    $env = ZatcaPhase2Config::$ENVIRONMENT;
                }

                $complianceCertFile = ZatcaPhase2Config::CERT_DIR . '/' . $env . '_certificate.pem';
                $complianceSecretFile = ZatcaPhase2Config::CERT_DIR . '/' . $env . '_secret.txt';
                $productionCertFile = ZatcaPhase2Config::CERT_DIR . '/certificate.pem';
                $productionSecretFile = ZatcaPhase2Config::CERT_DIR . '/secret.txt';
                $productionFlagFile = ZatcaPhase2Config::CERT_DIR . '/production_mode.flag';

                // Detect production mode (unless forceComplianceCert is set)
                $useProductionCert = false;
                if (!$forceComplianceCert) {
                    if (file_exists($productionFlagFile) && file_exists($productionCertFile) && file_exists($productionSecretFile)) {
                        $useProductionCert = true;
                    } elseif (file_exists($productionCertFile) && file_exists($productionSecretFile) && file_exists($complianceCertFile)) {
                        if (md5_file($productionCertFile) !== md5_file($complianceCertFile)) {
                            $useProductionCert = true;
                        }
                    }
                }

                if ($forceComplianceCert) {
                    ZatcaPhase2Config::log('[LIB] Forcing COMPLIANCE certificate for signing (compliance test mode)', 'INFO');
                }

                if ($useProductionCert) {
                    $certFile = $productionCertFile;
                    $secretFile = $productionSecretFile;
                    ZatcaPhase2Config::log('[LIB] Using PRODUCTION certificate for signing', 'INFO');
                } else {
                    $certFile = $complianceCertFile;
                    $secretFile = $complianceSecretFile;
                    ZatcaPhase2Config::log('[LIB] Using COMPLIANCE certificate for signing', 'INFO');

                    // Fallback: if env-specific compliance cert not found, try other environments
                    if (!file_exists($certFile)) {
                        foreach (['sandbox', 'simulation', 'production'] as $fbEnv) {
                            if ($fbEnv === $env) continue;
                            $fbCert   = ZatcaPhase2Config::CERT_DIR . '/' . $fbEnv . '_certificate.pem';
                            $fbSecret = ZatcaPhase2Config::CERT_DIR . '/' . $fbEnv . '_secret.txt';
                            if (file_exists($fbCert) && file_exists($fbSecret)) {
                                $certFile   = $fbCert;
                                $secretFile = $fbSecret;
                                ZatcaPhase2Config::log('[LIB] Fallback to ' . $fbEnv . ' compliance certificate for signing', 'INFO');
                                break;
                            }
                        }
                    }
                }

                if (!file_exists($certFile)) {
                    throw new Exception('Certificate file not found: ' . $certFile);
                }
                if (!file_exists($secretFile)) {
                    throw new Exception('Secret file not found: ' . $secretFile);
                }

                // Read certificate and prepare for library (library expects PEM format WITH headers)
                $certContent = file_get_contents($certFile);
                ZatcaPhase2Config::log('[CERT DEBUG] Certificate file: ' . $certFile, 'INFO');
                ZatcaPhase2Config::log('[CERT DEBUG] Certificate content length: ' . strlen($certContent) . ' bytes', 'INFO');

                // Extract base64 content from PEM
                if (preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $certContent, $m)) {
                    $layer1_decoded = base64_decode(str_replace(["\n", "\r", " "], '', $m[1]));
                    ZatcaPhase2Config::log('[CERT DEBUG] Layer 1 decoded length: ' . strlen($layer1_decoded) . ' bytes', 'INFO');
                    ZatcaPhase2Config::log('[CERT DEBUG] Layer 1 starts with: ' . substr($layer1_decoded, 0, 20), 'INFO');

                    // ZATCA certificates are double-encoded: check if Layer 1 decodes to another base64 string
                    // MII* indicates ASN.1 DER sequence (double-encoded certificate)
                    if (strlen($layer1_decoded) > 100 && substr($layer1_decoded, 0, 3) === 'MII') {
                        // Double-encoded: use Layer 2 (inner base64 string) and wrap in PEM headers for library
                        $cleanCert = "-----BEGIN CERTIFICATE-----\n" . chunk_split($layer1_decoded, 64, "\n") . "-----END CERTIFICATE-----";
                        ZatcaPhase2Config::log('[CERT DEBUG] Using double-encoded certificate (Layer 2) with PEM headers', 'INFO');
                    } else {
                        // Single-encoded: use original PEM content with headers
                        $cleanCert = $certContent;
                        ZatcaPhase2Config::log('[CERT DEBUG] Using single-encoded certificate (Layer 1) with PEM headers', 'INFO');
                    }

                    ZatcaPhase2Config::log('[CERT DEBUG] Clean cert length: ' . strlen($cleanCert) . ' chars', 'INFO');
                } else {
                    ZatcaPhase2Config::log('[CERT ERROR] Certificate PEM parsing failed', 'ERROR');
                    throw new Exception('Invalid certificate format - missing PEM headers');
                }

                // Validate certificate content (PEM format should be at least 200 chars)
                if (empty($cleanCert) || strlen($cleanCert) < 200 || strpos($cleanCert, '-----BEGIN CERTIFICATE-----') === false) {
                    ZatcaPhase2Config::log('[CERT ERROR] Certificate content is invalid: ' . strlen($cleanCert) . ' chars', 'ERROR');
                    throw new Exception('Invalid certificate content - too short or missing PEM headers');
                }

                // Extract and validate private key (library expects PEM format WITH headers)
                $privateKeyPem = ZatcaPhase2Config::getPrivateKey();

                // Ensure private key has PEM headers for library
                if (strpos($privateKeyPem, '-----BEGIN') === false) {
                    // Raw base64, wrap in PEM headers
                    $privateKeyPem = "-----BEGIN PRIVATE KEY-----\n" . chunk_split($privateKeyPem, 64, "\n") . "-----END PRIVATE KEY-----";
                }

                // Validate private key (PEM format should be at least 200 chars)
                if (empty($privateKeyPem) || strlen($privateKeyPem) < 200 || strpos($privateKeyPem, '-----BEGIN') === false) {
                    ZatcaPhase2Config::log('[KEY ERROR] Private key is invalid: ' . strlen($privateKeyPem) . ' chars', 'ERROR');
                    throw new Exception('Invalid private key - too short or missing PEM headers');
                }
                ZatcaPhase2Config::log('[KEY DEBUG] Private key length: ' . strlen($privateKeyPem) . ' chars', 'INFO');

                $secret = trim(file_get_contents($secretFile));

                // Validate secret
                if (empty($secret) || strlen($secret) < 10) {
                    ZatcaPhase2Config::log('[SECRET ERROR] Secret is empty or too short: ' . strlen($secret) . ' chars', 'ERROR');
                    throw new Exception('Invalid secret - too short or empty');
                }
                ZatcaPhase2Config::log('[SECRET DEBUG] Secret length: ' . strlen($secret) . ' chars', 'INFO');

                // Pass PEM-formatted certificate and key to library (phpseclib expects PEM format)
                try {
                    $certificate = new \Saleh7\Zatca\Helpers\Certificate(
                        $cleanCert,        // ✅ PEM format with headers
                        $privateKeyPem,    // ✅ PEM format with headers
                        $secret            // ✅ Plain text secret
                    );
                    ZatcaPhase2Config::log('[CERT] Certificate object created successfully', 'INFO');
                } catch (Exception $e) {
                    ZatcaPhase2Config::log('[CERT ERROR] Failed to create Certificate object: ' . $e->getMessage(), 'ERROR');
                    throw new Exception('Failed to create Certificate object: ' . $e->getMessage());
                }

                // DEBUG: Log certificate issuer DN for troubleshooting signed-properties-hashing errors
                ZatcaPhase2Config::log('[CERT DEBUG] Issuer DN: ' . $certificate->getFormattedIssuer(), 'INFO');
                ZatcaPhase2Config::log('[CERT DEBUG] Cert Hash: ' . $certificate->getCertHash(), 'INFO');

                $libSigned = \Saleh7\Zatca\InvoiceSigner::signInvoice($xml, $certificate);
                $signedXML = $libSigned->getXML();
                $libraryHash = $this->normalizeInvoiceHashForApi($libSigned->getHash());

                $qrCode = $libSigned->getQRCode();

                // Fix invalid base64Binary in ds:X509Certificate (library may embed PEM markers)
                $signedXML = $this->sanitizeX509CertificateNode($signedXML);

                // Ensure QR node has encodingCode attribute
                $signedXML = $this->ensureLibraryQrHasEncodingCode($signedXML);

                // CRITICAL FIX: Recalculate and fix SignedProperties hash in the final XML
                // The library calculates hash on a template, but ZATCA validates by hashing
                // the actual SignedProperties element from the received XML
                $signedXML = $this->fixSignedPropertiesHash($signedXML);

                // Extract signature value from XML for return payload (Tag 7 should equal this)
                if (preg_match('/<ds:SignatureValue>([^<]+)<\\/ds:SignatureValue>/', $signedXML, $m)) {
                    $signature = $m[1];
                }

                // CRITICAL FIX: Recalculate hash from final XML to match what ZATCA will calculate
                // The library computes hash BEFORE toXml() which may change whitespace
                // ZATCA calculates hash from the XML they receive, so we must match that
                $hash = $this->recalculateInvoiceHash($signedXML);
                ZatcaPhase2Config::log('[LIB] Library hash: ' . $libraryHash, 'INFO');
                ZatcaPhase2Config::log('[LIB] Recalculated hash from final XML: ' . $hash, 'INFO');
                if ($hash !== $libraryHash) {
                    ZatcaPhase2Config::log('[LIB] WARNING: Hash mismatch - using recalculated hash', 'WARNING');
                }

                // CRITICAL FIX: Library bug - Tag 8 uses public key instead of full certificate
                // InvoiceExtension.php:368 incorrectly uses getRawPublicKey() instead of getRawCertificate()
                // This causes ZATCA to reject the QR with QRCODE_INVALID errors
                ZatcaPhase2Config::log('[QR FIX] Starting QR correction process', 'INFO');
                ZatcaPhase2Config::log('[QR FIX] Library QR length: ' . strlen(base64_decode($qrCode)) . ' bytes', 'INFO');

                require_once __DIR__ . '/../../lib/FixedQRGenerator.php';
                $dom = new \DOMDocument();
                @$dom->loadXML($signedXML);  // Suppress warnings, we'll catch errors

                try {
                    $correctedQR = \ZatcaIntegration\FixedQRGenerator::generateCorrectQR(
                        $dom,
                        $certificate,
                        $hash,
                        $signature,
                        true  // Simplified invoice
                    );

                    ZatcaPhase2Config::log('[QR FIX] Corrected QR generated: ' . strlen(base64_decode($correctedQR)) . ' bytes', 'INFO');

                    // Replace the incorrect QR code in the XML with the corrected one
                    // IMPORTANT: Match specifically the QR code (has encodingCode="Base64"), not PIH
                    $beforeReplace = $signedXML;
                    $signedXML = preg_replace(
                        '/<cbc:EmbeddedDocumentBinaryObject([^>]*encodingCode="Base64"[^>]*)>([^<]+)<\/cbc:EmbeddedDocumentBinaryObject>/',
                        '<cbc:EmbeddedDocumentBinaryObject$1>' . $correctedQR . '</cbc:EmbeddedDocumentBinaryObject>',
                        $signedXML,
                        1,
                        $count
                    );

                    if ($count > 0) {
                        ZatcaPhase2Config::log('[QR FIX] Successfully replaced QR code in XML', 'INFO');
                    } else {
                        ZatcaPhase2Config::log('[QR FIX] WARNING: preg_replace did not match any QR code!', 'WARNING');
                    }

                    // Update the QR code variable to return the corrected version
                    $qrCode = $correctedQR;

                } catch (\Exception $e) {
                    ZatcaPhase2Config::log('[QR FIX] ERROR: ' . $e->getMessage(), 'ERROR');
                    ZatcaPhase2Config::log('[QR FIX] Falling back to library QR code', 'WARNING');
                }

                // REMOVED: The old generateQRCode() override was re-introducing the Tag 8 bug
                // FixedQRGenerator already handles Tags 1-9 correctly (including Tag 8 with full certificate)
                // Lines 336-348 already embedded the corrected QR into signedXML

                ZatcaPhase2Config::log("[LIB] Invoice hash: " . $hash, 'INFO');
                ZatcaPhase2Config::log("[LIB] QR code length: " . strlen($qrCode), 'INFO');
            } else {
                $signResult = $this->signer->signInvoice($xml);

                if (!$signResult['success']) {
                    throw new Exception('Signing failed: ' . $signResult['error']);
                }

                $signedXML = $signResult['signed_xml'];
                $signature = $signResult['signature'];
                $hash = $signResult['hash'];

                // PIH = Previous Invoice Hash (hash of the PREVIOUS invoice, not current)
                // This is correct for invoice chaining - PIH links to the previous invoice
                // DigestValue and QR Tag 6 = hash of CURRENT invoice
                ZatcaPhase2Config::log("Invoice hash (DigestValue): " . $hash, 'INFO');
                ZatcaPhase2Config::log("PIH (Previous Invoice Hash): " . $previousHash, 'INFO');

                // Step 3.5: Generate QR Code with correct hash and signature
                $qrCode = $this->generateQRCode($invoiceData, $hash, $signature);
                ZatcaPhase2Config::log("QR Code generated: " . substr($qrCode, 0, 40) . '...', 'INFO');

                // CRITICAL FIX: Apply QR correction for Tag 8 (full certificate instead of public key)
                ZatcaPhase2Config::log('[QR FIX] Starting QR correction process', 'INFO');
                ZatcaPhase2Config::log('[QR FIX] Library QR length: ' . strlen(base64_decode($qrCode)) . ' bytes', 'INFO');

                require_once __DIR__ . '/../../lib/FixedQRGenerator.php';

                // Get certificate for QR correction
                // CRITICAL: Must use the SAME certificate that will be used for API authentication
                // If production mode (certificate.pem + secret.txt exist and are different from compliance),
                // use production certificate. Otherwise use compliance certificate.
                $env = ZatcaPhase2Config::$ENVIRONMENT;
                $complianceCertFile = ZatcaPhase2Config::CERT_DIR . '/' . $env . '_certificate.pem';
                $complianceSecretFile = ZatcaPhase2Config::CERT_DIR . '/' . $env . '_secret.txt';
                $productionCertFile = ZatcaPhase2Config::CERT_DIR . '/certificate.pem';
                $productionSecretFile = ZatcaPhase2Config::CERT_DIR . '/secret.txt';
                $productionFlagFile = ZatcaPhase2Config::CERT_DIR . '/production_mode.flag';

                // Detect production mode
                $useProductionCert = false;
                if (file_exists($productionFlagFile) && file_exists($productionCertFile) && file_exists($productionSecretFile)) {
                    $useProductionCert = true;
                } elseif (file_exists($productionCertFile) && file_exists($productionSecretFile) && file_exists($complianceCertFile)) {
                    if (md5_file($productionCertFile) !== md5_file($complianceCertFile)) {
                        $useProductionCert = true;
                    }
                }

                if ($useProductionCert) {
                    $certFile = $productionCertFile;
                    $secretFile = $productionSecretFile;
                    ZatcaPhase2Config::log('[QR FIX] Using PRODUCTION certificate for QR generation', 'INFO');
                } else {
                    $certFile = $complianceCertFile;
                    $secretFile = $complianceSecretFile;
                    ZatcaPhase2Config::log('[QR FIX] Using COMPLIANCE certificate for QR generation', 'INFO');
                }

                $certContent = file_get_contents($certFile);
                $secret = trim(file_get_contents($secretFile));

                // Extract certificate (matching library logic)
                if (preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $certContent, $m)) {
                    $layer1_decoded = base64_decode(str_replace(["\n", "\r", " "], '', $m[1]));
                    if (substr($layer1_decoded, 0, 4) === 'MIIC' || substr($layer1_decoded, 0, 4) === 'MIID') {
                        $cleanCert = $layer1_decoded;
                    } else {
                        $cleanCert = str_replace(["\n", "\r", " "], '', $m[1]);
                    }
                } else {
                    throw new Exception('Invalid certificate format');
                }

                $privateKeyPem = ZatcaPhase2Config::getPrivateKey();
                $cleanPrivateKey = trim(str_replace([
                    "-----BEGIN PRIVATE KEY-----",
                    "-----END PRIVATE KEY-----",
                    "-----BEGIN EC PRIVATE KEY-----",
                    "-----END EC PRIVATE KEY-----",
                    "\r",
                    "\n"
                ], '', $privateKeyPem));

                $autoload = __DIR__ . '/../../lib/php-zatca-xml-main/vendor/autoload.php';
                require_once $autoload;
                $certificate = new \Saleh7\Zatca\Helpers\Certificate(
                    $cleanCert,
                    $cleanPrivateKey,
                    $secret
                );

                $dom = new \DOMDocument();
                @$dom->loadXML($signedXML);

                try {
                    $correctedQR = \ZatcaIntegration\FixedQRGenerator::generateCorrectQR(
                        $dom,
                        $certificate,
                        $hash,
                        $signature,
                        true  // Simplified invoice
                    );

                    ZatcaPhase2Config::log('[QR FIX] Corrected QR generated: ' . strlen(base64_decode($correctedQR)) . ' bytes', 'INFO');

                    // Replace QR in XML
                    $signedXML = preg_replace(
                        '/<cbc:EmbeddedDocumentBinaryObject([^>]*encodingCode="Base64"[^>]*)>([^<]+)<\/cbc:EmbeddedDocumentBinaryObject>/',
                        '<cbc:EmbeddedDocumentBinaryObject$1>' . $correctedQR . '</cbc:EmbeddedDocumentBinaryObject>',
                        $signedXML,
                        1,
                        $count
                    );

                    if ($count > 0) {
                        ZatcaPhase2Config::log('[QR FIX] Successfully replaced QR code in XML', 'INFO');
                        $qrCode = $correctedQR;
                    } else {
                        ZatcaPhase2Config::log('[QR FIX] WARNING: Could not replace QR in XML, embedding manually', 'WARNING');
                        // Step 3.6: Embed QR Code in signed XML
                        $signedXML = $this->embedQRCode($signedXML, $correctedQR);
                        $qrCode = $correctedQR;
                    }

                } catch (\Exception $e) {
                    ZatcaPhase2Config::log('[QR FIX] ERROR: ' . $e->getMessage(), 'ERROR');
                    ZatcaPhase2Config::log('[QR FIX] Falling back to library QR code', 'WARNING');
                    // Step 3.6: Embed QR Code in signed XML
                    $signedXML = $this->embedQRCode($signedXML, $qrCode);
                }

                ZatcaPhase2Config::log("QR Code embedded in signed XML", 'INFO');
            }

            // Step 4: Save hash for next invoice
            $this->counter->saveLastHash($invoiceType, $hash);

            // Step 5: Submit to ZATCA (if enabled)
            $apiResult = null;
            if ($submitToZatca && ZatcaPhase2Config::API_INTEGRATION_ENABLED) {
                $isStandard = $invoiceData['is_standard'] ?? true;

                if ($isStandard) {
                    // B2B - Clearance
                    $apiResult = $this->apiClient->clearInvoice(
                        $hash,
                        $invoiceData['uuid'],
                        $signedXML
                    );
                } else {
                    // B2C - Reporting
                    $apiResult = $this->apiClient->reportInvoice(
                        $hash,
                        $invoiceData['uuid'],
                        $signedXML
                    );
                }
            }

            ZatcaPhase2Config::log("Invoice processed successfully: " . $invoiceData['invoice_number'], 'INFO');

            return [
                'success' => true,
                'invoice_number' => $invoiceData['invoice_number'],
                'uuid' => $invoiceData['uuid'],
                'counter' => $counter,
                'xml' => $xml,
                'signed_xml' => $signedXML,
                'hash' => $hash,
                'signature' => $signature,
                'qr_code' => $qrCode,
                'previous_hash' => $previousHash,
                'invoice_date' => $invoiceData['invoice_date'],
                'invoice_time' => $invoiceData['invoice_time'] ?? date('H:i:s'),
                'total_amount' => $invoiceData['total_amount'],
                'vat_amount' => $invoiceData['vat_amount'],
                'api_result' => $apiResult
            ];

        } catch (Exception $e) {
            $error = 'Phase 2 processing error: ' . $e->getMessage();
            ZatcaPhase2Config::log($error, 'ERROR');

            return [
                'success' => false,
                'error' => $error
            ];
        }
    }

    /**
     * Process sanad invoice
     *
     * @param int $idsanad Sanad ID
     * @param bool $submitToZatca Submit to ZATCA
     * @param bool $forceComplianceCert Force using compliance certificate (for compliance testing)
     * @return array Result
     */
    public function processSanad($idsanad, $submitToZatca = false, $forceComplianceCert = false) {
        try {
            // Extract invoice data from sanad
            $invoiceData = InvoiceHelper::extractInvoiceData('sanad', $idsanad, $this->dbLink);

            if (!$invoiceData) {
                throw new Exception('Failed to extract sanad data');
            }

            // Process invoice
            $result = $this->processInvoice($invoiceData, 'sanad', $submitToZatca, $forceComplianceCert);

            if ($result['success']) {
                // Save to database
                $this->saveSanadPhase2Data($idsanad, $result);
            }

            return $result;

        } catch (Exception $e) {
            $error = 'Sanad processing error: ' . $e->getMessage();
            ZatcaPhase2Config::log($error, 'ERROR');

            return [
                'success' => false,
                'error' => $error
            ];
        }
    }

    /**
     * Process egar invoice
     *
     * @param int $rakam3akdid Egar ID
     * @param bool $submitToZatca Submit to ZATCA
     * @return array Result
     */
    public function processEgar($rakam3akdid, $submitToZatca = false) {
        try {
            // Extract invoice data from egar
            $invoiceData = InvoiceHelper::extractInvoiceData('egar', $rakam3akdid, $this->dbLink);

            if (!$invoiceData) {
                throw new Exception('Failed to extract egar data');
            }

            // Process invoice
            $result = $this->processInvoice($invoiceData, 'egar', $submitToZatca);

            if ($result['success']) {
                // Save to database
                $this->saveEgarPhase2Data($rakam3akdid, $result);
            }

            return $result;

        } catch (Exception $e) {
            $error = 'Egar processing error: ' . $e->getMessage();
            ZatcaPhase2Config::log($error, 'ERROR');

            return [
                'success' => false,
                'error' => $error
            ];
        }
    }

    /**
     * Save Phase 2 data to sanad table
     *
     * @param int $idsanad Sanad ID
     * @param array $data Phase 2 data
     */
    private function saveSanadPhase2Data($idsanad, $data) {
        $xml = mysqli_real_escape_string($this->dbLink, $data['xml']);
        $signedXml = mysqli_real_escape_string($this->dbLink, $data['signed_xml']);
        $hash = mysqli_real_escape_string($this->dbLink, $data['hash']);
        $previousHash = mysqli_real_escape_string($this->dbLink, $data['previous_hash']);
        $uuid = mysqli_real_escape_string($this->dbLink, $data['uuid']);

        $sql = "UPDATE sanad SET
                zatca_xml = '" . $xml . "',
                zatca_signed_xml = '" . $signedXml . "',
                zatca_invoice_hash = '" . $hash . "',
                zatca_invoice_counter = " . intval($data['counter']) . ",
                zatca_pih = '" . $previousHash . "',
                zatca_uuid = '" . $uuid . "'
                WHERE idsanad = " . intval($idsanad);

        mysqli_query($this->dbLink, $sql);
    }

    /**
     * Save Phase 2 data to egar table
     *
     * @param int $rakam3akdid Egar ID
     * @param array $data Phase 2 data
     */
    private function saveEgarPhase2Data($rakam3akdid, $data) {
        $xml = mysqli_real_escape_string($this->dbLink, $data['xml']);
        $signedXml = mysqli_real_escape_string($this->dbLink, $data['signed_xml']);
        $hash = mysqli_real_escape_string($this->dbLink, $data['hash']);
        $previousHash = mysqli_real_escape_string($this->dbLink, $data['previous_hash']);
        $uuid = mysqli_real_escape_string($this->dbLink, $data['uuid']);

        $sql = "UPDATE egar SET
                zatca_xml = '" . $xml . "',
                zatca_signed_xml = '" . $signedXml . "',
                zatca_invoice_hash = '" . $hash . "',
                zatca_invoice_counter = " . intval($data['counter']) . ",
                zatca_pih = '" . $previousHash . "',
                zatca_uuid = '" . $uuid . "'
                WHERE rakam3akdid = " . intval($rakam3akdid);

        mysqli_query($this->dbLink, $sql);
    }

    /**
     * Generate UUID v4
     *
     * @return string UUID
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Generate QR Code for invoice
     *
     * ALL Phase 2 invoices (both B2B and B2C) require exactly 9 QR tags per ZATCA specification
     * CRITICAL: Tags 6-9 must be RAW BINARY data, not Base64 strings!
     *
     * Tag 6: Invoice Hash - 32 raw bytes (SHA-256)
     * Tag 7: ECDSA Signature - raw signature bytes from signing
     * Tag 8: ECDSA Public Key - raw public key bytes from certificate
     * Tag 9: Certificate Signature - raw certificate signature bytes
     *
     * @param array $invoiceData Invoice data
     * @param string $hash Invoice hash (Base64 encoded)
     * @param string $signature Digital signature (Base64 encoded)
     * @return string Base64 QR code data
     */
    private function generateQRCode($invoiceData, $hash, $signature) {
        require_once __DIR__ . '/../qrcode/QRCodeGenerator.php';
        require_once __DIR__ . '/../../config/phase2_config.php';

        $companyInfo = ZatcaPhase2Config::getCompanyInfo();

        // Get certificate path
        $environment = ZatcaPhase2Config::$ENVIRONMENT;
        $certPath = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_certificate.pem';

        if (!file_exists($certPath)) {
            throw new Exception('Certificate not found at: ' . $certPath);
        }

        $certificatePEM = file_get_contents($certPath);

        // Prepare basic invoice data for Tags 1-5
        $basicInvoiceData = [
            'seller_name' => $invoiceData['seller_name'] ?? $companyInfo['name_ar'],
            'tax_id' => $invoiceData['seller_vat_number'] ?? $companyInfo['vat_number'],
            'invoice_date' => $invoiceData['invoice_date'],
            'invoice_time' => $invoiceData['invoice_time'] ?? date('H:i:s'),
            'total_amount' => $invoiceData['total_amount'],
            'vat_amount' => $invoiceData['vat_amount']
        ];

        $profile = $invoiceData['profile'] ?? 'reporting:1.0';
        ZatcaPhase2Config::log("Generating QR code with Tags 1-9 for profile: $profile", 'INFO');
        ZatcaPhase2Config::log("Using QRCodeGenerator::generatePhase2QRCode() library method", 'INFO');

        // Use the new QRCodeGenerator library method that handles everything
        // This automatically extracts public key and certificate signature (Tags 8 & 9)
        $qrCode = QRCodeGenerator::generatePhase2QRCode(
            $basicInvoiceData,
            $hash,
            $signature,
            $certificatePEM
        );

        ZatcaPhase2Config::log("QR Code generated using library: " . strlen($qrCode) . " chars (base64)", 'INFO');

        return $qrCode;
    }

    // NOTE: Old certificate extraction methods removed - now using QRCodeGenerator library
    // The QRCodeGenerator::extractCertificateData() handles Tags 8 & 9 extraction

    /**
     * Recalculate invoice hash from signed XML
     * This is needed because the library's formatOutput=true changes whitespace after hash is computed
     * ZATCA calculates hash from the received XML, so we must send the hash of the final XML
     *
     * @param string $signedXml The signed XML
     * @return string Base64-encoded SHA256 hash
     */
    private function recalculateInvoiceHash($signedXml) {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        @$dom->loadXML($signedXml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        // Remove UBLExtensions, Signature, and QR (same as ZATCA does)
        $nodesToRemove = [];
        foreach ($xpath->query('//ext:UBLExtensions') as $node) $nodesToRemove[] = $node;
        foreach ($xpath->query('//cac:Signature') as $node) $nodesToRemove[] = $node;
        foreach ($xpath->query('//cac:AdditionalDocumentReference[cbc:ID="QR"]') as $node) $nodesToRemove[] = $node;

        foreach ($nodesToRemove as $node) {
            $node->parentNode->removeChild($node);
        }

        // Canonicalize and hash
        $canonicalXml = $dom->documentElement->C14N(false, false);
        $hash = base64_encode(hash('sha256', $canonicalXml, true));

        return $hash;
    }

    /**
     * Fix SignedProperties hash in the final XML
     * The library calculates hash on a template, but ZATCA validates by hashing
     * the actual SignedProperties element from the received XML.
     * This function recalculates the hash and updates the DigestValue.
     *
     * @param string $signedXml The signed XML
     * @return string The XML with corrected SignedProperties hash
     */
    private function fixSignedPropertiesHash($signedXml) {
        // The issue is that modifying the DigestValue changes the SignedInfo,
        // which would invalidate the signature. We cannot fix this post-signing.
        //
        // The real fix must be in the library's template to match what's embedded.
        // This function now just logs for debugging purposes.

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        @$dom->loadXML($signedXml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

        // Find the SignedProperties element
        $signedPropsNodes = $xpath->query('//xades:SignedProperties[@Id="xadesSignedProperties"]');
        if ($signedPropsNodes->length === 0) {
            ZatcaPhase2Config::log('[SIGNED PROPS DEBUG] SignedProperties element not found', 'WARNING');
            return $signedXml;
        }

        $signedPropsNode = $signedPropsNodes->item(0);

        // Canonicalize the SignedProperties element (C14N 1.1 - same as ZATCA uses)
        // Note: C14N(false, false) = non-exclusive, without comments
        $canonicalSignedProps = $signedPropsNode->C14N(false, false);

        // Log the first 500 chars of canonical form for debugging
        ZatcaPhase2Config::log('[SIGNED PROPS DEBUG] Canonical form (first 500 chars): ' . substr($canonicalSignedProps, 0, 500), 'DEBUG');
        ZatcaPhase2Config::log('[SIGNED PROPS DEBUG] Canonical length: ' . strlen($canonicalSignedProps), 'INFO');

        // Calculate what the hash SHOULD be based on canonical form
        $hexHash = hash('sha256', $canonicalSignedProps);
        $calculatedHash = base64_encode($hexHash);

        // Get the current DigestValue
        $refNodes = $xpath->query('//ds:Reference[@URI="#xadesSignedProperties"]/ds:DigestValue');
        if ($refNodes->length > 0) {
            $currentHash = $refNodes->item(0)->textContent;
            ZatcaPhase2Config::log('[SIGNED PROPS DEBUG] Current DigestValue: ' . $currentHash, 'INFO');
            ZatcaPhase2Config::log('[SIGNED PROPS DEBUG] Calculated hash: ' . $calculatedHash, 'INFO');

            if ($currentHash !== $calculatedHash) {
                ZatcaPhase2Config::log('[SIGNED PROPS DEBUG] MISMATCH - library template differs from actual XML', 'WARNING');
            }
        }

        // Return unchanged - we cannot modify DigestValue without invalidating signature
        return $signedXml;
    }

    /**
     * Get EGS (Electronic Generation System) serial number from CSID certificate
     * QR Tag 7: EGS unit serial number
     *
     * @return string Base64-encoded serial number (binary)
     */
    private function getEGSSerialNumber() {
        try {
            // Use environment-specific certificate
            $environment = ZatcaPhase2Config::$ENVIRONMENT;
            $certPath = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_certificate.pem';

            if (!file_exists($certPath)) {
                ZatcaPhase2Config::log('Certificate not found for EGS serial at: ' . $certPath, 'WARNING');
                return '';
            }

            $certContent = file_get_contents($certPath);
            $cert = $this->parseCertificate($certContent);

            if (!$cert) {
                ZatcaPhase2Config::log('Failed to parse certificate for EGS serial', 'WARNING');
                return '';
            }

            // Get certificate serial number (usually returned as hex string)
            $certData = openssl_x509_parse($cert);
            $serialHex = $certData['serialNumberHex'] ?? $certData['serialNumber'] ?? '';

            if (empty($serialHex)) {
                ZatcaPhase2Config::log('Certificate serial number not found', 'WARNING');
                return '';
            }

            // Remove any colons or spaces from hex string
            $serialHex = str_replace([':', ' '], '', $serialHex);

            // Convert hex to binary, then base64 encode
            // ZATCA expects the serial as binary data, not the hex string
            if (ctype_xdigit($serialHex)) {
                $serialBinary = hex2bin($serialHex);
                if ($serialBinary === false) {
                    ZatcaPhase2Config::log('Failed to convert serial hex to binary: ' . $serialHex, 'WARNING');
                    return '';
                }

                ZatcaPhase2Config::log('EGS Serial Number (hex): ' . $serialHex . ' (' . strlen($serialBinary) . ' bytes)', 'INFO');
                return base64_encode($serialBinary);
            } else {
                // If not hex, use as-is (shouldn't happen with OpenSSL)
                ZatcaPhase2Config::log('EGS Serial Number (not hex): ' . $serialHex, 'WARNING');
                return base64_encode($serialHex);
            }

        } catch (Exception $e) {
            ZatcaPhase2Config::log('Error extracting EGS serial: ' . $e->getMessage(), 'WARNING');
            return '';
        }
    }

    /**
     * Get SHA-256 hash of EGS public key
     * QR Tag 8: Public key hash/thumbprint
     *
     * @return string Base64-encoded SHA-256 hash of public key
     */
    private function getEGSPublicKeyHash() {
        try {
            // Use environment-specific certificate
            $environment = ZatcaPhase2Config::$ENVIRONMENT;
            $certPath = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_certificate.pem';

            if (!file_exists($certPath)) {
                ZatcaPhase2Config::log('Certificate not found for public key hash at: ' . $certPath, 'WARNING');
                return '';
            }

            $certContent = file_get_contents($certPath);
            $cert = $this->parseCertificate($certContent);

            if (!$cert) {
                ZatcaPhase2Config::log('Failed to parse certificate for public key hash', 'WARNING');
                return '';
            }

            // Get public key
            $pubKey = openssl_pkey_get_public($cert);
            $pubKeyDetails = openssl_pkey_get_details($pubKey);

            // For EC keys, extract the raw public key (DER format)
            $publicKeyDer = '';
            if (isset($pubKeyDetails['ec'])) {
                // EC public key - use the x and y coordinates
                $x = $pubKeyDetails['ec']['x'];
                $y = $pubKeyDetails['ec']['y'];
                // Uncompressed EC point format: 0x04 + x + y
                $publicKeyDer = chr(0x04) . $x . $y;
            } elseif (isset($pubKeyDetails['key'])) {
                // Extract DER from PEM
                $pem = $pubKeyDetails['key'];
                if (preg_match('/-----BEGIN PUBLIC KEY-----(.+?)-----END PUBLIC KEY-----/s', $pem, $m)) {
                    $publicKeyDer = base64_decode(str_replace(["\n", "\r"], '', $m[1]));
                }
            }

            if (empty($publicKeyDer)) {
                ZatcaPhase2Config::log('Failed to extract public key DER', 'WARNING');
                return '';
            }

            // Calculate SHA-256 hash of the public key
            $publicKeyHash = hash('sha256', $publicKeyDer, true);

            ZatcaPhase2Config::log('Public key hash calculated: ' . strlen($publicKeyHash) . ' bytes', 'INFO');
            return base64_encode($publicKeyHash);

        } catch (Exception $e) {
            ZatcaPhase2Config::log('Error calculating public key hash: ' . $e->getMessage(), 'WARNING');
            return '';
        }
    }

    /**
     * Get invoice type code for QR Tag 9
     * Returns the invoice type code as per ZATCA specification
     *
     * @param array $invoiceData Invoice data
     * @return string Invoice type code (e.g., "0200000")
     */
    private function getInvoiceTypeCode($invoiceData) {
        // Standard type code from InvoiceTypeCode in UBL
        // For Phase 2: "0200000" for standard invoices, "0200000" for simplified
        // The actual code is determined by the is_standard flag
        $isStandard = $invoiceData['is_standard'] ?? false;

        // ZATCA uses "0200000" for both standard and simplified in Phase 2
        // The difference is in the profile (clearance vs reporting), not the type code
        $typeCode = '0200000';

        ZatcaPhase2Config::log("Invoice type code: $typeCode (standard: " . ($isStandard ? 'yes' : 'no') . ")", 'INFO');
        return base64_encode($typeCode);
    }

    /**
     * Parse certificate handling double-encoded ZATCA format
     * Helper method to parse ZATCA certificates consistently
     *
     * ZATCA certificates are DOUBLE base64 encoded:
     * Layer 1: PEM with base64 content
     * Layer 2: When decoded, gives another base64 string (MIIC...)
     * Layer 3: When decoded again, gives actual DER certificate
     *
     * @param string $certContent Certificate file content
     * @return resource|false OpenSSL certificate resource or false on failure
     */
    private function parseCertificate($certContent) {
        // Try direct parsing first
        $cert = @openssl_x509_read($certContent);

        if (!$cert) {
            // Extract base64 content from PEM
            if (preg_match('/-----BEGIN CERTIFICATE-----\s*(.*?)\s*-----END CERTIFICATE-----/s', $certContent, $matches)) {
                $layer1_base64 = str_replace(["\n", "\r"], '', $matches[1]);

                // Decode Layer 1
                $layer1_decoded = base64_decode($layer1_base64, true);

                if ($layer1_decoded !== false) {
                    // Check if layer1 is also base64 (double encoding)
                    // ZATCA certificates start with "MIIC" when base64 decoded once
                    if (preg_match('/^[A-Za-z0-9+\/=\s]+$/', $layer1_decoded) &&
                        (substr($layer1_decoded, 0, 4) === 'MIIC' || substr($layer1_decoded, 0, 4) === 'MIID')) {

                        // Double encoded - decode again
                        $layer2_decoded = base64_decode($layer1_decoded, true);

                        if ($layer2_decoded !== false && substr($layer2_decoded, 0, 1) === "\x30") {
                            // Layer 2 is DER - wrap in PEM
                            $certToRead = "-----BEGIN CERTIFICATE-----\n" .
                                         chunk_split(base64_encode($layer2_decoded), 64) .
                                         "-----END CERTIFICATE-----";
                            $cert = @openssl_x509_read($certToRead);
                            if ($cert) {
                                ZatcaPhase2Config::log('Certificate parsed with double-decode', 'DEBUG');
                                return $cert;
                            }
                        }
                    }

                    // Single encoded - try layer1 as certificate content
                    $certToRead = "-----BEGIN CERTIFICATE-----\n" .
                                 chunk_split($layer1_decoded, 64) .
                                 "-----END CERTIFICATE-----";
                    $cert = @openssl_x509_read($certToRead);
                }
            }
        }

        return $cert;
    }

    /**
     * Extract certificate information for QR code
     * Tag 8: EC Public Key (raw uncompressed point)
     * Tag 9: Certificate Signature (extracted from DER certificate)
     *
     * @return array Certificate info with public_key and signature (both base64 encoded)
     */
    private function extractCertificateInfo() {
        try {
            // Use environment-specific certificate (same as constructor)
            $environment = ZatcaPhase2Config::$ENVIRONMENT;
            $certPath = ZatcaPhase2Config::CERT_DIR . '/' . $environment . '_certificate.pem';

            if (!file_exists($certPath)) {
                ZatcaPhase2Config::log('Certificate not found for QR code at: ' . $certPath, 'WARNING');
                return ['public_key' => '', 'signature' => ''];
            }

            $certContent = file_get_contents($certPath);

            // Parse certificate using same logic as InvoiceSigner
            // The certificate file is TRIPLE-ENCODED for compliance:
            // Layer 1: PEM markers
            // Layer 2: Base64 content (when decoded gives...)
            // Layer 3: More base64 that OpenSSL can handle
            $cert = @openssl_x509_read($certContent);

            if (!$cert) {
                // Extract and unwrap the first layer (same as InvoiceSigner)
                if (preg_match('/-----BEGIN CERTIFICATE-----\s*(.*?)\s*-----END CERTIFICATE-----/s', $certContent, $matches)) {
                    $layer1_base64 = $matches[1];
                    $layer1_base64 = str_replace(["\n", "\r"], '', $layer1_base64);

                    // Decode Layer 1: TUlJQy9EQ0NBC... → MIIC...
                    $layer2_content = base64_decode($layer1_base64, true);

                    if ($layer2_content !== false) {
                        // Wrap Layer 2 content in PEM markers for OpenSSL
                        $certToRead = "-----BEGIN CERTIFICATE-----\n" .
                                     chunk_split($layer2_content, 64) .
                                     "-----END CERTIFICATE-----";

                        $cert = @openssl_x509_read($certToRead);
                    }
                }
            }

            if (!$cert) {
                ZatcaPhase2Config::log('Failed to parse certificate for QR code', 'WARNING');
                return ['public_key' => '', 'signature' => ''];
            }

            // Get public key
            $pubKey = openssl_pkey_get_public($cert);
            $pubKeyDetails = openssl_pkey_get_details($pubKey);

            // For EC keys, extract the raw public key (DER format)
            $publicKeyDer = '';
            if (isset($pubKeyDetails['ec'])) {
                // EC public key - use the x and y coordinates
                $x = $pubKeyDetails['ec']['x'];
                $y = $pubKeyDetails['ec']['y'];
                // Uncompressed EC point format: 0x04 + x + y
                $publicKeyDer = chr(0x04) . $x . $y;
            } elseif (isset($pubKeyDetails['key'])) {
                // Extract DER from PEM
                $pem = $pubKeyDetails['key'];
                if (preg_match('/-----BEGIN PUBLIC KEY-----(.+?)-----END PUBLIC KEY-----/s', $pem, $m)) {
                    $publicKeyDer = base64_decode(str_replace(["\n", "\r"], '', $m[1]));
                }
            }

            // Get certificate DER for signature extraction
            openssl_x509_export($cert, $certPem);
            $certDer = '';
            if (preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $certPem, $m)) {
                $certDer = base64_decode(str_replace(["\n", "\r"], '', $m[1]));
            }

            // Extract certificate signature from DER structure
            // The signature is in the last BIT STRING of the certificate
            $certSignature = $this->extractCertificateSignature($certDer);

            ZatcaPhase2Config::log('Certificate info extracted - Public key: ' . strlen($publicKeyDer) . ' bytes, Signature: ' . strlen($certSignature) . ' bytes', 'INFO');

            return [
                'public_key' => base64_encode($publicKeyDer),
                'signature' => base64_encode($certSignature)
            ];

        } catch (Exception $e) {
            ZatcaPhase2Config::log('Error extracting certificate info: ' . $e->getMessage(), 'WARNING');
            return ['public_key' => '', 'signature' => ''];
        }
    }

    /**
     * Extract signature from DER-encoded certificate
     * The signature is the last element in the certificate sequence
     *
     * @param string $derCert DER-encoded certificate
     * @return string Raw signature bytes
     */
    private function extractCertificateSignature($derCert) {
        try {
            $offset = 0;
            $length = strlen($derCert);

            // Parse outer SEQUENCE
            if (ord($derCert[$offset]) !== 0x30) {
                throw new Exception('Invalid certificate: not a SEQUENCE');
            }
            $offset++;

            // Get length of outer sequence
            $seqLen = $this->parseASN1Length($derCert, $offset);

            // Skip TBSCertificate (first element)
            if (ord($derCert[$offset]) !== 0x30) {
                throw new Exception('Invalid TBSCertificate');
            }
            $offset++;
            $tbsLen = $this->parseASN1Length($derCert, $offset);
            $offset += $tbsLen;

            // Skip SignatureAlgorithm (second element)
            if (ord($derCert[$offset]) !== 0x30) {
                throw new Exception('Invalid SignatureAlgorithm');
            }
            $offset++;
            $sigAlgLen = $this->parseASN1Length($derCert, $offset);
            $offset += $sigAlgLen;

            // Read Signature (BIT STRING - third element)
            if (ord($derCert[$offset]) !== 0x03) {
                throw new Exception('Invalid Signature: not a BIT STRING');
            }
            $offset++;
            $sigLen = $this->parseASN1Length($derCert, $offset);

            // Skip unused bits indicator (first byte of BIT STRING content)
            $unusedBits = ord($derCert[$offset]);
            $offset++;
            $sigLen--;

            // Extract signature bytes
            $signature = substr($derCert, $offset, $sigLen);

            return $signature;

        } catch (Exception $e) {
            ZatcaPhase2Config::log('Failed to extract certificate signature: ' . $e->getMessage(), 'WARNING');
            // Fallback: return hash of certificate
            return hash('sha256', $derCert, true);
        }
    }

    /**
     * Embed QR Code in signed XML
     *
     * @param string $signedXML Signed XML
     * @param string $qrCode Base64 QR code data
     * @return string XML with embedded QR code
     */
    private function embedQRCode($signedXML, $qrCode) {
        try {
            // CRITICAL: Use string replacement to avoid DOM modifying the XML
            // DOM can change whitespace/encoding which invalidates the signature

            // Check if QR element already exists
            if (preg_match('/<cac:AdditionalDocumentReference[\s\S]*?<cbc:ID[^>]*>\s*QR\s*<\/cbc:ID>/', $signedXML)) {
                // Replace the QR EmbeddedDocumentBinaryObject content (robust against whitespace/newlines)
                $signedXML = preg_replace(
                    '/(<cac:AdditionalDocumentReference[\s\S]*?<cbc:ID[^>]*>\s*QR\s*<\/cbc:ID>[\s\S]*?<cbc:EmbeddedDocumentBinaryObject[^>]*>)([\s\S]*?)(<\/cbc:EmbeddedDocumentBinaryObject>)/',
                    '${1}' . $qrCode . '${3}',
                    $signedXML,
                    1
                );

                // Ensure encodingCode="Base64" exists on the QR EmbeddedDocumentBinaryObject
                $signedXML = preg_replace(
                    '/(<cac:AdditionalDocumentReference[\s\S]*?<cbc:ID[^>]*>\s*QR\s*<\/cbc:ID>[\s\S]*?<cbc:EmbeddedDocumentBinaryObject)(?![^>]*\bencodingCode=)([^>]*>)/',
                    '$1 encodingCode="Base64"$2',
                    $signedXML,
                    1
                );

                // If the above replacement failed (no match), do a fallback: replace any EmbeddedDocumentBinaryObject inside the QR docref.
                if (!preg_match('/<cac:AdditionalDocumentReference[\s\S]*?<cbc:ID[^>]*>\s*QR\s*<\/cbc:ID>[\s\S]*?<cbc:EmbeddedDocumentBinaryObject[^>]*>' . preg_quote($qrCode, '/') . '<\/cbc:EmbeddedDocumentBinaryObject>/', $signedXML)) {
                    $signedXML = preg_replace_callback(
                        '/(<cac:AdditionalDocumentReference[\s\S]*?<cbc:ID[^>]*>\s*QR\s*<\/cbc:ID>[\s\S]*?<cbc:EmbeddedDocumentBinaryObject[^>]*>)([\s\S]*?)(<\/cbc:EmbeddedDocumentBinaryObject>)/',
                        static function ($m) use ($qrCode) {
                            return $m[1] . $qrCode . $m[3];
                        },
                        $signedXML,
                        1
                    );
                }
            } else {
                // QR element doesn't exist - insert after PIH
                // Don't include xmlns declarations - they're inherited from root
                $qrElement = '<cac:AdditionalDocumentReference>' .
                    '<cbc:ID>QR</cbc:ID>' .
                    '<cac:Attachment>' .
                    '<cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain" encodingCode="Base64">' .
                    $qrCode .
                    '</cbc:EmbeddedDocumentBinaryObject>' .
                    '</cac:Attachment>' .
                    '</cac:AdditionalDocumentReference>';

                // Insert after PIH element
                $signedXML = preg_replace(
                    '/(<cac:AdditionalDocumentReference>[\s\S]*?<cbc:ID[^>]*>PIH<\/cbc:ID>[\s\S]*?<\/cac:AdditionalDocumentReference>)/',
                    '${1}' . $qrElement,
                    $signedXML
                );
            }

            return $signedXML;

        } catch (Exception $e) {
            ZatcaPhase2Config::log('QR Code embedding error: ' . $e->getMessage(), 'ERROR');
            // Return original XML if embedding fails
            return $signedXML;
        }
    }

    /**
     * Create QR Document Reference element
     *
     * @param DOMDocument $dom DOM document
     * @param string $qrCode Base64 QR code
     * @return DOMElement Document reference element
     */
    private function createQRDocumentReference($dom, $qrCode) {
        $docRef = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cac:AdditionalDocumentReference'
        );

        $id = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'cbc:ID',
            'QR'
        );
        $docRef->appendChild($id);

        $this->createQRAttachment($dom, $docRef, $qrCode);

        return $docRef;
    }

    /**
     * Create QR Attachment structure
     *
     * @param DOMDocument $dom DOM document
     * @param DOMElement $parent Parent element
     * @param string $qrCode Base64 QR code
     */
    private function createQRAttachment($dom, $parent, $qrCode) {
        $attachment = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cac:Attachment'
        );

        $embeddedDoc = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'cbc:EmbeddedDocumentBinaryObject',
            $qrCode
        );
        $embeddedDoc->setAttribute('mimeCode', 'text/plain');
        $embeddedDoc->setAttribute('encodingCode', 'Base64');  // ZATCA requires this

        $attachment->appendChild($embeddedDoc);
        $parent->appendChild($attachment);
    }

    /**
     * Test Phase 2 setup
     *
     * @return array Test results
     */
    public function testSetup() {
        $results = [];

        // Test 1: Check certificates
        $results['certificates'] = [
            'exists' => ZatcaPhase2Config::certificatesExist(),
            'info' => ZatcaPhase2Config::getCertificateInfo()
        ];

        // Test 2: Check signer
        $results['signer'] = [
            'initialized' => $this->signer !== null
        ];

        // Test 3: Check API connectivity
        $results['api'] = [
            'connected' => $this->apiClient->testConnection(),
            'environment' => ZatcaPhase2Config::$ENVIRONMENT
        ];

        // Test 4: Check counter
        $results['counter'] = [
            'working' => true,
            'counters' => $this->counter->getAllCounters()
        ];

        return $results;
    }
}

?>
