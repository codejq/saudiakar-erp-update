<?php

namespace Saleh7\Zatca\Helpers;

use DOMException;

/**
 * Class InvoiceSignatureBuilder
 *
 * Builds the UBL signature XML for invoices.
 */
class InvoiceSignatureBuilder
{
    public const SAC = 'urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2';

    public const SBC = 'urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2';

    public const SIG = 'urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2';

    protected Certificate $cert;

    protected string $invoiceDigest;

    protected string $signatureValue;

    /**
     * Builds and returns the UBL signature XML as a formatted string.
     *
     * CRITICAL: The SignedProperties hash must be calculated from the exact historical
     * template string used by the working ZATCA integration path. The hash template and
     * the final inserted XML intentionally differ in namespace declarations.
     *
     * @return string The formatted UBL signature XML.
     *
     * @throws DOMException
     */
    public function buildSignatureXml(): string
    {
        // Get current date and time in ISO8601 format.
        $signingTime = date('Y-m-d').'T'.date('H:i:s');

        // 1. Hash template: WITH xmlns declarations (for calculating the hash)
        // 2. XML template: WITHOUT xmlns declarations (for inserting into XML - they inherit from ancestors)
        $signedPropertiesForHash = $this->createSignedPropertiesXml($signingTime, true);  // WITH xmlns
        $signedPropertiesForXml = $this->createSignedPropertiesXml($signingTime, false);  // WITHOUT xmlns

        // Historical ZATCA-compatible behavior: hash the exact template string.
        // This preserves the whitespace and namespace layout used in the previously
        // working implementation.
        $signedPropsHash = base64_encode(hash('sha256', $signedPropertiesForHash));

        // Create the UBLExtension element.
        $extensionXml = InvoiceExtension::newInstance('ext:UBLExtension');
        $extensionXml->addChild('ext:ExtensionURI', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades');
        $extensionContent = $extensionXml->addChild('ext:ExtensionContent');
        $signatureDetails = $extensionContent->addChild('sig:UBLDocumentSignatures', null, [
            'xmlns:sig' => self::SIG,
            'xmlns:sac' => self::SAC,
            'xmlns:sbc' => self::SBC,
        ]);

        // Build the signature information.
        $signatureContent = $signatureDetails->addChild('sac:SignatureInformation');
        $signatureContent->addChild('cbc:ID', 'urn:oasis:names:specification:ubl:signature:1');
        $signatureContent->addChild('sbc:ReferencedSignatureID', 'urn:oasis:names:specification:ubl:signature:Invoice');

        // Build the ds:Signature element.
        $dsSignature = $signatureContent->addChild('ds:Signature', null, [
            'xmlns:ds' => 'http://www.w3.org/2000/09/xmldsig#',
            'Id' => 'signature',
        ]);

        $signedInfo = $dsSignature->addChild('ds:SignedInfo');
        $signedInfo->addChild('ds:CanonicalizationMethod', null, [
            'Algorithm' => 'http://www.w3.org/2006/12/xml-c14n11',
        ]);
        $signedInfo->addChild('ds:SignatureMethod', null, [
            'Algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256',
        ]);

        // Build reference element for invoice signed data.
        $reference = $signedInfo->addChild('ds:Reference', null, [
            'Id' => 'invoiceSignedData',
            'URI' => '',
        ]);

        $transforms = $reference->addChild('ds:Transforms');
        // Exclude UBLExtensions.
        $xpath = $transforms->addChild('ds:Transform', null, [
            'Algorithm' => 'http://www.w3.org/TR/1999/REC-xpath-19991116',
        ]);
        $xpath->addChild('ds:XPath', 'not(//ancestor-or-self::ext:UBLExtensions)');
        // Exclude cac:Signature.
        $xpath = $transforms->addChild('ds:Transform', null, [
            'Algorithm' => 'http://www.w3.org/TR/1999/REC-xpath-19991116',
        ]);
        $xpath->addChild('ds:XPath', 'not(//ancestor-or-self::cac:Signature)');
        // Exclude AdditionalDocumentReference with ID "QR".
        $xpath = $transforms->addChild('ds:Transform', null, [
            'Algorithm' => 'http://www.w3.org/TR/1999/REC-xpath-19991116',
        ]);
        $xpath->addChild('ds:XPath', "not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID='QR'])");
        // Canonicalization transform.
        $transforms->addChild('ds:Transform', null, [
            'Algorithm' => 'http://www.w3.org/2006/12/xml-c14n11',
        ]);

        // Digest method and value for invoice data.
        $reference->addChild('ds:DigestMethod', null, [
            'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
        ]);
        $reference->addChild('ds:DigestValue', $this->invoiceDigest);

        // Add a second reference for the signed properties with the calculated hash
        $propsReference = $signedInfo->addChild('ds:Reference', null, [
            'Type' => 'http://uri.etsi.org/01903#SignedProperties',
            'URI' => '#xadesSignedProperties',
        ]);
        $propsReference->addChild('ds:DigestMethod', null, [
            'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
        ]);
        $propsReference->addChild('ds:DigestValue', $signedPropsHash);

        // Add the signature value.
        $dsSignature->addChild('ds:SignatureValue', $this->signatureValue);

        // Add key info with the certificate.
        $keyInfo = $dsSignature->addChild('ds:KeyInfo');
        $x509Data = $keyInfo->addChild('ds:X509Data');
        $x509Data->addChild('ds:X509Certificate', $this->cert->getCleanBase64Content());

        // Build the ds:Object with a placeholder for SignedProperties
        $dsObject = $dsSignature->addChild('ds:Object');
        $qualProps = $dsObject->addChild('xades:QualifyingProperties', null, [
            'xmlns:xades' => 'http://uri.etsi.org/01903/v1.3.2#',
            'Target' => 'signature',
        ]);
        // Add placeholder that will be replaced with exact template
        $qualProps->addChild('SIGNED_PROPERTIES_PLACEHOLDER');

        // Get the XML and format it
        $formattedXml = preg_replace('!^[^>]+>(\r\n|\n)!', '', $extensionXml->toXml());

        // Remove extra attributes added during UBLExtension creation.
        $formattedXml = str_replace(
            [
                ' xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"',
                '<ext:UBLExtension xmlns:sig="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#">',
            ],
            [
                '',
                '<ext:UBLExtension>',
            ],
            $formattedXml
        );

        // Apply indentation doubling BEFORE replacing the placeholder
        $formattedXml = preg_replace('/^[ ]+(?=<)/m', '$0$0', $formattedXml);

        // CRITICAL: Replace the placeholder with SignedProperties WITHOUT xmlns declarations
        // The xmlns declarations inherit from ancestors in the actual XML
        // But the hash was calculated from a template WITH xmlns declarations
        $formattedXml = preg_replace(
            '/<SIGNED_PROPERTIES_PLACEHOLDER\s*\/>/',
            $signedPropertiesForXml,
            $formattedXml
        );

        // Normalize line endings to LF
        $formattedXml = str_replace("\r\n", "\n", $formattedXml);

        return $formattedXml;
    }

    /**
     * Creates the signed properties XML string.
     *
     * CRITICAL: Do not alter the spacing/indentation as it will cause digest mismatches.
     * The exact whitespace must match what ZATCA expects.
     *
     * @param  string  $signingTime  The signing time.
     * @param  bool    $includeXmlns  Whether to include xmlns declarations (true for hash, false for XML)
     * @return string The signed properties XML.
     */
    private function createSignedPropertiesXml(string $signingTime, bool $includeXmlns = true): string
    {
        if ($includeXmlns) {
            // Historical hash template used by the previously working implementation.
            // xmlns:xades is on the root and xmlns:ds is repeated on each ds:* element.
            $template = '<xades:SignedProperties' .
                ' xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"' .
                ' Id="xadesSignedProperties">'."\n".
                '                                    <xades:SignedSignatureProperties>'."\n".
                '                                        <xades:SigningTime>SIGNING_TIME_PLACEHOLDER</xades:SigningTime>'."\n".
                '                                        <xades:SigningCertificate>'."\n".
                '                                            <xades:Cert>'."\n".
                '                                                <xades:CertDigest>'."\n".
                '                                                    <ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'."\n".
                '                                                    <ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">DIGEST_PLACEHOLDER</ds:DigestValue>'."\n".
                '                                                </xades:CertDigest>'."\n".
                '                                                <xades:IssuerSerial>'."\n".
                '                                                    <ds:X509IssuerName xmlns:ds="http://www.w3.org/2000/09/xmldsig#">ISSUER_PLACEHOLDER</ds:X509IssuerName>'."\n".
                '                                                    <ds:X509SerialNumber xmlns:ds="http://www.w3.org/2000/09/xmldsig#">SERIAL_PLACEHOLDER</ds:X509SerialNumber>'."\n".
                '                                                </xades:IssuerSerial>'."\n".
                '                                            </xades:Cert>'."\n".
                '                                        </xades:SigningCertificate>'."\n".
                '                                    </xades:SignedSignatureProperties>'."\n".
                '                                </xades:SignedProperties>';
        } else {
            // Template WITHOUT xmlns declarations - used for XML insertion (inherits from ancestors)
            $template = '<xades:SignedProperties Id="xadesSignedProperties">'."\n".
                '                                    <xades:SignedSignatureProperties>'."\n".
                '                                        <xades:SigningTime>SIGNING_TIME_PLACEHOLDER</xades:SigningTime>'."\n".
                '                                        <xades:SigningCertificate>'."\n".
                '                                            <xades:Cert>'."\n".
                '                                                <xades:CertDigest>'."\n".
                '                                                    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'."\n".
                '                                                    <ds:DigestValue>DIGEST_PLACEHOLDER</ds:DigestValue>'."\n".
                '                                                </xades:CertDigest>'."\n".
                '                                                <xades:IssuerSerial>'."\n".
                '                                                    <ds:X509IssuerName>ISSUER_PLACEHOLDER</ds:X509IssuerName>'."\n".
                '                                                    <ds:X509SerialNumber>SERIAL_PLACEHOLDER</ds:X509SerialNumber>'."\n".
                '                                                </xades:IssuerSerial>'."\n".
                '                                            </xades:Cert>'."\n".
                '                                        </xades:SigningCertificate>'."\n".
                '                                    </xades:SignedSignatureProperties>'."\n".
                '                                </xades:SignedProperties>';
        }

        $signedPropertiesXml = str_replace(
            [
                'SIGNING_TIME_PLACEHOLDER',
                'DIGEST_PLACEHOLDER',
                'ISSUER_PLACEHOLDER',
                'SERIAL_PLACEHOLDER',
            ],
            [
                $signingTime,
                $this->cert->getCertHash(),
                $this->cert->getFormattedIssuer(),
                $this->cert->getCurrentCert()['tbsCertificate']['serialNumber']->toString(),
            ],
            $template
        );

        // Normalize to LF (\n) for consistent XML digest hashing across platforms
        return str_replace("\r\n", "\n", $signedPropertiesXml);
    }

    /**
     * Sets the signature value.
     */
    public function setSignatureValue(string $signatureValue): self
    {
        $this->signatureValue = $signatureValue;

        return $this;
    }

    /**
     * Sets the invoice digest.
     */
    public function setInvoiceDigest(string $invoiceDigest): self
    {
        $this->invoiceDigest = $invoiceDigest;

        return $this;
    }

    /**
     * Sets the certificate.
     */
    public function setCertificate(Certificate $certificate): self
    {
        $this->cert = $certificate;

        return $this;
    }
}
