<?php
/**
 * OpenSSL Command Line Certificate Generator
 *
 * Uses command-line OpenSSL instead of PHP extension
 * Works around Windows PHP OpenSSL config issues
 *
 * @charset UTF-8
 * @version 1.0
 */

require_once __DIR__ . '/../../config/phase2_config.php';

class OpenSSLCommandLine {

    private $opensslPath;
    private $configFile;

    private function normalizeUtf8($value) {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1256,CP1256,ISO-8859-6,Windows-1252,UTF-8');
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            foreach (['Windows-1256', 'CP1256', 'ISO-8859-6', 'Windows-1252'] as $encoding) {
                $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            }
        }

        return $value;
    }

    private function escapeConfigValue($value) {
        $value = $this->normalizeUtf8($value);
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        return trim($value);
    }

    /**
     * Constructor
     *
     * @param string $opensslPath Path to openssl.exe (optional)
     */
    public function __construct($opensslPath = null) {
        // Try to find OpenSSL executable
        $this->opensslPath = $opensslPath ?? $this->findOpenSSLExecutable();

        if (!$this->opensslPath) {
            throw new Exception('OpenSSL executable not found. Please install OpenSSL or provide path.');
        }

        // Create OpenSSL config file
        $this->configFile = $this->createConfigFile();
    }

    /**
     * Find OpenSSL executable
     *
     * @return string|null Path to openssl.exe
     */
    private function findOpenSSLExecutable() {
        // IMPORTANT: Y: is a mapped network drive and may not be accessible from PHP web server
        // The actual physical path is on C: drive

        // Try to find OpenSSL relative to project root
        // Current file: Y:\admin\zatca\phase2\certificate\OpenSSLCommandLine.php (mapped drive)
        // Actual path: C:\Dropbox\...\hanet\aqary\admin\zatca\phase2\certificate\OpenSSLCommandLine.php
        // Go up 4 levels to get to project root (aqary folder)
        $projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
        $relativeOpenSSL = $projectRoot . '/OpenSSL-Win64/bin/openssl.exe';

        // Common paths for OpenSSL executable on Windows
        // PRIORITIZE the confirmed physical C: drive path
        $possiblePaths = [
            // User's confirmed physical path (highest priority - works from web server)
            '../../../../OpenSSL-Win64/bin/openssl.exe',
            'C:/saudiakar-erp/hanet/aqary/OpenSSL-Win64/bin/openssl.exe',
            '../../../../../OpenSSL-Win64/bin/openssl.exe',

            // Relative path (might resolve to correct location)
            $relativeOpenSSL,

            // Mapped Y: drive (works from command line but may fail from web server)
            'Y:/OpenSSL-Win64/bin/openssl.exe',

            // Standard Windows locations
            'C:/Program Files/Git/mingw64/bin/openssl.exe',  // Git for Windows
            'C:/Program Files/Git/usr/bin/openssl.exe',
            'C:/OpenSSL-Win64/bin/openssl.exe',
            'C:/OpenSSL-Win32/bin/openssl.exe',
            'C:/Program Files/OpenSSL-Win64/bin/openssl.exe',
            'C:/Program Files (x86)/OpenSSL-Win32/bin/openssl.exe',
            'C:/wamp/bin/php/php8.0.2/openssl.exe',
            'C:/wamp64/bin/php/php8.0.2/openssl.exe',
            'C:/xampp/apache/bin/openssl.exe',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                ZatcaPhase2Config::log("Found OpenSSL executable at: $path", 'INFO');
                return $path;
            }
        }

        // Try to find in system PATH
        $output = [];
        $return = 0;

        // Windows
        exec('where openssl 2>nul', $output, $return);
        if ($return === 0 && !empty($output[0])) {
            ZatcaPhase2Config::log("Found OpenSSL in PATH: " . $output[0], 'INFO');
            return trim($output[0]);
        }

        // Linux/Mac
        $output = [];
        $return = 0;
        exec('which openssl 2>/dev/null', $output, $return);
        if ($return === 0 && !empty($output[0])) {
            ZatcaPhase2Config::log("Found OpenSSL in PATH: " . $output[0], 'INFO');
            return trim($output[0]);
        }

        ZatcaPhase2Config::log("OpenSSL executable not found in common paths or PATH", 'ERROR');
        return null;
    }

    /**
     * Build complete Subject Distinguished Name
     * Follows quantum-billing working approach
     * OID attributes go in subjectAltName dirName, not in main DN
     * CN includes serial number in format: TST-1-XXX|2-YYY|3-UUID
     *
     * @return string Subject DN for main DN section (without OID attributes)
     */
    private function buildSubjectDN() {
        $country = ZatcaPhase2Config::$CERT_COUNTRY ?? 'SA';
        $location = ZatcaPhase2Config::$CERT_LOCATION ?? 'Riyadh';
        $orgName = ZatcaPhase2Config::$CERT_ORGANIZATION_NAME ?? 'Your Company Name';
        $orgUnit = ZatcaPhase2Config::$CERT_ORGANIZATIONAL_UNIT ?? 'IT Department';

        // Generate serial number for CN
        // Format: TST-1-XXX|2-YYY|3-UUID (TST prefix + full serial number)
        $serialNumber = $this->generateDynamicSerialNumber();
        $commonName = "TST-$serialNumber";

        $industry = ZatcaPhase2Config::$CERT_INDUSTRY ?? 'software';
        $orgIdentifier = ZatcaPhase2Config::$CERT_ORGANIZATION_IDENTIFIER;

        // Main DN — must include L, businessCategory, and organizationIdentifier for simulation
        $dn = sprintf(
            '/C=%s/L=%s/O=%s/OU=%s/CN=%s/businessCategory=%s/organizationIdentifier=%s',
            $country,
            $location,
            $orgName,
            $orgUnit,
            $commonName,
            $industry,
            $orgIdentifier
        );

        ZatcaPhase2Config::log("Built subject DN: $dn", 'DEBUG');
        ZatcaPhase2Config::log("OID attributes will be added via subjectAltName dirName (quantum-billing approach)", 'DEBUG');
        return $dn;
    }

    /**
     * Create OpenSSL configuration file using quantum-billing approach
     * Includes OID attributes via subjectAltName dirName structure
     * Based on working implementation from quantum-billing-repo
     *
     * @return string Path to config file
     */
    private function createConfigFile() {
        $certDir = ZatcaPhase2Config::CERT_DIR;
        $configPath = $certDir . '/zatca_openssl.cnf';

        // Resolve to absolute path to avoid OpenSSL path resolution issues
        if (!is_dir($certDir)) {
            mkdir($certDir, 0700, true);
        }
        $absoluteConfigPath = realpath($certDir) . '/zatca_openssl.cnf';

        // Get ZATCA configuration values
        $serialNumber = $this->generateDynamicSerialNumber();
        $orgIdentifier = ZatcaPhase2Config::$CERT_ORGANIZATION_IDENTIFIER;
        $invoiceType = ZatcaPhase2Config::$CERT_INVOICE_TYPE;
        $orgName = $this->escapeConfigValue(ZatcaPhase2Config::$CERT_ORGANIZATION_NAME ?? 'Your Company Name');
        $orgUnit = $this->escapeConfigValue(ZatcaPhase2Config::$CERT_ORGANIZATIONAL_UNIT ?? 'IT Department');
        $location = $this->escapeConfigValue(ZatcaPhase2Config::$CERT_LOCATION ?? 'Riyadh');
        $industry = $this->escapeConfigValue(ZatcaPhase2Config::$CERT_INDUSTRY ?? 'software');

        // registeredAddress = building number from data.hnt ($zatca_company_building_number)
        global $zatca_company_building_number, $zatca_company_postal_code;
        $buildingNumber = $zatca_company_building_number ?? $zatca_company_postal_code ?? '0000';

        // certificateTemplateName (OID 1.3.6.1.4.1.311.20.2) is ENVIRONMENT-SPECIFIC:
        //   sandbox    → TSTZATCA-Code-Signing
        //   simulation → PREZATCA-Code-Signing
        //   production → ZATCA-Code-Signing
        $env = ZatcaPhase2Config::$ENVIRONMENT ?? 'sandbox';
        switch ($env) {
            case 'production':
                $certTemplateName = 'ZATCA-Code-Signing';
                break;
            case 'simulation':
                $certTemplateName = 'PREZATCA-Code-Signing';
                break;
            default: // sandbox
                $certTemplateName = 'TSTZATCA-Code-Signing';
                break;
        }

        // CN is just the environment-specific template name (no serial number appended)
        $commonName = $certTemplateName;

        ZatcaPhase2Config::log("Creating OpenSSL config for ZATCA environment: $env", 'INFO');
        ZatcaPhase2Config::log("  Certificate Template: $certTemplateName", 'DEBUG');
        ZatcaPhase2Config::log("  Common Name (CN): $commonName", 'DEBUG');
        ZatcaPhase2Config::log("  Organization ID: $orgIdentifier", 'DEBUG');
        ZatcaPhase2Config::log("  Invoice Type: $invoiceType", 'DEBUG');
        ZatcaPhase2Config::log("  Location (L): $location", 'DEBUG');
        ZatcaPhase2Config::log("  Building Number (registeredAddress): $buildingNumber", 'DEBUG');

        $config = <<<EOT
oid_section = OIDs

[ OIDs ]
certificateTemplateName = 1.3.6.1.4.1.311.20.2

[ req ]
prompt             = no
default_md         = sha256
req_extensions     = reqExt
distinguished_name = dn
utf8               = yes
string_mask        = utf8only

[ dn ]
C                      = SA
L                      = $location
O                      = $orgName
OU                     = $orgUnit
CN                     = $commonName
organizationIdentifier = $orgIdentifier
businessCategory       = $industry

[ v3_req ]
basicConstraints = CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment

[ reqExt ]
certificateTemplateName = ASN1:PRINTABLESTRING:$certTemplateName
subjectAltName          = dirName:alt_names

[ alt_names ]
SN                = $serialNumber
UID               = $orgIdentifier
title             = $invoiceType
registeredAddress = $buildingNumber
businessCategory  = $industry

EOT;

        file_put_contents($absoluteConfigPath, $config);
        ZatcaPhase2Config::log("OpenSSL config created at: $absoluteConfigPath (with subjectAltName dirName for OID attributes)", 'INFO');

        return $absoluteConfigPath;
    }

    /**
     * Generate dynamic serial number with new UUID
     * Format: 1-TST|2-TST|3-<UUID>
     * Uses cryptographically secure random generation
     *
     * @return string Serial number with new UUID
     */
    private function generateDynamicSerialNumber() {
        // Generate a new UUID v4 using cryptographically secure random bytes
        $data = random_bytes(16);

        // Set version to 4 (random) and variant to RFC 4122
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        // Format the UUID
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        // Format: 1-TST|2-TST|3-<UUID>
        $serialNumber = "1-TST|2-TST|3-" . $uuid;

        return $serialNumber;
    }

    /**
     * Generate private key - ECDSA secp256k1 (REQUIRED by ZATCA)
     *
     * ZATCA Phase 2 requires ECDSA keys with secp256k1 curve, NOT RSA!
     * RSA certificates will be rejected by ZATCA compliance check.
     *
     * @param string $keyPath Path to save private key
     * @return array ['success' => bool, 'error' => string]
     */
    public function generatePrivateKey($keyPath = null) {
        $keyPath = $keyPath ?? ZatcaPhase2Config::PRIVATE_KEY_FILE;

        // Ensure directory exists
        $dir = dirname($keyPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        // ZATCA requires ECDSA with secp256k1 curve (per official ZATCA SDK)
        $command = sprintf(
            '"%s" ecparam -name secp256k1 -genkey -noout -out "%s" 2>&1',
            $this->opensslPath,
            $keyPath
        );

        ZatcaPhase2Config::log("Generating ECDSA secp256k1 private key (ZATCA SDK requirement)...", 'INFO');
        ZatcaPhase2Config::log("Executing: $command", 'DEBUG');

        $output = [];
        $return = 0;
        exec($command, $output, $return);

        if ($return !== 0) {
            $error = implode("\n", $output);
            ZatcaPhase2Config::log("ECDSA private key generation failed: $error", 'ERROR');
            return ['success' => false, 'error' => $error];
        }

        // Set proper permissions
        if (file_exists($keyPath)) {
            chmod($keyPath, 0600);
            ZatcaPhase2Config::log("✅ ECDSA secp256k1 private key generated: $keyPath", 'INFO');
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Key file not created'];
    }

    /**
     * Generate CSR (Certificate Signing Request)
     * Using -subj parameter to include OID attributes (2.5.4.97, 2.5.4.15)
     *
     * @param string $keyPath Path to private key
     * @param string $csrPath Path to save CSR
     * @return array ['success' => bool, 'error' => string, 'csr' => string]
     */
    public function generateCSR($keyPath = null, $csrPath = null) {
        $keyPath = $keyPath ?? ZatcaPhase2Config::PRIVATE_KEY_FILE;
        $csrPath = $csrPath ?? ZatcaPhase2Config::CSR_FILE;

        // Always regenerate private key to ensure correct curve (prime256v1)
        // Old keys may have been generated with secp256k1 which simulation rejects
        $result = $this->generatePrivateKey($keyPath);
        if (!$result['success']) {
            return $result;
        }

        ZatcaPhase2Config::log('', 'INFO');
        ZatcaPhase2Config::log('=== CSR GENERATION WITH QUANTUM-BILLING APPROACH ===', 'INFO');
        ZatcaPhase2Config::log('Approach: Using subjectAltName dirName to include OID attributes', 'INFO');
        ZatcaPhase2Config::log('Why: OpenSSL req command properly handles OID attributes via dirName extension, not config DN section', 'INFO');
        ZatcaPhase2Config::log('Config file: ' . $this->configFile, 'DEBUG');

        // Generate CSR using OpenSSL command with config file
        // OID attributes are included via [ dir_sect ] section in config (quantum-billing approach)
        // CN is set in config file, NOT via -subj parameter (which would drop OID attributes)
        $command = sprintf(
            '"%s" req -new -key "%s" -out "%s" -config "%s" 2>&1',
            $this->opensslPath,
            $keyPath,
            $csrPath,
            $this->configFile
        );

        ZatcaPhase2Config::log("Executing OpenSSL req command with config file...", 'INFO');
        ZatcaPhase2Config::log("Executing: $command", 'DEBUG');

        $output = [];
        $return = 0;
        exec($command, $output, $return);

        if ($return !== 0) {
            $error = implode("\n", $output);
            ZatcaPhase2Config::log("CSR generation failed: $error", 'ERROR');
            return ['success' => false, 'error' => $error];
        }

        if (file_exists($csrPath)) {
            $csrContent = file_get_contents($csrPath);
            // Normalize to LF line endings — CRLF (Windows) causes Invalid-CSR in ZATCA simulation
            $csrContent = str_replace("\r\n", "\n", $csrContent);
            $csrContent = str_replace("\r", "\n", $csrContent);
            file_put_contents($csrPath, $csrContent);
            ZatcaPhase2Config::log("✅ CSR generated successfully (LF line endings): $csrPath", 'INFO');
            return [
                'success' => true,
                'csr' => $csrContent,
                'csr_path' => $csrPath,
                'key_path' => $keyPath
            ];
        }

        return ['success' => false, 'error' => 'CSR file not created'];
    }

    /**
     * Get CSR in base64 format (without headers)
     *
     * @param string $csrPath Path to CSR file
     * @return string Base64 encoded CSR
     */
    public function getCSRBase64($csrPath = null) {
        $csrPath = $csrPath ?? ZatcaPhase2Config::CSR_FILE;

        if (!file_exists($csrPath)) {
            return null;
        }

        $csr = file_get_contents($csrPath);

        // Remove headers and newlines
        $csr = str_replace('-----BEGIN CERTIFICATE REQUEST-----', '', $csr);
        $csr = str_replace('-----END CERTIFICATE REQUEST-----', '', $csr);
        $csr = str_replace("\n", '', $csr);
        $csr = str_replace("\r", '', $csr);

        return trim($csr);
    }

    /**
     * Test if OpenSSL is working
     *
     * @return array ['success' => bool, 'version' => string, 'path' => string]
     */
    public function testOpenSSL() {
        $command = sprintf('"%s" version 2>&1', $this->opensslPath);

        $output = [];
        $return = 0;
        exec($command, $output, $return);

        if ($return === 0 && !empty($output)) {
            return [
                'success' => true,
                'version' => $output[0],
                'path' => $this->opensslPath
            ];
        }

        return [
            'success' => false,
            'error' => 'OpenSSL command failed'
        ];
    }

    /**
     * Get OpenSSL executable path
     *
     * @return string
     */
    public function getOpenSSLPath() {
        return $this->opensslPath;
    }
}

?>
