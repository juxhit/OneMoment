<?php

declare(strict_types=1);

final class GuestAccess
{
    public static function pinEnabled(array $event): bool
    {
        return !empty($event['pin_hash']);
    }

    public static function hasAccess(array $event): bool
    {
        if (!self::pinEnabled($event)) {
            return true;
        }

        $cookie = (string) ($_COOKIE[GUEST_ACCESS_COOKIE] ?? '');
        if ($cookie === '') {
            return false;
        }

        return hash_equals(self::sign($event['token']), $cookie);
    }

    public static function issueCookie(string $eventToken): void
    {
        setcookie(GUEST_ACCESS_COOKIE, self::sign($eventToken), [
            'expires' => time() + GUEST_ACCESS_LIFETIME,
            'path' => guest_cookie_path(),
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCookie(): void
    {
        setcookie(GUEST_ACCESS_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => guest_cookie_path(),
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function requireAccess(array $event): void
    {
        if (!self::hasAccess($event)) {
            json_error('pin_required', 403);
        }
    }

    private static function sign(string $eventToken): string
    {
        return hash_hmac('sha256', $eventToken . ':verified', EVENT_ACCESS_SECRET);
    }
}