<?php
/**
 * FileHandler Class
 * Handles all file-related operations: upload, download, delete, move, copy, etc.
 */

class FileHandler {
    private $link;
    private $upload_dir;
    private $allowed_extensions = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
        'txt', 'csv', 'json', 'xml',
        'zip', 'rar', '7z',
        'mp4', 'avi', 'mov', 'mkv', 'webm',
        'mp3', 'wav', 'flac'
    ];
    private $max_file_size = 104857600; // 100MB
    private $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'bmp' => 'image/bmp',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'csv' => 'text/csv'
    ];

    public function __construct($db_link) {
        $this->link = $db_link;
        $this->upload_dir = dirname(__FILE__) . '/../storage/uploads/';
    }

    /**
     * Validate a file upload
     */
    public function validateFile($file) {
        // Check if file exists
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'الملف غير موجود'];
        }

        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return ['valid' => false, 'error' => 'حجم الملف يتجاوز الحد المسموح به'];
        }

        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'حدث خطأ في التحميل'];
        }

        // Check file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $this->allowed_extensions)) {
            return ['valid' => false, 'error' => 'نوع الملف غير مدعوم'];
        }

        // Check MIME type
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            // Note: finfo_close() removed - auto-freed in PHP 8.5

            // Validate MIME type matches extension
            $expected_mime = $this->mime_types[$file_ext] ?? null;
            if ($expected_mime && !str_contains($mime_type, explode('/', $expected_mime)[0])) {
                return ['valid' => false, 'error' => 'نوع الملف غير صحيح'];
            }
        }

        return ['valid' => true];
    }

    /**
     * Get file icon based on extension
     */
    public function getFileIcon($file_type) {
        $icons = [
            'pdf' => 'bi-file-earmark-pdf',
            'doc' => 'bi-file-earmark-word',
            'docx' => 'bi-file-earmark-word',
            'xls' => 'bi-file-earmark-spreadsheet',
            'xlsx' => 'bi-file-earmark-spreadsheet',
            'ppt' => 'bi-file-earmark-presentation',
            'pptx' => 'bi-file-earmark-presentation',
            'jpg' => 'bi-file-earmark-image',
            'jpeg' => 'bi-file-earmark-image',
            'png' => 'bi-file-earmark-image',
            'gif' => 'bi-file-earmark-image',
            'bmp' => 'bi-file-earmark-image',
            'webp' => 'bi-file-earmark-image',
            'svg' => 'bi-file-earmark-image',
            'zip' => 'bi-file-earmark-zip',
            'rar' => 'bi-file-earmark-zip',
            'txt' => 'bi-file-earmark-text',
            'csv' => 'bi-file-earmark-spreadsheet',
        ];

        return $icons[strtolower($file_type)] ?? 'bi-file-earmark';
    }

    /**
     * Get file color code based on type
     */
    public function getFileColor($file_type) {
        $colors = [
            'pdf' => '#e74c3c',
            'doc' => '#3498db',
            'docx' => '#3498db',
            'xls' => '#27ae60',
            'xlsx' => '#27ae60',
            'ppt' => '#e67e22',
            'pptx' => '#e67e22',
            'jpg' => '#9b59b6',
            'jpeg' => '#9b59b6',
            'png' => '#9b59b6',
            'gif' => '#9b59b6',
            'bmp' => '#9b59b6',
            'webp' => '#9b59b6',
            'svg' => '#9b59b6',
            'zip' => '#f39c12',
            'rar' => '#f39c12',
            'txt' => '#34495e',
            'csv' => '#27ae60',
        ];

        return $colors[strtolower($file_type)] ?? '#95a5a6';
    }

    /**
     * Check if file is an image
     */
    public function isImage($file_type) {
        $image_types = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        return in_array(strtolower($file_type), $image_types);
    }

    /**
     * Check if file is a document
     */
    public function isDocument($file_type) {
        $doc_types = ['pdf', 'doc', 'docx', 'txt'];
        return in_array(strtolower($file_type), $doc_types);
    }

    /**
     * Check if file is a spreadsheet
     */
    public function isSpreadsheet($file_type) {
        $sheet_types = ['xls', 'xlsx', 'csv'];
        return in_array(strtolower($file_type), $sheet_types);
    }

    /**
     * Check if file is a media file
     */
    public function isMedia($file_type) {
        $media_types = ['mp4', 'avi', 'mov', 'mkv', 'mp3', 'wav', 'flac'];
        return in_array(strtolower($file_type), $media_types);
    }

    /**
     * Get file category by type
     */
    public function getFileCategory($file_type) {
        if ($this->isImage($file_type)) return 'image';
        if ($this->isDocument($file_type)) return 'document';
        if ($this->isSpreadsheet($file_type)) return 'spreadsheet';
        if ($this->isMedia($file_type)) return 'media';
        return 'other';
    }

    /**
     * Format file size for display
     */
    public static function formatFileSize($bytes) {
        if ($bytes == 0) return '0 B';

        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, $k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Format date for display
     */
    public static function formatDate($date) {
        $time = strtotime($date);
        $now = time();
        $diff = $now - $time;

        if ($diff < 60) {
            return 'الآن';
        } elseif ($diff < 3600) {
            return 'منذ ' . intval($diff / 60) . ' دقيقة';
        } elseif ($diff < 86400) {
            return 'منذ ' . intval($diff / 3600) . ' ساعة';
        } elseif ($diff < 604800) {
            return 'منذ ' . intval($diff / 86400) . ' أيام';
        } else {
            return date('Y-m-d', $time);
        }
    }

    /**
     * Get file information by ID
     */
    public function getFileInfo($file_id, $userid = null) {
        try {
            $sql = "SELECT * FROM file_management WHERE file_id = ? AND is_deleted = 0";
            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return null;
            }

            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $stmt->close();
                return null;
            }

            $file = $result->fetch_assoc();
            $stmt->close();

            // If userid is provided, check if user has access
            if ($userid) {
                if ($file['owner_userid'] != $userid) {
                    // Check if file is shared with user
                    $share_check = "SELECT * FROM file_sharing
                                    WHERE file_id = ?
                                    AND shared_with_userid = ?
                                    AND is_active = 1
                                    AND (expiry_date IS NULL OR expiry_date > NOW())";
                    $share_stmt = $this->link->prepare($share_check);
                    if (!$share_stmt) {
                        return null;
                    }
                    $share_stmt->bind_param("ii", $file_id, $userid);
                    $share_stmt->execute();
                    $share_result = $share_stmt->get_result();
                    if ($share_result->num_rows == 0) {
                        $share_stmt->close();
                        return null; // No access
                    }
                    $share_stmt->close();
                }
            }

            return $file;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get files by user
     */
    public function getUserFiles($userid, $limit = 20, $offset = 0) {
        try {
            $sql = "SELECT * FROM file_management
                    WHERE owner_userid = ? AND is_deleted = 0
                    ORDER BY created_date DESC
                    LIMIT ?, ?";

            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param("iii", $userid, $offset, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $files = [];
            while ($file = $result->fetch_assoc()) {
                $files[] = $file;
            }

            $stmt->close();
            return $files;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get category statistics
     */
    public function getCategoryStats($userid) {
        try {
            $sql = "SELECT category, COUNT(*) as count, SUM(file_size) as total_size
                    FROM file_management
                    WHERE owner_userid = ? AND is_deleted = 0
                    GROUP BY category";

            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param("i", $userid);
            $stmt->execute();
            $result = $stmt->get_result();

            $stats = [];
            while ($row = $result->fetch_assoc()) {
                $stats[$row['category']] = [
                    'count' => $row['count'],
                    'total_size' => $row['total_size']
                ];
            }

            $stmt->close();
            return $stats;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get file type statistics
     */
    public function getFileTypeStats($userid) {
        try {
            $sql = "SELECT file_type, COUNT(*) as count, SUM(file_size) as total_size
                    FROM file_management
                    WHERE owner_userid = ? AND is_deleted = 0
                    GROUP BY file_type
                    ORDER BY count DESC
                    LIMIT 10";

            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param("i", $userid);
            $stmt->execute();
            $result = $stmt->get_result();

            $stats = [];
            while ($row = $result->fetch_assoc()) {
                $stats[$row['file_type']] = [
                    'count' => $row['count'],
                    'total_size' => $row['total_size'],
                    'icon' => $this->getFileIcon($row['file_type']),
                    'color' => $this->getFileColor($row['file_type'])
                ];
            }

            $stmt->close();
            return $stats;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get storage statistics
     */
    public function getStorageStats($userid) {
        try {
            $storage_stats = [
                'total_files' => 0,
                'total_size' => 0,
                'total_categories' => 0,
                'shared_files' => 0,
                'total_size_formatted' => '0 B'
            ];

            $sql = "SELECT COUNT(*) as total_files,
                           SUM(file_size) as total_size,
                           COUNT(DISTINCT category) as total_categories
                    FROM file_management
                    WHERE owner_userid = ? AND is_deleted = 0";

            $stmt = $this->link->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $userid);
                $stmt->execute();
                $result = $stmt->get_result();
                $stats = $result->fetch_assoc();
                $storage_stats['total_files'] = intval($stats['total_files']);
                $storage_stats['total_size'] = intval($stats['total_size']);
                $storage_stats['total_categories'] = intval($stats['total_categories']);
                $storage_stats['total_size_formatted'] = self::formatFileSize($stats['total_size']);
                $stmt->close();
            }

            // Get shared files count
            $shared_sql = "SELECT COUNT(DISTINCT file_id) as shared_count
                           FROM file_sharing
                           WHERE shared_by_userid = ? AND is_active = 1";
            $shared_stmt = $this->link->prepare($shared_sql);
            if ($shared_stmt) {
                $shared_stmt->bind_param("i", $userid);
                $shared_stmt->execute();
                $shared_result = $shared_stmt->get_result();
                $shared_stats = $shared_result->fetch_assoc();
                $storage_stats['shared_files'] = intval($shared_stats['shared_count']);
                $shared_stmt->close();
            }

            return $storage_stats;
        } catch (Exception $e) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'total_categories' => 0,
                'shared_files' => 0,
                'total_size_formatted' => '0 B'
            ];
        }
    }

    /**
     * Restore deleted file
     */
    public function restoreFile($file_id, $userid) {
        try {
            $sql = "SELECT * FROM file_management WHERE file_id = ? AND owner_userid = ?";
            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'message' => 'خطأ في قاعدة البيانات'];
            }

            $stmt->bind_param("ii", $file_id, $userid);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $stmt->close();
                return ['success' => false, 'message' => 'الملف غير موجود'];
            }
            $stmt->close();

            $update_sql = "UPDATE file_management
                           SET is_deleted = 0, deleted_date = NULL
                           WHERE file_id = ?";

            $update_stmt = $this->link->prepare($update_sql);
            if (!$update_stmt) {
                return ['success' => false, 'message' => 'خطأ في قاعدة البيانات'];
            }

            $update_stmt->bind_param("i", $file_id);
            if ($update_stmt->execute()) {
                $update_stmt->close();
                return ['success' => true, 'message' => 'تم استعادة الملف بنجاح'];
            } else {
                $update_stmt->close();
                return ['success' => false, 'message' => 'فشل استعادة الملف'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطأ في النظام'];
        }
    }

    /**
     * Permanently delete file
     */
    public function permanentlyDeleteFile($file_id, $userid) {
        try {
            $file = $this->getFileInfo($file_id, $userid);

            if (!$file) {
                return ['success' => false, 'message' => 'الملف غير موجود'];
            }

            // Only owner can permanently delete
            if ($file['owner_userid'] != $userid) {
                return ['success' => false, 'message' => 'ليس لديك صلاحية حذف هذا الملف'];
            }

            // Delete from database
            $sql = "DELETE FROM file_management WHERE file_id = ?";
            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'message' => 'فشل حذف الملف من قاعدة البيانات'];
            }

            $stmt->bind_param("i", $file_id);
            if (!$stmt->execute()) {
                $stmt->close();
                return ['success' => false, 'message' => 'فشل حذف الملف من قاعدة البيانات'];
            }
            $stmt->close();

            // Delete physical file
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }

            return ['success' => true, 'message' => 'تم حذف الملف نهائياً'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطأ في النظام'];
        }
    }

    /**
     * Move file to another category
     */
    public function moveFileToCategory($file_id, $new_category, $userid) {
        try {
            $file = $this->getFileInfo($file_id, $userid);

            if (!$file || $file['owner_userid'] != $userid) {
                return ['success' => false, 'message' => 'الملف غير موجود أو ليس لديك صلاحية'];
            }

            $sql = "UPDATE file_management
                    SET category = ?, modified_date = NOW()
                    WHERE file_id = ?";

            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'message' => 'خطأ في قاعدة البيانات'];
            }

            $stmt->bind_param("si", $new_category, $file_id);
            if ($stmt->execute()) {
                $stmt->close();
                return ['success' => true, 'message' => 'تم نقل الملف بنجاح'];
            } else {
                $stmt->close();
                return ['success' => false, 'message' => 'فشل نقل الملف'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطأ في النظام'];
        }
    }

    /**
     * Update file description
     */
    public function updateFileDescription($file_id, $description, $userid) {
        try {
            $file = $this->getFileInfo($file_id, $userid);

            if (!$file || $file['owner_userid'] != $userid) {
                return ['success' => false, 'message' => 'الملف غير موجود أو ليس لديك صلاحية'];
            }

            $sql = "UPDATE file_management
                    SET description = ?, modified_date = NOW()
                    WHERE file_id = ?";

            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'message' => 'خطأ في قاعدة البيانات'];
            }

            $stmt->bind_param("si", $description, $file_id);
            if ($stmt->execute()) {
                $stmt->close();
                return ['success' => true, 'message' => 'تم تحديث الملف بنجاح'];
            } else {
                $stmt->close();
                return ['success' => false, 'message' => 'فشل تحديث الملف'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطأ في النظام'];
        }
    }

    /**
     * Check if username exists
     */
    public function userExists($username) {
        try {
            $sql = "SELECT userid FROM user WHERE username = ? LIMIT 1";
            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();
            return $exists;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get user ID by username
     */
    public function getUserIdByUsername($username) {
        try {
            $sql = "SELECT userid FROM user WHERE username = ? LIMIT 1";
            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row['userid'];
            }
            $stmt->close();
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
}
?>
