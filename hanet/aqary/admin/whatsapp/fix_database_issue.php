<?php
/**
 * Database Diagnostic and Fix Tool
 * Fixes the "doesn't exist in engine" error
 */

// Include database connection
require_once('../../connectdb.hnt');

echo "<html dir='rtl'><head><meta charset='utf-8'><title>إصلاح قاعدة البيانات</title>";
echo "<style>body{font-family:Arial;padding:20px;direction:rtl}";
echo ".success{color:green;padding:10px;background:#e0ffe0;margin:10px 0}";
echo ".error{color:red;padding:10px;background:#ffe0e0;margin:10px 0}";
echo ".info{color:blue;padding:10px;background:#e0e0ff;margin:10px 0}";
echo "table{border-collapse:collapse;width:100%;margin:20px 0}";
echo "th,td{border:1px solid #ccc;padding:8px;text-align:right}";
echo "th{background:#f0f0f0}</style></head><body>";

echo "<h1>أداة تشخيص وإصلاح قاعدة البيانات</h1>";

// Step 1: Check database connection
echo "<h2>1. فحص الاتصال بقاعدة البيانات</h2>";
if (!$db) {
    echo "<div class='error'>فشل الاتصال بقاعدة البيانات</div>";
    exit;
}
echo "<div class='success'>تم الاتصال بنجاح</div>";

// Step 2: Check MariaDB version and storage engines
echo "<h2>2. فحص إصدار MariaDB ومحركات التخزين</h2>";
$result = $db->query("SELECT VERSION() as version");
$row = $result->fetch_assoc();
echo "<div class='info'>إصدار قاعدة البيانات: " . $row['version'] . "</div>";

$result = $db->query("SHOW ENGINES");
echo "<table><tr><th>محرك</th><th>الدعم</th><th>الوصف</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['Engine']}</td><td>{$row['Support']}</td><td>{$row['Comment']}</td></tr>";
}
echo "</table>";

// Step 3: Check if tables exist
echo "<h2>3. فحص جداول WhatsApp</h2>";
$result = $db->query("SHOW TABLES LIKE 'whatsapp_%'");
if ($result->num_rows == 0) {
    echo "<div class='error'>لا توجد جداول! يجب تثبيت الجداول أولاً</div>";
    echo "<div class='info'><a href='database_install.php'>انقر هنا لتثبيت الجداول</a></div>";
} else {
    echo "<div class='success'>عدد الجداول: " . $result->num_rows . "</div>";

    // Step 4: Check table details
    echo "<h2>4. تفاصيل الجداول</h2>";
    $tables = [];
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    echo "<table><tr><th>الجدول</th><th>المحرك</th><th>الترميز</th><th>الحالة</th></tr>";
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLE STATUS LIKE '$table'");
        if ($row = $result->fetch_assoc()) {
            $engine = $row['Engine'];
            $collation = $row['Collation'];
            $status = $engine ? "<span style='color:green'>OK</span>" : "<span style='color:red'>خطأ</span>";
            echo "<tr><td>$table</td><td>$engine</td><td>$collation</td><td>$status</td></tr>";
        }
    }
    echo "</table>";
}

// Step 5: Test query on whatsapp_settings
echo "<h2>5. اختبار الاستعلام عن whatsapp_settings</h2>";
$testQuery = "SELECT * FROM whatsapp_settings LIMIT 1";
$result = $db->query($testQuery);
if ($result === false) {
    echo "<div class='error'>فشل الاستعلام: " . $db->error . "</div>";
    echo "<div class='info'><strong>السبب المحتمل:</strong> الجدول موجود في القائمة ولكن محرك التخزين لا يعمل</div>";

    // Try to repair table
    echo "<h3>محاولة إصلاح الجدول...</h3>";
    $repairResult = $db->query("REPAIR TABLE whatsapp_settings");
    if ($repairResult) {
        echo "<div class='success'>تم محاولة إصلاح الجدول</div>";
    }

} else {
    echo "<div class='success'>نجح الاستعلام! عدد السجلات: " . $result->num_rows . "</div>";
    if ($row = $result->fetch_assoc()) {
        echo "<div class='info'>أحد الإعدادات: " . $row['setting_key'] . " = " . $row['setting_value'] . "</div>";
    }
}

// Step 6: Fix button
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fix'])) {
    echo "<h2>6. إصلاح المشاكل</h2>";

    // Drop and recreate tables
    echo "<div class='info'>حذف وإعادة إنشاء الجداول...</div>";

    $dropTables = [
        'whatsapp_contacts',
        'whatsapp_sent_log',
        'whatsapp_service_log',
        'whatsapp_templates',
        'whatsapp_queue',
        'whatsapp_settings'
    ];

    foreach ($dropTables as $table) {
        $db->query("DROP TABLE IF EXISTS $table");
    }

    // Read and execute schema
    $sqlFile = __DIR__ . '/database_schema.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);

        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Split statements and execute
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || stripos($statement, 'DELIMITER') === 0) {
                continue;
            }

            if (!$db->query($statement)) {
                echo "<div class='error'>خطأ: " . $db->error . "</div>";
            }
        }

        echo "<div class='success'>تم إعادة إنشاء الجداول بنجاح!</div>";
        echo "<div class='info'><a href='fix_database_issue.php'>إعادة فحص النظام</a> | <a href='index.hnt'>الذهاب إلى لوحة التحكم</a></div>";
    }

} else {
    echo "<h2>6. إصلاح المشكلة</h2>";
    echo "<form method='post'>";
    echo "<p><strong>تنبيه:</strong> سيتم حذف جميع الجداول وإعادة إنشائها</p>";
    echo "<button type='submit' name='fix' style='padding:10px 20px;font-size:16px;background:#f00;color:#fff;border:none;cursor:pointer'>إصلاح قاعدة البيانات</button>";
    echo "</form>";
}

echo "</body></html>";
?>
