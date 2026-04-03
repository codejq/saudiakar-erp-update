<?php
/**
 * ZATCA Invoice Digital Signature
 *
 * Signs XML invoices with X.509 certificates
 *
 * @charset UTF-8
 * @version 2.0
 * @date 2025-11-01
 */

require_once __DIR__ . '/../../config/phase2_config.php';
require_once __DIR__ . '/HashGenerator.php';

class InvoiceSigner {

    private $privateKey;
    private $certificate;
    private $certificateString;
    private $signingTime; // Captured once to ensure XAdES hash consistency

    /**
     * Constructor
     *
     * @param string $privateKeyPath Path to private key file
     * @param string $certificatePath Path to certificate file
     */
    public function __construct($privateKeyPath = null, $certificatePath = null) {
        try {
            $privateKeyPath = $privateKeyPath ?? ZatcaPhase2Config::PRIVATE_KEY_FILE;
            $certificatePath = $certificatePath ?? ZatcaPhase2Config::CERTIFICATE_FILE;

            if (!file_exists($privateKeyPath)) {
                throw new Exception('Private key file not found: ' . $privateKeyPath);
            }

            if (!file_exists($certificatePath)) {
                throw new Exception('Certificate file not found: ' . $certificatePath);
            }

            // Load private key
            $privateKeyContent = file_get_contents($privateKeyPath);
            $this->privateKey = openssl_pkey_get_private($privateKeyContent);

            if (!$this->privateKey) {
                throw new Exception('Failed to load private key: ' . openssl_error_string());
            }

            // Load certificate
            $this->certificateString = file_get_contents($certificatePath);

            // The certificate file is TRIPLE-ENCODED for compliance:
            // Layer 1: PEM markers (-----BEGIN CERTIFICATE-----)
            // Layer 2: Base64 content (TUlJQy9EQ0NBC...) - when decoded gives...
            // Layer 3: More base64 (MIIC...) that OpenSSL can handle

            $certToRead = $this->certificateString;
            $this->certificate = @openssl_x509_read($certToRead);

            // If original fails, extract and unwrap the first layer
            if (!$this->certificate) {
                if (preg_match('/-----BEGIN CERTIFICATE-----\s*(.*?)\s*-----END CERTIFICATE-----/s', $this->certificateString, $matches)) {
                    $layer1_base64 = $matches[1];
                    $layer1_base64 = str_replace(["\n", "\r"], '', $layer1_base64);

                    // Decode Layer 1: TUlJQy9EQ0NBC... → MIIC...
                    $layer2_content = base64_decode($layer1_base64, true);

                    if ($layer2_content !== false) {
                        // Layer 2 content should be base64 text (MIIC...)
                        // Wrap it in PEM markers for OpenSSL to process
                        $certToRead = "-----BEGIN CERTIFICATE-----\n" .
                                     chunk_split($layer2_content, 64) .
                                     "-----END CERTIFICATE-----";

                        $this->certificate = @openssl_x509_read($certToRead);
                    }
                }
            }

            if (!$this->certificate) {
                throw new Exception('Failed to load certificate: ' . openssl_error_string());
            }

            ZatcaPhase2Config::log('Invoice signer initialized', 'INFO');

        } catch (Exception $e) {
            $error = 'Signer initialization error: ' . $e->getMessage();
            ZatcaPhase2Config::log($error, 'ERROR');
            throw new Exception($error);
        }
    }

