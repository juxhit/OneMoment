<?php

declare(strict_types=1);

final class AdminAuth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['admin_logged_in']);
    }

    public static function login(string $password): bool
    {
        if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
            return false;
        }

        self::startSession();
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_at'] = time();
        return true;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('admin/');
        }
    }

    /** @return true|string error code */
    public static function changePassword(string $current, string $new): bool|string
    {
        if (strlen($new) < 8) {
            return 'too_short';
        }
        if (!password_verify($current, ADMIN_PASSWORD_HASH)) {
            return 'wrong_current';
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        LocalConfig::setAdminPasswordHash($hash);
        return true;
    }
}
