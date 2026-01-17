<?php
/**
 * Fix AI Image URLs - Auto Migration
 * This script automatically updates all old image URLs to the new storage path
 *
 * Run once by visiting: http://192.168.0.105:9009/aqary/admin/ai/features/fix_image_urls.php
 */

// Include database connection
require_once('../../../connectdb.hnt');

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔧 Fixing AI Image URLs...</h2>";
echo "<pre>";

// Update image URLs in ai_generated_content table - Pattern 1: with /aqary/ prefix
$sql1 = "UPDATE ai_generated_content
        SET generated_text = REPLACE(generated_text, '/aqary/admin/ai/storage/images/', '/aqary/storage/uploads/ai/images/')
        WHERE entity_type = 'image_generated'
        AND generated_text LIKE '%/aqary/admin/ai/storage/images/%'";

$result1 = mysql_query($sql1, $link);

if ($result1) {
    $affected1 = mysql_affected_rows($link);
    echo "✓ Updated $affected1 image URL(s) with /aqary/ prefix\n";
} else {
    echo "✗ Error: " . mysql_error($link) . "\n";
}

// Update image URLs - Pattern 2: without /aqary/ prefix
$sql2 = "UPDATE ai_generated_content
         SET generated_text = REPLACE(generated_text, 'admin/ai/storage/images/', 'storage/uploads/ai/images/')
         WHERE entity_type = 'image_generated'
         AND generated_text LIKE '%admin/ai/storage/images/%'
         AND generated_text NOT LIKE '%/aqary/%'";

$result2 = mysql_query($sql2, $link);

if ($result2) {
    $affected2 = mysql_affected_rows($link);
    if ($affected2 > 0) {
        echo "✓ Updated $affected2 image URL(s) without /aqary/ prefix\n";
    }
} else {
    echo "✗ Error: " . mysql_error($link) . "\n";
}

// Update image URLs - Pattern 3: any remaining admin/ai/storage variations
$sql3 = "UPDATE ai_generated_content
         SET generated_text = CONCAT('/aqary/storage/uploads/ai/images/',
                                     SUBSTRING_INDEX(generated_text, '/', -1))
         WHERE entity_type = 'image_generated'
         AND (generated_text LIKE '%admin/ai/storage%'
              OR generated_text LIKE '%ai/storage/images%')
         AND generated_text NOT LIKE '%/aqary/storage/uploads/ai/images/%'";

$result3 = mysql_query($sql3, $link);

if ($result3) {
    $affected3 = mysql_affected_rows($link);
    if ($affected3 > 0) {
        echo "✓ Fixed $affected3 remaining image URL(s)\n";
    }
} else {
    echo "✗ Error: " . mysql_error($link) . "\n";
}

$total_affected = $affected1 + $affected2 + ($affected3 ?? 0);

echo "\n========================================\n";
echo "✅ Migration Complete!\n";
echo "Total records updated: $total_affected\n";
echo "========================================\n\n";

// Show current records
echo "Sample of updated records:\n";
echo "----------------------------------------\n";
$sql_check = "SELECT content_id, generated_text, created_date
              FROM ai_generated_content
              WHERE entity_type = 'image_generated'
              ORDER BY created_date DESC
              LIMIT 10";

$result_check = mysql_query($sql_check, $link);

if ($result_check) {
    $count = 0;
    while ($row = mysql_fetch_assoc($result_check)) {
        $count++;
        echo "$count. ID: {$row['content_id']}\n";
        echo "   URL: {$row['generated_text']}\n";
        echo "   Date: {$row['created_date']}\n\n";
    }

    if ($count == 0) {
        echo "No image records found in database.\n";
    }
} else {
    echo "Error checking records: " . mysql_error($link) . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<p><strong>✅ All done!</strong> You can now <a href='image_generator_ui.hnt'>return to the Image Generator</a></p>";
echo "<p>All existing images should now display with the correct storage path.</p>";
?>