    /**
     * Sign invoice XML
     *
     * @param string $xml Unsigned XML invoice
     * @return array ['signed_xml' => string, 'hash' => string, 'signature' => string]
     */
    public function signInvoice($xml) {
        try {
            // Remove elements that should be excluded from hash calculation
            // (cac:Signature must be excluded per ZATCA XPath transforms)
            $xmlForHashing = $this->removeExcludedElements($xml);

            // Canonicalize XML without excluded elements
            $canonicalXML = HashGenerator::canonicalizeXML($xmlForHashing);

            // Generate hash from XML without cac:Signature
            $hash = HashGenerator::generateInvoiceHash($canonicalXML, false);

            // Embed signature structure with placeholder SignatureValue and placeholder XAdES hash
            $signedXML = $this->embedSignature($xml, 'SIGNATURE_VALUE_PLACEHOLDER', $hash);

            // Load the XML to recalculate the XAdES SignedProperties hash from the ACTUAL embedded structure
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = true;
            $dom->formatOutput = false;
            if (!$dom->loadXML($signedXML)) {
                throw new Exception('Failed to load signed XML for SignedInfo signing');
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('ds', ZatcaPhase2Config::DS_NAMESPACE);
            $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

            // CRITICAL FIX: Recalculate SignedProperties hash from the ACTUAL embedded structure
            // ZATCA uses DOUBLE BASE64: base64(hex(sha256(canonical)))
            $signedPropsNode = $xpath->query('//xades:SignedProperties[@Id="xadesSignedProperties"]')->item(0);
            if ($signedPropsNode) {
                $actualCanonical = $signedPropsNode->C14N(false, false);
                $actualHashHex = hash('sha256', $actualCanonical, false); // Get hex string (64 chars)
                $actualHash = base64_encode($actualHashHex); // Base64 encode the hex string

                ZatcaPhase2Config::log('Actual SignedProperties canonical length: ' . strlen($actualCanonical) . ' bytes', 'INFO');
                ZatcaPhase2Config::log('Actual SignedProperties hash hex: ' . $actualHashHex, 'INFO');
                ZatcaPhase2Config::log('Actual SignedProperties hash: ' . $actualHash, 'INFO');

                // Log the embedded CertDigest to verify it matches
                $embeddedCertDigest = $xpath->query('//xades:SignedProperties//ds:DigestValue')->item(0);
                if ($embeddedCertDigest) {
                    ZatcaPhase2Config::log('Embedded CertDigest: ' . trim($embeddedCertDigest->textContent), 'INFO');
                }

                // Update the DigestValue for the xadesSignedProperties reference
                $xadesDigestNodes = $xpath->query('//ds:Reference[@URI="#xadesSignedProperties"]/ds:DigestValue');
                if ($xadesDigestNodes->length > 0) {
                    $xadesDigestNode = $xadesDigestNodes->item(0);
                    while ($xadesDigestNode->firstChild) {
                        $xadesDigestNode->removeChild($xadesDigestNode->firstChild);
                    }
                    $xadesDigestNode->appendChild($dom->createTextNode($actualHash));
                    ZatcaPhase2Config::log('Updated XAdES DigestValue with actual hash', 'INFO');
                }
            }

            $signedInfoNode = $xpath->query('//ds:Signature/ds:SignedInfo')->item(0);
            if (!$signedInfoNode) {
                throw new Exception('SignedInfo node not found for signing');
            }

            $signedInfoCanonical = $signedInfoNode->C14N(false, false);
            if ($signedInfoCanonical === false) {
                throw new Exception('Canonicalization of SignedInfo failed');
            }

            $signatureDer = '';
            $signResult = openssl_sign(
                $signedInfoCanonical,
                $signatureDer,
                $this->privateKey,
                OPENSSL_ALGO_SHA256
            );
            if (!$signResult) {
                throw new Exception('Signing SignedInfo failed: ' . openssl_error_string());
            }

            $signatureBase64 = base64_encode($signatureDer);

            $sigValueNode = $xpath->query('//ds:Signature/ds:SignatureValue')->item(0);
            if (!$sigValueNode) {
                throw new Exception('SignatureValue node not found for embedding');
            }
            while ($sigValueNode->firstChild) {
                $sigValueNode->removeChild($sigValueNode->firstChild);
            }
            $sigValueNode->appendChild($dom->createTextNode($signatureBase64));

            // Save XML without BOM (ZATCA requirement)
            $signedXML = ltrim($dom->saveXML(), "\xEF\xBB\xBF");

            ZatcaPhase2Config::log('Invoice signed successfully', 'INFO');

            return [
                'success' => true,
                'signed_xml' => $signedXML,
                'hash' => $hash,
                'signature' => $signatureBase64
            ];

        } catch (Exception $e) {
            $error = 'Signing error: ' . $e->getMessage();
            ZatcaPhase2Config::log($error, 'ERROR');

            return [
                'success' => false,
                'error' => $error
            ];
        }
    }

    /**
     * Remove elements that should be excluded from hash calculation
     * Per ZATCA XPath transforms: cac:Signature and QR AdditionalDocumentReference
     *
     * @param string $xml XML string
     * @return string XML without excluded elements
     */
    private function removeExcludedElements($xml) {
        try {
            $dom = new DOMDocument();
            // Match JS implementation: no whitespace preservation
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;

            if (!$dom->loadXML($xml)) {
                throw new Exception('Failed to load XML for element removal');
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
            $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
            $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

            // Remove elements in same order as JS implementation
            // 1. Remove ext:UBLExtensions
            $ublExtensions = $xpath->query('//ext:UBLExtensions');
            foreach ($ublExtensions as $ext) {
                $ext->parentNode->removeChild($ext);
            }

            // 2. Remove cac:Signature
            $signatures = $xpath->query('//cac:Signature');
            foreach ($signatures as $sig) {
                $sig->parentNode->removeChild($sig);
            }

            // 3. Remove QR AdditionalDocumentReference
            $qrRefs = $xpath->query("//cac:AdditionalDocumentReference[cbc:ID='QR']");
            foreach ($qrRefs as $qr) {
                $qr->parentNode->removeChild($qr);
            }

            // Serialize - JS uses XMLSerializer().serializeToString(dom)
            $result = $dom->saveXML($dom->documentElement);
            return $result;

        } catch (Exception $e) {
            ZatcaPhase2Config::log('Element removal error: ' . $e->getMessage(), 'ERROR');
            return $xml; // Return original on error
        }
    }

    /**
     * Embed signature in XML invoice
     *
     * @param string $xml Original XML
     * @param string $signature Base64 signature
     * @param string $hash Base64 hash
     * @return string Signed XML
     */
    private function embedSignature($xml, $signature, $hash) {
        try {
            $dom = new DOMDocument();
            // Preserve whitespace to maintain document integrity
            $dom->preserveWhiteSpace = true;
            $dom->formatOutput = false;

            if (!$dom->loadXML($xml)) {
                throw new Exception('Failed to load XML for signature embedding');
            }

            // Create UBL Extension
            $ublExtensions = $dom->createElementNS(
                ZatcaPhase2Config::UBL_EXT_NAMESPACE,
                'ext:UBLExtensions'
            );

            $ublExtension = $dom->createElementNS(
                ZatcaPhase2Config::UBL_EXT_NAMESPACE,
                'ext:UBLExtension'
            );

            // ExtensionURI - ZATCA uses 'urn:oasis:names:specification:ubl:dsig:enveloped:xades'
            $extensionURI = $dom->createElementNS(
                ZatcaPhase2Config::UBL_EXT_NAMESPACE,
                'ext:ExtensionURI',
                'urn:oasis:names:specification:ubl:dsig:enveloped:xades'
            );
            $ublExtension->appendChild($extensionURI);

            $extensionContent = $dom->createElementNS(
                ZatcaPhase2Config::UBL_EXT_NAMESPACE,
                'ext:ExtensionContent'
            );

            // Create UBLDocumentSignatures wrapper (required by ZATCA)
            $ublDocSigs = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2',
                'sig:UBLDocumentSignatures'
            );
            $ublDocSigs->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:sac',
                'urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2'
            );
            $ublDocSigs->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:sbc',
                'urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2'
            );

            // Create SignatureInformation (BR-KSA-28 requires this structure)
            $sigInfo = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2',
                'sac:SignatureInformation'
            );

