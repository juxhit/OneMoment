<?php

declare(strict_types=1);

final class ImageProcessor
{
    public static function processUploadedFile(array $file, int $eventId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Ошибка загрузки файла');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new InvalidArgumentException('Некорректный файл');
        }

        $size = (int) ($file['size'] ?? 0);
        $quota = QuotaService::check($size, $eventId);
        if (!$quota['ok']) {
            throw new RuntimeException($quota['message']);
        }

        $mime = self::detectMime($tmpPath);
        if (!in_array($mime, ALLOWED_IMAGE_MIMES, true)) {
            throw new InvalidArgumentException('Допустимы только JPEG, PNG и WebP');
        }

        if (!extension_loaded('gd')) {
            throw new RuntimeException('На сервере не включено расширение GD (см. php.ini)');
        }

        $ext = self::extensionForMime($mime);
        $basename = bin2hex(random_bytes(16));
        $filename = $basename . '.' . $ext;
        $uploadPath = UPLOADS_DIR . '/' . $filename;

        if (self::canStoreUploadAsIs($tmpPath, $mime)) {
            if (!move_uploaded_file($tmpPath, $uploadPath)) {
                throw new RuntimeException('Не удалось сохранить оригинал');
            }
            $savedSize = (int) (filesize($uploadPath) ?: $size);
            $image = self::loadImage($uploadPath, $mime);
            if ($image === false) {
                @unlink($uploadPath);
                throw new InvalidArgumentException('Не удалось прочитать изображение');
            }
            $width = imagesx($image);
            $height = imagesy($image);
        } else {
            $image = self::loadImage($tmpPath, $mime);
            if ($image === false) {
                throw new InvalidArgumentException('Не удалось прочитать изображение');
            }
            $image = self::applyExifOrientation($image, $tmpPath, $mime);
            $width = imagesx($image);
            $height = imagesy($image);
            if (!self::saveImage($image, $uploadPath, $mime)) {
                imagedestroy($image);
                throw new RuntimeException('Не удалось сохранить оригинал');
            }
            $savedSize = (int) (filesize($uploadPath) ?: $size);
        }

        foreach (THUMB_WIDTHS as $thumbWidth) {
            $thumbPath = THUMBS_DIR . '/' . $basename . '_' . $thumbWidth . '.jpg';
            self::saveThumbnail($image, $thumbPath, $thumbWidth);
        }

        imagedestroy($image);

        return [
            'filename' => $filename,
            'original_name' => (string) ($file['name'] ?? $filename),
            'mime_type' => $mime,
            'size_bytes' => $savedSize,
            'width' => $width,
            'height' => $height,
            'basename' => $basename,
        ];
    }

    private static function canStoreUploadAsIs(string $path, string $mime): bool
    {
        if ($mime === 'image/png' || $mime === 'image/webp') {
            return true;
        }
        if ($mime !== 'image/jpeg') {
            return false;
        }
        if (!function_exists('exif_read_data')) {
            return true;
        }
        $exif = @exif_read_data($path);
        if (!is_array($exif)) {
            return true;
        }
        $orientation = (int) ($exif['Orientation'] ?? 1);
        return $orientation === 0 || $orientation === 1;
    }

    private static function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $path) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    private static function loadImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private static function applyExifOrientation($image, string $path, string $mime)
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        if (!is_array($exif) || empty($exif['Orientation'])) {
            return $image;
        }

        return match ((int) $exif['Orientation']) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private static function saveImage($image, string $path, string $mime): bool
    {
        $jpegQ = defined('UPLOAD_JPEG_QUALITY') ? (int) UPLOAD_JPEG_QUALITY : 96;
        $webpQ = defined('UPLOAD_WEBP_QUALITY') ? (int) UPLOAD_WEBP_QUALITY : 92;

        return match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, max(85, min(100, $jpegQ))),
            'image/png' => imagepng($image, $path, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $path, max(80, min(100, $webpQ))) : false,
            default => false,
        };
    }

    private static function saveThumbnail($source, string $path, int $maxWidth): void
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW <= 0 || $srcH <= 0) {
            return;
        }

        if ($srcW <= $maxWidth) {
            $dstW = $srcW;
            $dstH = $srcH;
        } else {
            $dstW = $maxWidth;
            $dstH = (int) round($srcH * ($maxWidth / $srcW));
        }

        $thumb = imagecreatetruecolor($dstW, $dstH);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagejpeg($thumb, $path, 85);
        imagedestroy($thumb);
    }

    private static function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    public static function thumbFilename(string $basename, int $width): string
    {
        return $basename . '_' . $width . '.jpg';
    }

    public static function basenameFromFilename(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }
}