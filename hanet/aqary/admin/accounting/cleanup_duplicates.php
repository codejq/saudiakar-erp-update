<?php
/**
 * Cleanup Duplicate Journal Entries and FRESH Test Data
 */

$sc_title = "تنظيف البيانات المكررة";
$sc_id = "211";
include_once "../../nocash.hnt";
include_once "../../connectdb.hnt";
include_once "../../header.hnt";

$cleanupLog = [];
$stats = [
    'fresh_vouchers_found' => 0,
    'fresh_vouchers_deleted' => 0,
    'duplicate_entries_found' => 0,
    'duplicate_entries_deleted' => 0,
    'errors' => 0
];

// Handle cleanup action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cleanup_action'])) {
    $action = $_POST['cleanup_action'];

    switch ($action) {
        case 'delete_fresh_vouchers':
            // Count FRESH vouchers
            $countQuery = "SELECT COUNT(*) as count FROM sanad WHERE sanadrakam LIKE '%FRESH%'";
            $countResult = $db->query($countQuery);
            $stats['fresh_vouchers_found'] = $countResult->fetch_assoc()['count'];

            // Delete FRESH vouchers
            $deleteQuery = "DELETE FROM sanad WHERE sanadrakam LIKE '%FRESH%'";
            if ($db->query($deleteQuery)) {
                $stats['fresh_vouchers_deleted'] = $db->affected_rows;
                $cleanupLog[] = "✓ تم حذف {$stats['fresh_vouchers_deleted']} سند بعلامة FRESH";
            } else {
                $cleanupLog[] = "✗ فشل حذف سندات FRESH: " . $db->error;
                $stats['errors']++;
            }
            break;

        case 'delete_duplicate_entries':
            // Find journal entries created from FRESH vouchers
            $findQuery = "SELECT je.entry_id, je.entry_number, je.description
                         FROM acc_journal_entries je
                         WHERE je.description LIKE '%FRESH%'
                         OR je.reference_type = 'sanad'
                         AND je.reference_id IN (SELECT idsanad FROM sanad WHERE sanadrakam LIKE '%FRESH%')";

            $findResult = $db->query($findQuery);
            $stats['duplicate_entries_found'] = $findResult->num_rows;

            $entryIds = [];
            while ($row = $findResult->fetch_assoc()) {
                $entryIds[] = $row['entry_id'];
            }

            if (!empty($entryIds)) {
                $entryIdsStr = implode(',', $entryIds);

                // Delete entry lines first
                $deleteLinesQuery = "DELETE FROM acc_journal_entry_lines WHERE entry_id IN ($entryIdsStr)";
                if (!$db->query($deleteLinesQuery)) {
                    $cleanupLog[] = "✗ فشل حذف بنود القيود: " . $db->error;
                    $stats['errors']++;
                } else {
                    $cleanupLog[] = "✓ تم حذف بنود القيود المكررة";
                }

                // Delete entries
                $deleteEntriesQuery = "DELETE FROM acc_journal_entries WHERE entry_id IN ($entryIdsStr)";
                if ($db->query($deleteEntriesQuery)) {
                    $stats['duplicate_entries_deleted'] = $db->affected_rows;
                    $cleanupLog[] = "✓ تم حذف {$stats['duplicate_entries_deleted']} قيد مكرر";
                } else {
                    $cleanupLog[] = "✗ فشل حذف القيود: " . $db->error;
                    $stats['errors']++;
                }
            } else {
                $cleanupLog[] = "ℹ لا توجد قيود مكررة للحذف";
            }
            break;

        case 'reset_sync_status':
            // Reset accounting_entry_id for all FRESH vouchers
            $resetQuery = "UPDATE sanad SET accounting_entry_id = NULL WHERE sanadrakam LIKE '%FRESH%'";
            if ($db->query($resetQuery)) {
                $affected = $db->affected_rows;
                $cleanupLog[] = "✓ تم إعادة تعيين حالة المزامنة لـ $affected سند FRESH";
            } else {
                $cleanupLog[] = "✗ فشل إعادة التعيين: " . $db->error;
                $stats['errors']++;
            }
            break;

        case 'clean_all':
            // Do all cleanup in one go

            // 1. Find and delete duplicate entries
            $findQuery = "SELECT je.entry_id FROM acc_journal_entries je
                         WHERE je.description LIKE '%FRESH%'
                         OR je.entry_type = 'payment' AND je.reference_type = 'sanad'
                         AND je.reference_id IN (SELECT idsanad FROM sanad WHERE sanadrakam LIKE '%FRESH%')";

            $findResult = $db->query($findQuery);
            $entryIds = [];
            while ($row = $findResult->fetch_assoc()) {
                $entryIds[] = $row['entry_id'];
            }

            $stats['duplicate_entries_found'] = count($entryIds);

            if (!empty($entryIds)) {
                $entryIdsStr = implode(',', $entryIds);
                $db->query("DELETE FROM acc_journal_entry_lines WHERE entry_id IN ($entryIdsStr)");
                $db->query("DELETE FROM acc_journal_entries WHERE entry_id IN ($entryIdsStr)");
                $stats['duplicate_entries_deleted'] = $db->affected_rows;
                $cleanupLog[] = "✓ تم حذف {$stats['duplicate_entries_deleted']} قيد مرتبط بسندات FRESH";
            }

            // 2. Delete FRESH vouchers
            $countQuery = "SELECT COUNT(*) as count FROM sanad WHERE sanadrakam LIKE '%FRESH%'";
            $countResult = $db->query($countQuery);
            $stats['fresh_vouchers_found'] = $countResult->fetch_assoc()['count'];

            $deleteQuery = "DELETE FROM sanad WHERE sanadrakam LIKE '%FRESH%'";
            if ($db->query($deleteQuery)) {
                $stats['fresh_vouchers_deleted'] = $db->affected_rows;
                $cleanupLog[] = "✓ تم حذف {$stats['fresh_vouchers_deleted']} سند FRESH";
            }

            $cleanupLog[] = "✓ اكتمل التنظيف الشامل";
            break;
    }
}

