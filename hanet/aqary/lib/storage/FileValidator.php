<?php
/**
 * File Upload Validator
 * Validates file uploads for security and type checking
 * Location: Y:\lib\storage\FileValidator.php
 */

declare(strict_types=1);

class FileValidator
{
    private array $file;
    private const MAX_SIZE = 100 * 1024 * 1024; // 100MB

    private const ALLOWED_TYPES = [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
        'media' => ['mp3', 'mp4', 'avi', 'mov'],
        'archives' => ['zip', 'rar', '7z'],
    ];

    private const MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];

    public function __construct(array $file)
    {
        $this->file = $file;
    }

    /**
     * Validate file upload
     */
    public function validate(array $allowed_extensions = []): bool
    {
        // Check upload errors
        if (!isset($this->file['error']) || $this->file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadError());
        }

        // Check file size
        if ($this->file['size'] > self::MAX_SIZE) {
            throw new Exception("File exceeds maximum size of 100MB");
        }

        // Check extension
        $ext = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        if (!empty($allowed_extensions) && !in_array($ext, $allowed_extensions)) {
            throw new Exception("File type .{$ext} not allowed");
        }

        // Validate MIME type if we have a definition
        if (isset(self::MIME_TYPES[$ext])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $this->file['tmp_name']);
            finfo_close($finfo);

            if ($mime !== self::MIME_TYPES[$ext]) {
                throw new Exception("Invalid file MIME type");
            }
        }

        // Check for executable content
        $content = file_get_contents($this->file['tmp_name'], false, null, 0, 1024);
        if (str_contains($content, '<?php') || str_contains($content, '<script')) {
            throw new Exception("File contains executable code");
        }

        return true;
    }

    /**
     * Get upload error message
     */
    private function getUploadError(): string
    {
        if (!isset($this->file['error'])) {
            return 'No file uploaded';
        }

        return match($this->file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            default => 'Unknown upload error'
        };
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        $ext = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        return in_array($ext, self::ALLOWED_TYPES['images']);
    }

    /**
     * Check if file is a document
     */
    public function isDocument(): bool
    {
        $ext = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        return in_array($ext, self::ALLOWED_TYPES['documents']);
    }

    /**
     * Get file extension
     */
    public function getExtension(): string
    {
        return strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
    }

    /**
     * Get file size in bytes
     */
    public function getSize(): int
    {
        return $this->file['size'] ?? 0;
    }
}
