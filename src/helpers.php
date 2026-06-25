<?php

declare(strict_types=1);

function base_url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function url(string $path = ''): string
{
    return base_url($path);
}

function api_url(string $path = ''): string
{
    return url('api/' . ltrim($path, '/'));
}

function media_file_url(string $filename, string $type = 'thumb', int $width = 400): string
{
    if ($type === 'upload') {
        return url('i/u/' . rawurlencode($filename));
    }
    return url('i/t/' . $width . '/' . rawurlencode($filename));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): never
{
    json_response(array_merge(['error' => $message], $extra), $status);
}

function now_iso(): string
{
    return gmdate('Y-m-d H:i:s');
}

function random_token(int $bytes = 24): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function random_room_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function request_host(): string
{
    return (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function public_base_url(): string
{
    if (defined('PUBLIC_BASE_URL')) {
        $value = trim((string) PUBLIC_BASE_URL);
        if ($value !== '') {
            return rtrim($value, '/');
        }
    }

    $scheme = is_https() ? 'https' : 'http';
    return $scheme . '://' . request_host() . rtrim(BASE_URL, '/');
}


function guest_cookie_path(): string
{
    $path = rtrim(BASE_URL, '/');
    return $path !== '' ? $path : '/';
}
function guest_url(string $path, ?string $lanIp = null): string
{
    return rtrim(guest_public_base($lanIp), '/') . '/' . ltrim($path, '/');
}

function qr_image_url(string $data, int $size = 6): string
{
    $size = max(3, min(12, $size));
    return api_url('qr.php?data=' . rawurlencode($data) . '&s=' . $size);
}
function sanitize_guest_label(string $value): ?string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') {
        return null;
    }
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    if (mb_strlen($value) > 80) {
        $value = mb_substr($value, 0, 80);
    }
    return $value !== '' ? $value : null;
}
function wall_mode_options(): array
{
    return [
        'masonry' => 'Сетка «Мейсонри» (Pinterest)',
        'dynamic_mosaic' => 'Динамическая мозаика (плитка с акцентом)',
        'carousel' => 'Бесконечная лента (карусель)',
        'polaroid' => 'Эффект «Полароид» (хаотичные стопки)',
        'generations' => 'Смена поколений (затухающая сетка)',
        'global_mosaic' => 'Фото-мозаика из тысяч кадров',
        'honeycomb' => '«Соты» (гексагональная сетка)',
    ];
}

function normalize_wall_mode(string $mode): string
{
    $legacy = [
        'grid' => 'masonry',
        'tiles' => 'masonry',
        'mosaic' => 'global_mosaic',
        'collage' => 'polaroid',
    ];
    if (isset($legacy[$mode])) {
        return $legacy[$mode];
    }
    $allowed = array_keys(wall_mode_options());
    return in_array($mode, $allowed, true) ? $mode : 'carousel';
}
