<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;

final class FileService
{
    private const ALLOWED = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(private Database $db, private string $root)
    {
    }

    public function store(array $file, string $ownerType, int $ownerId, int $userId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw HttpException::unprocessable('تعذر رفع الملف.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw HttpException::unprocessable('حجم الملف يتجاوز 5 ميغابايت.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw HttpException::unprocessable('نوع الملف غير مسموح.');
        }
        $ext = self::ALLOWED[$mime];
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dir = $this->root . '/documents';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $path = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $path) && !rename($file['tmp_name'], $path)) {
            throw HttpException::unprocessable('تعذر حفظ الملف.');
        }
        $rel = 'documents/' . $name;
        $this->db->execute(
            'INSERT INTO file_attachments (owner_type, owner_id, original_name, stored_path, mime_type, size_bytes, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$ownerType, $ownerId, $file['name'] ?? $name, $rel, $mime, (int) $file['size'], $userId]
        );
        return ['id' => (int) $this->db->lastId(), 'path' => $rel, 'mime' => $mime];
    }

    public function absolute(string $rel): string
    {
        $path = realpath($this->root . '/' . ltrim($rel, '/'));
        $root = realpath($this->root);
        if (!$path || !$root || !str_starts_with($path, $root)) {
            throw HttpException::notFound('الملف غير موجود.');
        }
        return $path;
    }
}
