<?php
// Compare SignedProperties from our generated invoice vs ZATCA sample

require_once __DIR__ . '/config/phase2_config.php';
require_once __DIR__ . '/lib/php-zatca-xml-main/vendor/autoload.php';

use Saleh7\Zatca\Helpers\Certificate;
use Saleh7\Zatca\Helpers\InvoiceSignatureBuilder;

// Load production certificate
$certFile = ZatcaPhase2Config::CERT_DIR . '/certificate.pem';
$secretFile = ZatcaPhase2Config::CERT_DIR . '/secret.txt';
$certContent = file_get_contents($certFile);

if (preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $certContent, $m)) {
    $layer1_decoded = base64_decode(str_replace(["\n", "\r", " "], '', $m[1]));
    if (substr($layer1_decoded, 0, 4) === 'MIIC' || substr($layer1_decoded, 0, 4) === 'MIID') {
        $cleanCert = $layer1_decoded;
    } else {
        $cleanCert = str_replace(["\n", "\r", " "], '', $m[1]);
    }
}

$privateKeyPem = ZatcaPhase2Config::getPrivateKey();
$cleanPrivateKey = trim(str_replace([
    "-----BEGIN PRIVATE KEY-----", "-----END PRIVATE KEY-----",
    "-----BEGIN EC PRIVATE KEY-----", "-----END EC PRIVATE KEY-----",
    "\r", "\n"
], '', $privateKeyPem));

$secret = trim(file_get_contents($secretFile));
$certificate = new Certificate($cleanCert, $cleanPrivateKey, $secret);

// Generate signature XML using the library
$builder = new InvoiceSignatureBuilder();
$builder->setCertificate($certificate)
    ->setInvoiceDigest('dummyhash')
    ->setSignatureValue('dummysig');

$signatureXml = $builder->buildSignatureXml();

// Extract SignedProperties from generated XML
if (preg_match('/<xades:SignedProperties[^>]*>.*?<\/xades:SignedProperties>/s', $signatureXml, $matches)) {
    $ourSignedProps = $matches[0];

    echo "=== OUR GENERATED SIGNED PROPERTIES ===\n";
    echo $ourSignedProps . "\n\n";

    echo "=== LENGTH: " . strlen($ourSignedProps) . " bytes ===\n\n";

    // Hash it
    $ourHash = hash('sha256', $ourSignedProps);
    echo "=== OUR HASH ===\n";
    echo "SHA256: $ourHash\n";
    echo "Base64: " . base64_encode($ourHash) . "\n\n";
}

// ZATCA sample SignedProperties (from zatca_production_sample.xml)
$zatcaSample = '<xades:SignedProperties Id="xadesSignedProperties">
                                    <xades:SignedSignatureProperties>
                                        <xades:SigningTime>2025-06-02T15:44:01</xades:SigningTime>
                                        <xades:SigningCertificate>
                                            <xades:Cert>
                                                <xades:CertDigest>
                                                    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                                    <ds:DigestValue>ZDMwMmI0MTE1NzVjOTU2NTk4YzVlODhhYmI0ODU2NDUyNTU2YTVhYjhhMDFmN2FjYjk1YTA2OWQ0NjY2MjQ4NQ==</ds:DigestValue>
                                                </xades:CertDigest>
                                                <xades:IssuerSerial>
                                                    <ds:X509IssuerName>CN=PRZEINVOICESCA4-CA, DC=extgazt, DC=gov, DC=local</ds:X509IssuerName>
                                                    <ds:X509SerialNumber>379112742831380471835263969587287663520528387</ds:X509SerialNumber>
                                                </xades:IssuerSerial>
                                            </xades:Cert>
                                        </xades:SigningCertificate>
                                    </xades:SignedSignatureProperties>
                                </xades:SignedProperties>';

echo "=== ZATCA SAMPLE SIGNED PROPERTIES ===\n";
echo $zatcaSample . "\n\n";
echo "=== ZATCA LENGTH: " . strlen($zatcaSample) . " bytes ===\n\n";

