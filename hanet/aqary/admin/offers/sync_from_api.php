<?php
/**
 * Sync Script: Pull offers data from online API to local database
 *
 * This script:
 * - Syncs offers from online API
 * - Downloads offer images to local folder
 * - Syncs user information (without API keys)
 * - Syncs messages
 * - Prevents duplicate entries
 * - Runs as single execution (called externally every 5 minutes)
 */

// Disable output buffering for real-time logging
ob_implicit_flush(true);
set_time_limit(300); // 5 minutes max

// Include database connection
require_once("../../connectdb.hnt");
require_once("ApiKey.php");

// Database setup
$qdb_db_offers = "aqary_offers";
$offers_link = mysql_connect($qdb_server.":".$qdb_port, $qdb_user, $qdb_pass);

if (!$offers_link) {
    die("Failed to connect to MySQL server: " . mysql_error());
}

mysql_select_db($qdb_db_offers, $offers_link);

// Set UTF-8 character set for proper Arabic text handling
mysql_query("SET NAMES 'utf8mb4'", $offers_link);
mysql_query("SET CHARACTER SET 'utf8mb4'", $offers_link);

// =====================================================
// Configuration
// =====================================================

$DEBUG_MODE = true; // Set to false in production
$API_BASE_URL = 'https://api.saudiakar.net';
$IMAGES_DIR = __DIR__ . '/images';

// =====================================================
// Utility Functions
// =====================================================

function logMessage($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
}

function makeApiRequest($endpoint, $api_key, $method = 'GET') {
    global $API_BASE_URL, $DEBUG_MODE;

    $url = $API_BASE_URL . $endpoint;

    if ($DEBUG_MODE) {
        logMessage("DEBUG: Requesting {$method} {$url}");
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);     // Skip hostname verification
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
        'User-Agent: OfficesSync/1.0'
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    }

    if ($DEBUG_MODE) {
        curl_setopt($ch, CURLOPT_VERBOSE, true);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);

    // Get additional debug info
    $curl_info = curl_getinfo($ch);
    //Deprecated</b>:  Function curl_close() is deprecated since 8.5, as it has no effect since PHP 8.0
    //curl_close($ch);

    if ($curl_errno !== 0) {
        logMessage("cURL Error #{$curl_errno}: {$curl_error}");
        logMessage("Failed URL: {$url}");
        if ($DEBUG_MODE) {
            logMessage("DEBUG: Total time: " . $curl_info['total_time'] . "s");
            logMessage("DEBUG: Connect time: " . $curl_info['connect_time'] . "s");
            logMessage("DEBUG: Name lookup time: " . $curl_info['namelookup_time'] . "s");
        }
        return null;
    }

    if ($http_code !== 200) {
        logMessage("API Error: HTTP {$http_code} for {$endpoint}");
        logMessage("Response: " . substr($response, 0, 500));
        return null;
    }

    if ($DEBUG_MODE) {
        logMessage("DEBUG: Response received (length: " . strlen($response) . " bytes)");
        logMessage("DEBUG: Raw Response: " . $response);
    }

    $data = json_decode($response, true);

    if (!$data || $data['status'] !== 'success') {
        logMessage("API Error: Invalid response from {$endpoint}");
        if ($DEBUG_MODE) {
            logMessage("DEBUG: Response preview: " . substr($response, 0, 500));
            logMessage("DEBUG: Decoded data: " . print_r($data, true));
        }
        return null;
    }

    if ($DEBUG_MODE) {
        logMessage("DEBUG: Data structure: " . print_r($data['data'], true));
    }

    return $data['data'];
}

function downloadImage($image_url, $local_path) {
    global $API_BASE_URL, $DEBUG_MODE;

    // Construct full URL if relative
    if (strpos($image_url, 'http') !== 0) {
        $image_url = $API_BASE_URL . '/' . ltrim($image_url, '/');
    }

    if ($DEBUG_MODE) {
        logMessage("      [downloadImage] Full URL: {$image_url}");
        logMessage("      [downloadImage] Saving to: {$local_path}");
    }

    // Create directory if not exists
    $dir = dirname($local_path);
    if (!file_exists($dir)) {
        if ($DEBUG_MODE) {
            logMessage("      [downloadImage] Creating directory: {$dir}");
        }
        if (!mkdir($dir, 0755, true)) {
            logMessage("      ✗ Failed to create directory: {$dir}");
            return false;
        }
    }

    // Download image
    $image_data = @file_get_contents($image_url);

    if ($image_data === false) {
        $error = error_get_last();
        logMessage("      ✗ Failed to download image from: {$image_url}");
        if ($error) {
            logMessage("      ✗ Error: {$error['message']}");
        }
        return false;
    }

    if ($DEBUG_MODE) {
        logMessage("      [downloadImage] Downloaded " . strlen($image_data) . " bytes");
    }

    if (file_put_contents($local_path, $image_data) === false) {
        logMessage("      ✗ Failed to save image to: {$local_path}");
        return false;
    }

    if ($DEBUG_MODE) {
        logMessage("      ✓ Image saved successfully");
    }

    return true;
}

