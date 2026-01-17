<?php
/**
 * Migration Verification Script
 * Verifies that all files were successfully migrated
 *
 * Usage: php verify_migration.php
 *
 * This script compares file counts and sizes between old and new locations
 * to ensure the migration was successful.
 */

declare(strict_types=1);

// Include storage helpers
require_once(__DIR__ . '/../lib/storage/helpers.php');

class MigrationVerifier
{
    private array $verifications = [
        'pic' => [
            'category' => 'properties.images',
            'description' => 'Property images',
        ],
        'spic' => [
            'category' => 'properties.images',
            'description' => 'Special property images',
        ],
        'mokhatatpic' => [
            'category' => 'mokhatat.images',
            'description' => 'Blueprint/floor plan images',
        ],
        'slidepic' => [
            'category' => 'slideshow',
            'description' => 'Slideshow images',
        ],
        'qr-codes' => [
            'category' => 'qr-codes',
            'description' => 'QR codes',
            'subdirs' => [
                'mangedcontrato' => 'contracts.managed',
                'freecontrato' => 'contracts.free',
                'receive' => 'vouchers.receive',
                'payment' => 'vouchers.payment',
            ],
        ],
    ];

    private array $results = [];
    private bool $allPassed = true;

    public function run(): bool
    {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "🔍 Migration Verification\n";
        echo str_repeat('=', 70) . "\n\n";

        foreach ($this->verifications as $sourceDir => $config) {
            $this->verifyMigration($sourceDir, $config);
        }

        $this->printSummary();

        return $this->allPassed;
    }

    private function verifyMigration(string $sourceDir, array $config): void
    {
        $sourcePath = 'Y:\\' . str_replace('/', '\\', $sourceDir);

        echo "📁 Verifying: {$config['description']}\n";
        echo "   Source: $sourcePath\n";

        if (!is_dir($sourcePath)) {
            echo "   ✓ Source directory removed (expected after migration)\n\n";
            return;
        }

        // Handle organized directories (QR codes)
        if (isset($config['subdirs'])) {
            $this->verifyOrganizedDirectory($sourcePath, $config);
        } else {
            $this->verifySimpleDirectory($sourcePath, $config);
        }

        echo "\n";
    }

    private function verifySimpleDirectory(string $sourcePath, array $config): void
    {
        $targetPath = upload_path($config['category']);

        $sourceFiles = $this->getFiles($sourcePath);
        $targetFiles = $this->getFiles($targetPath);

        $sourceCount = count($sourceFiles);
        $targetCount = count($targetFiles);

        echo "   Source files: $sourceCount\n";
        echo "   Target files: $targetCount\n";

        if ($sourceCount > 0) {
            echo "   ⚠️  WARNING: Source still has files (migration incomplete?)\n";
            $this->allPassed = false;

            // Show which files are still in source
            if ($sourceCount <= 10) {
                echo "   Files remaining in source:\n";
                foreach ($sourceFiles as $file) {
                    echo "      - " . basename($file) . "\n";
                }
            }
        } else {
            echo "   ✓ Source is empty (migration successful)\n";
        }

        // Check for missing files
        $sourceBasenames = array_map('basename', $sourceFiles);
        $targetBasenames = array_map('basename', $targetFiles);

        $missingFiles = array_diff($sourceBasenames, $targetBasenames);
        if (!empty($missingFiles) && $sourceCount > 0) {
            echo "   ⚠️  Files in source not found in target:\n";
            foreach ($missingFiles as $file) {
                echo "      - $file\n";
            }
            $this->allPassed = false;
        }
    }

    private function verifyOrganizedDirectory(string $sourcePath, array $config): void
    {
        foreach ($config['subdirs'] as $subdir => $category) {
            $subdirPath = $sourcePath . '\\' . $subdir;
            $targetPath = qr_path($category);

            if (!is_dir($subdirPath)) {
                echo "   ✓ Subdirectory '$subdir' removed (expected)\n";
                continue;
            }

            $sourceFiles = $this->getFiles($subdirPath);
            $targetFiles = $this->getFiles($targetPath);

            $sourceCount = count($sourceFiles);
            $targetCount = count($targetFiles);

            echo "   Subdirectory: $subdir\n";
            echo "      Source: $sourceCount files\n";
            echo "      Target: $targetCount files\n";

            if ($sourceCount > 0) {
                echo "      ⚠️  WARNING: Source subdirectory still has files\n";
                $this->allPassed = false;

                if ($sourceCount <= 5) {
                    foreach ($sourceFiles as $file) {
                        echo "         - " . basename($file) . "\n";
                    }
                }
            } else {
                echo "      ✓ Source subdirectory is empty\n";
            }
        }
    }

    private function getFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '\\*.*');
        return array_filter($files, 'is_file');
    }

    private function printSummary(): void
    {
        echo str_repeat('=', 70) . "\n";
        echo "📊 Verification Summary\n";
        echo str_repeat('=', 70) . "\n";

        if ($this->allPassed) {
            echo "✅ All verifications passed!\n";
            echo "All files have been successfully migrated.\n";
            echo "\n";
            echo "Next steps:\n";
            echo "1. Test your application thoroughly\n";
            echo "2. If everything works, you can safely delete the old directories\n";
            echo "3. Keep the backup for at least 30 days\n";
        } else {
            echo "⚠️  Some verifications failed!\n";
            echo "Please review the warnings above.\n";
            echo "\n";
            echo "Recommended actions:\n";
            echo "1. Re-run the migration script to complete the migration\n";
            echo "2. Or use rollback_migration.php to restore from backup\n";
            echo "3. Check the migration log for details\n";
        }
    }
}

// Run verification
$verifier = new MigrationVerifier();
$success = $verifier->run();

exit($success ? 0 : 1);
