<?php
/**
 * Storage Path Helper Class (PHP 8.5)
 * Provides helper methods for storage path management
 */

declare(strict_types=1);

readonly class StoragePath
{
    public function __construct(
        public string $root = STORAGE_ROOT,
        public string $app = STORAGE_APP,
        public string $cache = STORAGE_CACHE,
        public string $logs = STORAGE_LOGS,
        public string $tmp = STORAGE_TMP,
        public string $uploads = STORAGE_UPLOADS,
        public string $generated = STORAGE_GENERATED,
        public string $protected = STORAGE_PROTECTED,
        public string $backups = STORAGE_BACKUPS,
    ) {}

    /**
     * Get path relative to storage root
     */
    public static function get(string $path): string
    {
        return STORAGE_ROOT . ltrim($path, '/\\');
    }

    /**
     * Ensure directory exists
     */
    public static function ensure(string $path, int $permissions = 0755): bool
    {
        if (!is_dir($path)) {
            return @mkdir($path, $permissions, true);
        }
        return true;
    }

    /**
     * Get upload path for specific category
     */
    public static function upload(string $category, string $filename = ''): string
    {
        $paths = [
            'properties.images' => STORAGE_UPLOADS_IMAGES,
            'properties.blueprints' => STORAGE_UPLOADS_BLUEPRINTS,
            'media.slides' => STORAGE_UPLOADS_MEDIA . 'slides' . DIRECTORY_SEPARATOR,
            'media.tvdisplay' => STORAGE_UPLOADS_MEDIA_TVDISPLAY,
            'documents.contracts' => STORAGE_UPLOADS_DOCUMENTS . 'contracts' . DIRECTORY_SEPARATOR,
            'documents.reports' => STORAGE_UPLOADS_DOCUMENTS_REPORTS,
            'documents.reports.pdf' => STORAGE_UPLOADS_DOCUMENTS_REPORTS_PDF,
            'documents.templates.word' => STORAGE_UPLOADS_DOCUMENTS_TEMPLATES_WORD,
            'documents.templates.original' => STORAGE_UPLOADS_DOCUMENTS_TEMPLATES_ORIGINAL,
            'communications.email' => STORAGE_UPLOADS_COMMUNICATIONS_EMAIL,
            'communications.email.maildata' => STORAGE_UPLOADS_COMMUNICATIONS_EMAIL_DATA,
            'communications.messaging' => STORAGE_UPLOADS_COMMUNICATIONS_MESSAGING,
            'communications.whatsapp' => STORAGE_UPLOADS_COMMUNICATIONS . 'whatsapp' . DIRECTORY_SEPARATOR,
            'ai.images' => STORAGE_UPLOADS_AI_IMAGES,
            'ai.audio' => STORAGE_UPLOADS_AI_AUDIO,
            'whatsapp.uploads' => STORAGE_UPLOADS_WHATSAPP_FILES,
            'whatsapp.logs' => STORAGE_APP . 'logs' . DIRECTORY_SEPARATOR . 'whatsapp' . DIRECTORY_SEPARATOR,
            'slideshow' => STORAGE_UPLOADS_SLIDESHOW,
            'mokhatat.images' => STORAGE_UPLOADS_MOKHATAT,
            'framework.file-management.uploads' => STORAGE_FRAMEWORK_FILE_MANAGEMENT_UPLOADS,
            'framework.file-management.cache' => STORAGE_FRAMEWORK_FILE_MANAGEMENT_CACHE,
            'framework.file-management.logs' => STORAGE_FRAMEWORK_FILE_MANAGEMENT_LOGS,
            'uploadcenter' => STORAGE_UPLOADS . 'uploadcenter' . DIRECTORY_SEPARATOR,
            'uploadcenter.thumbnails' => STORAGE_UPLOADS . 'uploadcenter' . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR,
        ];

        $base = $paths[$category] ?? STORAGE_UPLOADS;
        self::ensure($base);

        return $filename ? $base . $filename : $base;
    }

    /**
     * Get QR code path
     */
    public static function qrCode(string $type, string $filename = ''): string
    {
        $types = [
            'vouchers' => STORAGE_QR_CODES . 'vouchers' . DIRECTORY_SEPARATOR . 'receive' . DIRECTORY_SEPARATOR,
            'vouchers.receive' => STORAGE_QR_CODES . 'vouchers' . DIRECTORY_SEPARATOR . 'receive' . DIRECTORY_SEPARATOR,
            'vouchers.payment' => STORAGE_QR_CODES . 'vouchers' . DIRECTORY_SEPARATOR . 'payment' . DIRECTORY_SEPARATOR,
            'contracts.free' => STORAGE_QR_CODES . 'contracts' . DIRECTORY_SEPARATOR . 'free' . DIRECTORY_SEPARATOR,
            'contracts.managed' => STORAGE_QR_CODES . 'contracts' . DIRECTORY_SEPARATOR . 'managed' . DIRECTORY_SEPARATOR,
            'properties' => STORAGE_QR_CODES . 'properties' . DIRECTORY_SEPARATOR,
        ];

        $base = $types[$type] ?? STORAGE_QR_CODES;
        self::ensure($base);

        return $filename ? $base . $filename : $base;
    }

    /**
     * Get cache path
     */
    public static function cache(string $category, string $filename = ''): string
    {
        $categories = [
            'config' => STORAGE_CACHE . 'config' . DIRECTORY_SEPARATOR,
            'queries' => STORAGE_CACHE . 'queries' . DIRECTORY_SEPARATOR,
            'sessions' => STORAGE_CACHE . 'sessions' . DIRECTORY_SEPARATOR,
            'views' => STORAGE_CACHE . 'views' . DIRECTORY_SEPARATOR,
        ];

        $base = $categories[$category] ?? STORAGE_CACHE;
        self::ensure($base);

        return $filename ? $base . $filename : $base;
    }

    /**
     * Get log path
     */
    public static function log(string $filename = 'application.log'): string
    {
        self::ensure(STORAGE_LOGS);
        return STORAGE_LOGS . $filename;
    }
}
