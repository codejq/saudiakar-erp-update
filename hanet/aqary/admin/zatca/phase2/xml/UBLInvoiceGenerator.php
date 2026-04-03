<?php
/**
 * UBL 2.1 Invoice Generator for ZATCA Phase 2
 *
 * Generates XML invoices in UBL 2.1 format compliant with ZATCA requirements
 *
 * @charset UTF-8
 * @version 2.0
 * @date 2025-11-01
 */

require_once __DIR__ . '/../../config/phase2_config.php';
require_once __DIR__ . '/../../lib/InvoiceHelper.php';

class UBLInvoiceGenerator {

    private $dom;
    private $invoice;
    private $invoiceData;

    /**
     * Constructor
     */
    public function __construct() {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = false; // Don't format for signing
        $this->dom->preserveWhiteSpace = false;
    }

    /**
     * Generate UBL XML invoice
     *
     * @param array $invoiceData Invoice data array
     * @return string XML string
     */
    public function generateXML($invoiceData) {
        try {
            $this->invoiceData = $invoiceData;

            // Create root Invoice element
            $this->invoice = $this->dom->createElementNS(
                ZatcaPhase2Config::UBL_NAMESPACE,
                'Invoice'
            );

            // Add namespaces
            $this->invoice->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:cac',
                ZatcaPhase2Config::UBL_CAC_NAMESPACE
            );
            $this->invoice->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:cbc',
                ZatcaPhase2Config::UBL_CBC_NAMESPACE
            );
            $this->invoice->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:ext',
                ZatcaPhase2Config::UBL_EXT_NAMESPACE
            );

            $this->dom->appendChild($this->invoice);

            // Build invoice structure
            $this->addProfileID();
            $this->addInvoiceID();
            $this->addUUID();
            $this->addIssueDateTime();
            $this->addInvoiceTypeCode();
            $this->addNote();             // cbc:Note must follow InvoiceTypeCode per UBL 2.1 XSD
            $this->addDocumentCurrencyCode();
            $this->addTaxCurrencyCode();
            $this->addBillingReference();
            $this->addAdditionalDocumentReferences();
            $this->addSignature();
            $this->addAccountingSupplierParty();
            $this->addAccountingCustomerParty();
            $this->addDelivery();
            $this->addPaymentMeans();
            $this->addTaxTotal();
            $this->addLegalMonetaryTotal();
            $this->addInvoiceLines();

            // Get XML string (UTF-8 without BOM)
            $xml = $this->dom->saveXML();

            // Remove UTF-8 BOM if present (ZATCA requirement)
            $xml = ltrim($xml, "\xEF\xBB\xBF");

            ZatcaPhase2Config::log('UBL XML invoice generated: ' . ($invoiceData['invoice_number'] ?? 'N/A'), 'INFO');

            return $xml;

        } catch (Exception $e) {
            $error = 'XML Generation Error: ' . $e->getMessage();
            ZatcaPhase2Config::log($error, 'ERROR');
            throw new Exception($error);
        }
    }

    /**
     * Add ProfileID
     */
    private function addProfileID() {
        // reporting:1.0 = B2C Simplified Tax Invoice (requires QR Tags 1-9)
        // clearance:1.0 = B2B Standard Tax Invoice (QR Tags 1-5 sufficient in sandbox)
        $profile = $this->invoiceData['profile'] ?? 'reporting:1.0';
        $profileID = $this->createElement('cbc:ProfileID', $profile);
        $this->invoice->appendChild($profileID);
    }

    /**
     * Add Invoice ID
     */
    private function addInvoiceID() {
        $invoiceID = $this->createElement(
            'cbc:ID',
            $this->invoiceData['invoice_number']
        );
        $this->invoice->appendChild($invoiceID);
    }

    /**
     * Add UUID
     */
    private function addUUID() {
        $uuid = $this->invoiceData['uuid'] ?? $this->generateUUID();
        $uuidElement = $this->createElement('cbc:UUID', $uuid);
        $this->invoice->appendChild($uuidElement);
    }

    /**
     * Add Issue Date and Time
     */
    private function addIssueDateTime() {
        // Issue Date
        $issueDate = $this->createElement(
            'cbc:IssueDate',
            date('Y-m-d', strtotime($this->invoiceData['invoice_date']))
        );
        $this->invoice->appendChild($issueDate);

        // Issue Time
        $issueTime = $this->createElement(
            'cbc:IssueTime',
            $this->invoiceData['invoice_time'] ?? date('H:i:s')
        );
        $this->invoice->appendChild($issueTime);
    }

    /**
     * Add Invoice Type Code
     */
    private function addInvoiceTypeCode() {
        $isStandard = $this->invoiceData['is_standard'] ?? true;
        $isCredit   = $this->invoiceData['is_credit']   ?? false;
        $isDebit    = $this->invoiceData['is_debit']    ?? false;

        if ($isCredit)        { $typeCode = '381'; }
        elseif ($isDebit)     { $typeCode = '383'; }
        else                  { $typeCode = '388'; }

        $typeCodeName = ZatcaPhase2Config::getInvoiceTypeCodeName($isStandard, $isCredit, $isDebit);

        $invoiceTypeCode = $this->createElement('cbc:InvoiceTypeCode', $typeCode);
        $invoiceTypeCode->setAttribute('name', $typeCodeName);
        $invoiceTypeCode->setAttribute('listID', 'KSA');
        $this->invoice->appendChild($invoiceTypeCode);
    }

    /**
     * Add Document Currency Code
     */
    private function addDocumentCurrencyCode() {
        $currencyCode = $this->createElement(
            'cbc:DocumentCurrencyCode',
            ZatcaPhase2Config::CURRENCY_CODE
        );
        $this->invoice->appendChild($currencyCode);
    }

    /**
     * Add Tax Currency Code
     */
    private function addTaxCurrencyCode() {
        $taxCurrencyCode = $this->createElement(
            'cbc:TaxCurrencyCode',
            ZatcaPhase2Config::CURRENCY_CODE
        );
        $this->invoice->appendChild($taxCurrencyCode);
    }

    /**
     * Add Note (KSA-10) — required for credit/debit notes (BR-KSA-17)
     * KSA-10 is the cbc:Note element with languageID attribute
     */
    private function addNote() {
        $isCredit = $this->invoiceData['is_credit'] ?? false;
        $isDebit  = $this->invoiceData['is_debit']  ?? false;
        if ($isCredit || $isDebit) {
            // KSA-10: Reason for issuance - must be meaningful text (min 1 char per BR-KSA-17)
            $reason = $this->invoiceData['note'] ?? '';
            if (empty($reason)) {
                $reason = $isCredit ? 'إشعار دائن - استرجاع' : 'إشعار مدين - رسوم إضافية';
            }
            $note = $this->createElement('cbc:Note', $reason);
            $note->setAttribute('languageID', 'ar');
            $this->invoice->appendChild($note);
        }
    }

    /**
     * Add BillingReference — required for credit/debit notes (BR-KSA-56)
     */
    private function addBillingReference() {
        $isCredit = $this->invoiceData['is_credit'] ?? false;
        $isDebit  = $this->invoiceData['is_debit']  ?? false;
        if ($isCredit || $isDebit) {
            $originalId = $this->invoiceData['original_invoice_number'] ?? 'INV-ORIGINAL';
            $billingRef = $this->createElement('cac:BillingReference');
            $invoiceDocRef = $this->createElement('cac:InvoiceDocumentReference');
            $invoiceDocRef->appendChild($this->createElement('cbc:ID', $originalId));
            $billingRef->appendChild($invoiceDocRef);
            $this->invoice->appendChild($billingRef);
        }
    }

    /**
     * Add Additional Document References (ICV, PIH, QR)
     */
    private function addAdditionalDocumentReferences() {
        // Invoice Counter Value (ICV)
        $this->addDocumentReference('ICV', $this->invoiceData['invoice_counter'] ?? 1);

        // Previous Invoice Hash (PIH)
        $pih = $this->invoiceData['previous_invoice_hash'] ?? ZatcaPhase2Config::FIRST_INVOICE_PIH;
        $this->addDocumentReferenceWithAttachment('PIH', $pih);

        // QR Code
        if (isset($this->invoiceData['qr_code'])) {
            $this->addDocumentReferenceWithAttachment('QR', $this->invoiceData['qr_code']);
        }
    }

    /**
     * Add Document Reference
     */
    private function addDocumentReference($id, $uuid) {
        $docRef = $this->createElement('cac:AdditionalDocumentReference');
        $docRef->appendChild($this->createElement('cbc:ID', $id));
        $docRef->appendChild($this->createElement('cbc:UUID', $uuid));
        $this->invoice->appendChild($docRef);
    }

    /**
     * Add Document Reference with Attachment
     */
    private function addDocumentReferenceWithAttachment($id, $data) {
        $docRef = $this->createElement('cac:AdditionalDocumentReference');
        $docRef->appendChild($this->createElement('cbc:ID', $id));

        $attachment = $this->createElement('cac:Attachment');
        $embeddedDoc = $this->createElement('cbc:EmbeddedDocumentBinaryObject', $data);
        $embeddedDoc->setAttribute('mimeCode', 'text/plain');

        // ZATCA requires encodingCode="Base64" for QR code
        if ($id === 'QR') {
            $embeddedDoc->setAttribute('encodingCode', 'Base64');
        }

        $attachment->appendChild($embeddedDoc);
        $docRef->appendChild($attachment);

        $this->invoice->appendChild($docRef);
    }

    /**
     * Add Signature Reference
     *
     * This is required by ZATCA to reference the digital signature
     */
    private function addSignature() {
        $signature = $this->createElement('cac:Signature');

        // Signature ID - ZATCA requires this exact URN with '1'
        $signatureId = $this->createElement('cbc:ID', 'urn:oasis:names:specification:ubl:signature:1');
        $signature->appendChild($signatureId);

        // Signature Method
        $signatureMethod = $this->createElement('cbc:SignatureMethod', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades');
        $signature->appendChild($signatureMethod);

        $this->invoice->appendChild($signature);
    }

    /**
     * Add Accounting Supplier Party (Seller)
     */
    private function addAccountingSupplierParty() {
        $companyInfo = ZatcaPhase2Config::getCompanyInfo();

        $supplierParty = $this->createElement('cac:AccountingSupplierParty');
        $party = $this->createElement('cac:Party');

        // Party Identification (CR Number)
        $partyID = $this->createElement('cac:PartyIdentification');
        $id = $this->createElement('cbc:ID', $companyInfo['cr_number']);
        $id->setAttribute('schemeID', 'CRN');
        $partyID->appendChild($id);
        $party->appendChild($partyID);

        // Postal Address
        $postalAddress = $this->createElement('cac:PostalAddress');
        $postalAddress->appendChild($this->createElement('cbc:StreetName', $companyInfo['street_name']));
        $postalAddress->appendChild($this->createElement('cbc:BuildingNumber', $companyInfo['building_number']));
        $postalAddress->appendChild($this->createElement('cbc:PlotIdentification', '0000'));
        $postalAddress->appendChild($this->createElement('cbc:CitySubdivisionName', $companyInfo['district']));
        $postalAddress->appendChild($this->createElement('cbc:CityName', $companyInfo['city']));
        $postalAddress->appendChild($this->createElement('cbc:PostalZone', $companyInfo['postal_code']));

        $country = $this->createElement('cac:Country');
        $country->appendChild($this->createElement('cbc:IdentificationCode', $companyInfo['country_code']));
        $postalAddress->appendChild($country);
        $party->appendChild($postalAddress);

        // Party Tax Scheme (VAT)
        $partyTaxScheme = $this->createElement('cac:PartyTaxScheme');
        $partyTaxScheme->appendChild($this->createElement('cbc:CompanyID', $companyInfo['vat_number']));
        $taxScheme = $this->createElement('cac:TaxScheme');
        $taxScheme->appendChild($this->createElement('cbc:ID', 'VAT'));
        $partyTaxScheme->appendChild($taxScheme);
        $party->appendChild($partyTaxScheme);

        // Party Legal Entity
        $partyLegalEntity = $this->createElement('cac:PartyLegalEntity');
        $partyLegalEntity->appendChild($this->createElement('cbc:RegistrationName', $companyInfo['name_ar']));
        $party->appendChild($partyLegalEntity);

        $supplierParty->appendChild($party);
        $this->invoice->appendChild($supplierParty);
    }

    /**
     * Add Accounting Customer Party (Buyer)
     */
    private function addAccountingCustomerParty() {
        $customerParty = $this->createElement('cac:AccountingCustomerParty');
        $party = $this->createElement('cac:Party');

        // Party Identification
        if (!empty($this->invoiceData['customer_id'])) {
            $partyID = $this->createElement('cac:PartyIdentification');
            $id = $this->createElement('cbc:ID', $this->invoiceData['customer_id']);
            $id->setAttribute('schemeID', $this->invoiceData['customer_id_type'] ?? 'NAT');
            $partyID->appendChild($id);
            $party->appendChild($partyID);
        }

        // Postal Address - BR-KSA-63: All fields required for SA country
        $postalAddress = $this->createElement('cac:PostalAddress');
        $postalAddress->appendChild($this->createElement('cbc:StreetName', $this->invoiceData['customer_street'] ?? 'N/A'));
        $postalAddress->appendChild($this->createElement('cbc:BuildingNumber', $this->invoiceData['customer_building'] ?? '0000'));
        // BR-KSA-63: District is mandatory for SA
        $postalAddress->appendChild($this->createElement('cbc:CitySubdivisionName', $this->invoiceData['customer_district'] ?? 'Al Olaya'));
        $postalAddress->appendChild($this->createElement('cbc:CityName', $this->invoiceData['customer_city'] ?? 'Riyadh'));
        $postalAddress->appendChild($this->createElement('cbc:PostalZone', $this->invoiceData['customer_postal'] ?? '12345'));

        $country = $this->createElement('cac:Country');
        // BR-CL-14: Use ISO 3166-1 alpha-2 country code (SA = Saudi Arabia)
        $country->appendChild($this->createElement('cbc:IdentificationCode', 'SA'));
        $postalAddress->appendChild($country);
        $party->appendChild($postalAddress);

        // Party Tax Scheme (if VAT registered)
        if (!empty($this->invoiceData['customer_vat'])) {
            $partyTaxScheme = $this->createElement('cac:PartyTaxScheme');
            $partyTaxScheme->appendChild($this->createElement('cbc:CompanyID', $this->invoiceData['customer_vat']));
            $taxScheme = $this->createElement('cac:TaxScheme');
            $taxScheme->appendChild($this->createElement('cbc:ID', 'VAT'));
            $partyTaxScheme->appendChild($taxScheme);
            $party->appendChild($partyTaxScheme);
        }

        // Party Legal Entity
        $partyLegalEntity = $this->createElement('cac:PartyLegalEntity');
        $partyLegalEntity->appendChild($this->createElement('cbc:RegistrationName', $this->invoiceData['customer_name'] ?? 'Customer'));
        $party->appendChild($partyLegalEntity);

        $customerParty->appendChild($party);
        $this->invoice->appendChild($customerParty);
    }

    /**
     * Add Delivery
     */
    private function addDelivery() {
        $delivery = $this->createElement('cac:Delivery');
        $actualDeliveryDate = $this->createElement(
            'cbc:ActualDeliveryDate',
            date('Y-m-d', strtotime($this->invoiceData['invoice_date']))
        );
        $delivery->appendChild($actualDeliveryDate);
        $this->invoice->appendChild($delivery);
    }

    /**
     * Add Payment Means
     * For credit/debit notes, also add InstructionNote (KSA-10 for BR-KSA-17)
     */
    private function addPaymentMeans() {
        $isCredit = $this->invoiceData['is_credit'] ?? false;
        $isDebit  = $this->invoiceData['is_debit']  ?? false;

        $paymentMeans = $this->createElement('cac:PaymentMeans');
        $paymentMeansCode = $this->createElement('cbc:PaymentMeansCode', '10'); // 10 = Cash
        $paymentMeans->appendChild($paymentMeansCode);

        // BR-KSA-17: Add InstructionNote for credit/debit notes (KSA-10 reason for issuance)
        if ($isCredit || $isDebit) {
            $reason = $this->invoiceData['note'] ?? '';
            if (empty($reason)) {
                $reason = $isCredit ? 'Cancellation' : 'Price adjustment';
            }
            $instructionNote = $this->createElement('cbc:InstructionNote', $reason);
            $instructionNote->setAttribute('languageID', 'ar');
            $paymentMeans->appendChild($instructionNote);
        }

        $this->invoice->appendChild($paymentMeans);
    }

    /**
     * Add Tax Total
     * BR-KSA-EN16931-09: Only one tax total (BG-22) without tax subtotals (BG-23) when tax currency code is shown
     */
    private function addTaxTotal() {
        $taxTotal = $this->createElement('cac:TaxTotal');

        // Total Tax Amount (BG-22)
        $taxAmount = $this->createElement(
            'cbc:TaxAmount',
            ZatcaPhase2Config::formatAmount($this->invoiceData['vat_amount'])
        );
        $taxAmount->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
        $taxTotal->appendChild($taxAmount);

        // TaxSubtotal (BG-23) — required by BR-CO-18
        $taxSubtotal = $this->createElement('cac:TaxSubtotal');

        $taxableAmt = $this->createElement(
            'cbc:TaxableAmount',
            ZatcaPhase2Config::formatAmount($this->invoiceData['base_amount'])
        );
        $taxableAmt->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
        $taxSubtotal->appendChild($taxableAmt);

        $subtotalTaxAmt = $this->createElement(
            'cbc:TaxAmount',
            ZatcaPhase2Config::formatAmount($this->invoiceData['vat_amount'])
        );
        $subtotalTaxAmt->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
        $taxSubtotal->appendChild($subtotalTaxAmt);

        $taxCategory = $this->createElement('cac:TaxCategory');
        $taxCategory->appendChild($this->createElement('cbc:ID', 'S'));
        $taxCategory->appendChild($this->createElement('cbc:Percent', '15.00'));
        $taxScheme = $this->createElement('cac:TaxScheme');
        $taxScheme->appendChild($this->createElement('cbc:ID', 'VAT'));
        $taxCategory->appendChild($taxScheme);
        $taxSubtotal->appendChild($taxCategory);

        $taxTotal->appendChild($taxSubtotal);

        $this->invoice->appendChild($taxTotal);
    }

    /**
     * Add Legal Monetary Total
     */
    private function addLegalMonetaryTotal() {
        $legalMonetaryTotal = $this->createElement('cac:LegalMonetaryTotal');

        $baseAmount = $this->invoiceData['base_amount'];
        $vatAmount = $this->invoiceData['vat_amount'];
        $totalAmount = $this->invoiceData['total_amount'];

        // Line Extension Amount
        $lineExtension = $this->createElement(
            'cbc:LineExtensionAmount',
            ZatcaPhase2Config::formatAmount($baseAmount)
        );
        $lineExtension->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
        $legalMonetaryTotal->appendChild($lineExtension);

        // Tax Exclusive Amount
        $taxExclusive = $this->createElement(
            'cbc:TaxExclusiveAmount',
            ZatcaPhase2Config::formatAmount($baseAmount)
        );
        $taxExclusive->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
        $legalMonetaryTotal->appendChild($taxExclusive);

        // Tax Inclusive Amount
        $taxInclusive = $this->createElement(
            'cbc:TaxInclusiveAmount',
            ZatcaPhase2Config::formatAmount($totalAmount)
        );
        $taxInclusive->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
        $legalMonetaryTotal->appendChild($taxInclusive);

        // Allowance Total Amount
        $allowanceTotal = $this->createElement('cbc:AllowanceTotalAmount', '0.00');
        $allowanceTotal->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
        $legalMonetaryTotal->appendChild($allowanceTotal);

        // Payable Amount
        $payableAmount = $this->createElement(
            'cbc:PayableAmount',
            ZatcaPhase2Config::formatAmount($totalAmount)
        );
        $payableAmount->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
        $legalMonetaryTotal->appendChild($payableAmount);

        $this->invoice->appendChild($legalMonetaryTotal);
    }

    /**
     * Add Invoice Lines
     */
    private function addInvoiceLines() {
        $lines = $this->invoiceData['lines'] ?? [
            [
                'id' => 1,
                'name' => $this->invoiceData['description'] ?? 'Service',
                'quantity' => 1,
                'unit_price' => $this->invoiceData['base_amount'],
                'line_total' => $this->invoiceData['base_amount'],
                'vat_amount' => $this->invoiceData['vat_amount']
            ]
        ];

        foreach ($lines as $lineData) {
            $invoiceLine = $this->createElement('cac:InvoiceLine');

            // Line ID
            $invoiceLine->appendChild($this->createElement('cbc:ID', $lineData['id']));

            // Invoiced Quantity
            $quantity = $this->createElement('cbc:InvoicedQuantity', $lineData['quantity']);
            $quantity->setAttribute('unitCode', 'PCE');
            $invoiceLine->appendChild($quantity);

            // Line Extension Amount
            $lineExtension = $this->createElement(
                'cbc:LineExtensionAmount',
                ZatcaPhase2Config::formatAmount($lineData['line_total'])
            );
            $lineExtension->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
            $invoiceLine->appendChild($lineExtension);

            // Tax Total (line level)
            $taxTotal = $this->createElement('cac:TaxTotal');
            $taxAmount = $this->createElement(
                'cbc:TaxAmount',
                ZatcaPhase2Config::formatAmount($lineData['vat_amount'])
            );
            $taxAmount->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
            $taxTotal->appendChild($taxAmount);
            // KSA-12: line amount inclusive of VAT (mandatory per BR-KSA-53)
            $roundingAmount = $this->createElement(
                'cbc:RoundingAmount',
                ZatcaPhase2Config::formatAmount($lineData['line_total'] + $lineData['vat_amount'])
            );
            $roundingAmount->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
            $taxTotal->appendChild($roundingAmount);
            $invoiceLine->appendChild($taxTotal);

            // Item
            $item = $this->createElement('cac:Item');
            $item->appendChild($this->createElement('cbc:Name', $lineData['name']));

            // Classified Tax Category
            $taxCategory = $this->createElement('cac:ClassifiedTaxCategory');
            $taxCategory->appendChild($this->createElement('cbc:ID', 'S'));
            $taxCategory->appendChild($this->createElement('cbc:Percent', '15.00'));
            $taxScheme = $this->createElement('cac:TaxScheme');
            $taxScheme->appendChild($this->createElement('cbc:ID', 'VAT'));
            $taxCategory->appendChild($taxScheme);
            $item->appendChild($taxCategory);
            $invoiceLine->appendChild($item);

            // Price
            $price = $this->createElement('cac:Price');
            $priceAmount = $this->createElement(
                'cbc:PriceAmount',
                ZatcaPhase2Config::formatAmount($lineData['unit_price'])
            );
            $priceAmount->setAttribute('currencyID', ZatcaPhase2Config::CURRENCY_CODE);
            $price->appendChild($priceAmount);
            $invoiceLine->appendChild($price);

            $this->invoice->appendChild($invoiceLine);
        }
    }

    /**
     * Create element with namespace
     * Uses createElement instead of createElementNS to avoid redundant namespace declarations
     * The namespaces are already declared on the root Invoice element
     */
    private function createElement($name, $value = null) {
        // Use createElement with prefixed name - namespaces are inherited from root
        // This avoids redundant xmlns declarations on every element
        if ($value !== null) {
            // Escape special XML characters in value
            $element = $this->dom->createElement($name);
            $element->appendChild($this->dom->createTextNode($value));
            return $element;
        } else {
            return $this->dom->createElement($name);
        }
    }

    /**
     * Generate UUID v4
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
}

?>
