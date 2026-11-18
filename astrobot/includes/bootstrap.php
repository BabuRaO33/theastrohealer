<?php
// Shared bootstrap for index.php and admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
ini_set('display_errors', $isLocal ? '1' : '0');

function loadEnv($path) {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name); $value = trim($value);
        if (preg_match('/^"(.*)"$/', $value, $m)) $value = $m[1];
        elseif (preg_match("/^'(.*)'$/", $value, $m)) $value = $m[1];
        if (getenv($name) === false) { putenv("$name=$value"); $_ENV[$name] = $value; $_SERVER[$name] = $value; }
    }
}

$appRoot = dirname(__DIR__);
loadEnv($appRoot . '/.env');

$gemini_api_key = getenv('GEMINI_API_KEY') ?: '';
$xai_api_key = getenv('XAI_API_KEY') ?: '';
$stripe_secret_key = getenv('STRIPE_SECRET_KEY') ?: '';
$stripe_webhook_secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: '';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME') ?: '';
$vedic_api_key = getenv('VEDICASTRO_API_KEY') ?: '54077306-5557-52e1-b605-4d1dd400398f';

$db_mode = 'json';
$db_error = null;
$pdo = null;
$json_db_path = $appRoot . '/user_database.json';

if (!file_exists($json_db_path)) {
    $seed_data = [
        'users' => [
            'admin@theastrohealer.com' => [
                'email' => 'admin@theastrohealer.com',
                'password' => 'admin@theastrohealer.com12@@',
                'name' => 'Malik Admin',
                'dob' => '1985-05-15',
                'tob' => '08:15',
                'place' => 'Lahore, Pakistan',
                'lat' => 31.5204,
                'lon' => 74.3587,
                'timezone' => 5.0,
                'coins' => 9999,
                'plan' => 'premium',
                'role' => 'admin',
                'questionsAsked' => 0,
                'createdAt' => round(microtime(true) * 1000),
                'chatHistory' => [
                    ['role' => 'model', 'text' => "Admin panel ready.", 'timestamp' => round(microtime(true) * 1000)]
                ]
            ]
        ],
        'transactions' => [],
        'global_stats' => ['totalRevenue' => 0.0]
    ];
    file_put_contents($json_db_path, json_encode($seed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if (class_exists('PDO') && !empty($db_host) && !empty($db_user) && !empty($db_name)) {
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            email VARCHAR(255) PRIMARY KEY, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT '',
            dob VARCHAR(50) DEFAULT '', tob VARCHAR(50) DEFAULT '12:00', place VARCHAR(255) DEFAULT '',
            lat DOUBLE DEFAULT 0, lon DOUBLE DEFAULT 0, timezone DOUBLE DEFAULT 0,
            coins INT DEFAULT 50, plan VARCHAR(50) DEFAULT 'free', role VARCHAR(50) DEFAULT 'user',
            questionsAsked INT DEFAULT 0, createdAt BIGINT DEFAULT 0, chatHistory LONGTEXT
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL, sessionId VARCHAR(255) NOT NULL,
            amount DOUBLE NOT NULL, planKey VARCHAR(50) NOT NULL, timestamp BIGINT NOT NULL
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS global_stats (
            stat_key VARCHAR(50) PRIMARY KEY, stat_value DOUBLE NOT NULL
        )");

        // Migrate older live tables that were created before new columns existed.
        $userColumnMigrations = [
            "ALTER TABLE users ADD COLUMN phone VARCHAR(50) DEFAULT '' AFTER name",
            "ALTER TABLE users ADD COLUMN dob VARCHAR(50) DEFAULT '' AFTER phone",
            "ALTER TABLE users ADD COLUMN tob VARCHAR(50) DEFAULT '12:00' AFTER dob",
            "ALTER TABLE users ADD COLUMN place VARCHAR(255) DEFAULT '' AFTER tob",
            "ALTER TABLE users ADD COLUMN lat DOUBLE DEFAULT 0 AFTER place",
            "ALTER TABLE users ADD COLUMN lon DOUBLE DEFAULT 0 AFTER lat",
            "ALTER TABLE users ADD COLUMN timezone DOUBLE DEFAULT 0 AFTER lon",
            "ALTER TABLE users ADD COLUMN coins INT DEFAULT 50 AFTER timezone",
            "ALTER TABLE users ADD COLUMN plan VARCHAR(50) DEFAULT 'free' AFTER coins",
            "ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'user' AFTER plan",
            "ALTER TABLE users ADD COLUMN questionsAsked INT DEFAULT 0 AFTER role",
            "ALTER TABLE users ADD COLUMN createdAt BIGINT DEFAULT 0 AFTER questionsAsked",
            "ALTER TABLE users ADD COLUMN chatHistory LONGTEXT AFTER createdAt",
        ];
        foreach ($userColumnMigrations as $sql) {
            try { $pdo->exec($sql); } catch (Exception $e) {}
        }

        $cnt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($cnt == 0) {
            $admin_row = [
                'email' => 'admin@theastrohealer.com', 'password' => 'admin@theastrohealer.com12@@', 'name' => 'Malik Admin', 'phone' => '+1 (470) 650-5315',
                'dob' => '1985-05-15', 'tob' => '08:15', 'place' => 'Lahore, Pakistan', 'lat' => 31.5204, 'lon' => 74.3587, 'timezone' => 5.0,
                'coins' => 9999, 'plan' => 'premium', 'role' => 'admin', 'questionsAsked' => 0, 'createdAt' => round(microtime(true)*1000),
                'chatHistory' => json_encode([['role'=>'model', 'text'=>'Admin panel ready.', 'timestamp'=>round(microtime(true)*1000)]])
            ];
            $stmt = $pdo->prepare("INSERT INTO users (email, password, name, phone, dob, tob, place, lat, lon, timezone, coins, plan, role, questionsAsked, createdAt, chatHistory) VALUES (:email, :password, :name, :phone, :dob, :tob, :place, :lat, :lon, :timezone, :coins, :plan, :role, :questionsAsked, :createdAt, :chat_history)");
            $stmt->execute([
                'email' => $admin_row['email'], 'password' => $admin_row['password'], 'name' => $admin_row['name'], 'phone' => $admin_row['phone'],
                'dob' => $admin_row['dob'], 'tob' => $admin_row['tob'], 'place' => $admin_row['place'], 'lat' => $admin_row['lat'], 'lon' => $admin_row['lon'],
                'timezone' => $admin_row['timezone'], 'coins' => $admin_row['coins'], 'plan' => $admin_row['plan'], 'role' => $admin_row['role'],
                'questionsAsked' => $admin_row['questionsAsked'], 'createdAt' => $admin_row['createdAt'], 'chat_history' => $admin_row['chatHistory']
            ]);
        }
        $db_mode = 'mysql';
    } catch (Exception $e) {
        $db_error = "MySQL connection failed (" . $e->getMessage() . "). Falling back to user_database.json.";
        $db_mode = 'json';
    }
} else {
    $db_error = "MySQL credentials not provided. Running in file-fallback mode.";
    $db_mode = 'json';
}

function fetchUser($email) {
    global $db_mode, $pdo, $json_db_path;
    $email = strtolower(trim($email));
    if ($db_mode === 'mysql') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $row['chatHistory'] = json_decode($row['chatHistory'] ?: '[]', true);
            return $row;
        }
        return null;
    }
    if (!file_exists($json_db_path)) return null;
    $data = json_decode(file_get_contents($json_db_path), true);
    if (!is_array($data) || !isset($data['users'][$email])) return null;
    return $data['users'][$email];
}

