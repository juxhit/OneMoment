<?php

declare(strict_types=1);

final class LocalConfig
{
    public static function path(): string
    {
        return PROJECT_ROOT . '/config/config.local.php';
    }

    public static function setAdminPasswordHash(string $hash): void
    {
        $path = self::path();
        $line = "define('ADMIN_PASSWORD_HASH', " . var_export($hash, true) . ');';

        if (is_file($path)) {
            $content = (string) file_get_contents($path);
            if (preg_match("/define\s*\(\s*'ADMIN_PASSWORD_HASH'\s*,/m", $content)) {
                $content = (string) preg_replace(
                    "/define\s*\(\s*'ADMIN_PASSWORD_HASH'\s*,\s*[^)]+\)\s*;/",
                    $line,
                    $content,
                    1
                );
            } else {
                $content = rtrim($content) . PHP_EOL . PHP_EOL . $line . PHP_EOL;
            }
        } else {
            $content = "<?php\n\ndeclare(strict_types=1);\n\n" . $line . "\n";
        }

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException('Не удалось записать config.local.php');
        }
    }
}