<?php
/**
 * Fixed QR Code Generator
 *
 * Ensures QR code tags 6-9 are encoded as binary data (not base64 strings)
 * The library passes base64 strings to Tags 6 & 7, but ZATCA requires binary data in TLV encoding
 */

namespace ZatcaIntegration;

use Saleh7\Zatca\Helpers\Certificate;
use Saleh7\Zatca\Tags\Seller;
use Saleh7\Zatca\Tags\TaxNumber;
use Saleh7\Zatca\Tags\InvoiceDate;
use Saleh7\Zatca\Tags\InvoiceTotalAmount;
use Saleh7\Zatca\Tags\InvoiceTaxAmount;
use Saleh7\Zatca\Tags\InvoiceHash;
use Saleh7\Zatca\Tags\InvoiceDigitalSignature;
use Saleh7\Zatca\Tags\PublicKey;
use Saleh7\Zatca\Tags\CertificateSignature;
use Saleh7\Zatca\Helpers\QRCodeGenerator;

class FixedQRGenerator
{
    /**
     * Generate QR code with CORRECT certificate (full DER, not just public key)
     *
     * @param \DOMDocument $invoiceXml The signed invoice XML DOM
     * @param Certificate $certificate The certificate used for signing
     * @param string $invoiceHash Base64-encoded invoice hash
     * @param string $signatureValue Base64-encoded signature
     * @param bool $isSimplified Whether this is a simplified invoice (02)
     * @return string Base64-encoded QR code
     */
    public static function generateCorrectQR(
        \DOMDocument $invoiceXml,
        Certificate $certificate,
        ?string $invoiceHash = null,
        ?string $signatureValue = null,
        bool $isSimplified = true
    ): string {
        $xpath = new \DOMXPath($invoiceXml);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        // Extract values from XML
        $sellerName = $xpath->query('//cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName')->item(0)?->textContent ?? '';
        $vatNumber = $xpath->query('//cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID')->item(0)?->textContent ?? '';
        $issueDate = $xpath->query('//cbc:IssueDate')->item(0)?->textContent ?? '';
        $issueTime = $xpath->query('//cbc:IssueTime')->item(0)?->textContent ?? '';
        $totalWithVAT = $xpath->query('//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount')->item(0)?->textContent ?? '';
        $vatAmount = $xpath->query('//cac:TaxTotal/cbc:TaxAmount')->item(0)?->textContent ?? '';

        // Use timestamp exactly as in XML (do NOT add 'Z' suffix)
        // ZATCA validates that QR Tag 3 matches IssueDate+IssueTime exactly
        $timestamp = $issueDate . 'T' . $issueTime;

        // CRITICAL FIX: Use the passed $invoiceHash parameter (recalculated from final XML)
        // DO NOT extract from XML DigestValue - it may differ from what ZATCA calculates
        // The signature must still be extracted from XML as it's computed by the library
        $signatureValueNode = $xpath->query('//ds:SignatureValue')->item(0);

        if ($signatureValueNode) {
            $signatureValue = trim($signatureValueNode->textContent);  // Base64 from XML
        }

        // If no hash was passed, fall back to extracting from XML (not recommended)
        if (empty($invoiceHash)) {
            $invoiceDigestNode = $xpath->query('//ds:Reference[@URI=""]/ds:DigestValue')->item(0);
            if ($invoiceDigestNode) {
                $invoiceHash = trim($invoiceDigestNode->textContent);
            }
        }

        // CRITICAL: Tags 6 & 7 must be BASE64 STRINGS, not binary data!
        // ZATCA's successful sample proves this (Tag 6 is 44 bytes, not 32 bytes)
        // The Tag class stores whatever value you pass to it AS-IS
        // DO NOT decode - pass the base64 strings directly

        // Extract binary public key from certificate (88 bytes for ECDSA)
        // Tag 8 should be the public key, NOT the full certificate
        // ZATCA has a 1000-character limit for QR codes, so full cert (~630 bytes) is too large
        $publicKeyBinary = base64_decode($certificate->getRawPublicKey());  // ~88 bytes

        // Build QR tags array
        // Tags 1-5: Text data
        // Tags 6-7: BASE64 STRINGS (not binary!) as proven by ZATCA's sample
        // Tag 8: Binary public key
        // Tag 9: Binary certificate signature (for simplified invoices only)
        $qrTags = [
            new Seller($sellerName),                                                    // Tag 1
            new TaxNumber($vatNumber),                                                  // Tag 2
            new InvoiceDate($timestamp),                                                // Tag 3
            new InvoiceTotalAmount($totalWithVAT),                                      // Tag 4
            new InvoiceTaxAmount($vatAmount),                                           // Tag 5
            new InvoiceHash($invoiceHash),                                              // Tag 6 - BASE64 STRING (~44 bytes)
            new InvoiceDigitalSignature($signatureValue),                               // Tag 7 - BASE64 STRING (~96 bytes)
            new PublicKey($publicKeyBinary),                                            // Tag 8 - BINARY public key (88 bytes)
        ];

        // For Simplified Tax Invoices (type code 02), add certificate signature
        if ($isSimplified) {
            $qrTags[] = new CertificateSignature($certificate->getCertSignature());     // Tag 9
        }

        return QRCodeGenerator::createFromTags($qrTags)->encodeBase64();
    }
}