// =====================================================
// Sync Functions
// =====================================================

/**
 * Sync or create user (without API key)
 */
function syncUser($user_data, $offers_link) {
    global $qdb_db_offers, $DEBUG_MODE;

    $user_id = intval($user_data['id']);

    if ($DEBUG_MODE) {
        logMessage("  DEBUG: Syncing user ID {$user_id}");
    }

    // Check if user exists
    $check_query = "SELECT id FROM users WHERE id = {$user_id}";
    $result = mysql_query($check_query, $offers_link);

    if ($result && mysql_num_rows($result) > 0) {
        // User exists, update info (without API key)
        $office_name_ar = mysql_real_escape_string($user_data['office_name_ar'], $offers_link);
        $office_name_en = mysql_real_escape_string($user_data['office_name_en'] ?? '', $offers_link);
        $phone = mysql_real_escape_string($user_data['phone'] ?? '', $offers_link);

        $update_query = "UPDATE users SET
            office_name_ar = '{$office_name_ar}',
            office_name_en = '{$office_name_en}',
            phone = '{$phone}',
            updated_at = NOW()
            WHERE id = {$user_id}";

        if (mysql_query($update_query, $offers_link)) {
            logMessage("  ✓ Updated user ID {$user_id}");
        } else {
            logMessage("  ✗ Failed to update user ID {$user_id}: " . mysql_error($offers_link));
        }
    } else {
        // Create new user with dummy API key and serial
        $office_name_ar = mysql_real_escape_string($user_data['office_name_ar'], $offers_link);
        $office_name_en = mysql_real_escape_string($user_data['office_name_en'] ?? '', $offers_link);
        $phone = mysql_real_escape_string($user_data['phone'] ?? '', $offers_link);

        // Generate dummy API key and serial for synced users
        $dummy_api_key = 'SYNC-' . str_pad($user_id, 15, '0', STR_PAD_LEFT);
        $dummy_serial = 'SYNCED-USER-' . $user_id;

        if ($DEBUG_MODE) {
            logMessage("  DEBUG: Creating new user with dummy API key: {$dummy_api_key}");
        }

        $insert_query = "INSERT INTO users (
            id, api_key, serial_number, office_name_ar, office_name_en, phone, status, created_at
        ) VALUES (
            {$user_id},
            '{$dummy_api_key}',
            '{$dummy_serial}',
            '{$office_name_ar}',
            '{$office_name_en}',
            '{$phone}',
            'ACTIVE',
            NOW()
        )";

        if (mysql_query($insert_query, $offers_link)) {
            logMessage("  ✓ Created new user ID {$user_id}");
        } else {
            $error = mysql_error($offers_link);
            $errno = mysql_errno($offers_link);
            logMessage("  ✗ Failed to create user ID {$user_id}");
            logMessage("    MySQL Error #{$errno}: {$error}");
        }
    }
}

/**
 * Sync single offer with images
 */
