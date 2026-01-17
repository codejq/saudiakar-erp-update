<?php
/**
 * Storage File Migration Script
 * Migrates files from old locations to centralized storage
 *
 * Usage: php migrate_storage_files.php [--dry-run] [--backup] [--verify-only]
 *
 * Options:
 *   --dry-run      Show what would be done without actually moving files
 *   --backup       Create full backup before migration (recommended)
 *   --verify-only  Only verify file counts and sizes, don't migrate
 */

declare(strict_types=1);

// Include storage helpers
require_once(__DIR__ . '/../lib/storage/helpers.php');

class StorageMigration
{
    private bool $dryRun = false;
    private bool $createBackup = false;
    private bool $verifyOnly = false;
    private array $migrationLog = [];
    private array $errors = [];
    private int $filesMoved = 0;
    private int $filesSkipped = 0;

    // Migration mappings: [source_dir => [target_category, recursive, organize_subdirs]]
    private array $migrations = [
        'spic' => [
            'category' => 'properties.images',
            'recursive' => false,
            'organize' => false,
            'description' => 'Special property images',
        ],
        'admin/include/pic' => [
            'category' => 'properties.images',
            'recursive' => false,
            'organize' => false,
            'description' => 'Property images (general)',
        ],
        'admin/include/emara/pic' => [
            'category' => 'properties.images',
            'recursive' => false,
            'organize' => false,
            'description' => 'Property images (emara/buildings)',
        ],
        'admin/include/vila/pic' => [
            'category' => 'properties.images',
            'recursive' => false,
            'organize' => false,
            'description' => 'Property images (villas)',
        ],
        'admin/mokhatatpic' => [
            'category' => 'mokhatat.images',
            'recursive' => false,
            'organize' => false,
            'description' => 'Blueprint/floor plan images (admin)',
        ],
        'admin/slidepic' => [
            'category' => 'slideshow',
            'recursive' => false,
            'organize' => false,
            'description' => 'Slideshow images (admin)',
        ],
        'qr-codes' => [
            'category' => 'qr-codes',
            'recursive' => true,
            'organize' => true,
            'description' => 'QR codes (organized by type)',
            'subdirs' => [
                'mangedcontrato' => 'contracts.managed',
                'freecontrato' => 'contracts.free',
                'receive' => 'vouchers.receive',
                'payment' => 'vouchers.payment',
            ],
        ],
        'pdf-reports' => [
            'category' => 'documents.reports.pdf',
            'recursive' => false,
            'organize' => false,
            'description' => 'PDF reports (generated)',
        ],
        'admin/tvdisplay_media' => [
            'category' => 'media.tvdisplay',
            'recursive' => false,
            'organize' => false,
            'description' => 'TV display media files',
        ],
        'admin/word/original' => [
            'category' => 'documents.templates.original',
            'recursive' => false,
            'organize' => false,
            'description' => 'Word templates (originals)',
        ],
        'admin/ai/storage/images' => [
            'category' => 'ai.images',
            'recursive' => false,
            'organize' => false,
            'description' => 'AI-generated images',
        ],
        'admin/ai/storage/audio' => [
            'category' => 'ai.audio',
            'recursive' => false,
            'organize' => false,
            'description' => 'AI-generated audio (text-to-speech)',
        ],
        'admin/whatsapp/uploads' => [
            'category' => 'whatsapp.uploads',
            'recursive' => false,
            'organize' => false,
            'description' => 'WhatsApp file uploads',
        ],
        'admin/file-management/storage/uploads' => [
            'category' => 'framework.file-management.uploads',
            'recursive' => false,
            'organize' => false,
            'description' => 'File management system uploads',
        ],
    ];

    public function __construct(array $options = [])
    {
        $this->dryRun = in_array('--dry-run', $options);
        $this->createBackup = in_array('--backup', $options);
        $this->verifyOnly = in_array('--verify-only', $options);

        if ($this->dryRun) {
            echo "🔍 DRY RUN MODE - No files will be moved\n\n";
        }
        if ($this->verifyOnly) {
            echo "✓ VERIFY ONLY MODE - Checking file counts and sizes\n\n";
        }
    }

