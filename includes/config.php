<?php
/**
 * Configuration Settings for The Astro Healer
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load root .env or cpanel.env file if present
$appEnv = [];
$candidateEnvFiles = [
    dirname(__DIR__) . '/.env',
    dirname(__DIR__) . '/cpanel.env',
];

foreach ($candidateEnvFiles as $ef) {
    if (file_exists($ef)) {
        foreach (file($ef, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name); $value = trim($value);
            if (preg_match('/^"(.*)"$/', $value, $m)) $value = $m[1];
            elseif (preg_match("/^'(.*)'$/", $value, $m)) $value = $m[1];
            if (!isset($appEnv[$name])) {
                $appEnv[$name] = $value;
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                @putenv("$name=$value");
            }
        }
        break;
    }
}

if (!function_exists('astroEnv')) {
    function astroEnv($key, $default = '') {
        global $appEnv;
        if (isset($appEnv[$key]) && $appEnv[$key] !== '') return $appEnv[$key];
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        $val = @getenv($key);
        if ($val !== false && $val !== '') return $val;
        return $default;
    }
}

// Detect if running on Namecheap Production vs Local WAMP
$serverHost = $_SERVER['HTTP_HOST'] ?? '';
$docRootPath = $_SERVER['DOCUMENT_ROOT'] ?? '';
$isProduction = (strpos($serverHost, 'theastrohealer.com') !== false) 
    || (strpos($docRootPath, 'theahnel') !== false)
    || (PHP_OS_FAMILY === 'Linux' && strpos($serverHost, 'localhost') === false);

// Default credentials based on detected environment
$defaultHost = astroEnv('DB_HOST', 'localhost');
$defaultPort = intval(astroEnv('DB_PORT', 3306));
$defaultUser = astroEnv('DB_USER', $isProduction ? 'theahnel_astrohealer' : 'root');
$defaultPass = astroEnv('DB_PASSWORD', astroEnv('DB_PASS', $isProduction ? 'theastrohealer786*1' : ''));
$defaultName = astroEnv('DB_NAME', $isProduction ? 'theahnel_theastrohealer' : 'astrohealer_db');

// Failsafe auto-guard: If running on Namecheap/Linux production and local 'root' credentials were found in .env, automatically enforce live cPanel credentials
if ($isProduction && ($defaultUser === 'root' || empty($defaultPass) || $defaultName === 'astrohealer_db')) {
    $defaultUser = 'theahnel_astrohealer';
    $defaultPass = 'theastrohealer786*1';
    $defaultName = 'theahnel_theastrohealer';
}

define('DB_HOST', $defaultHost);
define('DB_PORT', $defaultPort);
define('DB_USER', $defaultUser);
define('DB_PASS', $defaultPass);
define('DB_NAME', $defaultName);

// Base URLs & SSL Detection (Supports direct SSL, Cloudflare & Namecheap cPanel proxies)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
$protocol = $isHttps ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$appDir = str_replace('\\', '/', realpath(dirname(__DIR__)));

$baseFolder = '';
if (!empty($docRoot) && !empty($appDir) && strpos($appDir, $docRoot) === 0) {
    $baseFolder = substr($appDir, strlen($docRoot));
}
$baseFolder = '/' . trim($baseFolder, '/');
if ($baseFolder === '/') {
    $baseFolder = '';
}
define('BASE_URL', rtrim($protocol . $host . $baseFolder, '/'));
define('SITE_NAME', 'The Astro Healer');
define('SITE_TAGLINE', 'Moon and Stars Solutions Provider');
define('SITE_CURRENCY', 'USD');
define('SITE_CURRENCY_SYMBOL', '$');

// Default Stripe Keys (Configured in .env / cpanel.env or Admin Settings)
define('DEFAULT_STRIPE_PUB_KEY', astroEnv('STRIPE_PUB_KEY', 'pk_live_configured_via_env'));
define('DEFAULT_STRIPE_SECRET_KEY', astroEnv('STRIPE_SECRET_KEY', 'sk_live_configured_via_env'));
define('ASTRO_STRIPE_PUB_KEY', DEFAULT_STRIPE_PUB_KEY);
define('ASTRO_STRIPE_SECRET_KEY', DEFAULT_STRIPE_SECRET_KEY);


// Admin Alerts & Notifications (Email & WhatsApp)
define('ASTRO_ADMIN_EMAIL', 'admin@theastrohealer.com');
define('ASTRO_WHATSAPP_PHONE', '14706505315');
/**
 * CallMeBot WhatsApp API Key for alerts to +1 (470) 650-5315:
 * 1) From +14706505315 WhatsApp, message "+34 644 71 81 99"
 * 2) Text: "I allow callmebot to send me messages"
 * 3) Paste the API key received below (or in Admin Settings)
 */
define('ASTRO_WHATSAPP_CALLMEBOT_APIKEY', '');

// Timezone
date_default_timezone_set('Asia/Karachi');

// Global Video Helper
require_once __DIR__ . '/video_helper.php';

