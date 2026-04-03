<?php
/**
 * ZATCA Compliance Steps - Run All 6 Required Invoice Types
 *
 * Submits all 6 invoice types to /compliance/invoices
 * Required before requesting Production CSID
 */
$sc_title = "ZATCA - خطوات الامتثال الست";
$sc_id = "158";

if ($_SERVER['REQUEST_METHOD'] != 'POST' && !isset($_POST['action'])) {
    include_once "../../nocash.hnt";
    include_once "../../header.hnt";
} else {
    include_once "../../reqloginajax.hnt";
}

require_once __DIR__ . '/../../connectdb.hnt';
require_once __DIR__ . '/config/phase2_config.php';
require_once __DIR__ . '/phase2/integration/Phase2Manager.php';
require_once __DIR__ . '/phase2/api/ZatcaAPIClient.php';

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="UTF-8">
<title>ZATCA - خطوات الامتثال الست</title>
<style>
body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
.card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
h1 { color: #2c3e50; border-bottom: 3px solid #27ae60; padding-bottom: 10px; }
h2 { color: #2c3e50; font-size: 16px; margin: 0 0 10px; }
.step { border-right: 4px solid #bdc3c7; padding: 15px; margin: 10px 0; border-radius: 5px; background: #f8f9fa; display: flex; align-items: flex-start; }
.step-icon { margin-left: 15px; font-size: 24px; min-width: 30px; }
.step-content { flex: 1; }
.step.running { border-right-color: #3498db; background: #ebf5fb; }
.step.pass { border-right-color: #27ae60; background: #eafaf1; }
.step.fail { border-right-color: #e74c3c; background: #fdedec; }
.step.warning { border-right-color: #f39c12; background: #fef9e7; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
.badge-pass { background: #27ae60; color: white; }
.badge-fail { background: #e74c3c; color: white; }
.badge-warning { background: #f39c12; color: white; }
.badge-pending { background: #bdc3c7; color: white; }
.details { font-size: 12px; color: #555; margin-top: 8px; font-family: monospace; }
.btn { display: inline-block; padding: 12px 30px; background: #27ae60; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; text-decoration: none; }
.btn:hover { background: #219a52; }
.btn-warning { background: #e67e22; }
.btn-warning:hover { background: #d35400; }
.summary { display: flex; gap: 15px; flex-wrap: wrap; margin: 15px 0; }
.summary-box { flex: 1; min-width: 120px; text-align: center; padding: 15px; border-radius: 8px; }
.summary-box.total { background: #3498db; color: white; }
.summary-box.passed { background: #27ae60; color: white; }
.summary-box.failed { background: #e74c3c; color: white; }
.summary-box .num { font-size: 32px; font-weight: bold; }
.summary-box .lbl { font-size: 12px; }
pre { font-size: 11px; background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; max-height: 150px; }
.header-icons { margin-bottom: 20px; }
.header-icons i { margin: 0 5px; font-size: 20px; }
</style>
</head>
<body>

<div class="card">
    <div style="margin-bottom: 15px;">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> العودة للقائمة الرئيسية
        </a>
    </div>
    <h1>
        <i class="bi bi-patch-check-fill" style="color: #27ae60;"></i>
        خطوات الامتثال الست - ZATCA
        <div class="header-icons">
            <i class="bi bi-shield-check" title="Compliance"></i>
            <i class="bi bi-file-earmark-check" title="Invoices"></i>
            <i class="bi bi-key" title="CSID"></i>
        </div>
    </h1>
    <p>يجب إتمام هذه الخطوات الست قبل طلب Production CSID. يتم إرسال نماذج فواتير إلى <code>/compliance/invoices</code> بشهادة Compliance.</p>

<?php

$run = isset($_GET['run']);

// Load compliance certificate
$env = ZatcaPhase2Config::$ENVIRONMENT;
$certFile   = ZatcaPhase2Config::CERT_DIR . '/' . $env . '_certificate.pem';
$secretFile = ZatcaPhase2Config::CERT_DIR . '/' . $env . '_secret.txt';

if (!file_exists($certFile) || !file_exists($secretFile)) {
    echo '<div class="step fail">❌ شهادة Compliance غير موجودة للبيئة: <strong>' . htmlspecialchars($env) . '</strong><br>';
    echo 'يجب الحصول على شهادة Compliance أولاً من صفحة الإعداد.</div>';
    echo '</div></body></html>';
    exit;
}

$certContent = file_get_contents($certFile);
$certificate = str_replace(['-----BEGIN CERTIFICATE-----','-----END CERTIFICATE-----',"\n","\r",' ',"\t"], '', $certContent);
$secret      = str_replace(["\n","\r",' ',"\t"], '', file_get_contents($secretFile));

echo '<div class="step pass"><i class="bi bi-check-circle-fill step-icon" style="color: #27ae60;"></i><div class="step-content">شهادة Compliance محملة - البيئة: <strong>' . htmlspecialchars(strtoupper($env)) . '</strong></div></div>';

// Define 6 invoice types with icons
// Profiles: clearance:1.0 = Standard B2B,  reporting:1.0 = Simplified B2C
// Credit/debit notes MUST have positive amounts — ZATCA uses InvoiceTypeCode (381/383) for the credit/debit nature
$invoiceTypes = [
    [
        'key'     => 'standard-compliant',
        'label'   => 'فاتورة ضريبية عادية (B2B)',
        'icon'    => 'bi-receipt',
        'data'    => [
            'profile'          => 'reporting:1.0',
            'invoice_number'   => 'COMP-STD-' . date('YmdHis'),
            'invoice_date'     => date('Y-m-d'),
            'invoice_time'     => date('H:i:s'),
            'base_amount'      => 1000.00,
            'total_amount'     => 1150.00,
            'vat_amount'       => 150.00,
            'is_standard'      => true,
            'customer_name'    => 'شركة الاختبار المحدودة',
            'customer_id'      => '1234567890',
            'customer_id_type' => 'CRN',
            'customer_vat'     => '300000000000003',
            'customer_street'  => 'شارع الملك فهد',
            'customer_building'=> '1234',
            'customer_city'    => 'الرياض',
            'customer_postal'  => '12345',
            'description'      => 'خدمة إيجار عقار تجاري',
        ],
        'type' => 'standard',
    ],
    [
        'key'   => 'standard-credit-note-compliant',
        'label' => 'إشعار دائن عادي (B2B)',
        'icon'  => 'bi-arrow-return-left',
        'data'  => [
            'profile'          => 'reporting:1.0',
            'invoice_number'   => 'COMP-SCRD-' . date('YmdHis'),
            'invoice_date'     => date('Y-m-d'),
            'invoice_time'     => date('H:i:s'),
            'base_amount'      => 200.00,
            'total_amount'     => 230.00,
            'vat_amount'       => 30.00,
            'is_standard'             => true,
            'is_credit'               => true,
            'original_invoice_number' => 'COMP-STD-ORIGINAL',
            'note'                    => 'إشعار دائن - استرجاع إيجار',
            'customer_name'    => 'شركة الاختبار المحدودة',
            'customer_id'      => '1012345678',
            'customer_id_type' => 'CRN',
            'customer_vat'     => '300000000000003',
            'customer_street'  => 'شارع الملك فهد',
            'customer_building'=> '1234',
            'customer_district'=> 'العليا',
            'customer_city'    => 'الرياض',
            'customer_postal'  => '12345',
            'description'      => 'إشعار دائن - استرجاع إيجار',
        ],
        'type' => 'standard',
    ],
    [
        'key'   => 'standard-debit-note-compliant',
        'label' => 'إشعار مدين عادي (B2B)',
        'icon'  => 'bi-arrow-return-right',
        'data'  => [
            'profile'          => 'reporting:1.0',
            'invoice_number'   => 'COMP-SDBT-' . date('YmdHis'),
            'invoice_date'     => date('Y-m-d'),
            'invoice_time'     => date('H:i:s'),
            'base_amount'      => 100.00,
            'total_amount'     => 115.00,
            'vat_amount'       => 15.00,
            'is_standard'             => true,
            'is_debit'                => true,
            'original_invoice_number' => 'COMP-STD-ORIGINAL',
            'note'                    => 'إشعار مدين - رسوم إضافية',
            'customer_name'    => 'شركة الاختبار المحدودة',
            'customer_id'      => '1012345678',
            'customer_id_type' => 'CRN',
            'customer_vat'     => '300000000000003',
            'customer_street'  => 'شارع الملك فهد',
            'customer_building'=> '1234',
            'customer_district'=> 'العليا',
            'customer_city'    => 'الرياض',
            'customer_postal'  => '12345',
            'description'      => 'إشعار مدين - رسوم إضافية',
        ],
        'type' => 'standard',
    ],
    [
        'key'   => 'simplified-compliant',
        'label' => 'فاتورة مبسطة (B2C)',
        'icon'  => 'bi-receipt-cutoff',
        'data'  => [
            'profile'          => 'reporting:1.0',
            'invoice_number'   => 'COMP-SMP-' . date('YmdHis'),
            'invoice_date'     => date('Y-m-d'),
            'invoice_time'     => date('H:i:s'),
            'base_amount'      => 500.00,
            'total_amount'     => 575.00,
            'vat_amount'       => 75.00,
            'is_standard'      => false,
            'customer_name'    => 'عميل نقدي',
            'customer_id'      => '',
            'customer_vat'     => '',
            'customer_street'  => 'غير محدد',
            'customer_building'=> '0000',
            'customer_city'    => 'الرياض',
            'customer_postal'  => '00000',
            'description'      => 'خدمة إيجار سكني',
        ],
        'type' => 'simplified',
    ],
    [
        'key'   => 'simplified-credit-note-compliant',
        'label' => 'إشعار دائن مبسط (B2C)',
        'icon'  => 'bi-arrow-return-left',
        'data'  => [
            'profile'          => 'reporting:1.0',
            'invoice_number'   => 'COMP-SCMP-' . date('YmdHis'),
            'invoice_date'     => date('Y-m-d'),
            'invoice_time'     => date('H:i:s'),
            'base_amount'      => 100.00,
            'total_amount'     => 115.00,
            'vat_amount'       => 15.00,
            'is_standard'             => false,
            'is_credit'               => true,
            'original_invoice_number' => 'COMP-SMP-ORIGINAL',
            'note'                    => 'إشعار دائن مبسط - استرجاع',
            'customer_name'    => 'عميل نقدي',
            'customer_id'      => '',
            'customer_vat'     => '',
            'customer_street'  => 'غير محدد',
            'customer_building'=> '0000',
            'customer_district'=> 'غير محدد',
            'customer_city'    => 'الرياض',
            'customer_postal'  => '00000',
            'description'      => 'إشعار دائن مبسط - استرجاع',
        ],
        'type' => 'simplified',
    ],
    [
        'key'   => 'simplified-debit-note-compliant',
        'label' => 'إشعار مدين مبسط (B2C)',
        'icon'  => 'bi-arrow-return-right',
        'data'  => [
            'profile'          => 'reporting:1.0',
            'invoice_number'   => 'COMP-SDMP-' . date('YmdHis'),
            'invoice_date'     => date('Y-m-d'),
            'invoice_time'     => date('H:i:s'),
            'base_amount'      => 50.00,
            'total_amount'     => 57.50,
            'vat_amount'       => 7.50,
            'is_standard'             => false,
            'is_debit'                => true,
            'original_invoice_number' => 'COMP-SMP-ORIGINAL',
            'note'                    => 'إشعار مدين مبسط - رسوم إضافية',
            'customer_name'    => 'عميل نقدي',
            'customer_id'      => '',
            'customer_vat'     => '',
            'customer_street'  => 'غير محدد',
            'customer_building'=> '0000',
            'customer_district'=> 'غير محدد',
            'customer_city'    => 'الرياض',
            'customer_postal'  => '00000',
            'description'      => 'إشعار مدين مبسط - رسوم إضافية',
        ],
        'type' => 'simplified',
    ],
];

if (!$run) {
    // Show checklist only
    echo '<div class="summary">';
    echo '<div class="summary-box total"><div class="num">6</div><div class="lbl">إجمالي الخطوات</div></div>';
    echo '<div class="summary-box passed"><div class="num">0</div><div class="lbl">مكتملة</div></div>';
    echo '<div class="summary-box failed"><div class="num">0</div><div class="lbl">فاشلة</div></div>';
    echo '</div>';
    echo '<p>انقر الزر أدناه لتشغيل جميع الخطوات:</p>';
    foreach ($invoiceTypes as $i => $inv) {
        $icon = $inv['icon'] ?? 'bi-file-earmark';
        echo '<div class="step"><i class="' . $icon . ' step-icon" style="color: #6c757d;"></i><div class="step-content"><span class="badge badge-pending">معلق</span> &nbsp; <strong>' . ($i+1) . '. ' . htmlspecialchars($inv['label']) . '</strong><br>';
        echo '<small style="color:#888;">' . htmlspecialchars($inv['key']) . '</small></div></div>';
    }
    echo '<br><a href="?run=1" class="btn">▶ تشغيل جميع خطوات الامتثال الست</a>';
} else {
    // Run all 6
    set_time_limit(120);

    $apiClient  = new ZatcaAPIClient();
    $apiClient->setCredentials($certificate, $secret);

    $passed  = 0;
    $failed  = 0;
    $results = [];

    foreach ($invoiceTypes as $i => $inv) {
        $icon = $inv['icon'] ?? 'bi-file-earmark';
        echo '<div class="step running"><i class="' . $icon . ' step-icon" style="color: #3498db;"></i><div class="step-content">';
        echo '<strong>' . ($i+1) . '. ' . htmlspecialchars($inv['label']) . '</strong> &nbsp; <span class="badge badge-pending">جاري...</span><br>';
        echo '<small style="color:#888;">' . htmlspecialchars($inv['key']) . '</small>';
        flush();

        try {
            // NEW manager per invoice — uses global $zatca_environment
            $manager = new Phase2Manager($link);

            // Add required fields
            $invoiceData = array_merge([
                'uuid'                  => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                                            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
                                            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
                                            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)),
                'invoice_counter'       => $i + 1,
                'previous_invoice_hash' => ZatcaPhase2Config::FIRST_INVOICE_PIH,
                'lines'                 => [[
                    'id'         => 1,
                    'name'       => $inv['data']['description'],
                    'quantity'   => 1,
                    'unit_price' => abs($inv['data']['base_amount']),
                    'line_total' => $inv['data']['base_amount'],
                    'vat_amount' => $inv['data']['vat_amount'],
                ]],
            ], $inv['data']);

            // Sign invoice using compliance certificate
            $processResult = $manager->processInvoice($invoiceData, $inv['type'], false, true);

            if (!$processResult['success']) {
                throw new Exception('فشل التوقيع: ' . ($processResult['error'] ?? 'خطأ غير معروف'));
            }

            $signedXml = $processResult['signed_xml'];
            $uuid      = $processResult['uuid'] ?? $invoiceData['uuid'];

            // DEBUG: show InvoiceTypeCode + Note area from signed XML
            if (preg_match('/<cbc:InvoiceTypeCode[^>]*>\d+<\/cbc:InvoiceTypeCode>(.{0,300})/s', $signedXml, $dbgm)) {
                echo '<div class="details" style="background:#fff3e0;font-size:11px;">XML after TypeCode: <code>' . htmlspecialchars(substr($dbgm[0], 0, 300)) . '</code></div>';
            }

            // Recalculate hash from signed XML (same method as phase2_test.php)
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = true;
            @$dom->loadXML($signedXml);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
            $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
            $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

            $toRemove = [];
            foreach ($xpath->query('//ext:UBLExtensions') as $n) $toRemove[] = $n;
            foreach ($xpath->query('//cac:Signature') as $n)     $toRemove[] = $n;
            foreach ($xpath->query('//cac:AdditionalDocumentReference[cbc:ID="QR"]') as $n) $toRemove[] = $n;
            foreach ($toRemove as $n) $n->parentNode->removeChild($n);

            $canonical = $dom->documentElement->C14N(false, false);
            $hash      = base64_encode(hash('sha256', $canonical, true));

            // Submit to compliance invoices endpoint
            $apiResult = $apiClient->validateCompliance($hash, $uuid, $signedXml);

            // Status from nested validationResults (validateCompliance returns 'UNKNOWN' at top level)
            $validationStatus = $apiResult['validation_results']['status']
                ?? $apiResult['status']
                ?? 'UNKNOWN';
            $status = strtoupper($validationStatus);

            // No errorMessages = pass (only warnings are acceptable)
            $errorMessages = $apiResult['validation_results']['errorMessages'] ?? [];
            $hasErrors = !empty($errorMessages);

            // Check if already completed (ZATCA returns HTTP 406 "Submitted before")
            $httpCode = $apiResult['http_code'] ?? 0;
            $alreadyDone = ($httpCode === 406);

            if ($apiResult['success'] && !$hasErrors) {
                $passed++;
                echo '<br><span class="badge ' . ($status === 'WARNING' ? 'badge-warning' : 'badge-pass') . '">';
                echo ($status === 'WARNING' ? '<i class="bi bi-exclamation-triangle"></i> WARNING (مقبول)' : '<i class="bi bi-check-circle"></i> PASS') . '</span>';
                echo '<div class="details">Status: ' . htmlspecialchars($validationStatus) . '</div>';
            } elseif ($alreadyDone) {
                $passed++;
                echo '<br><span class="badge badge-pass"><i class="bi bi-check-circle"></i> مكتمل مسبقاً (406)</span>';
                echo '<div class="details">هذه الخطوة مكتملة بالفعل في ZATCA - تُحتسب كنجاح</div>';
            } else {
                $failed++;
                $errMsg = $apiResult['error'] ?? json_encode($apiResult, JSON_UNESCAPED_UNICODE);
                echo '<br><span class="badge badge-fail"><i class="bi bi-x-circle"></i> FAIL</span>';
                echo '<div class="details">خطأ: ' . htmlspecialchars($errMsg) . '</div>';
                if (!empty($apiResult['validation_results'])) {
                    echo '<pre>' . htmlspecialchars(json_encode($apiResult['validation_results'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
                }
            }

        } catch (Exception $e) {
            $failed++;
            echo '<br><span class="badge badge-fail"><i class="bi bi-x-circle"></i> خطأ</span>';
            echo '<div class="details">' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        echo '</div></div>'; // close step-content and step
        flush();
    }

    // Summary
    echo '<div class="card" style="margin-top:20px;">';
    echo '<h2><i class="bi bi-clipboard-data"></i> النتيجة النهائية</h2>';
    echo '<div class="summary">';
    echo '<div class="summary-box total"><div class="num">6</div><div class="lbl"><i class="bi bi-list-ul"></i> إجمالي</div></div>';
    echo '<div class="summary-box passed"><div class="num">' . $passed . '</div><div class="lbl"><i class="bi bi-check-circle"></i> نجح</div></div>';
    echo '<div class="summary-box failed"><div class="num">' . $failed . '</div><div class="lbl"><i class="bi bi-x-circle"></i> فشل</div></div>';
    echo '</div>';

    if ($failed === 0) {
        echo '<div class="step pass"><i class="bi bi-check-circle-fill step-icon" style="color: #27ae60;"></i><div class="step-content"><strong>جميع الخطوات الست اكتملت بنجاح!</strong><br>يمكنك الآن العودة لصفحة الإعداد وطلب Production CSID.</div></div>';
        echo '<br><a href="phase2_setup_simple.php" class="btn"><i class="bi bi-arrow-right"></i> العودة لصفحة الإعداد وطلب Production CSID</a>';
    } else {
        echo '<div class="step fail"><i class="bi bi-x-circle-fill step-icon" style="color: #e74c3c;"></i><div class="step-content"><strong>' . $failed . ' من 6 خطوات فشلت.</strong><br>راجع الأخطاء أعلاه وحاول مجدداً.</div></div>';
        echo '<br><a href="?run=1" class="btn btn-warning"><i class="bi bi-arrow-clockwise"></i> إعادة المحاولة</a>';
    }
    echo '</div>';
}
?>
</div>
</body>
</html>
