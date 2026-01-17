<?php
/**
 * ZATCA CSR Debugger - Analyzes CSR structure and content
 *
 * This script reads the generated CSR and provides detailed debugging information
 * to help identify structural issues that ZATCA API might be rejecting.
 *
 * @charset UTF-8
 * @version 1.0
 */

require_once __DIR__ . '/../../config/phase2_config.php';

class CSRDebugger {

    private $csrPath;
    private $opensslPath;

    public function __construct($csrPath = null, $opensslPath = null) {
        $this->csrPath = $csrPath ?? ZatcaPhase2Config::CSR_FILE;
        $this->opensslPath = $opensslPath ?? $this->findOpenSSL();

        if (!file_exists($this->csrPath)) {
            throw new Exception("CSR file not found: {$this->csrPath}");
        }

        if (!$this->opensslPath) {
            throw new Exception("OpenSSL executable not found");
        }
    }

    /**
     * Find OpenSSL executable
     */
    private function findOpenSSL() {
        $possiblePaths = [
            '../../../../aqary/OpenSSL-Win64/bin/openssl.exe',
            'C:/saudiakar-erp/hanet/aqary/OpenSSL-Win64/bin/openssl.exe',
            'C:/Program Files/Git/mingw64/bin/openssl.exe',
            'C:/OpenSSL-Win64/bin/openssl.exe',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try system PATH
        $output = [];
        exec('where openssl 2>nul', $output);
        return !empty($output[0]) ? trim($output[0]) : null;
    }

    /**
     * Get CSR details using OpenSSL
     */
    public function getCSRDetails() {
        $output = [];
        $return = 0;

        // Use OpenSSL to read CSR details
        $command = sprintf(
            '"%s" req -in "%s" -noout -text 2>&1',
            $this->opensslPath,
            $this->csrPath
        );

        exec($command, $output, $return);

        if ($return !== 0) {
            throw new Exception("OpenSSL failed to read CSR: " . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    /**
     * Get CSR in PEM format
     */
    public function getCSRPEM() {
        return file_get_contents($this->csrPath);
    }

    /**
     * Decode CSR structure
     */
    public function decodeCSRStructure() {
        $output = [];
        $return = 0;

        // Use OpenSSL to decode CSR in ASN1 format
        $command = sprintf(
            '"%s" asn1parse -in "%s" -i 2>&1',
            $this->opensslPath,
            $this->csrPath
        );

        exec($command, $output, $return);

        if ($return !== 0) {
            return "Could not decode ASN1 structure: " . implode("\n", $output);
        }

        return implode("\n", $output);
    }

    /**
     * Extract DN attributes from CSR
     */
    public function extractDNAttributes() {
        $details = $this->getCSRDetails();

        $attributes = [];

        // Parse the CSR details to extract DN components
        $lines = explode("\n", $details);
        $inDN = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Look for Subject line
            if (strpos($line, 'Subject:') !== false) {
                $inDN = true;
                // Extract DN from this line onwards
                $dnMatch = preg_match('/Subject:\s*(.+)/i', $line, $matches);
                if ($dnMatch) {
                    $dnString = $matches[1];
                    $attributes = $this->parseDNString($dnString);
                }
                break;
            }
        }

        return $attributes;
    }

    /**
     * Parse DN string into components
     */
    private function parseDNString($dnString) {
        $attributes = [];

        // Split by comma but be careful with escaped commas
        $parts = preg_split('/(?<![\\\\]),\s*/', $dnString);

        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                $attributes[trim($key)] = trim($value);
            }
        }

        return $attributes;
    }

    /**
     * Check for required ZATCA attributes
     */
    public function checkRequiredAttributes() {
        $attributes = $this->extractDNAttributes();

        $required = [
            'C' => 'Country',
            'L' => 'Locality',
            'O' => 'Organization',
            'OU' => 'Organizational Unit',
            'CN' => 'Common Name',
        ];

        $recommended = [
            'ST' => 'State/Province',
            'SN' => 'Serial Number',
        ];

        // Check for ZATCA-specific attributes (as OIDs)
        $zatcaSpecific = [
            '2.5.4.97' => 'Organization Identifier (2.5.4.97)',
            '2.5.4.15' => 'Business Category (2.5.4.15)',
        ];

        $results = [
            'required' => [],
            'recommended' => [],
            'zatca_specific' => [],
            'all_attributes' => $attributes,
            'missing_required' => [],
            'missing_zatca' => []
        ];

        // Check required
        foreach ($required as $code => $name) {
            if (isset($attributes[$code])) {
                $results['required'][$code] = [
                    'name' => $name,
                    'value' => $attributes[$code],
                    'present' => true
                ];
            } else {
                $results['required'][$code] = [
                    'name' => $name,
                    'value' => null,
                    'present' => false
                ];
                $results['missing_required'][] = "$code ($name)";
            }
        }

        // Check recommended
        foreach ($recommended as $code => $name) {
            if (isset($attributes[$code])) {
                $results['recommended'][$code] = [
                    'name' => $name,
                    'value' => $attributes[$code],
                    'present' => true
                ];
            } else {
                $results['recommended'][$code] = [
                    'name' => $name,
                    'value' => null,
                    'present' => false
                ];
            }
        }

        // Check ZATCA-specific attributes
        // OpenSSL can display OIDs as either:
        // 1. Numeric format: 2.5.4.97, 2.5.4.15
        // 2. Friendly names: organizationIdentifier, businessCategory
        $asn1Structure = $this->decodeCSRStructure();

        // Map OIDs to their friendly names
        $oidFriendlyNames = [
            '2.5.4.97' => 'organizationIdentifier',
            '2.5.4.15' => 'businessCategory'
        ];

        foreach ($zatcaSpecific as $oid => $name) {
            $friendlyName = $oidFriendlyNames[$oid] ?? null;

            // Check for either numeric OID format OR friendly name in ASN1 output
            $found = (strpos($asn1Structure, $oid) !== false) ||
                     ($friendlyName && strpos($asn1Structure, $friendlyName) !== false);

            if ($found) {
                $results['zatca_specific'][$oid] = [
                    'name' => $name,
                    'present' => true,
                    'status' => '✅ Found in ASN1 structure'
                ];
            } else {
                $results['zatca_specific'][$oid] = [
                    'name' => $name,
                    'present' => false,
                    'status' => '❌ NOT FOUND in ASN1 structure'
                ];
                $results['missing_zatca'][] = "$oid ($name)";
            }
        }

        return $results;
    }

    /**
     * Generate debugging report
     */
    public function generateReport() {
        $report = [];
        $report[] = "================================================================================";
        $report[] = "                         ZATCA CSR DEBUG REPORT";
        $report[] = "================================================================================";
        $report[] = "";
        $report[] = "CSR File: " . $this->csrPath;
        $report[] = "File Size: " . filesize($this->csrPath) . " bytes";
        $report[] = "Last Modified: " . date('Y-m-d H:i:s', filemtime($this->csrPath));
        $report[] = "OpenSSL Path: " . $this->opensslPath;
        $report[] = "";

        // Get attribute check results
        $results = $this->checkRequiredAttributes();

        $report[] = "================================================================================";
        $report[] = "                    REQUIRED ATTRIBUTES STATUS";
        $report[] = "================================================================================";
        $report[] = "";
        foreach ($results['required'] as $code => $attr) {
            $status = $attr['present'] ? '✅' : '❌';
            $value = $attr['value'] ?? '(missing)';
            $report[] = "$status $code ({$attr['name']}): $value";
        }
        $report[] = "";

        if (!empty($results['missing_required'])) {
            $report[] = "⚠️  MISSING REQUIRED ATTRIBUTES:";
            foreach ($results['missing_required'] as $missing) {
                $report[] = "   - $missing";
            }
            $report[] = "";
        }

        $report[] = "================================================================================";
        $report[] = "                    ZATCA-SPECIFIC ATTRIBUTES";
        $report[] = "================================================================================";
        $report[] = "";
        foreach ($results['zatca_specific'] as $oid => $attr) {
            $report[] = "{$attr['status']} $oid ({$attr['name']})";
        }
        $report[] = "";

        if (!empty($results['missing_zatca'])) {
            $report[] = "⚠️  CRITICAL - MISSING ZATCA-SPECIFIC ATTRIBUTES:";
            foreach ($results['missing_zatca'] as $missing) {
                $report[] = "   - $missing";
            }
            $report[] = "";
            $report[] = "These attributes are required by ZATCA API for CSR acceptance.";
            $report[] = "";
        }

        $report[] = "================================================================================";
        $report[] = "                    RECOMMENDED ATTRIBUTES";
        $report[] = "================================================================================";
        $report[] = "";
        foreach ($results['recommended'] as $code => $attr) {
            $status = $attr['present'] ? '✅' : '⚠️ ';
            $value = $attr['value'] ?? '(missing)';
            $report[] = "$status $code ({$attr['name']}): $value";
        }
        $report[] = "";

        $report[] = "================================================================================";
        $report[] = "                    FULL CSR DETAILS (OpenSSL req -text)";
        $report[] = "================================================================================";
        $report[] = "";
        $report[] = $this->getCSRDetails();
        $report[] = "";

        $report[] = "================================================================================";
        $report[] = "                    ASN.1 STRUCTURE (OpenSSL asn1parse)";
        $report[] = "================================================================================";
        $report[] = "";
        $report[] = $this->decodeCSRStructure();
        $report[] = "";

        return implode("\n", $report);
    }

    /**
     * Write report to log file
     */
    public function writeReportToLog($logFile = null) {
        if (!$logFile) {
            $logDir = ZatcaPhase2Config::CERT_DIR . '/debug';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0700, true);
            }
            $logFile = $logDir . '/csr_debug_' . date('Y-m-d_H-i-s') . '.txt';
        }

        $report = $this->generateReport();
        file_put_contents($logFile, $report);

        ZatcaPhase2Config::log("CSR debug report written to: $logFile", 'INFO');

        return $logFile;
    }
}

?>
