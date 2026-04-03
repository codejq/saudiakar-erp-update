<?php
/**
 * Test ZATCA Environment Configuration
 * Verifies that $zatca_environment is being used correctly
 */
$sc_title = "ZATCA - اختبار البيئة";
$sc_id = "160";

include_once "../../nocash.hnt";
include_once "../../header.hnt";
include_once __DIR__ . '/../../data.hnt';
require_once __DIR__ . '/config/phase2_config.php';
require_once __DIR__ . '/phase2/integration/Phase2Manager.php';

?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4><i class="bi bi-gear-wide-connected"></i> اختبار بيئة ZATCA</h4>
        </div>
        <div class="card-body">
            <?php
            echo '<h5><i class="bi bi-info-circle"></i> البيئة الحالية</h5>';
            echo '<div class="alert alert-info">';
            echo '<strong>$zatca_environment = </strong>' . htmlspecialchars($zatca_environment ?? 'not set');
            echo '</div>';
            
            echo '<h5><i class="bi bi-folder"></i> ملفات الشهادات المتاحة</h5>';
            $certDir = ZatcaPhase2Config::CERT_DIR;
            $files = scandir($certDir);
            echo '<ul>';
            foreach ($files as $file) {
                if (strpos($file, '.pem') !== false || strpos($file, '.txt') !== false || strpos($file, '.flag') !== false) {
                    echo '<li>' . htmlspecialchars($file) . '</li>';
                }
            }
            echo '</ul>';
            
            echo '<h5><i class="bi bi-hdd-stack"></i> اختبار Phase2Manager</h5>';
            try {
                $manager = new Phase2Manager($db);
                
                // Use reflection to check which certificate file was selected
                $reflection = new ReflectionClass($manager);
                $signerProperty = $reflection->getProperty('signer');
                $signerProperty->setAccessible(true);
                $signer = $signerProperty->getValue($manager);
                
                if ($signer) {
                    echo '<div class="alert alert-success">';
                    echo '<i class="bi bi-check-circle"></i> <strong>Phase2Manager initialized successfully!</strong><br>';
                    echo 'Certificate is loaded and ready.';
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-warning">';
                    echo '<i class="bi bi-exclamation-triangle"></i> Signer not initialized (certificate files may be missing)';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">';
                echo '<i class="bi bi-x-circle"></i> <strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            
            echo '<hr>';
            echo '<a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة للرئيسية</a>';
            echo ' <a href="check_production_status.php" class="btn btn-info"><i class="bi bi-activity"></i> فحص حالة الإنتاج</a>';
            ?>
        </div>
    </div>
</div>
<?php
require('../../foter.hnt');
?>