// Get current counts
$freshCount = 0;
$result = $db->query("SELECT COUNT(*) as count FROM sanad WHERE sanadrakam LIKE '%FRESH%'");
if ($result) $freshCount = $result->fetch_assoc()['count'];

$duplicateEntriesCount = 0;
$result = $db->query("SELECT COUNT(*) as count FROM acc_journal_entries WHERE description LIKE '%FRESH%'");
if ($result) $duplicateEntriesCount = $result->fetch_assoc()['count'];

$unsyncedCount = 0;
$result = $db->query("SELECT COUNT(*) as count FROM sanad WHERE sanaddate >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AND (accounting_entry_id IS NULL OR accounting_entry_id = 0)");
if ($result) $unsyncedCount = $result->fetch_assoc()['count'];
?>

<link rel="stylesheet" href="<?= $urlh ?>/css/bootstrap-5.3.8-dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="<?= $urlh ?>/css/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
<script src="<?= $urlh ?>/css/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<style>
    :root {
        --danger-color: #dc3545;
        --warning-color: #ffc107;
    }

    .page-header {
        background: linear-gradient(135deg, var(--danger-color) 0%, var(--warning-color) 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
    }

    .cleanup-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .cleanup-button {
        border: none;
        color: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        width: 100%;
    }

    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .stats-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--danger-color);
    }