// Key differences to look for:
echo "=== KEY DIFFERENCES ===\n";
echo "1. Does our XML have xmlns:xades on SignedProperties? " . (strpos($ourSignedProps, 'xmlns:xades') !== false ? "YES" : "NO") . "\n";
echo "2. Does ZATCA have xmlns:xades on SignedProperties? " . (strpos($zatcaSample, 'xmlns:xades') !== false ? "YES" : "NO") . "\n";
echo "3. Does our XML have xmlns:ds on ds elements? " . (substr_count($ourSignedProps, 'xmlns:ds') > 0 ? "YES (" . substr_count($ourSignedProps, 'xmlns:ds') . " times)" : "NO") . "\n";
echo "4. Does ZATCA have xmlns:ds on ds elements? " . (substr_count($zatcaSample, 'xmlns:ds') > 0 ? "YES (" . substr_count($zatcaSample, 'xmlns:ds') . " times)" : "NO") . "\n";
/**
 * Compare our SignedProperties with ZATCA working sample
 */
require_once __DIR__ . '/config/phase2_config.php';
require_once __DIR__ . '/phase2/signature/InvoiceSigner.php';
require_once __DIR__ . '/phase2/integration/Phase2Manager.php';

echo "<pre>";
echo "=== ZATCA SignedProperties Comparison ===\n\n";

// Load ZATCA working sample
$zatcaSampleXml = file_get_contents(__DIR__ . '/zatca_working_sample.xml');
$zatcaDom = new DOMDocument();
@$zatcaDom->loadXML($zatcaSampleXml);

$zatcaXpath = new DOMXPath($zatcaDom);
$zatcaXpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');
$zatcaXpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

// Get ZATCA SignedProperties
$zatcaSignedProps = $zatcaXpath->query('//xades:SignedProperties')->item(0);
if ($zatcaSignedProps) {
    $zatcaCanonical = $zatcaSignedProps->C14N(false, false);
    $zatcaHash = base64_encode(hash('sha256', $zatcaCanonical, true));

    echo "=== ZATCA SAMPLE ===\n";
    echo "SignedProperties canonical length: " . strlen($zatcaCanonical) . " bytes\n";
    echo "SignedProperties hash: " . $zatcaHash . "\n\n";
    echo "Canonical form:\n";
    echo htmlspecialchars($zatcaCanonical) . "\n\n";

    // Get the DigestValue from ZATCA sample for SignedProperties reference
    $zatcaDigest = $zatcaXpath->query('//ds:Reference[@URI="#xadesSignedProperties"]/ds:DigestValue')->item(0);
    if ($zatcaDigest) {
        echo "ZATCA SignedProperties DigestValue in XML: " . $zatcaDigest->textContent . "\n";

        // Decode to see what format it is
        $decoded = base64_decode($zatcaDigest->textContent);
        echo "Decoded length: " . strlen($decoded) . " chars\n";
        echo "Decoded content: " . $decoded . "\n";

        // Check if it's hex
        if (ctype_xdigit($decoded) && strlen($decoded) == 64) {
            echo "Format: DOUBLE BASE64 (base64 of hex string)\n";
            $binaryHash = hex2bin($decoded);
            echo "Binary hash (base64): " . base64_encode($binaryHash) . "\n";
        } else {
            echo "Format: Standard base64 of binary\n";
        }
    }

    // Get CertDigest
    $zatcaCertDigest = $zatcaXpath->query('//xades:CertDigest/ds:DigestValue')->item(0);
    if ($zatcaCertDigest) {
        echo "\nZATCA CertDigest: " . $zatcaCertDigest->textContent . "\n";
        $decoded = base64_decode($zatcaCertDigest->textContent);
        echo "Decoded length: " . strlen($decoded) . " chars\n";
        echo "Decoded content: " . $decoded . "\n";

        if (ctype_xdigit($decoded) && strlen($decoded) == 64) {
            echo "Format: DOUBLE BASE64 (base64 of hex string)\n";
        } else {
            echo "Format: Standard base64 of binary\n";
        }
    }

    // Get X509IssuerName
    $zatcaIssuer = $zatcaXpath->query('//xades:IssuerSerial/ds:X509IssuerName')->item(0);
    if ($zatcaIssuer) {
        echo "\nZATCA X509IssuerName: " . $zatcaIssuer->textContent . "\n";
    }

    // Get SigningTime
    $zatcaSigningTime = $zatcaXpath->query('//xades:SigningTime')->item(0);
    if ($zatcaSigningTime) {
        echo "ZATCA SigningTime: " . $zatcaSigningTime->textContent . "\n";
    }
}

echo "\n</pre>";