function saveUser($user) {
    global $db_mode, $pdo, $json_db_path;
    $user['email'] = strtolower(trim($user['email']));
    $chatHistoryJson = json_encode($user['chatHistory'] ?: [], JSON_UNESCAPED_UNICODE);
    if ($db_mode === 'mysql') {
        $stmt = $pdo->prepare("INSERT INTO users (email, password, name, phone, dob, tob, place, lat, lon, timezone, coins, plan, role, questionsAsked, createdAt, chatHistory)
            VALUES (:email, :password, :name, :phone, :dob, :tob, :place, :lat, :lon, :timezone, :coins, :plan, :role, :questionsAsked, :createdAt, :chat_history)
            ON DUPLICATE KEY UPDATE password=VALUES(password), name=VALUES(name), phone=VALUES(phone), dob=VALUES(dob), tob=VALUES(tob), place=VALUES(place),
            lat=VALUES(lat), lon=VALUES(lon), timezone=VALUES(timezone), coins=VALUES(coins), plan=VALUES(plan), role=VALUES(role),
            questionsAsked=VALUES(questionsAsked), chatHistory=VALUES(chatHistory)");
        $stmt->execute([
            'email' => $user['email'], 'password' => $user['password'], 'name' => $user['name'], 'phone' => $user['phone'] ?? '',
            'dob' => $user['dob'], 'tob' => $user['tob'], 'place' => $user['place'], 'lat' => $user['lat'], 'lon' => $user['lon'],
            'timezone' => $user['timezone'], 'coins' => $user['coins'], 'plan' => $user['plan'], 'role' => $user['role'] ?: 'user',
            'questionsAsked' => $user['questionsAsked'], 'createdAt' => $user['createdAt'], 'chat_history' => $chatHistoryJson
        ]);
        return;
    }
    $data = file_exists($json_db_path) ? json_decode(file_get_contents($json_db_path), true) : [];
    if (!is_array($data)) $data = [];
    if (!isset($data['users'])) $data['users'] = [];
    $data['users'][$user['email']] = $user;
    file_put_contents($json_db_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function registerTransaction($email, $sessionId, $amount, $planKey) {
    global $db_mode, $pdo, $json_db_path;
    $email = strtolower(trim($email));
    $timestamp = round(microtime(true) * 1000);
    if ($db_mode === 'mysql') {
        $stmt = $pdo->prepare("INSERT INTO transactions (email, sessionId, amount, planKey, timestamp) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$email, $sessionId, $amount, $planKey, $timestamp]);
        $stmt2 = $pdo->prepare("INSERT INTO global_stats (stat_key, stat_value) VALUES ('totalRevenue', ?) ON DUPLICATE KEY UPDATE stat_value = stat_value + ?");
        $stmt2->execute([$amount, $amount]);
        return;
    }
    $data = file_exists($json_db_path) ? json_decode(file_get_contents($json_db_path), true) : [];
    if (!is_array($data)) $data = [];
    if (!isset($data['transactions'])) $data['transactions'] = [];
    $data['transactions'][] = ['email' => $email, 'sessionId' => $sessionId, 'amount' => $amount, 'planKey' => $planKey, 'timestamp' => $timestamp];
    if (!isset($data['global_stats'])) $data['global_stats'] = [];
    $data['global_stats']['totalRevenue'] = ($data['global_stats']['totalRevenue'] ?? 0) + doubleval($amount);
    file_put_contents($json_db_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getStripePlans() {
    return [
        'basic' => ['coins' => 250, 'amount' => 11.00, 'lbl' => 'Basic Pack'],
        'medium' => ['coins' => 500, 'amount' => 30.00, 'lbl' => 'Medium Pack'],
        'ultra' => ['coins' => 1000, 'amount' => 60.00, 'lbl' => 'Ultra Pack'],
        'max' => ['coins' => 1500, 'amount' => 100.00, 'lbl' => 'Max Pack'],
    ];
}

function transactionExists($sessionId) {
    if (empty($sessionId)) return false;
    foreach (getSystemMetrics()['transactions'] as $tx) {
        if (($tx['sessionId'] ?? '') === $sessionId) return true;
    }
    return false;
}

function verifyStripeWebhookSignature($payload, $sigHeader, $secret) {
    if (empty($secret) || empty($sigHeader)) return false;
    $timestamp = null; $signatures = [];
    foreach (explode(',', $sigHeader) as $element) {
        $parts = explode('=', trim($element), 2);
        if (count($parts) !== 2) continue;
        if ($parts[0] === 't') $timestamp = $parts[1];
        if ($parts[0] === 'v1') $signatures[] = $parts[1];
    }
    if (!$timestamp || empty($signatures) || abs(time() - intval($timestamp)) > 300) return false;
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

function creditCoinsFromCheckout($email, $sessionId, $planKey) {
    $email = strtolower(trim($email));
    $planKey = strtolower(trim($planKey ?: 'basic'));
    if (strpos($sessionId, 'sim_') === 0) return ['success' => false, 'error' => 'Simulated payments disabled.'];
    $u = fetchUser($email);
    if (!$u) return ['success' => false, 'error' => 'User not found'];
    if (transactionExists($sessionId)) return ['success' => true, 'user' => $u, 'already_processed' => true];
    $plans = getStripePlans();
    $coinsToAdd = $plans[$planKey]['coins'] ?? 250;
    $amount = $plans[$planKey]['amount'] ?? 11.00;
    $u['coins'] = intval($u['coins']) + $coinsToAdd;
    $u['plan'] = 'premium';
    $u['chatHistory'][] = ['role' => 'model', 'text' => "Coins added: +$coinsToAdd. Balance: {$u['coins']}.", 'timestamp' => round(microtime(true) * 1000)];
    saveUser($u);
    registerTransaction($email, $sessionId, $amount, $planKey);


    // Notify Admin on Coin Purchase
    $notifFile = dirname(dirname(__DIR__)) . '/includes/notifications.php';
    if (file_exists($notifFile)) {
        require_once $notifFile;
        try {
            sendAdminPaymentAlert('booking', [
                'name' => $u['name'] ?? 'Astrobot Seeker',
                'email' => $email,
                'item_title' => "Astrobot " . ($plans[$planKey]['lbl'] ?? 'Coin Pack') . " ({$coinsToAdd} Coins)",
                'amount' => $amount,
                'session_id' => $sessionId
            ]);
        } catch (\Throwable $e) {}
    }

    return ['success' => true, 'user' => $u, 'already_processed' => false];

}

function getSystemMetrics() {
    global $db_mode, $pdo, $json_db_path;
    if ($db_mode === 'mysql') {
        $totalRevenue = doubleval($pdo->query("SELECT stat_value FROM global_stats WHERE stat_key = 'totalRevenue'")->fetchColumn() ?: 0.0);
        $txs = $pdo->query("SELECT * FROM transactions ORDER BY timestamp DESC LIMIT 100")->fetchAll();
        $usersRaw = $pdo->query("SELECT email, name, phone, plan, coins, questionsAsked, role, createdAt FROM users ORDER BY createdAt DESC")->fetchAll();
        $totalGuests = 0;
        foreach ($usersRaw as $u) {
            if (strpos(strtolower($u['email'] ?? ''), 'seeker_') === 0) $totalGuests++;
        }
        return ['totalUsers' => count($usersRaw), 'totalGuests' => $totalGuests, 'totalRevenue' => $totalRevenue, 'transactions' => $txs, 'users' => $usersRaw];
    }
    $data = file_exists($json_db_path) ? json_decode(file_get_contents($json_db_path), true) : [];
    if (!is_array($data)) $data = [];
    $usersList = [];
    foreach (($data['users'] ?? []) as $email => $u) {
        if (!is_array($u)) continue;
        $usersList[] = [
            'email' => $u['email'] ?? $email, 'name' => $u['name'] ?? 'Guest', 'phone' => $u['phone'] ?? '',
            'plan' => $u['plan'] ?? 'free', 'coins' => $u['coins'] ?? 50, 'questionsAsked' => $u['questionsAsked'] ?? 0,
            'role' => $u['role'] ?? 'user', 'createdAt' => $u['createdAt'] ?? round(microtime(true)*1000)
        ];
    }
    usort($usersList, fn($a, $b) => ($b['createdAt'] ?? 0) <=> ($a['createdAt'] ?? 0));
    $totalGuests = 0;
    foreach ($usersList as $u) {
        if (strpos(strtolower($u['email'] ?? ''), 'seeker_') === 0) $totalGuests++;
    }
    return [
        'totalUsers' => count($usersList),
        'totalGuests' => $totalGuests,
        'totalRevenue' => doubleval($data['global_stats']['totalRevenue'] ?? 0),
        'transactions' => array_reverse(array_slice($data['transactions'] ?? [], -100)),
        'users' => $usersList
    ];
}

function isGuestEmail($email) {
    return strpos(strtolower($email), 'seeker_') === 0;
}

function requireAdminSession() {
    $email = $_SESSION['user_email'] ?? '';
    $user = $email ? fetchUser($email) : null;
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        return null;
    }
    return $user;
}
