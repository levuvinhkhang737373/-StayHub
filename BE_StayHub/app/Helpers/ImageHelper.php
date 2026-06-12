<?php

namespace App\Helpers;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImageHelper
{
    private const DEFAULT_IMAGE = '/upload/no-images.png';
    private const MIN_COMPRESS_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function create(UploadedFile $image, string $folder, int $quality = 95, int $maxWidth = 2560): string
    {
        self::ensureValidImage($image);

        $folder = self::normalizeFolder($folder);
        $directory = self::ensureDirectory($folder);
        $fileName = self::makeFileName($image);
        $image->move($directory, $fileName);

        $absolutePath = $directory.DIRECTORY_SEPARATOR.$fileName;
        self::compress($absolutePath, $quality, $maxWidth);

        return self::normalizePath($folder.'/'.$fileName);
    }

    public static function update(?UploadedFile $image, ?string $oldPath, string $folder, int $quality = 95, int $maxWidth = 2560): ?string
    {
        if (! $image) {
            return $oldPath;
        }

        $newPath = self::create($image, $folder, $quality, $maxWidth);
        self::delete($oldPath);

        return $newPath;
    }

    public static function delete(?string $path): bool
    {
        $absolutePath = self::toAbsolutePath($path);

        if (! $absolutePath || ! is_file($absolutePath)) {
            return false;
        }

        return @unlink($absolutePath);
    }

    public static function load(?string $path): string
    {
        if (blank($path)) {
            $path = self::DEFAULT_IMAGE;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return rtrim(config('app.url'), '/').self::normalizeStoredPath($path);
    }

    /**
     * Lưu ảnh lên disk S3/MinIO ở chế độ private để chỉ xem qua link ký tạm thời.
     */
    public static function storeOnDisk(UploadedFile $image, string $folder, string $disk = 's3'): string
    {
        if ($disk === 'local') {
            return self::create($image, $folder);
        }

        self::ensureValidImage($image);

        $folder = trim(str_replace('\\', '/', $folder), '/');
        $fileName = self::makeFileName($image);
        $path = $folder.'/'.$fileName;
        $stream = fopen($image->getRealPath(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('Không thể đọc file ảnh để tải lên MinIO.');
        }

        try {
            $stored = Storage::disk($disk)->put($path, $stream, 'private');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $stored) {
            throw new RuntimeException('Không thể lưu ảnh lên MinIO.');
        }

        return $path;
    }

    public static function deleteFromDisk(?string $path, string $disk = 's3'): bool
    {
        if (blank($path) || filter_var($path, FILTER_VALIDATE_URL)) {
            return false;
        }

        return Storage::disk($disk)->delete(ltrim((string) $path, '/'));
    }

    public static function urlFromDisk(?string $path, string $disk = 's3'): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if ($disk === 'local' || self::isLocalUploadPath($path)) {
            return self::load($path);
        }

        return Storage::disk($disk)->url(ltrim((string) $path, '/'));
    }

    /**
     * Tạo link xem ảnh MinIO có chữ ký và chỉ tồn tại trong thời gian cấu hình.
     */
    public static function temporaryUrlFromDisk(?string $path, string $disk = 's3', int $minutes = 5): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if ($disk === 'local' || self::isLocalUploadPath($path)) {
            return self::load($path);
        }

        $minutes = max(1, $minutes);
        $normalizedPath = ltrim((string) $path, '/');
        $cacheNonce = Str::random(16);

        return Storage::disk(self::temporaryDiskName($disk))->temporaryUrl(
            $normalizedPath,
            now()->addMinutes($minutes),
            [
                'ResponseCacheControl' => 'private, max-age='.($minutes * 60).', must-revalidate, stayhub-nonce='.$cacheNonce,
                'ResponseContentDisposition' => 'inline; filename="'.self::makeTemporaryFileName($normalizedPath).'"',
            ]
        );
    }

