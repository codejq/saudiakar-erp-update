<?php
/**
 * Fix All AI Image URL Inconsistencies
 * Standardizes all image URLs to use consistent absolute paths
 *
 * Run by visiting: http://192.168.0.105:9009/aqary/admin/ai/features/fix_all_image_paths.php
 */

require_once('../../../connectdb.hnt');

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔧 Fixing All AI Image URL Inconsistencies...</h2>";
echo "<pre>";

$total_updated = 0;

// Pattern 1: Fix absolute paths without relative prefix
// Example: /storage/uploads/ai/images/file.png → ../../storage/uploads/ai/images/file.png
$sql1 = "UPDATE ai_generated_content
        SET generated_text = CONCAT('../../', SUBSTRING(generated_text, 2))
        WHERE entity_type = 'image_generated'
        AND generated_text LIKE '/storage/uploads/ai/images/%'
        AND generated_text NOT LIKE '../../%'";

$result1 = mysql_query($sql1, $link);
if ($result1) {
    $affected1 = mysql_affected_rows($link);
    if ($affected1 > 0) {
        echo "✓ Fixed $affected1 URL(s) - Converted absolute to relative paths\n";
        $total_updated += $affected1;
    }
} else {
    echo "✗ Error in Pattern 1: " . mysql_error($link) . "\n";
}

// Pattern 2: Fix old relative paths from admin/ai/storage (one level up)
// Example: ../storage/images/file.png → ../../storage/uploads/ai/images/file.png
$sql2 = "UPDATE ai_generated_content
        SET generated_text = CONCAT('../../../storage/uploads/ai/images/',
                                    SUBSTRING_INDEX(generated_text, '/', -1))
        WHERE entity_type = 'image_generated'
        AND (generated_text LIKE '../storage/images/%'
             OR generated_text LIKE '../../storage/%')
        AND generated_text NOT LIKE '../../../storage/%'";

$result2 = mysql_query($sql2, $link);
if ($result2) {
    $affected2 = mysql_affected_rows($link);
    if ($affected2 > 0) {
        echo "✓ Fixed $affected2 URL(s) - Updated from ../storage to ../../storage\n";
        $total_updated += $affected2;
    }
} else {
    echo "✗ Error in Pattern 2: " . mysql_error($link) . "\n";
}

// Pattern 3: Fix old admin/ai/storage/images paths (with /aqary/ prefix)
// Example: /aqary/admin/ai/storage/images/file.png → ../../storage/uploads/ai/images/file.png
$sql3 = "UPDATE ai_generated_content
        SET generated_text = CONCAT('../../storage/uploads/ai/images/',
                                    SUBSTRING_INDEX(generated_text, '/', -1))
        WHERE entity_type = 'image_generated'
        AND generated_text LIKE '%/aqary/admin/ai/storage/images/%'";

$result3 = mysql_query($sql3, $link);
if ($result3) {
    $affected3 = mysql_affected_rows($link);
    if ($affected3 > 0) {
        echo "✓ Fixed $affected3 URL(s) - Converted old absolute paths to relative\n";
        $total_updated += $affected3;
    }
} else {
    echo "✗ Error in Pattern 3: " . mysql_error($link) . "\n";
}

// Pattern 4: Fix admin/ai/storage/images paths (without /aqary/ prefix)
// Example: admin/ai/storage/images/file.png → ../../storage/uploads/ai/images/file.png
$sql4 = "UPDATE ai_generated_content
        SET generated_text = CONCAT('../../storage/uploads/ai/images/',
                                    SUBSTRING_INDEX(generated_text, '/', -1))
        WHERE entity_type = 'image_generated'
        AND generated_text LIKE '%admin/ai/storage/images/%'
        AND generated_text NOT LIKE '../../storage/uploads/ai/images/%'";

$result4 = mysql_query($sql4, $link);
if ($result4) {
    $affected4 = mysql_affected_rows($link);
    if ($affected4 > 0) {
        echo "✓ Fixed $affected4 URL(s) - Converted admin paths to new format\n";
        $total_updated += $affected4;
    }
} else {
    echo "✗ Error in Pattern 4: " . mysql_error($link) . "\n";
}

// Pattern 5: Fix paths that already have /aqary/ prefix (current state)
// Example: /aqary/storage/uploads/ai/images/file.png → ../../storage/uploads/ai/images/file.png
$sql5 = "UPDATE ai_generated_content
        SET generated_text = REPLACE(generated_text, '/aqary/storage/', '../../storage/')
        WHERE entity_type = 'image_generated'
        AND generated_text LIKE '/aqary/storage/%'
        AND generated_text NOT LIKE '../../storage/%'";

$result5 = mysql_query($sql5, $link);
if ($result5) {
    $affected5 = mysql_affected_rows($link);
    if ($affected5 > 0) {
        echo "✓ Fixed $affected5 URL(s) - Removed /aqary/ hardcoding to use relative paths\n";
        $total_updated += $affected5;
    }
} else {
    echo "✗ Error in Pattern 5: " . mysql_error($link) . "\n";
}

echo "\n========================================\n";
echo "✅ Migration Complete!\n";
echo "Total records updated: $total_updated\n";
echo "========================================\n\n";

// Show all current records to verify
echo "All AI Image URLs (verification):\n";
echo "----------------------------------------\n";
$sql_check = "SELECT content_id, generated_text, created_date
              FROM ai_generated_content
              WHERE entity_type = 'image_generated'
              ORDER BY created_date DESC";

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
    } else {
        echo "Total: $count image(s) in database\n";
    }
} else {
    echo "Error checking records: " . mysql_error($link) . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<p><strong>✅ All done!</strong></p>";
echo "<p>All image URLs now use the relative path format: <code>../../storage/uploads/ai/images/filename.png</code></p>";
echo "<p>This makes the application portable without hardcoding the folder name.</p>";
echo "<p><a href='image_generator_ui.hnt'>Return to Image Generator</a></p>";
?>