    public function run(): bool
    {
        $this->log("=== Storage Migration Started ===");
        $this->log("Date: " . date('Y-m-d H:i:s'));
        $this->log("Mode: " . ($this->dryRun ? 'DRY RUN' : 'LIVE'));
        $this->log("");

        // Create backup if requested
        if ($this->createBackup && !$this->dryRun && !$this->verifyOnly) {
            $this->createFullBackup();
        }

        // Process each migration
        foreach ($this->migrations as $sourceDir => $config) {
            $this->processMigration($sourceDir, $config);
        }

        // Summary
        $this->printSummary();

        // Save log
        if (!$this->dryRun && !$this->verifyOnly) {
            $this->saveLog();
        }

        return empty($this->errors);
    }

    private function processMigration(string $sourceDir, array $config): void
    {
        $sourcePath = 'Y:\\' . str_replace('/', '\\', $sourceDir);

        echo "\n" . str_repeat('=', 70) . "\n";
        echo "📁 Processing: {$config['description']}\n";
        echo "   Source: $sourcePath\n";
        echo str_repeat('=', 70) . "\n";

        if (!is_dir($sourcePath)) {
            echo "⚠️  Source directory does not exist - skipping\n";
            $this->log("Skipped $sourceDir - directory not found");
            return;
        }

        // Count files
        $fileCount = $this->countFiles($sourcePath, $config['recursive']);
        echo "   Files found: $fileCount\n\n";

        if ($fileCount === 0) {
            echo "ℹ️  No files to migrate\n";
            return;
        }

        if ($this->verifyOnly) {
            $this->verifyDirectory($sourcePath, $config);
            return;
        }

        // Organize subdirectories if configured
        if ($config['organize'] && isset($config['subdirs'])) {
            $this->migrateOrganizedDirectory($sourcePath, $config);
        } else {
            $this->migrateSimpleDirectory($sourcePath, $config);
        }
    }

    private function migrateSimpleDirectory(string $sourcePath, array $config): void
    {
        $files = glob($sourcePath . '\\*.*');

        foreach ($files as $sourceFile) {
            if (!is_file($sourceFile)) {
                continue;
            }

            $filename = basename($sourceFile);
            $targetPath = upload_path($config['category'], $filename);

            $this->moveFile($sourceFile, $targetPath, $config['description']);
        }
    }

    private function migrateOrganizedDirectory(string $sourcePath, array $config): void
    {
        // First, handle files in subdirectories
        if (isset($config['subdirs'])) {
            foreach ($config['subdirs'] as $subdir => $category) {
                $subdirPath = $sourcePath . '\\' . $subdir;

                if (!is_dir($subdirPath)) {
                    echo "   ⚠️  Subdirectory not found: $subdir\n";
                    continue;
                }

                echo "   📂 Processing subdirectory: $subdir → $category\n";

                $files = glob($subdirPath . '\\*.*');
                foreach ($files as $sourceFile) {
                    if (!is_file($sourceFile)) {
                        continue;
                    }

                    $filename = basename($sourceFile);
                    $targetPath = qr_path($category, $filename);

                    $this->moveFile($sourceFile, $targetPath, "$subdir QR codes");
                }
            }
        }

        // Handle any files in the root directory
        $files = glob($sourcePath . '\\*.*');
        foreach ($files as $sourceFile) {
            if (!is_file($sourceFile)) {
                continue;
            }

            $filename = basename($sourceFile);
            // Files in root go to generic QR codes directory
            $targetPath = STORAGE_QR_CODES . $filename;

            $this->moveFile($sourceFile, $targetPath, 'root QR codes');
        }
    }

    private function moveFile(string $source, string $target, string $description): void
    {
        $filename = basename($source);

        // Ensure target directory exists
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            if ($this->dryRun) {
                echo "   [DRY RUN] Would create directory: $targetDir\n";
            } else {
                if (!@mkdir($targetDir, 0755, true)) {
                    $this->errors[] = "Failed to create directory: $targetDir";
                    echo "   ❌ Error creating directory: $targetDir\n";
                    return;
                }
            }
        }

