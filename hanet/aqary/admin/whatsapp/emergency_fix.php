<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="utf-8">
<title>إصلاح طارئ - Emergency Fix</title>
<style>
body { font-family: Arial; padding: 20px; direction: rtl; background: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
.success { color: green; padding: 15px; background: #e0ffe0; margin: 10px 0; border-radius: 5px; border-right: 4px solid green; }
.error { color: red; padding: 15px; background: #ffe0e0; margin: 10px 0; border-radius: 5px; border-right: 4px solid red; }
.info { color: blue; padding: 15px; background: #e0e0ff; margin: 10px 0; border-radius: 5px; border-right: 4px solid blue; }
.warning { color: #856404; padding: 15px; background: #fff3cd; margin: 10px 0; border-radius: 5px; border-right: 4px solid #856404; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: right; }
th { background: #f0f0f0; font-weight: bold; }
.btn { padding: 12px 25px; font-size: 16px; border: none; cursor: pointer; border-radius: 5px; margin: 5px; text-decoration: none; display: inline-block; }
.btn-danger { background: #dc3545; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-primary { background: #007bff; color: white; }
h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; }
code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
</style>
</head>
<body>
<div class="container">
<h1>🔧 أداة إصلاح WhatsApp الطارئة</h1>

<?php
// Database connection details - EDIT THESE!
$dbHost = 'localhost';
$dbPort = '3329';
$dbUser = 'root';
$dbPass = '1';
$dbName = 'aqary_utf';

echo "<div class='info'><strong>إعدادات قاعدة البيانات:</strong><br>";
echo "Host: $dbHost:$dbPort<br>";
echo "Database: $dbName<br>";
echo "User: $dbUser</div>";

// Connect to database
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($mysqli->connect_error) {
    die("<div class='error'><strong>فشل الاتصال:</strong> " . $mysqli->connect_error . "</div>");
}

echo "<div class='success'>✅ تم الاتصال بقاعدة البيانات بنجاح</div>";

// Set charset
$mysqli->set_charset('utf8mb4');

// ==========================================
// DIAGNOSTIC SECTION
// ==========================================

echo "<h2>📊 1. تشخيص النظام</h2>";

// Check MariaDB version
$result = $mysqli->query("SELECT VERSION() as version");
$row = $result->fetch_assoc();
echo "<div class='info'><strong>إصدار MariaDB:</strong> " . $row['version'] . "</div>";

// Check storage engines
echo "<h3>محركات التخزين المتاحة:</h3>";
$result = $mysqli->query("SHOW ENGINES");
echo "<table><tr><th>المحرك</th><th>الحالة</th><th>الوصف</th></tr>";
$engines = [];
while ($row = $result->fetch_assoc()) {
    $engines[$row['Engine']] = $row['Support'];
    $color = ($row['Support'] == 'DEFAULT' || $row['Support'] == 'YES') ? 'green' : 'gray';
    echo "<tr><td>{$row['Engine']}</td><td style='color:$color'><strong>{$row['Support']}</strong></td><td>{$row['Comment']}</td></tr>";
}
echo "</table>";

// Check if InnoDB is available
$innodbAvailable = (isset($engines['InnoDB']) && ($engines['InnoDB'] == 'DEFAULT' || $engines['InnoDB'] == 'YES'));
if ($innodbAvailable) {
    echo "<div class='success'>✅ InnoDB متاح ويعمل بشكل صحيح</div>";
} else {
    echo "<div class='error'>❌ InnoDB غير متاح! سنستخدم MyISAM بدلاً منه</div>";
}

// Check existing WhatsApp tables
echo "<h3>الجداول الموجودة:</h3>";
$result = $mysqli->query("SHOW TABLES LIKE 'whatsapp_%'");
$existingTables = [];
while ($row = $result->fetch_row()) {
    $existingTables[] = $row[0];
}

if (count($existingTables) > 0) {
    echo "<div class='warning'>⚠️ توجد " . count($existingTables) . " جداول</div>";

    // Get table details
    echo "<table><tr><th>اسم الجدول</th><th>المحرك</th><th>الترميز</th><th>الحالة</th><th>اختبار</th></tr>";
    foreach ($existingTables as $table) {
        $result = $mysqli->query("SHOW TABLE STATUS LIKE '$table'");
        if ($row = $result->fetch_assoc()) {
            $engine = $row['Engine'] ? $row['Engine'] : '<span style="color:red">NULL</span>';
            $collation = $row['Collation'] ? $row['Collation'] : '<span style="color:red">NULL</span>';

            // Test if table is accessible
            $testResult = $mysqli->query("SELECT COUNT(*) as cnt FROM $table");
            if ($testResult) {
                $testRow = $testResult->fetch_assoc();
                $status = "<span style='color:green'>✅ يعمل (" . $testRow['cnt'] . " سجل)</span>";
            } else {
                $status = "<span style='color:red'>❌ لا يعمل: " . $mysqli->error . "</span>";
            }

            echo "<tr><td>$table</td><td>$engine</td><td>$collation</td><td>$status</td><td>$status</td></tr>";
        }
    }
    echo "</table>";
} else {
    echo "<div class='info'>ℹ️ لا توجد جداول - جاهز للتثبيت</div>";
}

// ==========================================
// FIX SECTION
// ==========================================

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'drop_all') {
        echo "<h2>🗑️ 2. حذف جميع الجداول</h2>";

        // Drop in correct order
        $dropOrder = [
            'v_message_stats',
            'v_recent_messages',
            'sp_update_message_status',
            'sp_get_pending_messages',
            'sp_clean_old_logs',
            'tr_template_usage',
            'whatsapp_contacts',
            'whatsapp_sent_log',
            'whatsapp_service_log',
            'whatsapp_templates',
            'whatsapp_queue',
            'whatsapp_settings'
        ];

        foreach ($dropOrder as $item) {
            if (strpos($item, 'v_') === 0) {
                $sql = "DROP VIEW IF EXISTS $item";
            } elseif (strpos($item, 'sp_') === 0) {
                $sql = "DROP PROCEDURE IF EXISTS $item";
            } elseif (strpos($item, 'tr_') === 0) {
                $sql = "DROP TRIGGER IF EXISTS $item";
            } else {
                $sql = "DROP TABLE IF EXISTS $item";
            }

            if ($mysqli->query($sql)) {
                echo "<div class='success'>✅ حذف: $item</div>";
            } else {
                echo "<div class='warning'>⚠️ $item: " . $mysqli->error . "</div>";
            }
        }

        echo "<div class='success'><strong>✅ تم حذف جميع العناصر</strong></div>";
        echo "<div class='info'><a href='emergency_fix.php' class='btn btn-primary'>إعادة فحص</a></div>";

    } elseif ($action == 'create_all') {
        echo "<h2>🔨 3. إنشاء الجداول</h2>";

        // Force MyISAM (InnoDB has issues)
        $engine = 'MyISAM';
        echo "<div class='warning'>⚠️ المحرك المستخدم: <strong>$engine</strong> (تبعاً لطلبك)</div>";

        // Create whatsapp_settings
        $sql = "CREATE TABLE IF NOT EXISTS whatsapp_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            description VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        ) ENGINE=$engine DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($mysqli->query($sql)) {
            echo "<div class='success'>✅ تم إنشاء whatsapp_settings</div>";

            // Insert default settings
            $defaults = [
                "('service_url', 'http://localhost:8010', 'WhatsApp service URL')",
                "('service_status', '0', 'Service status')",
                "('account_connected', '0', 'Account connection')",
                "('account_phone', '', 'Phone number')",
                "('account_name', '', 'Account name')",
                "('max_file_size', '25', 'Max file size MB')",
                "('messages_per_minute', '20', 'Messages per minute')",
                "('auto_retry', '1', 'Auto retry')",
                "('max_retries', '3', 'Max retries')",
                "('log_retention_days', '90', 'Log retention')",
                "('cron_enabled', '1', 'Cron enabled')",
                "('last_cron_run', '', 'Last cron run')"
            ];

            $sql = "INSERT IGNORE INTO whatsapp_settings (setting_key, setting_value, description) VALUES " . implode(',', $defaults);
            $mysqli->query($sql);
            echo "<div class='success'>✅ تم إضافة الإعدادات الافتراضية</div>";
        } else {
            echo "<div class='error'>❌ فشل: " . $mysqli->error . "</div>";
        }

        // Create whatsapp_queue
        $sql = "CREATE TABLE IF NOT EXISTS whatsapp_queue (
            id INT PRIMARY KEY AUTO_INCREMENT,
            recipient VARCHAR(50) NOT NULL,
            recipient_name VARCHAR(255),
            message_text TEXT,
            attachment_path VARCHAR(500),
            attachment_type ENUM('image', 'video', 'document', 'audio', 'contact', 'location'),
            attachment_size INT,
            scheduled_time DATETIME,
            status ENUM('pending', 'sending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
            priority INT DEFAULT 5,
            retry_count INT DEFAULT 0,
            max_retries INT DEFAULT 3,
            error_message TEXT,
            error_code VARCHAR(50),
            sent_at DATETIME,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            metadata TEXT,
            INDEX idx_status (status),
            INDEX idx_scheduled_time (scheduled_time),
            INDEX idx_recipient (recipient)
        ) ENGINE=$engine DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($mysqli->query($sql)) {
            echo "<div class='success'>✅ تم إنشاء whatsapp_queue</div>";
        } else {
            echo "<div class='error'>❌ فشل: " . $mysqli->error . "</div>";
        }

        // Create whatsapp_sent_log (without foreign key if MyISAM)
        $foreignKey = $engine == 'InnoDB' ? ", FOREIGN KEY (queue_id) REFERENCES whatsapp_queue(id) ON DELETE SET NULL" : "";
        $sql = "CREATE TABLE IF NOT EXISTS whatsapp_sent_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            queue_id INT,
            recipient VARCHAR(50) NOT NULL,
            recipient_name VARCHAR(255),
            message_text TEXT,
            attachment_info TEXT,
            response_data TEXT,
            message_id VARCHAR(255),
            status ENUM('sent', 'delivered', 'read', 'failed') DEFAULT 'sent',
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_by INT,
            INDEX idx_queue_id (queue_id),
            INDEX idx_sent_at (sent_at)
            $foreignKey
        ) ENGINE=$engine DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($mysqli->query($sql)) {
            echo "<div class='success'>✅ تم إنشاء whatsapp_sent_log</div>";
        } else {
            echo "<div class='error'>❌ فشل: " . $mysqli->error . "</div>";
        }

        // Create other tables
        $otherTables = [
            "whatsapp_templates" => "CREATE TABLE IF NOT EXISTS whatsapp_templates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                template_name VARCHAR(100) NOT NULL UNIQUE,
                template_content TEXT NOT NULL,
                template_type ENUM('text', 'image', 'document', 'video') DEFAULT 'text',
                variables TEXT,
                description VARCHAR(255),
                is_active TINYINT(1) DEFAULT 1,
                usage_count INT DEFAULT 0,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_template_name (template_name)
            ) ENGINE=$engine DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "whatsapp_service_log" => "CREATE TABLE IF NOT EXISTS whatsapp_service_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                action_type ENUM('start', 'stop', 'restart', 'login', 'logout', 'error', 'send', 'install') NOT NULL,
                description TEXT,
                user_id INT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action_type (action_type)
            ) ENGINE=$engine DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "whatsapp_contacts" => "CREATE TABLE IF NOT EXISTS whatsapp_contacts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                phone VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                group_name VARCHAR(100),
                notes TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_phone (phone)
            ) ENGINE=$engine DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        foreach ($otherTables as $tableName => $createSQL) {
            if ($mysqli->query($createSQL)) {
                echo "<div class='success'>✅ تم إنشاء $tableName</div>";
            } else {
                echo "<div class='error'>❌ فشل $tableName: " . $mysqli->error . "</div>";
            }
        }

        // Insert sample templates
        $mysqli->query("INSERT IGNORE INTO whatsapp_templates (template_name, template_content, template_type, variables, description, created_by) VALUES
            ('welcome_message', 'مرحباً {name}، شكراً لتواصلك معنا.', 'text', '[\"name\"]', 'رسالة ترحيب', 1),
            ('reminder', 'تذكير: لديك موعد في {date} الساعة {time}', 'text', '[\"date\", \"time\"]', 'تذكير بموعد', 1),
            ('notification', 'إشعار: {message}', 'text', '[\"message\"]', 'إشعار عام', 1)");

        echo "<div class='success'><strong>✅ تم إنشاء جميع الجداول بنجاح!</strong></div>";
        echo "<div class='info'><a href='emergency_fix.php' class='btn btn-primary'>إعادة فحص</a> | <a href='index.hnt' class='btn btn-success'>لوحة التحكم</a></div>";
    }
}

// ==========================================
// ACTION BUTTONS
// ==========================================

if (!isset($_POST['action'])) {
    echo "<h2>⚡ إجراءات الإصلاح</h2>";

    if (count($existingTables) > 0) {
        echo "<div class='warning'><strong>تنبيه:</strong> توجد جداول حالياً. يجب حذفها أولاً</div>";
        echo "<form method='post' style='display:inline;' onsubmit=\"return confirm('هل أنت متأكد من حذف جميع الجداول؟ سيتم حذف جميع البيانات!')\">";
        echo "<input type='hidden' name='action' value='drop_all'>";
        echo "<button type='submit' class='btn btn-danger'>🗑️ حذف جميع الجداول</button>";
        echo "</form>";
    } else {
        echo "<form method='post' style='display:inline;'>";
        echo "<input type='hidden' name='action' value='create_all'>";
        echo "<button type='submit' class='btn btn-success'>🔨 إنشاء جميع الجداول</button>";
        echo "</form>";
    }

    echo "<br><br>";
    echo "<div class='info'><strong>ملاحظة:</strong> بعد إنشاء الجداول، يجب حذف ملفات الجداول القديمة من مجلد data/ إذا كانت لا تزال موجودة</div>";
}

echo "<hr>";
echo "<div class='info'>";
echo "<strong>ملفات مفيدة أخرى:</strong><br>";
echo "• <a href='database_install.php'>صفحة التثبيت العادية</a><br>";
echo "• <a href='fix_database_issue.php'>تشخيص متقدم</a><br>";
echo "• <a href='index.hnt'>لوحة التحكم</a><br>";
echo "</div>";

$mysqli->close();
?>

</div>
</body>
</html>
