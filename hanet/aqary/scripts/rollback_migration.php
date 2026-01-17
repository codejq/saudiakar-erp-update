<?php
/**
 * Migration Rollback Script
 * Restores files from backup if migration needs to be reverted
 *
 * Usage: php rollback_migration.php <backup-name> [--dry-run]
 *
 * Example:
 *   php rollback_migration.php pre-migration-2025-12-27_143022
 *   php rollback_migration.php pre-migration-2025-12-27_143022 --dry-run
 *
 * WARNING: This will restore files from backup and may overwrite
 * any files that were modified after the migration.
 */

declare(strict_types=1);

// Include storage helpers
require_once(__DIR__ . '/../lib/storage/helpers.php');

class MigrationRollback
{
    private string $backupName;
    private bool $dryRun = false;
    private array $errors = [];
    private int $filesRestored = 0;

    private array $rollbackMappings = [
        'pic' => 'pic',
        'spic' => 'spic',
        'mokhatatpic' => 'mokhatatpic',
        'slidepic' => 'slidepic',
        'qr-codes' => 'qr-codes',
    ];

    public function __construct(string $backupName, bool $dryRun = false)
    {
        $this->backupName = $backupName;
        $this->dryRun = $dryRun;

        if ($this->dryRun) {
            echo "🔍 DRY RUN MODE - No files will be restored\n\n";
        }
    }

    public function run(): bool
    {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "🔄 Migration Rollback\n";
        echo str_repeat('=', 70) . "\n";
        echo "Backup: {$this->backupName}\n";
        echo "Date: " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat('=', 70) . "\n\n";

        // Verify backup exists
        $backupPath = STORAGE_BACKUPS . $this->backupName;
        if (!is_dir($backupPath)) {
            echo "❌ Error: Backup directory not found: $backupPath\n";
            echo "\nAvailable backups:\n";
            $this->listAvailableBackups();
            return false;
        }

        echo "✓ Backup found: $backupPath\n\n";

        // Confirm with user (unless dry run)
        if (!$this->dryRun) {
            echo "⚠️  WARNING: This will restore files from backup!\n";
            echo "This may overwrite any files modified after the migration.\n";
            echo "\nType 'yes' to continue: ";

            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);

            if ($line !== 'yes') {
                echo "\n❌ Rollback cancelled.\n";
                return false;
            }
            echo "\n";
        }

        // Process each directory
        foreach ($this->rollbackMappings as $backupDir => $targetDir) {
            $this->rollbackDirectory($backupPath . '\\' . $backupDir, 'Y:\\' . $targetDir);
        }

        // Summary
        $this->printSummary();

        return empty($this->errors);
    }

    private function rollbackDirectory(string $backupDir, string $targetDir): void
    {
        if (!is_dir($backupDir)) {
            echo "⏭️  Skipping: " . basename($backupDir) . " (not in backup)\n";
            return;
        }

        echo "📁 Restoring: " . basename($backupDir) . "\n";
        echo "   From: $backupDir\n";
        echo "   To: $targetDir\n";

        // Ensure target directory exists
        if (!is_dir($targetDir)) {
            if ($this->dryRun) {
                echo "   [DRY RUN] Would create directory: $targetDir\n";
            } else {
                if (!@mkdir($targetDir, 0755, true)) {
                    $this->errors[] = "Failed to create directory: $targetDir";
                    echo "   ❌ Error creating directory\n";
                    return;
                }
            }
        }

        // Copy files recursively
        $this->recursiveCopy($backupDir, $targetDir);

        echo "\n";
    }

    private function recursiveCopy(string $source, string $target): void
    {
        $files = glob($source . '\\*');

        foreach ($files as $file) {
            $targetFile = $target . '\\' . basename($file);

            if (is_dir($file)) {
                // Recursive directory
                if ($this->dryRun) {
                    echo "   [DRY RUN] Would create directory: " . basename($file) . "\n";
                } else {
                    if (!is_dir($targetFile)) {
                        mkdir($targetFile, 0755, true);
                    }
                }
                $this->recursiveCopy($file, $targetFile);
            } else {
                // File
                if ($this->dryRun) {
                    echo "   [DRY RUN] Would restore: " . basename($file) . "\n";
                    $this->filesRestored++;
                } else {
                    if (@copy($file, $targetFile)) {
                        echo "   ✓ Restored: " . basename($file) . "\n";
                        $this->filesRestored++;
                    } else {
                        echo "   ❌ Failed to restore: " . basename($file) . "\n";
                        $this->errors[] = "Failed to copy: $file → $targetFile";
                    }
                }
            }
        }
    }

    private function listAvailableBackups(): void
    {
        $backups = glob(STORAGE_BACKUPS . 'pre-migration-*');

        if (empty($backups)) {
            echo "   (No backups found)\n";
            return;
        }

        foreach ($backups as $backup) {
            echo "   - " . basename($backup) . "\n";
        }
    }

    private function printSummary(): void
    {
        echo str_repeat('=', 70) . "\n";
        echo "📊 Rollback Summary\n";
        echo str_repeat('=', 70) . "\n";

        echo "Files restored: {$this->filesRestored}\n";
        echo "Errors: " . count($this->errors) . "\n";

        if (!empty($this->errors)) {
            echo "\n⚠️  Errors encountered:\n";
            foreach ($this->errors as $error) {
                echo "   - $error\n";
            }
        }

        if ($this->dryRun) {
            echo "\n💡 This was a dry run. Run without --dry-run to actually restore files.\n";
        } elseif (empty($this->errors)) {
            echo "\n✅ Rollback completed successfully!\n";
            echo "\nNext steps:\n";
            echo "1. Test your application\n";
            echo "2. Remove the new storage directories if needed\n";
            echo "3. Re-run the migration when ready\n";
        }
    }
}

// Parse arguments
if ($argc < 2) {
    echo "Usage: php rollback_migration.php <backup-name> [--dry-run]\n";
    echo "\nExample:\n";
    echo "  php rollback_migration.php pre-migration-2025-12-27_143022\n";
    echo "  php rollback_migration.php pre-migration-2025-12-27_143022 --dry-run\n";
    echo "\nAvailable backups:\n";

    $backups = glob(STORAGE_BACKUPS . 'pre-migration-*');
    if (empty($backups)) {
        echo "  (No backups found)\n";
    } else {
        foreach ($backups as $backup) {
            echo "  - " . basename($backup) . "\n";
        }
    }

    exit(1);
}

$backupName = $argv[1];
$dryRun = in_array('--dry-run', array_slice($argv, 2));

$rollback = new MigrationRollback($backupName, $dryRun);
$success = $rollback->run();

exit($success ? 0 : 1);
