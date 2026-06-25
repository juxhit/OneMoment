<?php

declare(strict_types=1);

final class Pwa
{
    public static function renderHead(string $token, array $event): void
    {
        $theme = (string) ($event['accent_color'] ?? '#6366f1');
        $manifestUrl = url('manifest.php?token=' . rawurlencode($token));
        $icon192 = url('assets/img/icons/icon-192.png');
        $appleIcon = url('assets/img/icons/apple-touch-icon.png');
        $appBase = rtrim(url(''), '/') . '/';
        $appName = APP_NAME;
        $title = (string) ($event['title'] ?? $appName);
        ?>
    <meta name="theme-color" content="<?= e($theme) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= e($title) ?>">
    <meta name="app-base" content="<?= e($appBase) ?>">
    <link rel="manifest" href="<?= e($manifestUrl) ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e($icon192) ?>">
    <link rel="apple-touch-icon" href="<?= e($appleIcon) ?>">
        <?php
    }
}