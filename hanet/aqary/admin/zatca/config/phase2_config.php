<?php
/**
 * ZATCA Phase 2 Configuration
 * XML Generation, Digital Signatures, API Integration
 *
 * @charset UTF-8
 * @version 2.0
 * @date 2025-11-01
 */

require_once __DIR__ . '/zatca_config.php';

// Load company data from data.hnt (if not already loaded)
$dataFile = __DIR__ . '/../../../data.hnt';
if (file_exists($dataFile)) {
    require_once $dataFile;
}

class ZatcaPhase2Config extends ZatcaConfig {

    // Certificate Configuration (loaded from data.hnt with fallback defaults)
    public static $CERT_ORGANIZATION_IDENTIFIER;
    public static $CERT_SERIAL_NUMBER;
    public static $CERT_COMMON_NAME;
    public static $CERT_COUNTRY;
    public static $CERT_ORGANIZATION_NAME;
    public static $CERT_ORGANIZATIONAL_UNIT;
    public static $CERT_INVOICE_TYPE;
    public static $CERT_LOCATION;
    public static $CERT_INDUSTRY;

    // Environment Configuration (loaded from data.hnt)
    public static $ENVIRONMENT;

    // Phase 2 Feature Flags
    const PHASE_2_ENABLED = true;
    const XML_GENERATION_ENABLED = true;
    const DIGITAL_SIGNATURE_ENABLED = true;
    const API_INTEGRATION_ENABLED = true; // Enable after testing

    // API Endpoints
    const API_SANDBOX = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal';
    const API_SIMULATION = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation';
    const API_PRODUCTION = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core';

    // API Paths
    const API_PATH_COMPLIANCE_CSID = '/compliance';
    const API_PATH_COMPLIANCE_CHECK = '/compliance/invoices';
    const API_PATH_PRODUCTION_CSID = '/production/csids';
    const API_PATH_CLEARANCE = '/invoices/clearance/single';
    const API_PATH_REPORTING = '/invoices/reporting/single';

    // Certificate Paths
    const CERT_DIR = __DIR__ . '/../certificates';
    const PRIVATE_KEY_FILE = self::CERT_DIR . '/private.key';
    const CERTIFICATE_FILE = self::CERT_DIR . '/certificate.pem';
    const CSR_FILE = self::CERT_DIR . '/certificate.csr';

    // Invoice Type Codes
    const INVOICE_TYPE_CODE_STANDARD = '388';
    const INVOICE_TYPE_CODE_SIMPLIFIED = '388';
    const INVOICE_TYPE_CODE_DEBIT = '383';
    const INVOICE_TYPE_CODE_CREDIT = '381';

    // Invoice Type Names (for PIH tracking)
    const INVOICE_TYPE_STANDARD_B2B = '0200000'; // Standard B2B
    const INVOICE_TYPE_SIMPLIFIED_B2C = '0200000'; // Simplified B2C

    // UBL Namespaces
    const UBL_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    const UBL_CAC_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    const UBL_CBC_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    const UBL_EXT_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    const DS_NAMESPACE = 'http://www.w3.org/2000/09/xmldsig#';
    const XADES_NAMESPACE = 'http://uri.etsi.org/01903/v1.3.2#';

    // Signature Algorithm
    // ZATCA uses ECDSA-SHA256 for compliance certificates
    const SIGNATURE_ALGORITHM = 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256';
    const DIGEST_ALGORITHM = 'http://www.w3.org/2001/04/xmlenc#sha256';
    // IMPORTANT: Use C14N 1.1 (NOT Exclusive C14N) - this matches ZATCA demo
    const CANONICALIZATION_ALGORITHM = 'http://www.w3.org/2006/12/xml-c14n11';

    // API Configuration
    const API_TIMEOUT = 30; // seconds
    const API_RETRY_COUNT = 3;
    const API_RETRY_DELAY = 2; // seconds

    // SSL Configuration
    // WARNING: Only disable SSL verification for testing! Re-enable for production!
    const SKIP_SSL_VERIFICATION = true; // Set to false once CA bundle is configured

    // Invoice Counter Configuration
    const COUNTER_START = 1;
    const COUNTER_INCREMENT = 1;

    // Hash Configuration
    const HASH_ALGORITHM = 'sha256';
    // FIRST_INVOICE_PIH: This is the SHA-256 hash of "0" (zero) encoded as Base64
    // Raw bytes of SHA-256("0") = 5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9 (hex)
    // Correct Base64 encoding of those raw bytes:
    const FIRST_INVOICE_PIH = 'X+zrZv/IbzjZUnhsbWlsecLbwjndTpG0ZynXOif7V+k=';