        // Check if target already exists
        if (file_exists($target)) {
            // Compare file sizes
            $sourceSize = filesize($source);
            $targetSize = filesize($target);

            if ($sourceSize === $targetSize) {
                echo "   ⏭️  Skipped (already exists): $filename\n";
                $this->filesSkipped++;
                $this->log("Skipped $filename - already exists with same size");
                return;
            } else {
                echo "   ⚠️  Warning: $filename exists but size differs (source: $sourceSize, target: $targetSize)\n";
            }
        }

        if ($this->dryRun) {
            echo "   [DRY RUN] Would move: $filename → " . basename($targetDir) . "/\n";
            $this->filesMoved++;
        } else {
            // Use copy + unlink for better error handling than rename across filesystems
            if (@copy($source, $target)) {
                if (@unlink($source)) {
                    echo "   ✓ Moved: $filename\n";
                    $this->filesMoved++;
                    $this->log("Moved $description: $filename");
                } else {
                    echo "   ⚠️  Copied but failed to delete source: $filename\n";
                    $this->errors[] = "Failed to delete source: $source";
                }
            } else {
                echo "   ❌ Failed to move: $filename\n";
                $this->errors[] = "Failed to copy: $source → $target";
            }
        }
    }

    private function verifyDirectory(string $sourcePath, array $config): void
    {
        $files = glob($sourcePath . '\\*.*');
        $totalSize = 0;
        $fileTypes = [];

        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $fileTypes[$ext] = ($fileTypes[$ext] ?? 0) + 1;
            }
        }

        echo "   Total size: " . $this->formatBytes($totalSize) . "\n";
        echo "   File types:\n";
        foreach ($fileTypes as $ext => $count) {
            echo "      .$ext: $count files\n";
        }
    }

    private function createFullBackup(): void
    {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "💾 Creating Backup\n";
        echo str_repeat('=', 70) . "\n";

        $backupDir = STORAGE_BACKUPS . 'pre-migration-' . date('Y-m-d_His') . '\\';

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        foreach ($this->migrations as $sourceDir => $config) {
            $sourcePath = 'Y:\\' . str_replace('/', '\\', $sourceDir);

            if (!is_dir($sourcePath)) {
                continue;
            }

            $backupTarget = $backupDir . basename($sourceDir);
            echo "   Backing up: $sourceDir → " . basename($backupDir) . "/" . basename($sourceDir) . "\n";

            $this->recursiveCopy($sourcePath, $backupTarget);
        }

        echo "   ✓ Backup created: $backupDir\n";
        $this->log("Backup created at: $backupDir");
    }

    private function recursiveCopy(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $files = glob($source . '\\*');
        foreach ($files as $file) {
            $targetFile = $target . '\\' . basename($file);

            if (is_dir($file)) {
                $this->recursiveCopy($file, $targetFile);
            } else {
                copy($file, $targetFile);
            }
        }
    }

    private function countFiles(string $dir, bool $recursive): int
    {
        $count = 0;

        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        } else {
            $files = glob($dir . '\\*.*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    private function printSummary(): void
    {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "📊 Migration Summary\n";
        echo str_repeat('=', 70) . "\n";

        if ($this->verifyOnly) {
            echo "✓ Verification completed\n";
        } else {
            echo "Files moved: {$this->filesMoved}\n";
            echo "Files skipped: {$this->filesSkipped}\n";
            echo "Errors: " . count($this->errors) . "\n";

            if (!empty($this->errors)) {
                echo "\n⚠️  Errors encountered:\n";
                foreach ($this->errors as $error) {
                    echo "   - $error\n";
                }
            }

            if ($this->dryRun) {
                echo "\n💡 This was a dry run. Run without --dry-run to actually move files.\n";
            } elseif (empty($this->errors)) {
                echo "\n✅ Migration completed successfully!\n";
            }
        }
    }

    private function log(string $message): void
    {
        $this->migrationLog[] = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    }

    private function saveLog(): void
    {
        $logDir = STORAGE_LOGS;
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . 'migration_' . date('Y-m-d_His') . '.log';
        file_put_contents($logFile, implode("\n", $this->migrationLog));

        echo "\n📝 Migration log saved: $logFile\n";
    }
}

// Run migration
$options = array_slice($argv, 1);
$migration = new StorageMigration($options);
$success = $migration->run();

exit($success ? 0 : 1);