function syncOffer($offer_data, $offers_link) {
    global $IMAGES_DIR, $DEBUG_MODE;

    $offer_id = intval($offer_data['id']);

    // Check if offer exists
    $check_query = "SELECT id FROM offers WHERE id = {$offer_id}";
    $result = mysql_query($check_query, $offers_link);

    if ($result && mysql_num_rows($result) > 0) {
        logMessage("Offer ID {$offer_id} already exists, skipping");
        return;
    }

    // Sync user first - user data is embedded in offer response
    $user_data = [
        'id' => $offer_data['user_id'],
        'office_name_ar' => $offer_data['office_name_ar'] ?? '',
        'office_name_en' => $offer_data['office_name_en'] ?? '',
        'phone' => $offer_data['phone'] ?? ''
    ];
    syncUser($user_data, $offers_link);

    // Prepare offer data
    if ($DEBUG_MODE) {
        logMessage("  DEBUG: Preparing offer data for insertion");
    }

    $user_id = intval($offer_data['user_id']);
    $property_type_id = intval($offer_data['property_type_id']);
    $title_ar = mysql_real_escape_string($offer_data['title_ar'], $offers_link);
    $title_en = mysql_real_escape_string($offer_data['title_en'] ?? '', $offers_link);
    $description_ar = mysql_real_escape_string($offer_data['description_ar'], $offers_link);
    $description_en = mysql_real_escape_string($offer_data['description_en'] ?? '', $offers_link);

    if ($DEBUG_MODE) {
        logMessage("  DEBUG: user_id={$user_id}, property_type_id={$property_type_id}");
    }
    $offer_type = mysql_real_escape_string($offer_data['offer_type'], $offers_link);
    $price = floatval($offer_data['price']);
    $area = isset($offer_data['area']) ? floatval($offer_data['area']) : 'NULL';
    $location = mysql_real_escape_string($offer_data['location'], $offers_link);
    $city = mysql_real_escape_string($offer_data['city'], $offers_link);
    $district = mysql_real_escape_string($offer_data['district'] ?? '', $offers_link);
    $street = mysql_real_escape_string($offer_data['street'] ?? '', $offers_link);
    $latitude = isset($offer_data['latitude']) ? floatval($offer_data['latitude']) : 'NULL';
    $longitude = isset($offer_data['longitude']) ? floatval($offer_data['longitude']) : 'NULL';
    $bedrooms = isset($offer_data['bedrooms']) ? intval($offer_data['bedrooms']) : 'NULL';
    $bathrooms = isset($offer_data['bathrooms']) ? intval($offer_data['bathrooms']) : 'NULL';
    $floor_number = isset($offer_data['floor_number']) ? intval($offer_data['floor_number']) : 'NULL';
    $building_age = isset($offer_data['building_age']) ? intval($offer_data['building_age']) : 'NULL';
    $has_elevator = isset($offer_data['has_elevator']) ? ($offer_data['has_elevator'] ? 1 : 0) : 0;
    $has_parking = isset($offer_data['has_parking']) ? ($offer_data['has_parking'] ? 1 : 0) : 0;
    $has_ac = isset($offer_data['has_ac']) ? ($offer_data['has_ac'] ? 1 : 0) : 0;
    $has_kitchen = isset($offer_data['has_kitchen']) ? ($offer_data['has_kitchen'] ? 1 : 0) : 0;
    $is_furnished = isset($offer_data['is_furnished']) ? ($offer_data['is_furnished'] ? 1 : 0) : 0;
    $status = mysql_real_escape_string($offer_data['status'], $offers_link);
    $views_count = intval($offer_data['views_count'] ?? 0);
    $contact_count = intval($offer_data['contact_count'] ?? 0);
    $expires_at = isset($offer_data['expires_at']) ? "'{$offer_data['expires_at']}'" : 'NULL';
    $created_at = $offer_data['created_at'];
    $updated_at = $offer_data['updated_at'];

    // Insert offer
    $insert_query = "INSERT INTO offers (
        id, user_id, property_type_id, title_ar, title_en, description_ar, description_en,
        offer_type, price, area, location, city, district, street, latitude, longitude,
        bedrooms, bathrooms, floor_number, building_age, has_elevator, has_parking,
        has_ac, has_kitchen, is_furnished, status, views_count, contact_count,
        expires_at, created_at, updated_at
    ) VALUES (
        {$offer_id}, {$user_id}, {$property_type_id}, '{$title_ar}', '{$title_en}',
        '{$description_ar}', '{$description_en}', '{$offer_type}', {$price},
        " . ($area !== 'NULL' ? $area : 'NULL') . ", '{$location}', '{$city}', '{$district}',
        '{$street}', " . ($latitude !== 'NULL' ? $latitude : 'NULL') . ",
        " . ($longitude !== 'NULL' ? $longitude : 'NULL') . ",
        " . ($bedrooms !== 'NULL' ? $bedrooms : 'NULL') . ",
        " . ($bathrooms !== 'NULL' ? $bathrooms : 'NULL') . ",
        " . ($floor_number !== 'NULL' ? $floor_number : 'NULL') . ",
        " . ($building_age !== 'NULL' ? $building_age : 'NULL') . ",
        {$has_elevator}, {$has_parking}, {$has_ac}, {$has_kitchen}, {$is_furnished},
        '{$status}', {$views_count}, {$contact_count}, {$expires_at},
        '{$created_at}', '{$updated_at}'
    )";

    if ($DEBUG_MODE) {
        logMessage("  DEBUG: Executing INSERT query...");
        logMessage("  DEBUG: Query preview: INSERT INTO offers (id, user_id, property_type_id...) VALUES ({$offer_id}, {$user_id}, {$property_type_id}...)");
    }

    $insert_result = mysql_query($insert_query, $offers_link);

    if ($insert_result) {
        logMessage("✓ Created offer ID {$offer_id}");
        // Images will be synced separately after this function returns
    } else {
        $error = mysql_error($offers_link);
        $errno = mysql_errno($offers_link);
        logMessage("✗ Failed to create offer ID {$offer_id}");
        logMessage("  MySQL Error #{$errno}: {$error}");
        if ($DEBUG_MODE) {
            logMessage("  DEBUG: Full INSERT query:");
            logMessage("  " . substr($insert_query, 0, 500) . "...");
        }
    }
}

