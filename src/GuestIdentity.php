<?php

declare(strict_types=1);

final class GuestIdentity
{
    public static function ensure(array $event): string
    {
        $token = self::readVerified($event);
        if ($token !== null) {
            return $token;
        }

        $token = bin2hex(random_bytes(16));
        self::issueCookie($event, $token);
        return $token;
    }

    public static function requireToken(array $event): string
    {
        $token = self::readVerified($event);
        if ($token === null) {
            $token = self::ensure($event);
        }
        return $token;
    }

    private static function readVerified(array $event): ?string
    {
        $name = self::cookieName($event);
        $raw = (string) ($_COOKIE[$name] ?? '');
        if ($raw === '' || !str_contains($raw, '.')) {
            return null;
        }

        [$guestToken, $sig] = explode('.', $raw, 2);
        if ($guestToken === '' || $sig === '') {
            return null;
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $guestToken)) {
            return null;
        }
        if (!hash_equals(self::sign($event['token'], $guestToken), $sig)) {
            return null;
        }

        return $guestToken;
    }

    private static function issueCookie(array $event, string $guestToken): void
    {
        $name = self::cookieName($event);
        $value = $guestToken . '.' . self::sign($event['token'], $guestToken);
        setcookie($name, $value, [
            'expires' => time() + GUEST_IDENTITY_LIFETIME,
            'path' => guest_cookie_path(),
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $value;
    }

    private static function cookieName(array $event): string
    {
        return 'om_guest_' . substr(hash('sha256', (string) $event['token']), 0, 12);
    }

    private static function sign(string $eventToken, string $guestToken): string
    {
        return hash_hmac('sha256', $eventToken . ':' . $guestToken, EVENT_ACCESS_SECRET);
    }
}
