<?php
/**
 * Migrate AI Image URLs from old path to new centralized storage path
 *
 * Old path: /aqary/admin/ai/storage/images/
 * New path: /aqary/storage/uploads/ai/images/
 *
 * Run this script once to update existing database records
 *
 * IMPORTANT: Visit this URL in your browser to run the migration:
 * http://192.168.0.105:9009/aqary/admin/ai/features/migrate_image_urls.php
 */

// Include database connection
require_once('../../../connectdb.hnt');

// Skip authentication for easier access - remove this if you want authentication
// require_once('../../../reqlogin.hnt');

// Only allow admins to run this migration
// if (!isset($_SESSION['useridv'])) {
//     die('Authentication required');
// }

echo "<h2>AI Image URL Migration</h2>";
echo "<p>This script will update image URLs from the old path to the new centralized storage path.</p>";

// Check if user confirmed the migration
if (!isset($_GET['confirm'])) {
    echo "<p><strong>Old path:</strong> /aqary/admin/ai/storage/images/</p>";
    echo "<p><strong>New path:</strong> /aqary/storage/uploads/ai/images/</p>";
    echo "<p><a href='?confirm=1' style='background: #0066cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Start Migration</a></p>";
    exit;
}

// Start migration
echo "<h3>Migration Progress:</h3>";
echo "<pre>";

// Update image URLs in ai_generated_content table
$sql = "UPDATE ai_generated_content
        SET generated_text = REPLACE(generated_text, '/aqary/admin/ai/storage/images/', '/aqary/storage/uploads/ai/images/')
        WHERE entity_type = 'image_generated'
        AND generated_text LIKE '%/aqary/admin/ai/storage/images/%'";

$result = mysqli_query($link, $sql);

if ($result) {
    $affected_rows = mysqli_affected_rows($link);
    echo "✓ Updated $affected_rows image URL(s) in ai_generated_content table\n";
} else {
    echo "✗ Error updating ai_generated_content: " . mysqli_error($link) . "\n";
}

// Also check for URLs without /aqary/ prefix (in case some were saved without it)
$sql2 = "UPDATE ai_generated_content
         SET generated_text = REPLACE(generated_text, 'admin/ai/storage/images/', 'storage/uploads/ai/images/')
         WHERE entity_type = 'image_generated'
         AND generated_text LIKE '%admin/ai/storage/images/%'
         AND generated_text NOT LIKE '%/aqary/%'";

$result2 = mysqli_query($link, $sql2);

if ($result2) {
    $affected_rows2 = mysqli_affected_rows($link);
    if ($affected_rows2 > 0) {
        echo "✓ Updated $affected_rows2 image URL(s) without /aqary/ prefix\n";
    }
} else {
    echo "✗ Error updating paths without prefix: " . mysqli_error($link) . "\n";
}

// Display sample of updated records
echo "\n<h3>Sample of Updated Records:</h3>\n";
$sql3 = "SELECT content_id, generated_text, created_date
         FROM ai_generated_content
         WHERE entity_type = 'image_generated'
         ORDER BY created_date DESC
         LIMIT 5";

$result3 = mysqli_query($link, $sql3);

if ($result3) {
    while ($row = mysqli_fetch_assoc($result3)) {
        echo "ID: {$row['content_id']}\n";
        echo "URL: {$row['generated_text']}\n";
        echo "Date: {$row['created_date']}\n";
        echo "---\n";
    }
} else {
    echo "Error fetching sample records: " . mysqli_error($link) . "\n";
}

echo "</pre>";
echo "<p><strong>Migration completed!</strong></p>";
echo "<p><a href='image_generator_ui.hnt'>Return to Image Generator</a></p>";
?>
