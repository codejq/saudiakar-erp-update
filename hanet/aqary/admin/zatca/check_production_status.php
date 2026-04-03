<?php
/**
 * Check ZATCA Production Status
 * Diagnose production certificate and API credentials
 */
$sc_title = "ZATCA - حالة الإنتاج";
$sc_id = "159";

include_once "../../nocash.hnt";
include_once "../../header.hnt";

// Include data.hnt to get $zatca_environment global variable
include_once __DIR__ . '/../../data.hnt';

require_once __DIR__ . '/config/phase2_config.php';
require_once __DIR__ . '/phase2/api/ZatcaAPIClient.php';

$certDir = ZatcaPhase2Config::CERT_DIR;

// Display current environment
echo '<div class="alert alert-info m-3">';
echo '<i class="bi bi-info-circle"></i> <strong>Current Environment:</strong> ' . strtoupper($zatca_environment ?? 'not set');
echo '</div>';
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h4><i class="bi bi-activity"></i> فحص حالة الإنتاج - ZATCA</h4>
        </div>
        <div class="card-body">
            <?php
            // Check certificate files
            echo '<h5><i class="bi bi-file-earmark"></i> ملفات الشهادات</h5>';
            $files = [
                'Production Certificate' => 'certificate.pem',
                'Production Secret' => 'secret.txt',
                'Production Flag' => 'production_mode.flag',
                'Simulation Certificate' => 'simulation_certificate.pem',
                'Simulation Secret' => 'simulation_secret.txt',
            ];

            echo '<table class="table table-sm">';
            foreach ($files as $name => $file) {
                $path = $certDir . '/' . $file;
                $exists = file_exists($path);
                $size = $exists ? filesize($path) : 0;
                echo '<tr>';
                echo '<td>' . $name . '</td>';
                echo '<td>' . ($exists ? '<span class="badge bg-success">موجود</span>' : '<span class="badge bg-danger">مفقود</span>') . '</td>';
                echo '<td>' . ($exists ? $size . ' bytes' : '-') . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            // Check production mode
            echo '<h5><i class="bi bi-gear"></i> وضع الإنتاج</h5>';
            $productionFlag = file_exists($certDir . '/production_mode.flag');
            echo '<div class="alert ' . ($productionFlag ? 'alert-success' : 'alert-warning') . '">';
            echo $productionFlag
                ? '<i class="bi bi-check-circle"></i> وضع الإنتاج مفعل (production_mode.flag موجود)'
                : '<i class="bi bi-exclamation-triangle"></i> وضع المحاكاة مفعل (production_mode.flag غير موجود)';
            echo '</div>';

            // Load and test credentials
            echo '<h5><i class="bi bi-shield-lock"></i> اختبار بيانات الاعتماد</h5>';

            $prodCertFile = $certDir . '/certificate.pem';
            $prodSecretFile = $certDir . '/secret.txt';

            if (file_exists($prodCertFile) && file_exists($prodSecretFile)) {
                $certContent = file_get_contents($prodCertFile);
                $secret = trim(file_get_contents($prodSecretFile));

                // Extract certificate without headers
                $certBody = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r", ' '], '', $certContent);

                echo '<div class="alert alert-info">';
                echo '<strong>Production Certificate:</strong><br>';
                echo '<small>Starts with: ' . substr($certBody, 0, 20) . '...</small><br>';
                echo '<small>Length: ' . strlen($certBody) . ' chars</small>';
                echo '</div>';

                echo '<div class="alert alert-info">';
                echo '<strong>Production Secret:</strong><br>';
                echo '<small>Length: ' . strlen($secret) . ' chars</small><br>';
                echo '<small>Starts with: ' . substr($secret, 0, 10) . '...</small>';
                echo '</div>';

                // Test API connection
                echo '<h5><i class="bi bi-cloud"></i> اختبار الاتصال بـ ZATCA API</h5>';

                $apiClient = new ZatcaAPIClient();
                $apiClient->setCredentials($certBody, $secret);

                // Try a simple compliance check (will fail but shows if credentials are valid)
                $testHash = base64_encode(hash('sha256', 'test', true));
                $testUuid = '00000000-0000-0000-0000-000000000000';
                $testXml = '<Invoice></Invoice>';

                echo '<p>جاري الاتصال بـ ZATCA API...</p>';

                try {
                    $result = $apiClient->validateCompliance($testHash, $testUuid, $testXml);

                    echo '<div class="alert ' . ($result['success'] ? 'alert-success' : 'alert-warning') . '">';
                    echo '<strong>نتيجة الاتصال:</strong><br>';
                    echo 'HTTP Code: ' . ($result['http_code'] ?? 'N/A') . '<br>';
                    echo 'Success: ' . ($result['success'] ? 'Yes' : 'No') . '<br>';

                    if (isset($result['error'])) {
                        echo 'Error: ' . htmlspecialchars($result['error']);

                        if (strpos($result['error'], 'Unauthorized') !== false) {
                            echo '<br><br><strong class="text-danger">⚠ تحذير:</strong> بيانات الاعتماد غير صالحة أو منتهية الصلاحية';
                            echo '<br>الأسباب المحتملة:';
                            echo '<ul>';
                            echo '<li>لم يتم تفعيل CSID الإنتاجي بعد (انتظر 24-48 ساعة)</li>';
                            echo '<li>الـ Secret غير صحيح</li>';
                            echo '<li>الشهادة الإنتاجية غير مفعلة من ZATCA</li>';
                            echo '</ul>';
                        }
                    }
                    echo '</div>';

                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">';
                    echo '<strong>خطأ في الاتصال:</strong> ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }

            } else {
                echo '<div class="alert alert-danger">';
                echo '<i class="bi bi-x-circle"></i> ملفات الاعتماد الإنتاجية غير موجودة';
                echo '</div>';
            }

            // Recommendations
            echo '<h5><i class="bi bi-lightbulb"></i> التوصيات</h5>';
            echo '<div class="alert ' . ($productionFlag ? 'alert-warning' : 'alert-info') . '">';

            if ($productionFlag) {
                echo '<strong>⚠ النظام في وضع الإنتاج:</strong><br>';
                echo 'لل-development/testing، يمكنك:<br>';
                echo '1. حذف ملف <code>production_mode.flag</code> للعودة لوضع المحاكاة<br>';
                echo '2. أو استخدام <code>forceSimulation=true</code> في الكود<br>';
                echo '3. تأكد من تفعيل CSID الإنتاجي من بوابة ZATCA<br>';
                echo '4. قد يستغرق التفعيل 24-48 ساعة';
            } else {
                echo '<strong>✓ النظام في وضع المحاكاة:</strong><br>';
                echo 'يمكنك استخدام الفواتير الاختبارية للتجربة';
            }
            echo '</div>';

            // Back button
            echo '<hr>';
            echo '<a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة للرئيسية</a>';
            echo ' <a href="phase2_setup_simple.php" class="btn btn-primary"><i class="bi bi-gear"></i> إعدادات ZATCA</a>';
            ?>
        </div>
    </div>
</div>
<?php
require('../../foter.hnt');
?>
