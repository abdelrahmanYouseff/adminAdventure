<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Central helper for permanent user-uploaded media on the S3 disk.
 */
class MediaStorage
{
    public const DISK = 's3';

    /** @var list<string> */
    private static array $tempFiles = [];

    public static function disk(): string
    {
        return self::DISK;
    }

    public static function url(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk(self::DISK)->url(ltrim($path, '/'));
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        return $file->store(trim($directory, '/'), self::DISK);
    }

    public static function exists(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return false;
        }

        return Storage::disk(self::DISK)->exists(ltrim($path, '/'));
    }

    public static function delete(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        Storage::disk(self::DISK)->delete(ltrim($path, '/'));
    }

    public static function mimeType(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk(self::DISK)->mimeType(ltrim($path, '/')) ?: null;
    }

    /**
     * Download an S3 object to a temporary local file for PDF/image libraries
     * that require a filesystem path. Call cleanupTempFiles() after use.
     */
    public static function temporaryLocalPath(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return null;
        }

        $key = ltrim($path, '/');
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($key)) {
            return null;
        }

        $extension = pathinfo($key, PATHINFO_EXTENSION);
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'media_'.uniqid('', true);
        $tmp = $extension !== '' ? $prefix.'.'.$extension : $prefix;

        file_put_contents($tmp, $disk->get($key));
        self::$tempFiles[] = $tmp;

        return $tmp;
    }

    public static function trackTempFile(string $path): void
    {
        if ($path !== '') {
            self::$tempFiles[] = $path;
        }
    }

    public static function cleanupTempFiles(): void
    {
        foreach (self::$tempFiles as $file) {
            if (is_string($file) && is_file($file)) {
                @unlink($file);
            }
        }

        self::$tempFiles = [];
    }
}