            // BR-KSA-28: ID must be 'urn:oasis:names:specification:ubl:signature:1'
            $sigInfoId = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                'cbc:ID',
                'urn:oasis:names:specification:ubl:signature:1'
            );
            $sigInfo->appendChild($sigInfoId);

            // ReferencedSignatureID references the ExtensionURI
            $refSigId = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2',
                'sbc:ReferencedSignatureID',
                'urn:oasis:names:specification:ubl:signature:Invoice'
            );
            $sigInfo->appendChild($refSigId);

            // Create Signature element
            $signatureElement = $this->createSignatureElement($dom, $signature, $hash);
            $sigInfo->appendChild($signatureElement);

            $ublDocSigs->appendChild($sigInfo);
            $extensionContent->appendChild($ublDocSigs);

            $ublExtension->appendChild($extensionContent);
            $ublExtensions->appendChild($ublExtension);

            // Insert at beginning of Invoice
            $invoice = $dom->documentElement;
            $invoice->insertBefore($ublExtensions, $invoice->firstChild);

            // Return XML without BOM (ZATCA requirement)
            $xml = $dom->saveXML();
            return ltrim($xml, "\xEF\xBB\xBF");

        } catch (Exception $e) {
            $error = 'Signature embedding error: ' . $e->getMessage();
            ZatcaPhase2Config::log($error, 'ERROR');
            throw new Exception($error);
        }
    }

    /**
     * Create XML Signature element
     *
     * @param DOMDocument $dom DOM document
     * @param string $signature Base64 signature
     * @param string $hash Base64 hash
     * @return DOMElement Signature element
     */
    private function createSignatureElement($dom, $signature, $hash) {
        // CRITICAL: Capture signing time ONCE to ensure consistency between hash calculation and XAdES embedding
        // If this changes between hash calc and embedding, ZATCA validation will fail!
        $this->signingTime = gmdate('Y-m-d\TH:i:s\Z');

        // Create ds:Signature
        $sig = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Signature');
        $sig->setAttribute('Id', 'signature');

        // SignedInfo
        $signedInfo = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:SignedInfo');

        // CanonicalizationMethod
        $canonMethod = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', ZatcaPhase2Config::CANONICALIZATION_ALGORITHM);
        $signedInfo->appendChild($canonMethod);

        // SignatureMethod
        $sigMethod = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', ZatcaPhase2Config::SIGNATURE_ALGORITHM);
        $signedInfo->appendChild($sigMethod);

        // Reference
        $reference = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Reference');
        $reference->setAttribute('Id', 'invoiceSignedData');
        $reference->setAttribute('URI', '');

        // Transforms - using namespace prefixes like ZATCA demo
        $transforms = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Transforms');

        // Transform 1: Exclude UBLExtensions (signature container)
        $transform1 = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Transform');
        $transform1->setAttribute('Algorithm', 'http://www.w3.org/TR/1999/REC-xpath-19991116');
        $xpath = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:XPath', 'not(//ancestor-or-self::ext:UBLExtensions)');
        $transform1->appendChild($xpath);
        $transforms->appendChild($transform1);

        // Transform 2: Exclude cac:Signature reference
        $transform2 = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Transform');
        $transform2->setAttribute('Algorithm', 'http://www.w3.org/TR/1999/REC-xpath-19991116');
        $xpath2 = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:XPath', 'not(//ancestor-or-self::cac:Signature)');
        $transform2->appendChild($xpath2);
        $transforms->appendChild($transform2);

        // Transform 3: Exclude QR Code (added after signing)
        $transform3 = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Transform');
        $transform3->setAttribute('Algorithm', 'http://www.w3.org/TR/1999/REC-xpath-19991116');
        $xpath3 = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:XPath', 'not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID=\'QR\'])');
        $transform3->appendChild($xpath3);
        $transforms->appendChild($transform3);

        // Transform 4: Canonicalization (C14N 1.1)
        $transform4 = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Transform');
        $transform4->setAttribute('Algorithm', ZatcaPhase2Config::CANONICALIZATION_ALGORITHM);
        $transforms->appendChild($transform4);

        $reference->appendChild($transforms);

        // DigestMethod
        $digestMethod = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', ZatcaPhase2Config::DIGEST_ALGORITHM);
        $reference->appendChild($digestMethod);

        // DigestValue
        $digestValue = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:DigestValue', $hash);
        $reference->appendChild($digestValue);

        $signedInfo->appendChild($reference);

        // ZATCA Phase 2 requires XAdES-EPES signature with second reference to SignatureProperties
        // Calculate hash of XAdES SignedProperties for the second reference
        $xadesPropsHash = $this->generateXAdESPropertiesHash($dom);

        // Second Reference: XAdES SignatureProperties
        $xadesReference = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Reference');
        $xadesReference->setAttribute('Type', 'http://www.w3.org/2000/09/xmldsig#SignatureProperties');
        $xadesReference->setAttribute('URI', '#xadesSignedProperties');

        // NOTE: ZATCA sample does NOT have Transforms element for XAdES Reference

        $xadesDigestMethod = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:DigestMethod');
        $xadesDigestMethod->setAttribute('Algorithm', ZatcaPhase2Config::DIGEST_ALGORITHM);
        $xadesReference->appendChild($xadesDigestMethod);

        $xadesDigestValue = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:DigestValue', $xadesPropsHash);
        $xadesReference->appendChild($xadesDigestValue);

        $signedInfo->appendChild($xadesReference);
        $sig->appendChild($signedInfo);

        // SignatureValue
        $sigValue = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:SignatureValue', $signature);
        $sig->appendChild($sigValue);

        // KeyInfo
        $keyInfo = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:KeyInfo');
        $x509Data = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:X509Data');

        // Get certificate without headers
        $certContent = $this->getCertificateContent();

        // CRITICAL: Log the certificate content for debugging
        ZatcaPhase2Config::log('X509Certificate content length: ' . strlen($certContent) . ' chars', 'INFO');
        ZatcaPhase2Config::log('X509Certificate first 50 chars: ' . substr($certContent, 0, 50), 'DEBUG');

        // Verify hash matches what we calculated in SignedProperties
        $verifyDER = base64_decode($certContent);
        $verifyHash = base64_encode(hash('sha256', $verifyDER, true));
        ZatcaPhase2Config::log('X509Certificate DER length: ' . strlen($verifyDER) . ' bytes', 'INFO');
        ZatcaPhase2Config::log('X509Certificate hash (verify): ' . $verifyHash, 'INFO');

        $x509Cert = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:X509Certificate', $certContent);
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);
        $sig->appendChild($keyInfo);

        // XAdES Object with QualifyingProperties (required by ZATCA Phase 2)
        $xadesObject = $this->createXAdESObject($dom);
        $sig->appendChild($xadesObject);

        return $sig;
    }

    /**
     * Generate hash of XAdES SignedProperties
     *
     * @param DOMDocument $dom DOM document
     * @return string Base64 encoded hash
     */
    private function generateXAdESPropertiesHash($dom) {
        // CRITICAL: With INCLUSIVE canonicalization, SignedProperties will include ALL inherited
        // namespace declarations from parent elements (Invoice, UBLExtensions, etc.).
        // We must recreate the EXACT parent namespace context to get the correct canonical form.
        //
        // The embedded SignedProperties inherits namespaces from:
        // 1. Invoice element: default xmlns, cac, cbc, ext
        // 2. UBLExtensions: sig, sac, sbc, ds, xades
        //
        // These inherited namespaces WILL appear in the canonical form due to inclusive C14N.

        // CRITICAL: The hash calculation structure MUST match the embedded structure EXACTLY
        // createXAdESObject() creates: ds:Object > xades:QualifyingProperties (with xmlns:xades, xmlns:ds) > xades:SignedProperties
        // We must replicate this EXACT structure for hash calculation

        $tempDoc = new DOMDocument('1.0', 'UTF-8');
        $tempDoc->formatOutput = false;
        $tempDoc->preserveWhiteSpace = false;

        $xadesNS = 'http://uri.etsi.org/01903/v1.3.2#';

        // Create ds:Object as root (matches createXAdESObject)
        $object = $tempDoc->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Object');
        $tempDoc->appendChild($object);

        // Create QualifyingProperties with ONLY xmlns:xades (matches createXAdESObject and ZATCA sample)
        $qualifyingProps = $tempDoc->createElementNS($xadesNS, 'xades:QualifyingProperties');
        $qualifyingProps->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', $xadesNS);
        $qualifyingProps->setAttribute('Target', 'signature');
        $object->appendChild($qualifyingProps);

        // Create SignedProperties - inherits namespaces from QualifyingProperties
        $signedProps = $this->createXAdESSignedProperties($tempDoc);
        $qualifyingProps->appendChild($signedProps);

        // Canonicalize SignedProperties only (not the whole document)
        $canonical = $signedProps->C14N(false, false);

        // Debug logging
        ZatcaPhase2Config::log('XAdES SignedProperties canonical form length: ' . strlen($canonical) . ' bytes', 'INFO');
        ZatcaPhase2Config::log('XAdES SignedProperties canonical (first 500 chars): ' . substr($canonical, 0, 500), 'DEBUG');
        if (strlen($canonical) < 50) {
            ZatcaPhase2Config::log('WARNING: XAdES canonical form seems too short: ' . $canonical, 'ERROR');
        }

        // ZATCA uses DOUBLE BASE64: base64(hex(sha256(canonical)))
        $hashHex = hash('sha256', $canonical, false); // Get hex string (64 chars)
        $hashBase64 = base64_encode($hashHex); // Base64 encode the hex string
        ZatcaPhase2Config::log('XAdES SignedProperties hash hex: ' . $hashHex, 'INFO');
        ZatcaPhase2Config::log('XAdES SignedProperties hash: ' . $hashBase64, 'INFO');

        return $hashBase64;
    }

    /**
     * Create XAdES SignedProperties element
     *
     * @param DOMDocument $dom DOM document
     * @return DOMElement SignedProperties element
     */
    private function createXAdESSignedProperties($dom) {
        $xadesNS = 'http://uri.etsi.org/01903/v1.3.2#';

        // SignedProperties
        $signedProps = $dom->createElementNS($xadesNS, 'xades:SignedProperties');
        $signedProps->setAttribute('Id', 'xadesSignedProperties');

        // NOTE: With exclusive canonicalization, namespace declarations are automatically
        // added for all visibly utilized namespaces, so we don't add them explicitly

        // SignedSignatureProperties
        $signedSigProps = $dom->createElementNS($xadesNS, 'xades:SignedSignatureProperties');

        // SigningTime (ISO 8601 format WITHOUT 'Z' suffix - ZATCA requirement)
        // CRITICAL: Use the captured signing time to ensure hash consistency
        $signingTimeValue = str_replace('Z', '', $this->signingTime); // Remove Z suffix
        $signingTimeElement = $dom->createElementNS($xadesNS, 'xades:SigningTime', $signingTimeValue);
        $signedSigProps->appendChild($signingTimeElement);

        // SigningCertificate
        $signingCert = $dom->createElementNS($xadesNS, 'xades:SigningCertificate');
        $cert = $dom->createElementNS($xadesNS, 'xades:Cert');

        // CertDigest
        $certDigest = $dom->createElementNS($xadesNS, 'xades:CertDigest');
        $certDigestMethod = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:DigestMethod');
        $certDigestMethod->setAttribute('Algorithm', ZatcaPhase2Config::DIGEST_ALGORITHM);
        $certDigest->appendChild($certDigestMethod);

        // Calculate certificate hash - ZATCA uses DOUBLE BASE64: base64(hex(sha256(cert)))
        $certDER = $this->getCertificateDER();
        $certHashHex = hash('sha256', $certDER, false); // Get hex string (64 chars)
        $certHash = base64_encode($certHashHex); // Base64 encode the hex string
        ZatcaPhase2Config::log('Certificate DER length: ' . strlen($certDER) . ' bytes', 'INFO');
        ZatcaPhase2Config::log('Certificate hash hex: ' . $certHashHex, 'INFO');
        ZatcaPhase2Config::log('Certificate hash (CertDigest): ' . $certHash, 'INFO');
        $certDigestValue = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:DigestValue', $certHash);
        $certDigest->appendChild($certDigestValue);
        $cert->appendChild($certDigest);

        // IssuerSerial
        $certData = openssl_x509_parse($this->certificate);
        $issuerSerial = $dom->createElementNS($xadesNS, 'xades:IssuerSerial');

        // CRITICAL FIX: Must use issuer DN, not subject DN!
        // Format issuer as RFC 2253 DN (required by XML DSIG spec)
        $issuerDN = $this->formatDN($certData['issuer']);
        $issuerName = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:X509IssuerName', $issuerDN);
        // FIX: Convert hex to decimal
        $serialNumberValue = $certData['serialNumber'];
        if (strpos($serialNumberValue, '0x') === 0) {
            $hexValue = substr($serialNumberValue, 2);
            if (function_exists('gmp_init')) {
                $serialNumberValue = gmp_strval(gmp_init($hexValue, 16), 10);
            } elseif (function_exists('bcpow')) {
                $decimal = '0';
                for ($i = 0; $i < strlen($hexValue); $i++) {
                    $decimal = bcmul($decimal, '16');
                    $decimal = bcadd($decimal, (string)hexdec($hexValue[$i]));
                }
                $serialNumberValue = $decimal;
            }
        }
        $serialNumber = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:X509SerialNumber', $serialNumberValue);
        $issuerSerial->appendChild($issuerName);
        $issuerSerial->appendChild($serialNumber);
        $cert->appendChild($issuerSerial);

        $signingCert->appendChild($cert);
        $signedSigProps->appendChild($signingCert);

        $signedProps->appendChild($signedSigProps);

        return $signedProps;
    }

    /**
     * Create XAdES ds:Object with QualifyingProperties
     *
     * @param DOMDocument $dom DOM document
     * @return DOMElement ds:Object element
     */
    private function createXAdESObject($dom) {
        $xadesNS = 'http://uri.etsi.org/01903/v1.3.2#';

        // ds:Object
        $object = $dom->createElementNS(ZatcaPhase2Config::DS_NAMESPACE, 'ds:Object');

        // xades:QualifyingProperties - ONLY declare xmlns:xades, NOT xmlns:ds
        // The ds namespace is inherited from parent ds:Signature element
        // ZATCA sample shows: <xades:QualifyingProperties xmlns:xades="..." Target="signature">
        $qualifyingProps = $dom->createElementNS($xadesNS, 'xades:QualifyingProperties');
        $qualifyingProps->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', $xadesNS);
        $qualifyingProps->setAttribute('Target', 'signature');

        // Add SignedProperties
        $signedProps = $this->createXAdESSignedProperties($dom);
        $qualifyingProps->appendChild($signedProps);

        $object->appendChild($qualifyingProps);

        return $object;
    }

    /**
     * Get certificate in DER format
     *
     * @return string Certificate DER bytes
     */
    private function getCertificateDER() {
        // CRITICAL FIX: Must use the same certificate content that's embedded in X509Certificate
        // Otherwise the CertDigest won't match what ZATCA validates
        $certContent = $this->getCertificateContent();
        return base64_decode($certContent);
    }

    /**
     * Get certificate content without headers
     * Handles double-encoded certificates from ZATCA
     *
     * @return string Certificate content (single base64 encoded)
     */
    private function getCertificateContent() {
        $cert = $this->certificateString;
        $cert = str_replace('-----BEGIN CERTIFICATE-----', '', $cert);
        $cert = str_replace('-----END CERTIFICATE-----', '', $cert);
        $cert = str_replace(["\n", "\r", " "], '', $cert);
        $cert = trim($cert);

        // Check if the certificate is double-encoded
        // Double-encoded starts with "TU" (base64 of "MI" which is base64 of DER)
        // Single-encoded starts with "MI" (base64 of DER)
        if (strpos($cert, 'TU') === 0) {
            // Double-encoded: decode once to get the actual base64 certificate
            $decoded = base64_decode($cert, true);
            if ($decoded !== false && strpos($decoded, 'MI') === 0) {
                // Successfully decoded to single-encoded format
                ZatcaPhase2Config::log('Certificate was double-encoded, decoded to single layer', 'INFO');
                return $decoded;
            }
        }

        // Already single-encoded or couldn't decode
        return $cert;
    }

    /**
     * Format Distinguished Name (DN) array to RFC 2253 comma-separated format
     * ZATCA requires: CN=..., DC=..., DC=..., DC=...
     *
     * @param array $dn DN components from openssl_x509_parse()
     * @return string Formatted DN string (e.g., "CN=Test, DC=example, DC=com")
     */
    private function formatDN($dn) {
        // RFC 2253 format: comma-separated, CN first, then DC in order
        $components = [];

        // CN comes first in RFC 2253 format
        if (isset($dn['CN'])) {
            $components[] = 'CN=' . $dn['CN'];
        }

        // Then DC fields in order (most specific to least specific)
        if (isset($dn['DC']) && is_array($dn['DC'])) {
            foreach ($dn['DC'] as $dc) {
                $components[] = 'DC=' . $dc;
            }
        }

        // Other fields if present
        $otherFields = ['OU', 'O', 'L', 'ST', 'C'];
        foreach ($otherFields as $key) {
            if (isset($dn[$key])) {
                $components[] = $key . '=' . $dn[$key];
            }
        }

        // Join with comma-space (RFC 2253 format as used by ZATCA)
        return implode(', ', $components);
    }

    /**
     * Verify signed invoice
     *
     * @param string $signedXML Signed XML
     * @return bool True if valid
     */
    public function verifySignature($signedXML) {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($signedXML);

            // Extract signature value
            $signatureValues = $dom->getElementsByTagNameNS(ZatcaPhase2Config::DS_NAMESPACE, 'SignatureValue');
            if ($signatureValues->length === 0) {
                throw new Exception('Signature not found in XML');
            }

            $signatureBase64 = $signatureValues->item(0)->nodeValue;
            $signature = base64_decode($signatureBase64);

            // Get canonical XML without signature
            $canonical = HashGenerator::canonicalizeXML($signedXML);

            // Verify signature
            $result = openssl_verify(
                $canonical,
                $signature,
                $this->certificate,
                OPENSSL_ALGO_SHA256
            );

            if ($result === 1) {
                ZatcaPhase2Config::log('Signature verification: VALID', 'INFO');
                return true;
            } elseif ($result === 0) {
                ZatcaPhase2Config::log('Signature verification: INVALID', 'WARNING');
                return false;
            } else {
                throw new Exception('Signature verification error: ' . openssl_error_string());
            }

        } catch (Exception $e) {
            ZatcaPhase2Config::log('Signature verification error: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Destructor - free resources
     * Note: In PHP 8.0+, openssl_free_key() and openssl_x509_free() are deprecated
     * Resources are automatically freed when no longer referenced
     */
    public function __destruct() {
        // Resources are automatically freed in PHP 8.0+
        // No need to manually free OpenSSL resources
    }
}

?>
