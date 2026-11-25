<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;

/**
 * Stores uploaded documents outside the web root (DD-13) with validation (NFR-SEC-07).
 * Files are never overwritten: every upload gets a new UUID name.
 */
final class FileService
{
    /** Test hook: accept files that did not come through an HTTP upload. */
    public static bool $testing = false;

    public const MIME = [
        'pdf'  => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
    ];

    /** Max size in MB = min(setting, PHP upload_max_filesize). */
    public static function maxMb(string $settingKey): int
    {
        $setting = SettingsService::int($settingKey, 10);
        $ini = self::iniBytes((string) ini_get('upload_max_filesize'));
        $iniMb = $ini > 0 ? max(1, intdiv($ini, 1024 * 1024)) : $setting;
        return max(1, min($setting, $iniMb));
    }

    /**
     * Validates and stores an uploaded file.
     * @param array $upload one entry of $_FILES
     * @param string[] $allowedExt
     * @return int document_files.id
     */
    public static function store(array $upload, string $ownerType, ?int $ownerId, array $allowedExt, string $maxSetting, int $userId): int
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuleViolation('file_too_large', 'File too large');
        }
        if ($error !== UPLOAD_ERR_OK || (!self::$testing && !is_uploaded_file((string) $upload['tmp_name']))) {
            throw new RuleViolation('file_upload_failed', 'Upload failed');
        }

        $original = basename((string) $upload['name']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true) || !isset(self::MIME[$ext])) {
            throw new RuleViolation('file_type', 'File type not allowed');
        }
        $size = (int) filesize((string) $upload['tmp_name']);
        $maxMb = self::maxMb($maxSetting);
        if ($size <= 0 || $size > $maxMb * 1024 * 1024) {
            throw new RuleViolation('file_too_large', 'File too large');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
        if (!in_array($mime, self::MIME[$ext], true)) {
            throw new RuleViolation('file_type', 'File content does not match its extension');
        }
        if ($ext === 'pdf') {
            $head = (string) file_get_contents((string) $upload['tmp_name'], false, null, 0, 5);
            if ($head !== '%PDF-') {
                throw new RuleViolation('file_type', 'Not a PDF file');
            }
        }

        $dir = date('Y') . '/' . $ownerType . '/';
        $base = (string) config('storage.uploads');
        if (!is_dir($base . '/' . $dir) && !@mkdir($base . '/' . $dir, 0775, true) && !is_dir($base . '/' . $dir)) {
            throw new RuleViolation('file_upload_failed', 'Cannot create upload folder');
        }
        $stored = self::uuid() . '.' . $ext;
        $target = $base . '/' . $dir . $stored;
        $moved = self::$testing ? copy((string) $upload['tmp_name'], $target) : move_uploaded_file((string) $upload['tmp_name'], $target);
        if (!$moved) {
            throw new RuleViolation('file_upload_failed', 'Cannot store file');
        }

        return Database::connection()->insert('document_files', [
            'owner_type'    => $ownerType,
            'owner_id'      => $ownerId,
            'original_name' => mb_substr($original, 0, 255),
            'stored_name'   => $stored,
            'storage_path'  => $dir,
            'mime_type'     => $mime,
            'file_size'     => $size,
            'sha256'        => hash_file('sha256', $target),
            'uploaded_by'   => $userId,
        ]);
    }

    public static function attach(int $fileId, string $ownerType, int $ownerId): void
    {
        Database::connection()->update('document_files', ['owner_type' => $ownerType, 'owner_id' => $ownerId], 'id = :id', ['id' => $fileId]);
    }

    public static function find(int $id): ?array
    {
        return Database::connection()->fetch('SELECT * FROM document_files WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
    }

    public static function path(array $file): string
    {
        return rtrim((string) config('storage.uploads'), '/') . '/' . $file['storage_path'] . $file['stored_name'];
    }

    /** @return array<int,array> files attached to an owner */
    public static function forOwner(string $ownerType, int $ownerId): array
    {
        return Database::connection()->fetchAll(
            'SELECT * FROM document_files WHERE owner_type = :t AND owner_id = :o AND deleted_at IS NULL ORDER BY id',
            ['t' => $ownerType, 'o' => $ownerId]
        );
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $n = (int) $value;
        return match ($unit) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => (int) $value,
        };
    }
}