/**
 * Sync offer image
 */
function syncOfferImage($offer_id, $image_data, $display_order, $offers_link) {
    global $IMAGES_DIR, $DEBUG_MODE;

    if ($DEBUG_MODE) {
        logMessage("    [syncOfferImage] Processing image: " . json_encode($image_data));
    }

    $image_id = intval($image_data['id']);

    // Check if image already exists
    $check_query = "SELECT id FROM offer_images WHERE id = {$image_id}";
    $result = mysql_query($check_query, $offers_link);

    if ($result && mysql_num_rows($result) > 0) {
        logMessage("    - Image ID {$image_id} already exists, skipping");
        return; // Image already exists
    }

    // Download image
    $remote_path = $image_data['image_path'];
    $filename = basename($remote_path);
    $local_dir = $IMAGES_DIR . '/' . $offer_id;
    $local_path = $local_dir . '/' . $filename;

    if ($DEBUG_MODE) {
        logMessage("    [syncOfferImage] Remote path: {$remote_path}");
        logMessage("    [syncOfferImage] Local path: {$local_path}");
    }

    // Download thumbnail if exists
    $thumbnail_path = '';
    if (isset($image_data['thumbnail_path'])) {
        $thumb_filename = basename($image_data['thumbnail_path']);
        $thumb_local_path = $local_dir . '/' . $thumb_filename;

        logMessage("    - Downloading thumbnail: {$thumb_filename}");
        if (downloadImage($image_data['thumbnail_path'], $thumb_local_path)) {
            $thumbnail_path = 'images/' . $offer_id . '/' . $thumb_filename;
            logMessage("    - Thumbnail downloaded successfully");
        } else {
            logMessage("    - Failed to download thumbnail");
        }
    }

    // Download main image
    logMessage("    - Downloading image: {$filename}");
    if (downloadImage($remote_path, $local_path)) {
        $image_path = 'images/' . $offer_id . '/' . $filename;
        $thumbnail_path_sql = $thumbnail_path ? "'{$thumbnail_path}'" : 'NULL';
        $is_primary = isset($image_data['is_primary']) && $image_data['is_primary'] ? 1 : 0;
        $file_size = isset($image_data['file_size']) ? intval($image_data['file_size']) : 'NULL';

        $insert_query = "INSERT INTO offer_images (
            id, offer_id, image_path, thumbnail_path, display_order, is_primary, file_size, created_at
        ) VALUES (
            {$image_id}, {$offer_id}, '{$image_path}', {$thumbnail_path_sql},
            {$display_order}, {$is_primary}, {$file_size}, NOW()
        )";

        if (mysql_query($insert_query, $offers_link)) {
            logMessage("    ✓ Downloaded and saved image {$filename} (size: " . ($file_size !== 'NULL' ? number_format($file_size) . ' bytes' : 'unknown') . ")");
        } else {
            logMessage("    ✗ Failed to insert image record: " . mysql_error($offers_link));
        }
    } else {
        logMessage("    ✗ Failed to download main image {$filename}");
    }
}

/**
 * Sync messages
 */
function syncMessage($message_data, $offers_link) {
    $message_id = intval($message_data['id']);

    // Check if message exists
    $check_query = "SELECT id FROM messages WHERE id = {$message_id}";
    $result = mysql_query($check_query, $offers_link);

    if ($result && mysql_num_rows($result) > 0) {
        return; // Message already exists
    }

    $sender_id = intval($message_data['sender_id']);
    $receiver_id = intval($message_data['receiver_id']);
    $offer_id = isset($message_data['offer_id']) ? intval($message_data['offer_id']) : 'NULL';
    $subject = mysql_real_escape_string($message_data['subject'] ?? '', $offers_link);
    $message = mysql_real_escape_string($message_data['message'], $offers_link);
    $status = mysql_real_escape_string($message_data['status'] ?? 'SENT', $offers_link);
    $read_at = isset($message_data['read_at']) ? "'{$message_data['read_at']}'" : 'NULL';
    $created_at = $message_data['created_at'];

    $insert_query = "INSERT INTO messages (
        id, sender_id, receiver_id, offer_id, subject, message, status, read_at, created_at
    ) VALUES (
        {$message_id}, {$sender_id}, {$receiver_id},
        " . ($offer_id !== 'NULL' ? $offer_id : 'NULL') . ",
        '{$subject}', '{$message}', '{$status}', {$read_at}, '{$created_at}'
    )";

    if (mysql_query($insert_query, $offers_link)) {
        logMessage("Synced message ID {$message_id}");
    } else {
        logMessage("Failed to sync message ID {$message_id}: " . mysql_error($offers_link));
    }
}

