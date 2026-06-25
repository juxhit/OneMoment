<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));

define('DATA_DIR', PROJECT_ROOT . '/data');
define('UPLOADS_DIR', PROJECT_ROOT . '/uploads');
define('THUMBS_DIR', PROJECT_ROOT . '/thumbs');
define('EXPORTS_DIR', PROJECT_ROOT . '/exports');

define('DB_PATH', DATA_DIR . '/onemoment.db');

define('BASE_URL', '/onemoment/public');

define('MAX_FILE_MB', 20);
define('UPLOAD_JPEG_QUALITY', 96);
define('UPLOAD_WEBP_QUALITY', 92);
define('MAX_TOTAL_FILES', 500);
define('MAX_DISK_PERCENT', 90);

define('THUMB_WIDTHS', [400, 1200]);

define('ALLOWED_IMAGE_MIMES', [
    'image/jpeg',
    'image/png',
    'image/webp',
]);

define('GUEST_ACCESS_COOKIE', 'om_access');
define('GUEST_ACCESS_LIFETIME', 86400);
define('GUEST_IDENTITY_LIFETIME', 2592000);

define('SESSION_NAME', 'onemoment_admin');
define('SESSION_LIFETIME', 86400);

if (!defined('ADMIN_PASSWORD_HASH')) {
    define('ADMIN_PASSWORD_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
}

if (!defined('EVENT_ACCESS_SECRET')) {
    define('EVENT_ACCESS_SECRET', 'change-me-in-config-local-php');
}

define('APP_NAME', 'OneMoment');
define('SCHEMA_VERSION', 3);

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}