</style>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-2">
                    <i class="bi bi-trash me-2"></i>تنظيف البيانات المكررة
                </h1>
                <p class="mb-0 opacity-75">Cleanup Duplicate Data & FRESH Test Vouchers</p>
            </div>
            <div>
                <button class="btn btn-light btn-lg" onclick="window.location='sync_amlak.hnt'">
                    <i class="bi bi-arrow-right me-2"></i>العودة
                </button>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-value text-danger"><?= number_format($freshCount) ?></div>
                <div class="text-muted">سندات FRESH (بيانات تجريبية)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-value text-warning"><?= number_format($duplicateEntriesCount) ?></div>
                <div class="text-muted">قيود مكررة من FRESH</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-value text-info"><?= number_format($unsyncedCount) ?></div>
                <div class="text-muted">سندات غير مزامنة (آخر 30 يوم)</div>
            </div>
        </div>
    </div>

    <!-- Warning -->
    <?php if ($freshCount > 0 || $duplicateEntriesCount > 0): ?>
    <div class="alert alert-warning mb-4">
        <h5><i class="bi bi-exclamation-triangle me-2"></i>تحذير: بيانات تجريبية موجودة</h5>
        <p class="mb-0">
            تم العثور على <strong><?= $freshCount ?></strong> سند بعلامة "FRESH" و <strong><?= $duplicateEntriesCount ?></strong> قيد مكرر.
            هذه البيانات تسبب مشاكل في المزامنة ويجب حذفها.
        </p>
    </div>
    <?php endif; ?>

    <!-- Cleanup Actions -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="cleanup-card">
                <h5 class="mb-3">
                    <i class="bi bi-file-earmark-x me-2"></i>حذف سندات FRESH
                </h5>
                <p class="text-muted">حذف جميع سندات القبض التي تحتوي على "FRESH" في رقم السند (بيانات تجريبية)</p>
                <form method="POST" id="deleteFreshForm">
                    <input type="hidden" name="cleanup_action" value="delete_fresh_vouchers">
                    <button type="button" class="cleanup-button bg-danger" onclick="confirmDeleteFresh()">
                        <i class="bi bi-trash me-2"></i>حذف <?= $freshCount ?> سند FRESH
                    </button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="cleanup-card">
                <h5 class="mb-3">
                    <i class="bi bi-journal-x me-2"></i>حذف القيود المكررة
                </h5>
                <p class="text-muted">حذف القيود المحاسبية المرتبطة بسندات FRESH</p>
                <form method="POST" id="deleteDuplicatesForm">
                    <input type="hidden" name="cleanup_action" value="delete_duplicate_entries">
                    <button type="button" class="cleanup-button bg-warning text-dark" onclick="confirmDeleteDuplicates()">
                        <i class="bi bi-trash me-2"></i>حذف <?= $duplicateEntriesCount ?> قيد مكرر
                    </button>
                </form>
            </div>
        </div>

        <div class="col-md-12">
            <div class="cleanup-card border border-danger">
                <h5 class="mb-3 text-danger">
                    <i class="bi bi-exclamation-octagon me-2"></i>تنظيف شامل (حذف كل شيء)
                </h5>
                <p class="text-muted">
                    حذف جميع سندات FRESH والقيود المرتبطة بها في عملية واحدة.
                    <strong>تحذير: هذا الإجراء لا يمكن التراجع عنه!</strong>
                </p>
                <form method="POST" id="cleanAllForm">
                    <input type="hidden" name="cleanup_action" value="clean_all">
                    <button type="button" class="cleanup-button bg-danger" onclick="confirmCleanAll()">
                        <i class="bi bi-trash-fill me-2"></i>تنظيف شامل - حذف كل البيانات التجريبية
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Cleanup Log -->
    <?php if (!empty($cleanupLog)): ?>
    <div class="cleanup-card">
        <h5 class="mb-3">
            <i class="bi bi-list-check me-2"></i>نتائج التنظيف
        </h5>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="alert alert-danger mb-0">
                    <strong><?= $stats['fresh_vouchers_deleted'] ?></strong> سند FRESH محذوف
                </div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-warning mb-0">
                    <strong><?= $stats['duplicate_entries_deleted'] ?></strong> قيد مكرر محذوف
                </div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-info mb-0">
                    <strong><?= $stats['fresh_vouchers_found'] ?></strong> سند تم العثور عليه
                </div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-secondary mb-0">
                    <strong><?= $stats['errors'] ?></strong> أخطاء
                </div>
            </div>
        </div>

        <div style="max-height: 300px; overflow-y: auto;">
            <?php foreach ($cleanupLog as $log): ?>
                <div class="alert <?= strpos($log, '✓') !== false ? 'alert-success' : 'alert-danger' ?> mb-2">
                    <?= $log ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmDeleteFresh() {
    confirm('هل أنت متأكد من حذف جميع سندات FRESH؟\n\nسيتم حذف <?= $freshCount ?> سند قبض بعلامة FRESH من قاعدة البيانات.', 'تأكيد الحذف', function() {
        // User confirmed - submit the form
        document.getElementById('deleteFreshForm').submit();
    }, function() {
        // User cancelled - do nothing
    });
}

function confirmDeleteDuplicates() {
    confirm('هل أنت متأكد من حذف القيود المكررة؟\n\nسيتم حذف <?= $duplicateEntriesCount ?> قيد محاسبي مرتبط بسندات FRESH.', 'تأكيد الحذف', function() {
        // User confirmed - submit the form
        document.getElementById('deleteDuplicatesForm').submit();
    }, function() {
        // User cancelled - do nothing
    });
}

function confirmCleanAll() {
    confirm('⚠️ تحذير: تنظيف شامل!\n\nهذا سيحذف:\n• جميع سندات FRESH (<?= $freshCount ?> سند)\n• جميع القيود المرتبطة بها (<?= $duplicateEntriesCount ?> قيد)\n\nهذا الإجراء لا يمكن التراجع عنه!\n\nهل أنت متأكد تماماً؟', 'تأكيد التنظيف الشامل', function() {
        // User confirmed - submit the form
        document.getElementById('cleanAllForm').submit();
    }, function() {
        // User cancelled - do nothing
    });
}
</script>

<?php require("../../foter.hnt"); ?>