    public static function compress(string $filePath, int $quality = 95, int $maxWidth = 2560): bool
    {
        if (! is_file($filePath) || ! is_readable($filePath) || ! is_writable($filePath) || ! self::gdIsAvailable()) {
            return false;
        }

        $quality = max(1, min(100, $quality));
        $maxWidth = max(1, $maxWidth);
        $info = @getimagesize($filePath);

        if (! $info || empty($info['mime']) || ! in_array($info['mime'], self::ALLOWED_MIMES, true)) {
            return false;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $fileSize = filesize($filePath);

        if ($fileSize !== false && $fileSize < self::MIN_COMPRESS_SIZE && $width <= $maxWidth) {
            return true;
        }

        try {
            $image = self::createImageResource($filePath, $info['mime']);

            if (! $image) {
                return false;
            }

            if ($width > $maxWidth) {
                $image = self::resizeImage($image, $width, $height, $maxWidth, $info['mime']);
            }

            return self::saveImage($image, $filePath, $info['mime'], $quality);
        } catch (Throwable) {
            return false;
        }
    }

    public static function normalizePath(string $path): string
    {
        return '/'.trim($path, '/');
    }

    public static function normalizeFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder), '/');
        $folder = preg_replace('#^public/#', '', $folder);
        $folder = preg_replace('#^uploads?/#', '', $folder);

        return 'upload/'.trim($folder, '/');
    }

    public static function toAbsolutePath(?string $path): ?string
    {
        if (blank($path) || filter_var($path, FILTER_VALIDATE_URL)) {
            return null;
        }

        return public_path(ltrim(self::normalizeStoredPath($path), '/'));
    }

    private static function normalizeStoredPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#^public/#', '', $path);
        $path = preg_replace('#^uploads/#', 'upload/', $path);

        return self::normalizePath($path);
    }

    private static function isLocalUploadPath(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return str_starts_with($path, 'upload/') || str_starts_with($path, 'storage/');
    }

    private static function makeTemporaryFileName(string $path): string
    {
        return str_replace('"', '', basename($path));
    }

    private static function temporaryDiskName(string $disk): string
    {
        $temporaryDisk = $disk.'_temporary';

        return config('filesystems.disks.'.$temporaryDisk) ? $temporaryDisk : $disk;
    }

    private static function ensureValidImage(UploadedFile $image): void
    {
        $extension = strtolower($image->getClientOriginalExtension() ?: $image->extension() ?: '');
        $mime = $image->getMimeType();

        if (! $image->isValid() || ! in_array($extension, self::ALLOWED_EXTENSIONS, true) || ! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException('File ảnh không hợp lệ.');
        }
    }

    private static function ensureDirectory(string $folder): string
    {
        $directory = public_path($folder);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục lưu ảnh.');
        }

        return $directory;
    }

    private static function makeFileName(UploadedFile $image): string
    {
        $extension = strtolower($image->getClientOriginalExtension() ?: $image->extension() ?: 'jpg');
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($originalName) ?: 'image';

        return now()->format('YmdHis').'_'.$slug.'_'.Str::random(12).'.'.$extension;
    }

    private static function gdIsAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    private static function createImageResource(string $filePath, string $mime): GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($filePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($filePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
            default => false,
        };
    }

    private static function resizeImage(GdImage $image, int $width, int $height, int $maxWidth, string $mime): GdImage|false
    {
        $newWidth = $maxWidth;
        $newHeight = (int) round($height * ($maxWidth / $width));
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        if (! $newImage) {
            return false;
        }

        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $newImage;
    }

    private static function saveImage(GdImage $image, string $filePath, string $mime, int $quality): bool
    {
        if ($mime === 'image/jpeg') {
            return imagejpeg($image, $filePath, $quality);
        }

        if ($mime === 'image/webp' && function_exists('imagewebp')) {
            return imagewebp($image, $filePath, $quality);
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $pngQuality = max(0, min(9, (int) round((100 - $quality) / 10)));

        return imagepng($image, $filePath, $pngQuality);
    }
}
