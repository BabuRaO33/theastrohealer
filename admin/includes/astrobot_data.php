<?php
/**
 * Astrobot Data Integration Bridge for Main Admin Dashboard
 * The Astro Healer
 */

function getAstrobotMetricsAndUsers() {
    $dbPath = dirname(dirname(__DIR__)) . '/astrobot/user_database.json';
    if (!file_exists($dbPath)) {
        return [
            'total_users' => 0,
            'vip_users' => 0,
            'free_users' => 0,
            'total_questions' => 0,
            'total_coins' => 0,
            'total_revenue' => 0.0,
            'transactions' => [],
            'users' => []
        ];
    }

    $raw = file_get_contents($dbPath);
    $data = json_decode($raw, true) ?: [];

    $usersMap = $data['users'] ?? [];
    $transactions = $data['transactions'] ?? [];
    $totalRevenue = floatval($data['global_stats']['totalRevenue'] ?? 0.0);

    $usersList = [];
    $vipCount = 0;
    $freeCount = 0;
    $totalQuestions = 0;
    $totalCoins = 0;

    foreach ($usersMap as $email => $u) {
        $plan = strtolower($u['plan'] ?? 'free');
        if ($plan === 'premium' || ($u['coins'] ?? 0) > 200) {
            $vipCount++;
        } else {
            $freeCount++;
        }

        $qCount = intval($u['questionsAsked'] ?? 0);
        $totalQuestions += $qCount;

        $cCount = intval($u['coins'] ?? 0);
        $totalCoins += $cCount;

        $usersList[] = [
            'email' => $email,
            'name' => $u['name'] ?? 'Seeker',
            'phone' => $u['phone'] ?? '',
            'dob' => $u['dob'] ?? '',
            'tob' => $u['tob'] ?? '',
            'place' => $u['place'] ?? 'Not specified',
            'lat' => $u['lat'] ?? 0,
            'lon' => $u['lon'] ?? 0,
            'coins' => $cCount,
            'plan' => $plan,
            'role' => $u['role'] ?? 'user',
            'questionsAsked' => $qCount,
            'createdAt' => $u['createdAt'] ?? 0,
            'chatHistory' => $u['chatHistory'] ?? []
        ];
    }

    // Sort newest first
    usort($usersList, function($a, $b) {
        return ($b['createdAt'] ?? 0) <=> ($a['createdAt'] ?? 0);
    });

    usort($transactions, function($a, $b) {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });

    return [
        'total_users' => count($usersList),
        'vip_users' => $vipCount,
        'free_users' => $freeCount,
        'total_questions' => $totalQuestions,
        'total_coins' => $totalCoins,
        'total_revenue' => $totalRevenue,
        'transactions' => $transactions,
        'users' => $usersList
    ];
}

/**
 * Gift coins to an Astrobot user from Admin Panel
 */
function giftAstrobotCoins($email, $coinsToAdd = 250) {
    $dbPath = dirname(dirname(__DIR__)) . '/astrobot/user_database.json';
    if (!file_exists($dbPath)) return false;

    $raw = file_get_contents($dbPath);
    $data = json_decode($raw, true) ?: [];

    $email = strtolower(trim($email));
    if (!isset($data['users'][$email])) return false;

    $data['users'][$email]['coins'] = intval($data['users'][$email]['coins'] ?? 0) + $coinsToAdd;
    $data['users'][$email]['plan'] = 'premium';
    $data['users'][$email]['chatHistory'][] = [
        'role' => 'model',
        'text' => "🌟 Admin Grant: +{$coinsToAdd} coins awarded directly from The Astro Healer Academy desk. Your updated balance is {$data['users'][$email]['coins']} coins.",
        'timestamp' => round(microtime(true) * 1000)
    ];

    file_put_contents($dbPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return true;
}