    // XML Validation
    const VALIDATE_XML_SCHEMA = true;
    const XML_SCHEMA_PATH = __DIR__ . '/../schemas/UBL-Invoice-2.1.xsd';

    // Logging
    const LOG_XML_INVOICES = true;
    const LOG_API_REQUESTS = true;
    const LOG_SIGNATURES = true;

    /**
     * Initialize Phase 2 certificate configuration from data.hnt
     */
    public static function initPhase2() {
        global $zatca_cert_organization_identifier, $zatca_cert_serial_number;
        global $zatca_cert_common_name, $zatca_cert_country, $zatca_cert_organization_name;
        global $zatca_cert_organizational_unit, $zatca_cert_invoice_type;
        global $zatca_cert_location, $zatca_cert_industry, $zatca_environment;

        // Load environment from data.hnt with fallback to sandbox
        self::$ENVIRONMENT = $zatca_environment ?? 'sandbox';

        // Load certificate values from data.hnt with fallback to parent class values
        self::$CERT_ORGANIZATION_IDENTIFIER = $zatca_cert_organization_identifier ?? parent::$VAT_REGISTRATION_NUMBER;
        self::$CERT_SERIAL_NUMBER = $zatca_cert_serial_number ?? '1-TST|2-TST|3-ed22f1d8-e6a2-1118-9b58-d9a8f11e445f';
        self::$CERT_COMMON_NAME = $zatca_cert_common_name ?? parent::$COMPANY_NAME_AR;
        self::$CERT_COUNTRY = $zatca_cert_country ?? parent::$COMPANY_COUNTRY_CODE;
        self::$CERT_ORGANIZATION_NAME = $zatca_cert_organization_name ?? parent::$COMPANY_NAME_AR;
        self::$CERT_ORGANIZATIONAL_UNIT = $zatca_cert_organizational_unit ?? 'IT Department';
        self::$CERT_INVOICE_TYPE = $zatca_cert_invoice_type ?? 1100; // 1000=Standard, 0100=Simplified, 1100=Both
        self::$CERT_LOCATION = $zatca_cert_location ?? parent::$COMPANY_CITY;
        self::$CERT_INDUSTRY = $zatca_cert_industry ?? 'Real Estate';
    }

    /**
     * Get certificate configuration
     */
    public static function getCertConfig() {
        return [
            'organization_identifier' => self::$CERT_ORGANIZATION_IDENTIFIER,
            'serial_number' => self::$CERT_SERIAL_NUMBER,
            'common_name' => self::$CERT_COMMON_NAME,
            'country' => self::$CERT_COUNTRY,
            'organization_name' => self::$CERT_ORGANIZATION_NAME,
            'organizational_unit' => self::$CERT_ORGANIZATIONAL_UNIT,
            'invoice_type' => self::$CERT_INVOICE_TYPE,
            'location' => self::$CERT_LOCATION,
            'industry' => self::$CERT_INDUSTRY
        ];
    }

    /**
     * Get API base URL based on environment
     */
    public static function getAPIBaseURL() {
        switch (self::$ENVIRONMENT) {
            case 'production':
                return self::API_PRODUCTION;
            case 'simulation':
                return self::API_SIMULATION;
            case 'sandbox':
            default:
                return self::API_SANDBOX;
        }
    }

    /**
     * Get full API endpoint URL
     */
    public static function getAPIEndpoint($path) {
        return self::getAPIBaseURL() . $path;
    }

    /**
     * Check if certificates exist
     * Checks for either production certificate or environment-specific certificate
     */
    public static function certificatesExist() {
        // Check for private key first
        if (!file_exists(self::PRIVATE_KEY_FILE)) {
            return false;
        }

        // Check for production certificate
        if (file_exists(self::CERTIFICATE_FILE)) {
            return true;
        }

        // Check for environment-specific certificate (e.g., sandbox_certificate.pem)
        $envCertFile = self::CERT_DIR . '/' . self::$ENVIRONMENT . '_certificate.pem';
        if (file_exists($envCertFile)) {
            return true;
        }

        return false;
    }

    /**
     * Get private key
     */
    public static function getPrivateKey() {
        if (!file_exists(self::PRIVATE_KEY_FILE)) {
            throw new Exception('Private key file not found');
        }
        return file_get_contents(self::PRIVATE_KEY_FILE);
    }

    /**
     * Get certificate
     */
    public static function getCertificate() {
        if (!file_exists(self::CERTIFICATE_FILE)) {
            throw new Exception('Certificate file not found');
        }
        return file_get_contents(self::CERTIFICATE_FILE);
    }

