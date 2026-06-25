<?php

declare(strict_types=1);

final class DeployCheck
{
    public static function run(): array
    {
        $publicUrl = defined('PUBLIC_BASE_URL') ? trim((string) PUBLIC_BASE_URL) : '';

        return [
            'https_active' => is_https(),
            'public_url_set' => $publicUrl !== '',
            'public_url' => $publicUrl !== '' ? rtrim($publicUrl, '/') : null,
            'gd' => extension_loaded('gd'),
            'zip' => class_exists('ZipArchive'),
            'sqlite' => extension_loaded('pdo_sqlite'),
            'writable_data' => is_writable(DATA_DIR),
            'writable_uploads' => is_writable(UPLOADS_DIR),
        ];
    }

    public static function statusLabel(bool $ok): string
    {
        return $ok ? 'ok' : 'fail';
    }
}