// =====================================================
// Main Sync Process
// =====================================================

logMessage("=== Starting Sync Process ===");

// Get API key
$api_key = getApiKey();
if (!$api_key) {
    die("ERROR: Could not retrieve API key\n");
}

logMessage("API Key loaded: " . substr($api_key, 0, 4) . "****");

// Get last synced offer ID
$last_offer_query = "SELECT MAX(id) as max_id FROM offers";
$result = mysql_query($last_offer_query, $offers_link);
$last_offer_id = 0;

if ($result) {
    $row = mysql_fetch_assoc($result);
    $last_offer_id = intval($row['max_id'] ?? 0);
}

logMessage("Last synced offer ID: {$last_offer_id}");

// Fetch offers from API (paginated)
$page = 1;
$limit = 50;
$total_synced = 0;

do {
    // Add last_id parameter to get only new offers after the last synced ID
    // This prevents getting cached old results
    $endpoint = "/offers?page={$page}&limit={$limit}";
    if ($last_offer_id > 0) {
        $endpoint .= "&last_id={$last_offer_id}";
    }

    logMessage("Fetching offers: page {$page}" . ($last_offer_id > 0 ? " (after ID {$last_offer_id})" : ""));

    $data = makeApiRequest($endpoint, $api_key);

    if ($DEBUG_MODE) {
        logMessage("DEBUG: Returned data keys: " . ($data ? implode(', ', array_keys($data)) : 'NULL'));
    }

    if (!$data || !isset($data['items'])) {
        logMessage("No offers found in response or invalid data structure");
        if ($DEBUG_MODE && $data) {
            logMessage("DEBUG: Available keys in data: " . implode(', ', array_keys($data)));
        }
        break;
    }

    $offers = $data['items'];
    $pagination = $data['pagination'] ?? [];

    logMessage("Found " . count($offers) . " offers on page {$page}");

    foreach ($offers as $offer) {
        // The API already returns complete offer data with images embedded
        $offer_id = $offer['id'];
        logMessage("Syncing offer ID {$offer_id}: {$offer['title_ar']}");

        // Sync the offer (data includes embedded images)
        syncOffer($offer, $offers_link);

        // Sync embedded images (images are included in GET /offers response)
        if (isset($offer['images']) && is_array($offer['images']) && count($offer['images']) > 0) {
            logMessage("  - Syncing " . count($offer['images']) . " images from offer data");
            foreach ($offer['images'] as $image) {
                // Use display_order from the image data
                $display_order = isset($image['display_order']) ? intval($image['display_order']) : 0;
                syncOfferImage($offer_id, $image, $display_order, $offers_link);
            }
        } else {
            logMessage("  - No images in offer data");
        }

        $total_synced++;
    }

    // Check if there are more pages (using has_more from pagination)
    $has_more = isset($pagination['has_more']) && $pagination['has_more'];
    $page++;

} while ($has_more && $page <= 10); // Limit to 10 pages per run (500 offers max)

logMessage("Total offers synced: {$total_synced}");

// Sync messages
logMessage("Fetching messages...");
$messages_data = makeApiRequest("/messages/inbox", $api_key);

if ($DEBUG_MODE && $messages_data) {
    logMessage("DEBUG: Messages data keys: " . implode(', ', array_keys($messages_data)));
}

if ($messages_data && isset($messages_data['items'])) {
    $messages = $messages_data['items'];
    $messages_synced = 0;

    logMessage("Found " . count($messages) . " messages");

    foreach ($messages as $message) {
        syncMessage($message, $offers_link);
        $messages_synced++;
    }

    logMessage("Total messages synced: {$messages_synced}");
} else {
    logMessage("No messages found or invalid data structure");
    if ($DEBUG_MODE && $messages_data) {
        logMessage("DEBUG: Available keys in messages data: " . implode(', ', array_keys($messages_data)));
    }
}

logMessage("=== Sync Process Completed ===");
?>
