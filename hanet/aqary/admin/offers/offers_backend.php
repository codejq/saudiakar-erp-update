<?php
// Backend for offers management - handles all AJAX requests
header('Content-Type: application/json; charset=utf-8');

// Include authentication and setup
require_once("../../connectdb.hnt");

// Database connection
$offers_link = mysql_connect($qdb_server.":".$qdb_port, $qdb_user, $qdb_pass, true);

if (!$offers_link) {
    echo json_encode([
        'success' => false,
        'message' => 'فشل الاتصال بقاعدة البيانات: ' . mysql_error()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!mysql_select_db($qdb_db_offers, $offers_link)) {
    echo json_encode([
        'success' => false,
        'message' => 'فشل اختيار قاعدة البيانات: ' . mysql_error($offers_link)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Set UTF-8 character set for proper Arabic text handling
mysql_query("SET NAMES 'utf8mb4'", $offers_link);
mysql_query("SET CHARACTER SET 'utf8mb4'", $offers_link);

// Get action from request
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// =====================================================
// Action: Fetch offers with pagination
// =====================================================
if ($action === 'fetch_offers') {
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
    $search = isset($_GET['search']) ? mysql_real_escape_string($_GET['search'], $offers_link) : '';
    $status_filter = isset($_GET['status']) ? mysql_real_escape_string($_GET['status'], $offers_link) : '';
    $offer_type_filter = isset($_GET['offer_type']) ? mysql_real_escape_string($_GET['offer_type'], $offers_link) : '';
    $property_type_filter = isset($_GET['property_type_id']) ? intval($_GET['property_type_id']) : 0;
    $favorites_only = isset($_GET['favorites_only']) && $_GET['favorites_only'] === 'true';
    $user_id_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    // Ensure valid pagination values
    if ($page < 1) $page = 1;
    if ($per_page < 1) $per_page = 10;
    if ($per_page > 100) $per_page = 100; // Max 100 per page

    $offset = ($page - 1) * $per_page;

    // Determine join type for favorites filter
    $favorites_join = "";
    if ($favorites_only) {
        $favorites_join = "INNER JOIN favorites f ON o.id = f.offer_id";
    }

    // Build WHERE clause for filters
    $where_clauses = [];
    $where_sql = "";

    if (!empty($search)) {
        $where_clauses[] = "(o.title_ar LIKE '%{$search}%' OR o.location LIKE '%{$search}%' OR o.city LIKE '%{$search}%' OR u.office_name_ar LIKE '%{$search}%')";
    }

    if (!empty($status_filter)) {
        $where_clauses[] = "o.status = '{$status_filter}'";
    }

    if (!empty($offer_type_filter)) {
        $where_clauses[] = "o.offer_type = '{$offer_type_filter}'";
    }

    if ($property_type_filter > 0) {
        $where_clauses[] = "o.property_type_id = {$property_type_filter}";
    }

    if ($user_id_filter > 0) {
        $where_clauses[] = "o.user_id = {$user_id_filter}";
    }

    if (count($where_clauses) > 0) {
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total
                    FROM offers o
                    INNER JOIN users u ON o.user_id = u.id
                    INNER JOIN property_types pt ON o.property_type_id = pt.id
                    {$favorites_join}
                    {$where_sql}";

    $count_result = mysql_query($count_query, $offers_link);
    $total_records = 0;

    if ($count_result) {
        $count_row = mysql_fetch_assoc($count_result);
        $total_records = intval($count_row['total']);
    }

    $total_pages = ceil($total_records / $per_page);

    // Fetch offers with pagination
    $offers_query = "SELECT
        o.id,
        o.user_id,
        o.title_ar,
        o.offer_type,
        o.price,
        o.location,
        o.city,
        o.status,
        o.created_at,
        u.office_name_ar,
        pt.name_ar as property_type_ar
    FROM offers o
    INNER JOIN users u ON o.user_id = u.id
    INNER JOIN property_types pt ON o.property_type_id = pt.id
    {$favorites_join}
    {$where_sql}
    ORDER BY o.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}";

    $offers_result = mysql_query($offers_query, $offers_link);

    if (!$offers_result) {
        echo json_encode([
            'success' => false,
            'message' => 'فشل جلب البيانات: ' . mysql_error($offers_link)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $offers = [];
    while ($row = mysql_fetch_assoc($offers_result)) {
        $offers[] = [
            'id' => intval($row['id']),
            'user_id' => intval($row['user_id']),
            'title_ar' => $row['title_ar'],
            'office_name_ar' => $row['office_name_ar'],
            'status' => $row['status'],
            'city' => $row['city'],
            'offer_type' => $row['offer_type'],
            'location' => $row['location'],
            'created_at' => $row['created_at'],
            'price' => floatval($row['price']),
            'property_type_ar' => $row['property_type_ar']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $offers,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================
// Action: Delete offer
// =====================================================
if ($action === 'delete' && isset($_POST['offer_id'])) {
    $offer_id = intval($_POST['offer_id']);

    // Check if offer exists
    $check_query = "SELECT id FROM offers WHERE id = {$offer_id}";
    $check_result = mysql_query($check_query, $offers_link);

    if (!$check_result || mysql_num_rows($check_result) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'العرض غير موجود'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Delete offer (will cascade to related tables due to foreign keys)
    $delete_query = "DELETE FROM offers WHERE id = {$offer_id}";

    if (mysql_query($delete_query, $offers_link)) {
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف العرض بنجاح'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل حذف العرض: ' . mysql_error($offers_link)
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// =====================================================
// Action: Toggle favorite
// =====================================================
if ($action === 'toggle_favorite' && isset($_POST['offer_id']) && isset($_POST['user_id'])) {
    $offer_id = intval($_POST['offer_id']);
    $user_id = intval($_POST['user_id']);

    // Check if already favorited
    $check_fav = "SELECT id FROM favorites WHERE user_id = {$user_id} AND offer_id = {$offer_id}";
    $fav_result = mysql_query($check_fav, $offers_link);

    if ($fav_result && mysql_num_rows($fav_result) > 0) {
        // Remove from favorites
        $delete_fav = "DELETE FROM favorites WHERE user_id = {$user_id} AND offer_id = {$offer_id}";
        if (mysql_query($delete_fav, $offers_link)) {
            echo json_encode([
                'success' => true,
                'favorited' => false,
                'message' => 'تم إزالة العرض من المفضلة'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'فشل إزالة العرض من المفضلة: ' . mysql_error($offers_link)
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Add to favorites
        $add_fav = "INSERT INTO favorites (user_id, offer_id) VALUES ({$user_id}, {$offer_id})";
        if (mysql_query($add_fav, $offers_link)) {
            echo json_encode([
                'success' => true,
                'favorited' => true,
                'message' => 'تم إضافة العرض للمفضلة'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'فشل إضافة العرض للمفضلة: ' . mysql_error($offers_link)
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    exit;
}

// =====================================================
// Action: Get offer details
// =====================================================
if ($action === 'get_offer' && isset($_GET['offer_id'])) {
    $offer_id = intval($_GET['offer_id']);

    $query = "SELECT
        o.*,
        u.office_name_ar,
        u.office_name_en,
        u.phone,
        u.email,
        pt.name_ar as property_type_ar,
        pt.name_en as property_type_en
    FROM offers o
    INNER JOIN users u ON o.user_id = u.id
    INNER JOIN property_types pt ON o.property_type_id = pt.id
    WHERE o.id = {$offer_id}";

    $result = mysql_query($query, $offers_link);

    if (!$result || mysql_num_rows($result) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'العرض غير موجود'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $offer = mysql_fetch_assoc($result);

    // Get images for this offer
    $images_query = "SELECT image_path, is_primary FROM offer_images WHERE offer_id = {$offer_id} ORDER BY display_order, is_primary DESC";
    $images_result = mysql_query($images_query, $offers_link);
    $images = [];

    if ($images_result) {
        while ($img = mysql_fetch_assoc($images_result)) {
            $images[] = $img;
        }
    }

    $offer['images'] = $images;

    echo json_encode([
        'success' => true,
        'data' => $offer
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================
// Action: Get statistics
// =====================================================
if ($action === 'get_stats') {
    $stats_query = "SELECT
        COUNT(*) as total_offers,
        COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as active_offers,
        COUNT(CASE WHEN status = 'SOLD' THEN 1 END) as sold_offers,
        COUNT(CASE WHEN status = 'PENDING' THEN 1 END) as pending_offers,
        COUNT(CASE WHEN status = 'EXPIRED' THEN 1 END) as expired_offers,
        COUNT(CASE WHEN offer_type = 'SALE' THEN 1 END) as sale_offers,
        COUNT(CASE WHEN offer_type = 'RENT' THEN 1 END) as rent_offers,
        COUNT(CASE WHEN offer_type = 'MORTGAGE' THEN 1 END) as mortgage_offers
    FROM offers";

    $result = mysql_query($stats_query, $offers_link);

    if ($result) {
        $stats = mysql_fetch_assoc($result);
        echo json_encode([
            'success' => true,
            'data' => $stats
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل جلب الإحصائيات'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// =====================================================
// Action: Get property types
// =====================================================
if ($action === 'get_property_types') {
    $property_types_query = "SELECT
        id,
        name_ar,
        name_en,
        slug,
        display_order
    FROM property_types
    WHERE is_active = 1
    ORDER BY display_order ASC";

    $result = mysql_query($property_types_query, $offers_link);

    if ($result) {
        $property_types = [];
        while ($row = mysql_fetch_assoc($result)) {
            $property_types[] = [
                'id' => intval($row['id']),
                'name_ar' => $row['name_ar'],
                'name_en' => $row['name_en'],
                'slug' => $row['slug'],
                'display_order' => intval($row['display_order'])
            ];
        }
        echo json_encode([
            'success' => true,
            'data' => $property_types
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل جلب أنواع العقارات'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// =====================================================
// Action: Get unread messages count
// =====================================================
if ($action === 'get_unread_count') {
    // Get current user ID from session or config
    // For now, we'll get the first active user (you should replace with actual logged-in user)
    $user_query = "SELECT id FROM users WHERE status = 'ACTIVE' LIMIT 1";
    $user_result = mysql_query($user_query, $offers_link);

    if (!$user_result || mysql_num_rows($user_result) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'المستخدم غير موجود'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user_row = mysql_fetch_assoc($user_result);
    $current_user_id = intval($user_row['id']);

    // Count unread messages
    $count_query = "SELECT COUNT(*) as unread_count
                    FROM messages
                    WHERE receiver_id = {$current_user_id}
                    AND status = 'SENT'";

    $result = mysql_query($count_query, $offers_link);

    if ($result) {
        $row = mysql_fetch_assoc($result);
        echo json_encode([
            'success' => true,
            'unread_count' => intval($row['unread_count'])
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل جلب عدد الرسائل غير المقروءة'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// =====================================================
// Action: Get all messages (inbox)
// =====================================================
if ($action === 'get_messages') {
    // Get current user ID
    $user_query = "SELECT id FROM users WHERE status = 'ACTIVE' LIMIT 1";
    $user_result = mysql_query($user_query, $offers_link);

    if (!$user_result || mysql_num_rows($user_result) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'المستخدم غير موجود'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user_row = mysql_fetch_assoc($user_result);
    $current_user_id = intval($user_row['id']);

    // Fetch all messages for this user
    $messages_query = "SELECT
        m.id,
        m.sender_id,
        m.receiver_id,
        m.offer_id,
        m.subject,
        m.message,
        m.status,
        m.read_at,
        m.created_at,
        u.office_name_ar as sender_name,
        u.phone as sender_phone,
        o.title_ar as offer_title
    FROM messages m
    INNER JOIN users u ON m.sender_id = u.id
    LEFT JOIN offers o ON m.offer_id = o.id
    WHERE m.receiver_id = {$current_user_id}
    ORDER BY m.created_at DESC";

    $result = mysql_query($messages_query, $offers_link);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'فشل جلب الرسائل: ' . mysql_error($offers_link)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $messages = [];
    while ($row = mysql_fetch_assoc($result)) {
        $messages[] = [
            'id' => intval($row['id']),
            'sender_id' => intval($row['sender_id']),
            'receiver_id' => intval($row['receiver_id']),
            'offer_id' => $row['offer_id'] ? intval($row['offer_id']) : null,
            'subject' => $row['subject'],
            'message' => $row['message'],
            'status' => $row['status'],
            'read_at' => $row['read_at'],
            'created_at' => $row['created_at'],
            'sender_name' => $row['sender_name'],
            'sender_phone' => $row['sender_phone'],
            'offer_title' => $row['offer_title']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $messages
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================
// Action: Mark message as read
// =====================================================
if ($action === 'mark_as_read' && isset($_POST['message_id'])) {
    $message_id = intval($_POST['message_id']);

    // Update message status to READ
    $update_query = "UPDATE messages
                     SET status = 'READ', read_at = NOW()
                     WHERE id = {$message_id} AND status = 'SENT'";

    if (mysql_query($update_query, $offers_link)) {
        echo json_encode([
            'success' => true,
            'message' => 'تم تعليم الرسالة كمقروءة'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل تحديث حالة الرسالة: ' . mysql_error($offers_link)
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// =====================================================
// Invalid action
// =====================================================
echo json_encode([
    'success' => false,
    'message' => 'عملية غير صالحة'
], JSON_UNESCAPED_UNICODE);
?>
