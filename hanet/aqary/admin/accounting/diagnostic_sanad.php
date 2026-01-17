<?php
include '../../connectdb.hnt';

echo "<h2>Diagnostic: Sanad Table Investigation</h2>";
echo "<pre>";

// 1. Check table structure
echo "=== 1. Sanad Table Structure ===\n";
$result = $db->query("SHOW COLUMNS FROM sanad");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf("%-30s %-15s %-10s\n", $row['Field'], $row['Type'], $row['Null']);
    }
} else {
    echo "Error: " . $db->error . "\n";
}

echo "\n=== 2. Check if accounting_entry_id column exists ===\n";
$checkColumn = $db->query("SHOW COLUMNS FROM sanad LIKE 'accounting_entry_id'");
if ($checkColumn && $checkColumn->num_rows > 0) {
    echo "✓ Column 'accounting_entry_id' EXISTS\n";
} else {
    echo "✗ Column 'accounting_entry_id' DOES NOT EXIST\n";
}

echo "\n=== 3. Sample Records from sanad (last 30 days) ===\n";
$query = "SELECT idsanad, sanadrakam, mybyan, sanaddate, accounting_entry_id
          FROM sanad
          WHERE sanaddate >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 30 DAY))
          ORDER BY sanaddate DESC
          LIMIT 10";
$result = $db->query($query);
if ($result) {
    echo sprintf("%-10s %-30s %-30s %-15s %-10s\n",
                 "idsanad", "sanadrakam", "mybyan", "sanaddate", "acc_entry_id");
    echo str_repeat("-", 100) . "\n";
    while ($row = $result->fetch_assoc()) {
        $date = $row['sanaddate'] ? date('Y-m-d', $row['sanaddate']) : 'NULL';
        echo sprintf("%-10s %-30s %-30s %-15s %-10s\n",
                     $row['idsanad'],
                     substr($row['sanadrakam'], 0, 28),
                     substr($row['mybyan'] ?? '', 0, 28),
                     $date,
                     $row['accounting_entry_id'] ?? 'NULL');
    }
} else {
    echo "Error: " . $db->error . "\n";
}

echo "\n=== 4. Count records by accounting_entry_id status ===\n";
$query = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN accounting_entry_id IS NULL OR accounting_entry_id = 0 THEN 1 ELSE 0 END) as unsynced,
            SUM(CASE WHEN accounting_entry_id IS NOT NULL AND accounting_entry_id > 0 THEN 1 ELSE 0 END) as synced
          FROM sanad
          WHERE sanaddate >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 30 DAY))";
$result = $db->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total vouchers (last 30 days): {$row['total']}\n";
    echo "Unsynced: {$row['unsynced']}\n";
    echo "Synced: {$row['synced']}\n";
} else {
    echo "Error: " . $db->error . "\n";
}

echo "\n=== 5. Test UPDATE statement ===\n";
$query = "SELECT idsanad, sanadrakam FROM sanad
          WHERE sanaddate >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 30 DAY))
          AND (accounting_entry_id IS NULL OR accounting_entry_id = 0)
          LIMIT 1";
$result = $db->query($query);
if ($result && $result->num_rows > 0) {
    $testRow = $result->fetch_assoc();
    echo "Testing UPDATE on idsanad={$testRow['idsanad']}, sanadrakam={$testRow['sanadrakam']}\n";

    // Try to update with a test value
    $testEntryId = 99999;
    $updateQuery = "UPDATE sanad SET accounting_entry_id = ? WHERE idsanad = ?";
    $stmt = $db->prepare($updateQuery);
    if ($stmt) {
        $stmt->bind_param('ii', $testEntryId, $testRow['idsanad']);
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            echo "✓ UPDATE successful, affected rows: $affectedRows\n";

            // Verify the update
            $verifyQuery = "SELECT accounting_entry_id FROM sanad WHERE idsanad = {$testRow['idsanad']}";
            $verifyResult = $db->query($verifyQuery);
            if ($verifyResult) {
                $verifyRow = $verifyResult->fetch_assoc();
                echo "Verification: accounting_entry_id = {$verifyRow['accounting_entry_id']}\n";
            }

            // Rollback the test
            $db->query("UPDATE sanad SET accounting_entry_id = NULL WHERE idsanad = {$testRow['idsanad']}");
            echo "✓ Test rolled back\n";
        } else {
            echo "✗ UPDATE failed: " . $stmt->error . "\n";
        }
        $stmt->close();
    } else {
        echo "✗ Prepare failed: " . $db->error . "\n";
    }
} else {
    echo "No unsynced records found for testing\n";
}

echo "\n=== 6. Check for 'FRESH' records ===\n";
$query = "SELECT COUNT(*) as count FROM sanad WHERE sanadrakam LIKE '%FRESH%'";
$result = $db->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Records with 'FRESH' in sanadrakam: {$row['count']}\n";

    if ($row['count'] > 0) {
        echo "\nSample FRESH records:\n";
        $sampleQuery = "SELECT idsanad, sanadrakam, mybyan, sanaddate FROM sanad WHERE sanadrakam LIKE '%FRESH%' LIMIT 5";
        $sampleResult = $db->query($sampleQuery);
        while ($sampleRow = $sampleResult->fetch_assoc()) {
            echo "  ID: {$sampleRow['idsanad']}, Number: {$sampleRow['sanadrakam']}\n";
        }
    }
}

echo "</pre>";
?>