    /**
     * Save private key
     */
    public static function savePrivateKey($privateKey) {
        if (!is_dir(self::CERT_DIR)) {
            mkdir(self::CERT_DIR, 0700, true);
        }
        file_put_contents(self::PRIVATE_KEY_FILE, $privateKey);
        chmod(self::PRIVATE_KEY_FILE, 0600);
        self::log('Private key saved', 'INFO');
    }

    /**
     * Save certificate
     */
    public static function saveCertificate($certificate) {
        if (!is_dir(self::CERT_DIR)) {
            mkdir(self::CERT_DIR, 0700, true);
        }
        file_put_contents(self::CERTIFICATE_FILE, $certificate);
        chmod(self::CERTIFICATE_FILE, 0600);
        self::log('Certificate saved', 'INFO');
    }

    /**
     * Save CSR
     */
    public static function saveCSR($csr) {
        if (!is_dir(self::CERT_DIR)) {
            mkdir(self::CERT_DIR, 0700, true);
        }
        file_put_contents(self::CSR_FILE, $csr);
        self::log('CSR saved', 'INFO');
    }

    /**
     * Get certificate info
     */
    public static function getCertificateInfo() {
        if (!self::certificatesExist()) {
            return null;
        }

        try {
            $certContent = self::getCertificate();

            // Check if certificate file is not empty
            if (empty(trim($certContent))) {
                return null;
            }

            // Suppress warning for invalid certificates
            $cert = @openssl_x509_read($certContent);
            if (!$cert) {
                return null;
            }

            $info = openssl_x509_parse($cert);
            if (!$info) {
                return null;
            }

            return [
                'subject' => $info['subject'] ?? [],
                'issuer' => $info['issuer'] ?? [],
                'valid_from' => date('Y-m-d H:i:s', $info['validFrom_time_t'] ?? 0),
                'valid_to' => date('Y-m-d H:i:s', $info['validTo_time_t'] ?? 0),
                'serial_number' => $info['serialNumber'] ?? '',
                'is_valid' => ($info['validTo_time_t'] ?? 0) > time()
            ];
        } catch (Exception $e) {
            self::log('Failed to read certificate info: ' . $e->getMessage(), 'WARNING');
            return null;
        }
    }

    /**
     * Validate Phase 2 configuration
     */
    public static function validatePhase2Config() {
        $errors = [];

        // Check basic VAT number
        if (!self::isValidVatNumber(parent::$VAT_REGISTRATION_NUMBER)) {
            $errors[] = 'Invalid VAT registration number in configuration';
        }

        // Check certificate directory
        if (!is_dir(self::CERT_DIR)) {
            $errors[] = 'Certificate directory does not exist: ' . self::CERT_DIR;
        } elseif (!is_writable(self::CERT_DIR)) {
            $errors[] = 'Certificate directory is not writable';
        }

        // Check OpenSSL extension
        if (!extension_loaded('openssl')) {
            $errors[] = 'OpenSSL extension is not loaded';
        }

        // Check cURL extension
        if (!extension_loaded('curl')) {
            $errors[] = 'cURL extension is not loaded';
        }

        // Check DOM extension
        if (!extension_loaded('dom')) {
            $errors[] = 'DOM extension is not loaded';
        }

        // Check certificate organization identifier
        if (!self::isValidVatNumber(self::$CERT_ORGANIZATION_IDENTIFIER)) {
            $errors[] = 'Invalid certificate organization identifier (VAT number)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get invoice type code name
     */
    public static function getInvoiceTypeCodeName($isStandard, $isCredit = false, $isDebit = false) {
        // 0100000 = Standard B2B (clearance:1.0)
        // 0200000 = Simplified B2C (reporting:1.0)
        // Must match ProfileID: clearance↔0100000, reporting↔0200000
        return $isStandard ? '0100000' : '0200000';
    }

    /**
     * Is production environment
     */
    public static function isProduction() {
        return self::$ENVIRONMENT === 'production';
    }

    /**
     * Get environment name
     */
    public static function getEnvironmentName() {
        return strtoupper(self::$ENVIRONMENT);
    }
}

// Initialize Phase 2 certificate configuration
ZatcaPhase2Config::initPhase2();

// Initialize certificate directory
$certDir = ZatcaPhase2Config::CERT_DIR;
if (!file_exists($certDir)) {
    mkdir($certDir, 0700, true);
}

// Create .gitignore in certificates directory
$gitignorePath = $certDir . '/.gitignore';
if (!file_exists($gitignorePath)) {
    file_put_contents($gitignorePath, "*.key\n*.pem\n*.csr\n*.p12\n");
}

ZatcaPhase2Config::log('Phase 2 configuration loaded from data.hnt - Environment: ' . ZatcaPhase2Config::$ENVIRONMENT, 'INFO');

?>
