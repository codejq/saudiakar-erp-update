<?php
/**
 * Fix SSL Certificate Issue
 *
 * Downloads cacert.pem (CA bundle) for cURL SSL verification
 *
 * @charset UTF-8
 */

require_once './config/phase2_config.php';

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إصلاح مشكلة SSL Certificate</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #e74c3c;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-right: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-right: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-right: 4px solid #17a2b8;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-right: 4px solid #ffc107;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #c0392b;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        .code {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            overflow-x: auto;
            margin: 10px 0;
        }
        .step {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            border-right: 4px solid #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 إصلاح مشكلة SSL Certificate</h1>

        <div class="error">
            <strong>❌ المشكلة:</strong><br>
            cURL Error (60): SSL certificate problem: self signed certificate in certificate chain
        </div>

        <div class="info">
            <strong>📋 الشرح:</strong><br>
            هذا الخطأ يحدث عندما لا يستطيع PHP التحقق من صحة شهادة SSL الخاصة بـ ZATCA.<br>
            الحل هو تحميل ملف CA Bundle (cacert.pem) الذي يحتوي على شهادات الجهات المعتمدة.
        </div>

        <?php
        if (isset($_GET['download_cacert'])) {
            $certDir = ZatcaPhase2Config::CERT_DIR;
            $cacertPath = $certDir . '/cacert.pem';

            try {
                echo '<div class="info"><strong>جاري تحميل cacert.pem...</strong></div>';

                // Download cacert.pem from curl.se
                $cacertUrl = 'https://curl.se/ca/cacert.pem';
                $cacertContent = @file_get_contents($cacertUrl);

                if ($cacertContent === false) {
                    // Try alternative source
                    $cacertUrl = 'https://raw.githubusercontent.com/bagder/ca-bundle/master/ca-bundle.crt';
                    $cacertContent = @file_get_contents($cacertUrl);
                }

                if ($cacertContent === false) {
                    throw new Exception('فشل تحميل ملف cacert.pem. تحقق من اتصال الإنترنت.');
                }

                // Save cacert.pem
                if (file_put_contents($cacertPath, $cacertContent) === false) {
                    throw new Exception('فشل حفظ ملف cacert.pem');
                }

                chmod($cacertPath, 0644);
                ZatcaPhase2Config::log("CA Bundle downloaded: $cacertPath", 'INFO');

                echo '<div class="success">';
                echo '<strong>✅ تم تحميل cacert.pem بنجاح!</strong><br><br>';
                echo 'المسار: ' . htmlspecialchars($cacertPath) . '<br>';
                echo 'الحجم: ' . number_format(strlen($cacertContent)) . ' bytes<br>';
                echo '</div>';

                echo '<div class="step">';
                echo '<h3>🎯 الخطوات التالية:</h3>';
                echo '<ol style="text-align: right; line-height: 2;">';
                echo '<li>ارجع إلى صفحة الإعداد</li>';
                echo '<li>حاول الحصول على الشهادة مرة أخرى</li>';
                echo '<li>يجب أن تعمل الآن بدون أخطاء SSL</li>';
                echo '</ol>';
                echo '<a href="phase2_setup_simple.php" class="btn btn-success">العودة للإعداد</a>';
                echo '</div>';

            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<strong>❌ فشل التحميل:</strong><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';

                echo '<div class="warning">';
                echo '<strong>⚠️ حل بديل:</strong><br>';
                echo 'يمكنك تحميل الملف يدوياً وحفظه في:<br>';
                echo '<code>' . htmlspecialchars($cacertPath) . '</code>';
                echo '</div>';
            }
        }
        ?>

        <div class="step">
            <h3>🔧 الحل الأول: تحميل CA Bundle تلقائياً (مستحسن)</h3>
            <p>سيقوم النظام بتحميل ملف cacert.pem من مصدر موثوق وحفظه في مجلد الشهادات.</p>
            <a href="?download_cacert=1" class="btn btn-success">📥 تحميل cacert.pem الآن</a>
        </div>

        <div class="step">
            <h3>🔧 الحل الثاني: تحميل يدوي</h3>
            <ol style="text-align: right; line-height: 1.8;">
                <li>افتح: <a href="https://curl.se/ca/cacert.pem" target="_blank">https://curl.se/ca/cacert.pem</a></li>
                <li>احفظ الملف باسم <code>cacert.pem</code></li>
                <li>ضعه في مجلد: <code><?php echo htmlspecialchars(ZatcaPhase2Config::CERT_DIR); ?></code></li>
            </ol>
        </div>

        <div class="step">
            <h3>🔧 الحل الثالث: تعطيل التحقق من SSL (للتجربة فقط)</h3>
            <p>أضف هذا السطر في ملف <code>config/phase2_config.php</code>:</p>
            <div class="code">
define('ZATCA_DISABLE_SSL_VERIFY', true);
            </div>
            <div class="warning">
                <strong>⚠️ تحذير:</strong><br>
                هذا الحل غير آمن ويجب استخدامه فقط للتجربة في بيئة Sandbox.<br>
                لا تستخدمه في بيئة الإنتاج (Production).
            </div>
        </div>

        <div class="step">
            <h3>🔧 الحل الرابع: تكوين php.ini</h3>
            <p>أضف هذه الأسطر في ملف <code>php.ini</code>:</p>
            <div class="code">
curl.cainfo = "<?php echo str_replace('\\', '/', ZatcaPhase2Config::CERT_DIR); ?>/cacert.pem"
openssl.cafile = "<?php echo str_replace('\\', '/', ZatcaPhase2Config::CERT_DIR); ?>/cacert.pem"
            </div>
            <p>ثم أعد تشغيل Apache/PHP.</p>
        </div>

        <div class="info">
            <strong>ℹ️ ملاحظة:</strong><br>
            بعد تطبيق أي من الحلول أعلاه، النظام سيتمكن من الاتصال بـ ZATCA API بدون أخطاء SSL.
        </div>

        <div style="margin-top: 20px;">
            <a href="phase2_setup_simple.php" class="btn">← العودة للإعداد</a>
            <a href="phase2_setup.php?test_components=1" class="btn">اختبار المكونات</a>
        </div>
    </div>
</body>
</html>
