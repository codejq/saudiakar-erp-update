<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<title>إصلاح فوري - MyISAM Fix</title>
<style>
body { font-family: Arial, sans-serif; padding: 20px; direction: rtl; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.success { color: #155724; padding: 15px; background: #d4edda; margin: 10px 0; border-radius: 5px; border-right: 4px solid #28a745; }
.error { color: #721c24; padding: 15px; background: #f8d7da; margin: 10px 0; border-radius: 5px; border-right: 4px solid #dc3545; }
.info { color: #004085; padding: 15px; background: #cce5ff; margin: 10px 0; border-radius: 5px; border-right: 4px solid #0c5460; }
.warning { color: #856404; padding: 15px; background: #fff3cd; margin: 10px 0; border-radius: 5px; border-right: 4px solid #ffc107; }
.btn { padding: 15px 30px; font-size: 18px; border: none; cursor: pointer; border-radius: 5px; margin: 10px 5px; color: white; font-weight: bold; }
.btn-danger { background: #dc3545; }
.btn-success { background: #28a745; }
.btn-primary { background: #007bff; }
h1 { color: #333; border-bottom: 3px solid #28a745; padding-bottom: 10px; }
h2 { color: #555; margin-top: 25px; background: #f8f9fa; padding: 10px; border-right: 4px solid #28a745; }
h3 { color: #333; }
</style>
</head>
<body>
<div class="container">
<h1>🔧 إصلاح فوري - MyISAM Engine Fix</h1>

<?php
// Database connection
require_once('../../connectdb.hnt');

if (!$db) {
    die('<div class="error"><strong>خطأ:</strong> فشل الاتصال بقاعدة البيانات</div>');
}

echo '<div class="success">✅ تم الاتصال بقاعدة البيانات بنجاح</div>';
echo '<div class="info">📊 إصدار MariaDB: ' . $db->server_info . '</div>';
echo '<div class="info">🗄️ قاعدة البيانات: aqary_utf</div>';
echo '<div class="warning">⚠️ سنستخدم MyISAM (لأن InnoDB لا يعمل)</div>';

$db->set_charset('utf8mb4');

// ==========================================
// STEP 1: DROP ALL TABLES
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fix_now'])) {

    echo '<h2>🗑️ الخطوة 1: حذف الجداول التالفة</h2>';

    $tablesToDrop = [
        'whatsapp_contacts',
        'whatsapp_sent_log',
        'whatsapp_service_log',
        'whatsapp_templates',
        'whatsapp_queue',
        'whatsapp_settings'
    ];

    foreach ($tablesToDrop as $table) {
        $sql = "DROP TABLE IF EXISTS $table";
        if ($db->query($sql)) {
            echo "<div class='success'>✅ حذف: $table</div>";
        } else {
            echo "<div class='warning'>⚠️ $table: " . $db->error . "</div>";
        }
    }

    // ==========================================
    // STEP 2: CREATE TABLES WITH MyISAM
    // ==========================================
    echo '<h2>🔨 الخطوة 2: إنشاء الجداول (MyISAM)</h2>';

    // Table 1: whatsapp_settings
    $sql = "CREATE TABLE whatsapp_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_setting_key (setting_key)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($db->query($sql)) {
        echo '<div class="success">✅ تم إنشاء whatsapp_settings</div>';

        // Insert default settings
        $db->query("INSERT INTO whatsapp_settings (setting_key, setting_value, description) VALUES
            ('service_url', 'http://localhost:8010', 'عنوان خدمة WhatsApp'),
            ('service_status', '0', 'حالة الخدمة: 0=متوقفة، 1=تعمل'),
            ('account_connected', '0', 'اتصال الحساب: 0=غير متصل، 1=متصل'),
            ('account_phone', '', 'رقم هاتف الحساب المتصل'),
            ('account_name', '', 'اسم الحساب المتصل'),
            ('max_file_size', '25', 'الحد الأقصى لحجم المرفقات بالميجابايت'),
            ('messages_per_minute', '20', 'الحد الأقصى للرسائل في الدقيقة'),
            ('auto_retry', '1', 'إعادة المحاولة التلقائية: 0=لا، 1=نعم'),
            ('max_retries', '3', 'الحد الأقصى لمحاولات الإعادة'),
            ('log_retention_days', '90', 'مدة الاحتفاظ بالسجلات بالأيام'),
            ('cron_enabled', '1', 'تفعيل معالج الخلفية: 0=لا، 1=نعم'),
            ('last_cron_run', '', 'آخر تشغيل للمعالج')");

        echo '<div class="success">✅ تم إضافة 12 إعداد افتراضي</div>';
    } else {
        echo '<div class="error">❌ فشل: ' . $db->error . '</div>';
    }

    // Table 2: whatsapp_queue
    $sql = "CREATE TABLE whatsapp_queue (
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
        INDEX idx_recipient (recipient),
        INDEX idx_created_by (created_by),
        INDEX idx_priority (priority)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($db->query($sql)) {
        echo '<div class="success">✅ تم إنشاء whatsapp_queue</div>';
    } else {
        echo '<div class="error">❌ فشل: ' . $db->error . '</div>';
    }

    // Table 3: whatsapp_sent_log (no foreign key for MyISAM)
    $sql = "CREATE TABLE whatsapp_sent_log (
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
        INDEX idx_sent_at (sent_at),
        INDEX idx_recipient (recipient),
        INDEX idx_message_id (message_id)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($db->query($sql)) {
        echo '<div class="success">✅ تم إنشاء whatsapp_sent_log</div>';
    } else {
        echo '<div class="error">❌ فشل: ' . $db->error . '</div>';
    }

    // Table 4: whatsapp_templates
    $sql = "CREATE TABLE whatsapp_templates (
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
        INDEX idx_template_name (template_name),
        INDEX idx_is_active (is_active)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($db->query($sql)) {
        echo '<div class="success">✅ تم إنشاء whatsapp_templates</div>';

        // Insert sample templates
        $db->query("INSERT INTO whatsapp_templates (template_name, template_content, template_type, variables, description, is_active, created_by) VALUES
            ('welcome_message', 'مرحباً {name}، شكراً لتواصلك معنا.', 'text', '[\"name\"]', 'رسالة ترحيب', 1, 1),
            ('appointment_reminder', 'تذكير: لديك موعد في {date} الساعة {time}', 'text', '[\"date\",\"time\"]', 'تذكير بموعد', 1, 1),
            ('general_notification', 'إشعار: {message}', 'text', '[\"message\"]', 'إشعار عام', 1, 1)");

        echo '<div class="success">✅ تم إضافة 3 قوالب نموذجية</div>';
    } else {
        echo '<div class="error">❌ فشل: ' . $db->error . '</div>';
    }

    // Table 5: whatsapp_service_log
    $sql = "CREATE TABLE whatsapp_service_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        action_type ENUM('start', 'stop', 'restart', 'login', 'logout', 'error', 'send', 'install') NOT NULL,
        description TEXT,
        user_id INT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action_type (action_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($db->query($sql)) {
        echo '<div class="success">✅ تم إنشاء whatsapp_service_log</div>';
    } else {
        echo '<div class="error">❌ فشل: ' . $db->error . '</div>';
    }

    // Table 6: whatsapp_contacts
    $sql = "CREATE TABLE whatsapp_contacts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        phone VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        group_name VARCHAR(100),
        notes TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_phone (phone),
        INDEX idx_group_name (group_name),
        INDEX idx_is_active (is_active)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($db->query($sql)) {
        echo '<div class="success">✅ تم إنشاء whatsapp_contacts</div>';
    } else {
        echo '<div class="error">❌ فشل: ' . $db->error . '</div>';
    }

    // ==========================================
    // STEP 3: VERIFY
    // ==========================================
    echo '<h2>✅ الخطوة 3: التحقق من النجاح</h2>';

    $result = $db->query("SHOW TABLES LIKE 'whatsapp_%'");
    $count = $result->num_rows;

    if ($count == 6) {
        echo '<div class="success"><strong>🎉 تبارك! تم إنشاء جميع الجداول بنجاح!</strong></div>';
        echo '<div class="success">✅ عدد الجداول: ' . $count . ' جدول</div>';

        // Test query
        $testResult = $db->query("SELECT COUNT(*) as cnt FROM whatsapp_settings");
        if ($testResult) {
            $row = $testResult->fetch_assoc();
            echo '<div class="success">✅ اختبار الجداول: ناجح (' . $row['cnt'] . ' إعداد)</div>';
        }

        echo '<div class="info" style="margin-top:20px;">';
        echo '<h3>🚀 الخطوات التالية:</h3>';
        echo '<p>1. <a href="index.hnt" style="font-size:18px; font-weight:bold;">✅ افتح لوحة التحكم</a></p>';
        echo '<p>2. <a href="install.hnt" style="font-size:18px;">⚙️ ثبت خدمة WhatsApp</a></p>';
        echo '<p>3. <a href="account.hnt" style="font-size:18px;">👤 اربط حسابك</a></p>';
        echo '<p>4. <a href="send.hnt" style="font-size:18px;">📤 أرسل رسالة تجريبية</a></p>';
        echo '</div>';

    } else {
        echo '<div class="error">❌ خطأ: تم إنشاء ' . $count . ' جدول فقط (مطلوب 6)</div>';
    }

} else {
    // ==========================================
    // SHOW FIX BUTTON
    // ==========================================

    echo '<div class="warning">';
    echo '<h3>⚠️ تنبيه هام:</h3>';
    echo '<p>• سيتم حذف جميع الجداول الحالية</p>';
    echo '<p>• سيتم إنشاء جداول جديدة باستخدام MyISAM</p>';
    echo '<p>• سيتم حذف جميع البيانات القديمة!</p>';
    echo '</div>';

    echo '<div class="info">';
    echo '<h3>📊 ماذا سيتم إنشاؤه:</h3>';
    echo '<p>✅ whatsapp_settings (12 إعداد)</p>';
    echo '<p>✅ whatsapp_queue (قائمة الرسائل)</p>';
    echo '<p>✅ whatsapp_sent_log (سجل الإرسال)</p>';
    echo '<p>✅ whatsapp_templates (3 قوالب)</p>';
    echo '<p>✅ whatsapp_service_log (سجل النشاطات)</p>';
    echo '<p>✅ whatsapp_contacts (دليل الجهات)</p>';
    echo '</div>';

    echo '<form method="post" onsubmit="return confirm(\'هل أنت متأكد؟ سيتم حذف جميع البيانات القديمة!\')" style="text-align:center; margin-top:30px;">';
    echo '<button type="submit" name="fix_now" class="btn btn-success" style="font-size:22px; padding:20px 40px;">🔨 إصلاح الآن!</button>';
    echo '</form>';
}

$db->close();
?>

</div>
</body>
</html>
