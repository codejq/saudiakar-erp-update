<?php
/**
 * System Settings & Options
 * Refactored version with Bootstrap 5
 */

$sc_title = "الزكاة والدخل";
$sc_id = "150";

include_once "../../nocash.hnt";
include_once "../../header.hnt";







// Define all options in a flat array
$options = [
    [
        'title' => 'إعداد الشهادات (بسيط)',
        'icon' => 'bi-shield-check',
        'url' => 'phase2_setup_simple.php',
        'policy' => ''
    ],
    [
        'title' => 'إعداد الشهادات (متقدم)',
        'icon' => 'bi-shield-lock',
        'url' => 'phase2_setup.php',
        'policy' => ''
    ],
    [
        'title' => 'اختبار الفواتير',
        'icon' => 'bi-file-earmark-text',
        'url' => 'phase2_test.php',
        'policy' => ''
    ],
    [
        'title' => 'اختبار المكونات',
        'icon' => 'bi-tools',
        'url' => 'phase2_setup.php?test_components=1',
        'policy' => 'policy_158_sees'
    ],
    [
        'title' => 'خطوات الامتثال الست',
        'icon' => 'bi-patch-check-fill',
        'url' => 'zatca_compliance_steps.php',
        'policy' => ''
    ],
    [
        'title' => 'طلب CSID إنتاجي',
        'icon' => 'bi-key-fill',
        'url' => 'phase2_setup_simple.php',
        'policy' => ''
    ],
    [
        'title' => 'فحص حالة الإنتاج',
        'icon' => 'bi-activity',
        'url' => 'check_production_status.php',
        'policy' => ''
    ],
    [
        'title' => 'اختبار البيئة',
        'icon' => 'bi-gear-wide-connected',
        'url' => 'test_environment.php',
        'policy' => ''
    ]
];
?>

<style>
.icon-card {
    min-height: 150px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.icon-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.icon-card a {
    text-decoration: none;
    color: #333;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    padding: 1rem;
}

.icon-card .icon48 {
    margin-bottom: 0.5rem;
}

/* Icon colors for different option types - ZATCA theme */
.option-logo .icon48 { color: #059669; }
.option-user .icon48 { color: #047857; }
.option-notification .icon48 { color: #0891b2; }
.option-backup .icon48 { color: #0e7490; }
.option-permissions .icon48 { color: #8b5cf6; }
.option-reports .icon48 { color: #29a0f0ff; }
.option-location .icon48 { color: #10b981; }
.option-datetime .icon48 { color: #06b6d4; }
.option-appearance .icon48 { color: #ec4899; }
.option-favorite .icon48 { color: #fbbf24; }
.option-vouchers .icon48 { color: #14b8a6; }
.option-departments .icon48 { color: #3b82f6; }
.option-search .icon48 { color: #6366f1; }
.option-designer .icon48 { color: #a855f7; }
.option-templates .icon48 { color: #22c55e; }
.option-word .icon48 { color: #2563eb; }
.option-contracts .icon48 { color: #f97316; }
.option-database .icon48 { color: #0891b2; }
.option-numbering .icon48 { color: #64748b; }
.option-branches .icon48 { color: #0e7490; }
.option-logs .icon48 { color: #7c3aed; }
.option-maintenance .icon48 { color: #dc3545; }
</style>

<!-- Main Content -->
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #189c2aff 0%, #066d96ff 100%);">
                    <h4 class="mb-0">
                        <i class="bi bi-gear-wide-connected me-2"></i>
                        <?= $sc_title ?>
                    </h4>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                       الربط مع الزكاة والدخل
                    </p>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <a href="../option.hnt" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-right"></i> العودة للخيارات الرئيسية
                </a>
            </div>

            <!-- Options Grid -->
            <div class="row g-3">
                <?php
                $option_classes = [
                    'option-logo', 'option-user', 'option-notification', 'option-backup',
                    'option-permissions', 'option-reports', 'option-location', 'option-datetime',
                    'option-appearance', 'option-favorite', 'option-vouchers', 'option-departments',
                    'option-search', 'option-designer', 'option-templates', 'option-word',
                    'option-contracts', 'option-database', 'option-numbering', 'option-branches',
                    'option-logs', 'option-maintenance'
                ];

                foreach ($options as $index => $option):
                    $class = $option_classes[$index] ?? 'option-database';
                ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2 <?= $option['policy'] ?>">
                    <div class="card icon-card text-center h-100 <?= $class ?>" style="background: white;">
                        <a href="<?= $option['url'] ?>" class="uifont">
                            <div class="icon48">
                                <i class="<?= $option['icon'] ?>" style="font-size: 48px;"></i>
                            </div>
                            <span style="font-weight: 600;"><?= $option['title'] ?></span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
require('../../foter.hnt');
?>
