<?php
require_once __DIR__ . '/includes/bootstrap.php';

// 3. API Handler Requests
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: $_POST;

    try {
        switch ($action) {
        case 'stripe-webhook':
            if (empty($stripe_webhook_secret)) {
                http_response_code(500);
                echo json_encode(['error' => 'STRIPE_WEBHOOK_SECRET is not configured']);
                exit;
            }
            $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            if (!verifyStripeWebhookSignature($rawBody, $sigHeader, $stripe_webhook_secret)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid Stripe webhook signature']);
                exit;
            }
            $event = json_decode($rawBody, true);
            if (!is_array($event) || empty($event['type'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid webhook payload']);
                exit;
            }
            if ($event['type'] === 'checkout.session.completed') {
                $session = $event['data']['object'] ?? [];
                if (($session['payment_status'] ?? '') !== 'paid') {
                    echo json_encode(['received' => true, 'skipped' => 'not_paid']);
                    exit;
                }
                $sessionId = $session['id'] ?? '';
                $email = strtolower(trim($session['metadata']['email'] ?? $session['customer_email'] ?? ''));
                $planKey = strtolower(trim($session['metadata']['plan_key'] ?? 'basic'));
                if (empty($email) || empty($sessionId)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing email or session id in webhook']);
                    exit;
                }
                $result = creditCoinsFromCheckout($email, $sessionId, $planKey);
                if (!$result['success']) {
                    error_log('Stripe webhook credit failed: ' . ($result['error'] ?? 'unknown'));
                }
            }
            echo json_encode(['received' => true]);
            exit;
        case 'geolocate':
            $place = $input['place'] ?? '';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . $gemini_api_key;
            $prompt = "Astro-geolocate: \"$place\". Return JSON ONLY with keys: lat (float), lon (float), timezone (float e.g 5.0), country (string), label (canonical formatted address). Default to Lahore if completely unknown.";
            $payload = [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'lat' => ['type' => 'NUMBER'], 'lon' => ['type' => 'NUMBER'], 'timezone' => ['type' => 'NUMBER'],
                            'country' => ['type' => 'STRING'], 'label' => ['type' => 'STRING']
                        ],
                        'required' => ['lat', 'lon', 'timezone', 'country', 'label']
                    ]
                ]
            ];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload)
            ]);
            $res = curl_exec($ch); curl_close($ch);
            $dec = json_decode($res, true);
            if (isset($dec['candidates'][0]['content']['parts'][0]['text'])) {
                echo $dec['candidates'][0]['content']['parts'][0]['text'];
            } else {
                echo json_encode(['lat' => 31.5204, 'lon' => 74.3587, 'timezone' => 5.0, 'country' => 'Pakistan', 'label' => $place . ' (Astro Defaulted)']);
            }
            break;

        case 'planet-details':
            $day = intval($input['day'] ?? 1); $month = intval($input['month'] ?? 1); $year = intval($input['year'] ?? 1990);
            $hour = intval($input['hour'] ?? 12); $min = intval($input['min'] ?? 0);
            $lat = doubleval($input['lat'] ?? 31.5204); $lon = doubleval($input['lon'] ?? 74.3587); $tzone = doubleval($input['timezone'] ?? 5.0);
            $planetNames = [
                'As' => 'Ascendant', 'Su' => 'Sun', 'Mo' => 'Moon', 'Ma' => 'Mars', 'Me' => 'Mercury',
                'Ju' => 'Jupiter', 'Ve' => 'Venus', 'Sa' => 'Saturn', 'Ra' => 'Rahu', 'Ke' => 'Ketu'
            ];
            try {
                if (!empty($vedic_api_key)) {
                    $dob = sprintf('%02d/%02d/%04d', $day, $month, $year);
                    $tob = sprintf('%02d:%02d', $hour, $min);
                    $query = http_build_query([
                        'dob' => $dob,
                        'tob' => $tob,
                        'lat' => $lat,
                        'lon' => $lon,
                        'tz' => $tzone,
                        'api_key' => $vedic_api_key,
                        'lang' => 'en',
                    ]);
                    $apiUrl = 'https://api.vedicastroapi.com/v3-json/horoscope/planet-details?' . $query;
                    $ch = curl_init($apiUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false
                    ]);
                    $res = curl_exec($ch);
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $body = json_decode($res, true);
                    if ($status === 200 && is_array($body) && intval($body['status'] ?? 0) === 200 && isset($body['response'])) {
                        $planets = [];
                        foreach ($body['response'] as $item) {
                            if (!is_array($item)) continue;
                            $shortName = (string) ($item['name'] ?? '');
                            if (!isset($item['zodiac'], $item['house']) || (!isset($item['full_name']) && !isset($planetNames[$shortName]))) {
                                continue;
                            }
                            $fullName = (string) ($item['full_name'] ?? ($planetNames[$shortName] ?? $shortName));
                            $localDegree = floatval($item['local_degree'] ?? 0);
                            $degree = intval(floor($localDegree));
                            $minute = intval(floor(($localDegree - $degree) * 60));
                            $planets[] = [
                                'name' => $fullName !== '' ? $fullName : 'Planet',
                                'sign' => (string) ($item['zodiac'] ?? 'Aries'),
                                'sign_id' => intval($item['rasi_no'] ?? 1),
                                'house' => intval($item['house'] ?? 1),
                                'degree' => $degree,
                                'minute' => $minute,
                                'retrograde' => !empty($item['retro']),
                                'lord' => (string) ($item['zodiac_lord'] ?? 'Mars'),
                                'nakshatra' => (string) ($item['nakshatra'] ?? 'Ashwini'),
                                'strength' => max(10, min(99, 100 - intval($degree * 2)))
                            ];
                        }
                        if (!empty($planets)) {
                            echo json_encode([
                                'planets' => $planets,
                                'source' => 'live_api',
                                'remaining_api_calls' => $body['remaining_api_calls'] ?? null
                            ]);
                            exit;
                        }
                    }
                }
            } catch (Exception $e) {}
            // Fallback Engine
            $seed = ($day * 3 + $month * 7 + $year + $hour + $min) % 12;
            $signs = ["Aries", "Taurus", "Gemini", "Cancer", "Leo", "Virgo", "Libra", "Scorpio", "Sagittarius", "Capricorn", "Aquarius", "Pisces"];
            $lords = ["Mars", "Venus", "Mercury", "Moon", "Sun", "Mercury", "Venus", "Mars", "Jupiter", "Saturn", "Saturn", "Jupiter"];
            $plist = ["Ascendant", "Sun", "Moon", "Mars", "Mercury", "Jupiter", "Venus", "Saturn", "Rahu", "Ketu"];
            $planets = [];
            foreach ($plist as $idx => $p) {
                $pSeed = ($seed + $idx * 4) % 12; $deg = ($seed * 7 + $idx * 3) % 30; $house = (($pSeed + 1 - ($seed % 12) + 12) % 12) + 1;
                $planets[] = [
                    'name' => $p, 'sign' => $signs[$pSeed], 'sign_id' => $pSeed + 1, 'house' => $house,
                    'degree' => $deg, 'minute' => 20, 'retrograde' => ($p !== "Ascendant" && $p !== "Sun" && $p !== "Moon" && ($idx % 5 === 0)),
                    'lord' => $lords[$pSeed], 'nakshatra' => 'Ashwini', 'strength' => 100 - ($deg * 2)
                ];
            }
            echo json_encode(['planets' => $planets, 'source' => 'astrological_engine']);
            break;

        case 'register':
            $email = strtolower(trim($input['email'] ?? '')); $password = $input['password'] ?? ''; $name = $input['name'] ?? '';
            $phone = trim($input['phone'] ?? '');
            $dob = $input['dob'] ?? ''; $tob = $input['tob'] ?? '12:00'; $place = $input['place'] ?? '';
            $lat = doubleval($input['lat'] ?? 0); $lon = doubleval($input['lon'] ?? 0); $tzone = doubleval($input['timezone'] ?? 0);
            if (empty($email) || empty($password) || empty($name)) { echo json_encode(['error' => 'Email, Password, Name required']); exit; }
            if (empty($dob)) { echo json_encode(['error' => 'Date of birth is required']); exit; }
            
            $existing = fetchUser($email);
            if ($existing) { echo json_encode(['error' => 'Account email already exists in room.']); exit; }
            
            $user = [
                'email' => $email, 'password' => $password, 'name' => $name, 'phone' => $phone, 'dob' => $dob, 'tob' => $tob, 'place' => $place,
                'lat' => $lat, 'lon' => $lon, 'timezone' => $tzone, 'coins' => 50, 'plan' => 'free', 'role' => 'user', 'questionsAsked' => 0, 'createdAt' => round(microtime(true)*1000),
                'chatHistory' => [['role'=>'model', 'text'=>"Welcome $name. Your chart is ready. You have 5 free questions (50 coins). Ask your question in any language.", 'timestamp'=>round(microtime(true)*1000)]]
            ];
            saveUser($user);
            $_SESSION['user_email'] = $email;

            // Audit Trail
            $auditFile = dirname(__DIR__) . '/includes/audit.php';
            if (file_exists($auditFile)) {
                require_once $auditFile;
                logAuditTrail('BOT_USER_REGISTER', 'bot', "New seeker registered on Astrobot: {$name} ({$email}) from " . ($place ?: 'Unknown location') . " with 50 free coins", 'success', null, $email, $name);
            }

            echo json_encode(['success' => true, 'user' => $user]);
            break;

        case 'login':
            $email = strtolower(trim($input['email'] ?? '')); $password = $input['password'] ?? '';
            $user = fetchUser($email);
            if (!$user || $user['password'] !== $password) {
                $auditFile = dirname(__DIR__) . '/includes/audit.php';
                if (file_exists($auditFile)) {
                    require_once $auditFile;
                    logAuditTrail('BOT_LOGIN_FAILED', 'bot', "Failed login attempt on Astrobot for {$email}", 'warning', null, $email);
                }
                echo json_encode(['error' => 'Mizaj-e-Aali! Invalid authentication credentials.']); exit;
            }
            $_SESSION['user_email'] = $email;

            // Audit Trail
            $auditFile = dirname(__DIR__) . '/includes/audit.php';
            if (file_exists($auditFile)) {
                require_once $auditFile;
                logAuditTrail('BOT_USER_LOGIN', 'bot', "Seeker logged into Astrobot chamber: {$email} [Coins: " . ($user['coins'] ?? 0) . "]", 'success', null, $email, $user['name'] ?? 'Seeker');
            }

            echo json_encode(['success' => true, 'user' => $user]);
            break;


        case 'admin-metrics':
            $sessionUser = isset($_SESSION['user_email']) ? fetchUser($_SESSION['user_email']) : null;
            if (!$sessionUser || ($sessionUser['role'] ?? 'user') !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin login required']);
                exit;
            }
            echo json_encode(getSystemMetrics());
            break;

        case 'payment-checkout':
            $email = strtolower(trim($input['email'] ?? '')); $planKey = strtolower(trim($input['planKey'] ?? 'basic'));
            $sessionUser = isset($_SESSION['user_email']) ? fetchUser($_SESSION['user_email']) : null;
            if (!$sessionUser || (strtolower($sessionUser['email'] ?? '') !== $email && ($sessionUser['role'] ?? 'user') !== 'admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Login required before payment']);
                exit;
            }
            $PLANS = getStripePlans();
            $sel = $PLANS[$planKey] ?? $PLANS['basic'];
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? "https://" : "http://";
            $origin = $proto . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            if (substr($origin, -1) === '/' || substr($origin, -1) === '\\') $origin = rtrim($origin, '/\\');
            $success = $origin . '/#payment-success?email=' . urlencode($email) . '&session_id={CHECKOUT_SESSION_ID}&plan=' . urlencode($planKey);
            $cancel = $origin . '/#payment-cancelled';

            try {
                if (empty($stripe_secret_key)) throw new Exception("Stripe key missing");
                $ch = curl_init("https://api.stripe.com/v1/checkout/sessions");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $stripe_secret_key . ":", CURLOPT_POST => true,
                    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_POSTFIELDS => http_build_query([
                        'payment_method_types[0]' => 'card', 'mode' => 'payment', 'customer_email' => $email,
                        'success_url' => $success, 'cancel_url' => $cancel,
                        'metadata[email]' => $email, 'metadata[plan_key]' => $planKey,
                        'line_items[0][price_data][currency]' => 'usd', 'line_items[0][price_data][unit_amount]' => round($sel['amount']*100),
                        'line_items[0][price_data][product_data][name]' => "The Astro Healer " . $sel['lbl'], 'line_items[0][quantity]' => 1
                    ])
                ]);
                $res = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
                $dec = json_decode($res, true);
                if ($status === 200 && isset($dec['url'])) { echo json_encode(['url' => $dec['url']]); }
                else { throw new Exception($dec['error']['message'] ?? "Checkout fail"); }
            } catch (Exception $e) {
                echo json_encode(['error' => 'Stripe checkout failed: ' . $e->getMessage()]);
            }
            break;

        case 'payment-verify':
            $email = strtolower(trim($input['email'] ?? '')); $sid = $input['sessionId'] ?? ''; $plan = strtolower(trim($input['plan'] ?? 'basic'));
            if (empty($stripe_secret_key)) {
                echo json_encode(['error' => 'Stripe secret key is missing.']); exit;
            }
            try {
                $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/" . urlencode($sid));
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERPWD => $stripe_secret_key . ":",
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]);
                $stripeRes = curl_exec($ch);
                $stripeStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $stripeSession = json_decode($stripeRes, true);
                if ($stripeStatus !== 200 || !is_array($stripeSession)) {
                    echo json_encode(['error' => 'Stripe session verification failed.']); exit;
                }
                if (($stripeSession['payment_status'] ?? '') !== 'paid') {
                    echo json_encode(['error' => 'Stripe payment is not paid yet.']); exit;
                }
                $stripeEmail = strtolower(trim($stripeSession['metadata']['email'] ?? $stripeSession['customer_email'] ?? ''));
                if ($stripeEmail && $stripeEmail !== $email) {
                    echo json_encode(['error' => 'Stripe email does not match this account.']); exit;
                }
                $plan = strtolower(trim($stripeSession['metadata']['plan_key'] ?? $plan));
            } catch (Exception $e) {
                echo json_encode(['error' => 'Stripe verification error: ' . $e->getMessage()]); exit;
            }
            $result = creditCoinsFromCheckout($email, $sid, $plan);
            if (!$result['success']) {
                echo json_encode(['error' => $result['error'] ?? 'Payment credit failed']); exit;
            }

            // Audit Trail
            $auditFile = dirname(__DIR__) . '/includes/audit.php';
            if (file_exists($auditFile) && empty($result['already_processed'])) {
                require_once $auditFile;
                logAuditTrail('BOT_COIN_PURCHASE', 'payment', "Astrobot {$plan} pack credited via Stripe [Session: {$sid}]", 'success', null, $email, $result['user']['name'] ?? 'Seeker');
            }

            echo json_encode(['success' => true, 'user' => $result['user'], 'already_processed' => !empty($result['already_processed'])]);
            break;


        case 'manual-purchase':
            $sessionUser = isset($_SESSION['user_email']) ? fetchUser($_SESSION['user_email']) : null;
            if (!$sessionUser || ($sessionUser['role'] ?? 'user') !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin login required']);
                exit;
            }
            $email = strtolower(trim($input['email'] ?? '')); $planKey = strtolower(trim($input['planKey'] ?? 'basic'));
            $PLANS = ['basic'=>250, 'medium'=>500, 'ultra'=>1000, 'max'=>1500]; $coinsToAdd = $PLANS[$planKey] ?? 250;
            $u = fetchUser($email);
            if ($u) {
                $u['coins'] = intval($u['coins']) + $coinsToAdd;
                $u['plan'] = 'premium';
                $u['chatHistory'][] = ['role'=>'model', 'text'=>"⭐ Sandbox activated! Real-time topup loaded: +$coinsToAdd coins.", 'timestamp'=>round(microtime(true)*1000)];
                saveUser($u);
                echo json_encode($u);
            } else {
                echo json_encode(['error' => 'User not found']);
            }
            break;

        case 'reset':
            $email = strtolower(trim($input['email'] ?? ''));
            $sessionUser = isset($_SESSION['user_email']) ? fetchUser($_SESSION['user_email']) : null;
            if (!$sessionUser || (strtolower($sessionUser['email'] ?? '') !== $email && ($sessionUser['role'] ?? 'user') !== 'admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Login required']);
                exit;
            }
            $u = fetchUser($email);
            if ($u) {
                $u['plan'] = 'free';
                $u['coins'] = 50;
                $u['questionsAsked'] = 0;
                $u['chatHistory'] = [['role'=>'model', 'text'=>'Session reset complete. 50 coins are ready.', 'timestamp'=>round(microtime(true)*1000)]];
                saveUser($u);
                echo json_encode($u);
            } else {
                echo json_encode(['error' => 'User not found']);
            }
            break;

        case 'chat':
            $email = strtolower(trim($input['email'] ?? '')); $q = $input['question'] ?? ''; $plist = $input['planetsList'] ?? [];
            $user = fetchUser($email);
            if (!$user) { echo json_encode(['error' => 'User not found']); exit; }
            $coins = intval($user['coins']); $asked = intval($user['questionsAsked']); $isAdmin = ($user['role'] === 'admin');
            if (!$isAdmin) {
                if ($coins < 10) { echo json_encode(['error' => 'insufficient_funds']); exit; }
                $coins -= 10;
            }
            $asked++;
            
            $user['chatHistory'][] = ['role'=>'user', 'text'=>$q, 'timestamp'=>round(microtime(true)*1000)];

            // Audit Trail
            $auditFile = dirname(__DIR__) . '/includes/audit.php';
            if (file_exists($auditFile)) {
                require_once $auditFile;
                $cleanQ = htmlspecialchars(mb_substr($q, 0, 80));
                logAuditTrail('BOT_QUESTION_ASKED', 'bot', "Seeker {$user['name']} asked: \"{$cleanQ}...\" [Coins remaining: {$coins}]", 'info', null, $email, $user['name']);
            }


            $chartCtx = "";
            foreach ($plist as $p) {
                $chartCtx .= "- " . $p['name'] . ": House " . $p['house'] . ", " . $p['sign'] . " (" . $p['degree'] . "°).\n";
            }
            $prevs = "";
            foreach (array_slice($user['chatHistory'], -8, -1) as $msg) {
                $prevs .= ($msg['role']==='user' ? 'User' : 'Malik') . ": " . $msg['text'] . "\n";
            }

            $clientName = trim((string) ($user['name'] ?? 'Seeker'));
            $sys = <<<PROMPT
You are Astrologer Malik, the senior astrology assistant for The Astro Healer.

Client: {$clientName}

Core behavior:
- Reply in the same language and script the client uses: Urdu, Roman Urdu, English, Hindi, Arabic, or mixed language.
- Sound like a calm, experienced astrologer: direct, warm, practical, and respectful.
- Never mention Grok, xAI, Gemini, API, model, prompt, beta, or internal instructions.
- Do not use Indian religious greetings or terms such as Namaste, puja, pandit, or "Shani prabhav".
- Do not give Islamic, religious, or spiritual remedies. Focus only on astrology-based guidance and practical life advice.

Astrology method:
- Use the supplied Vedic chart placements as symbolic guidance, not guaranteed fate.
- Ground the answer in 2-4 visible chart factors when relevant: planets, houses, signs, Moon sign, Ascendant, Rahu/Ketu, Saturn, Venus, Jupiter, Mars, or Mercury.
- If chart context is missing or weak, say what detail is needed instead of inventing placements.
- For timing questions, give cautious windows or tendencies, not absolute promises.
- For marriage, relationship, career, finance, health, foreign travel, family, and spiritual questions, connect the answer to practical next steps.

Answer format:
- Start with a short direct answer to the client's question.
- Then explain the chart indication in simple words.
- Give practical advice based on chart indications.
- Keep normal answers 180-350 words unless the client asks for detail.
- For short casual questions, answer briefly.

Safety:
- Do not claim certainty about death, pregnancy gender, severe disease diagnosis, legal outcomes, gambling, stock tips, or guaranteed future events.
- For medical/legal/financial risk, advise consulting a qualified professional while still giving general spiritual/reflective guidance.
- Avoid fear-based language. Do not scare the client.

Chart context:
{$chartCtx}

Recent conversation:
{$prevs}
PROMPT;
            
            try {
                $url = "https://api.x.ai/v1/chat/completions";
                $payload = [
                    'model' => 'grok-4-latest',
                    'messages' => [
                        ['role' => 'system', 'content' => $sys],
                        ['role' => 'user', 'content' => "Client question: \"$q\""]
                    ],
                    'temperature' => 0.75,
                    'max_tokens' => 1400
                ];
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 60,
                    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $xai_api_key
                    ],
                    CURLOPT_POSTFIELDS => json_encode($payload)
                ]);
                $res = curl_exec($ch);
                $curl_error = curl_error($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $dec = json_decode($res, true);
                if (isset($dec['choices'][0]['message']['content'])) {
                    $answer = $dec['choices'][0]['message']['content'];
                } else {
                    if (empty($xai_api_key)) {
                        $answer = "XAI_API_KEY is missing in the .env file. Please add the Grok key so I can give detailed answers.";
                    } else if ($curl_error) {
                        $answer = "Connection to the Grok AI service failed: " . $curl_error . ". Please check hosting outbound connections.";
                    } else if (isset($dec['error']['message'])) {
                        $answer = "Sitaron ne jawab diya: " . $dec['error']['message'];
                    } else {
                        $answer = "The Grok AI response was unclear. Please try again. (HTTP $status: " . substr(strip_tags($res ?: 'Empty response'), 0, 150) . ")";
                    }
                }
            } catch (Exception $e) { $answer = "The Grok AI connection timed out. Please try again in a moment: " . $e->getMessage(); }

            $user['chatHistory'][] = ['role'=>'model', 'text'=>$answer, 'timestamp'=>round(microtime(true)*1000)];
            $user['coins'] = $coins;
            $user['questionsAsked'] = $asked;
            saveUser($user);
            
            echo json_encode(['answer' => $answer, 'coins' => $coins, 'questionsAsked' => $asked, 'chatHistory' => $user['chatHistory'], 'ai_provider' => 'grok']);
            break;

        case 'daily-forecast':
            $sid = intval($_GET['sign_id'] ?? 1); $signs = ["Aries","Taurus","Gemini","Cancer","Leo","Virgo","Libra","Scorpio","Sagittarius","Capricorn","Aquarius","Pisces"];
            $sgn = $signs[max(0, min(11, $sid - 1))];
            try {
                $url = "https://api.x.ai/v1/chat/completions";
                $prompt = "Write a dynamic daily astrology forecast for Zodiac Sign '$sgn' for The Astro Healer. Return concise HTML only. Use Roman Urdu plus simple English. Sections: Vibe, Career, Relationships, Advice. Do not include religious or spiritual remedies. Do not mention Grok, API, model, beta, Namaste, puja, or Shani prabhav. Strictly 120 words max.";
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $xai_api_key
                    ],
                    CURLOPT_POSTFIELDS => json_encode([
                        'model' => 'grok-4-latest',
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are Astrologer Malik for The Astro Healer. Write calm, practical astrology forecasts without religious remedies or mentioning AI or internal systems.'],
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'temperature' => 0.75,
                        'max_tokens' => 350
                    ])
                ]);
                $res = curl_exec($ch); curl_close($ch);
                $dec = json_decode($res, true);
                $pred = $dec['choices'][0]['message']['content'] ?? "Din khushgawar rahega. Sadaqah dein blockages door karne ke liye.";
            } catch (Exception $e) { $pred = "🌟 Good celestial opportunities expected today. Focus on relationships and steady progress."; }
            echo json_encode(['prediction' => $pred, 'sign' => $sgn, 'ai_provider' => 'grok']);
            break;
        }
    } catch (Throwable $e) {
        echo json_encode([
            'error' => 'Portal execution error: ' . $e->getMessage() . ' in file ' . basename($e->getFile()) . ' on line ' . $e->getLine()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talk with Malik AI Oracle — The Astro Healer | 24/7 Vedic Astrologer Assistant</title>
    <meta name="description" content="Consult the 24/7 Vedic Astrobot AI Chamber. Calculate your Janam Kundli, planetary strengths, dashas, lucky gemstones, and personalized cosmic remedies instantly.">
    <meta name="keywords" content="astrology AI, Vedic AI oracle, online kundli calculator, astrologer malik bot, birth chart AI analysis">
    <link rel="canonical" href="https://theastrohealer.com/ai.php">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], outfit: ['Outfit', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] }
                }
            }
        }
    </script>
    <!-- Lucide Mini -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #312e81; border-radius: 4px; }
        @keyframes twinkle { 0%, 100% { opacity: 0.2; } 50% { opacity: 1; } }
        .star { animation: twinkle 3s infinite ease-in-out; }
        @media (max-width: 640px) {
            .mobile-safe-panel { max-height: calc(100vh - 2rem); overflow-y: auto; }
            #chart-svg-container svg { max-width: 100%; height: auto; }
        }
    </style>
</head>
<body class="h-full flex flex-col relative overflow-x-hidden font-sans">
    <!-- Star Background -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-950 via-slate-950 to-slate-950">
        <div class="absolute inset-0 opacity-40">
            <div class="absolute w-1 h-1 bg-white rounded-full star top-10 left-12"></div>
            <div class="absolute w-1.5 h-1.5 bg-yellow-150 rounded-full star top-28 left-1/3" style="animation-delay: 1.5s"></div>
            <div class="absolute w-1 h-1 bg-white rounded-full star top-20 right-24" style="animation-delay: 0.8s"></div>
            <div class="absolute w-2 h-2 bg-indigo-300 rounded-full star top-1/2 left-2/3" style="animation-delay: 2.1s"></div>
            <div class="absolute w-1 h-1 bg-white rounded-full star bottom-1/4 left-10" style="animation-delay: 0.3s"></div>
            <div class="absolute w-1 h-1 bg-amber-200 rounded-full star bottom-12 right-1/3" style="animation-delay: 1.1s"></div>
        </div>
    </div>

    <!-- MAIN CONTAINER -->
    <main class="flex-1 flex flex-col z-10 w-full max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-6">
        
        <!-- HEADER -->
        <header class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4 sm:mb-6 pb-4 border-b border-indigo-950/40">
            <div class="flex items-center gap-3">
                <a href="<?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'ai.php') ? 'index.php' : '../index.php' ?>" class="w-10 h-10 rounded-full bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-amber-400 hover:scale-105 transition" title="Return to The Astro Healer Main Site">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </a>
                <div>
                    <h1 class="font-outfit font-extrabold tracking-tight text-white text-base md:text-lg">THE ASTRO HEALER</h1>
                    <p class="text-[10px] uppercase font-mono text-indigo-400 tracking-widest leading-none">Astrologer Malik Bot</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-end">
                <a href="<?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'ai.php') ? 'index.php' : '../index.php' ?>" class="text-[11px] sm:text-xs bg-slate-900/80 hover:bg-slate-850 border border-slate-800 text-slate-300 px-3 py-1.5 rounded-full font-mono flex items-center gap-1.5 transition">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5 text-amber-400"></i>
                    Main Academy
                </a>

                <!-- Fallback warning icon (only show if running on JSON file backup) -->
                <?php if ($db_mode === 'json'): ?>
                    <span class="text-[9px] bg-amber-500/10 border border-amber-500/25 px-2.5 py-1 rounded-full text-amber-300 font-mono hidden md:flex items-center gap-1" title="<?php echo htmlspecialchars($db_error); ?>">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-ping"></span> Portal Mode: Local State File
                    </span>
                <?php endif; ?>

                <!-- Logout / User Actions -->
                <div id="user-header-badges" class="hidden flex items-center gap-2 sm:gap-3 w-full sm:w-auto justify-end">
                    <button onclick="openProfileModal()" class="text-[11px] sm:text-xs bg-slate-900 border border-slate-800 px-2.5 sm:px-3 py-1 rounded-full font-mono flex items-center gap-1.5 hover:border-indigo-500/60 active:scale-95 transition">
                        <i data-lucide="user" class="w-3.5 h-3.5 text-indigo-300"></i>
                        Profile
                    </button>
                    <button onclick="openBillingModal()" class="text-[11px] sm:text-xs bg-indigo-950 border border-indigo-500/20 px-2.5 sm:px-3 py-1 rounded-full font-mono flex items-center gap-1.5 hover:border-indigo-500/60 active:scale-95 transition">
                        <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-400 animate-pulse"></i>
                        <span id="header-coins">0</span> <span class="text-[10px] text-slate-400">Coins</span>
                    </button>
                    <button onclick="logout()" class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white transition active:scale-95" title="Exit Room">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- AUTH SCREEN -->
        <section id="auth-screen" class="flex-1 flex items-center justify-center py-3 sm:py-6">
            <div class="w-full max-w-md bg-slate-900/65 backdrop-blur-xl border border-indigo-500/15 rounded-3xl p-4 sm:p-6 shadow-2xl relative overflow-hidden mobile-safe-panel">
                <div class="absolute -top-12 -right-12 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl"></div>
                <div class="absolute -bottom-12 -left-12 w-32 h-32 bg-amber-500/5 rounded-full blur-2xl"></div>

                <div class="text-center mb-6 relative">
                    <h2 id="auth-title" class="font-outfit text-2xl font-extrabold text-white">Create Spiritual Session</h2>
                    <p id="auth-subtitle" class="text-slate-400 text-xs mt-1.5 leading-relaxed">Enter your email and birth details to cast your Janam Kundli. You get 5 free questions, then purchase coins to continue.</p>
                </div>

                <!-- Alert Message -->
                <div id="auth-alert" class="hidden p-3.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs mb-5 flex gap-2.5 items-start">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-red-400 shrink-0 mt-0.5"></i>
                    <span id="auth-alert-text">Error message here</span>
                </div>

                <form id="auth-form" onsubmit="handleAuth(event)" class="space-y-4">
                    <!-- Email first -->
                    <div>
                        <label class="block text-[10.5px] font-mono tracking-wider text-slate-400 uppercase mb-1.5">Email Address</label>
                        <input id="auth-email" type="email" placeholder="example@gmail.com" class="w-full bg-slate-950 border border-slate-850 px-3.5 py-2.5 rounded-xl text-sm focus:border-indigo-500/80 outline-none transition" required>
                    </div>

                    <!-- Signup Extra Fields -->
                    <div id="signup-fields" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10.5px] font-mono tracking-wider text-slate-400 uppercase mb-1.5">Date of Birth</label>
                                <input id="reg-dob" type="date" value="1995-10-18" required class="w-full bg-slate-950 border border-slate-850 px-3.5 py-2.5 rounded-xl text-sm focus:border-indigo-500/80 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-[10.5px] font-mono tracking-wider text-slate-400 uppercase mb-1.5">Time of Birth</label>
                                <input id="reg-tob" type="time" value="08:30" class="w-full bg-slate-950 border border-slate-850 px-3.5 py-2.5 rounded-xl text-sm focus:border-indigo-500/80 outline-none transition">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10.5px] font-mono tracking-wider text-slate-400 uppercase mb-1.5">Your Name</label>
                            <input id="reg-name" type="text" placeholder="Malik Jameel" class="w-full bg-slate-950 border border-slate-850 px-3.5 py-2.5 rounded-xl text-sm focus:border-indigo-500/80 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[10.5px] font-mono tracking-wider text-slate-400 uppercase mb-1.5">Phone Number</label>
                            <input id="reg-phone" type="tel" placeholder="+92 300 1234567" class="w-full bg-slate-950 border border-slate-850 px-3.5 py-2.5 rounded-xl text-sm focus:border-indigo-500/80 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[10.5px] font-mono tracking-wider text-slate-400 uppercase mb-1.5">Birth Place (City, Country)</label>
                            <div class="relative">
                                <input id="reg-place" type="text" placeholder="e.g. Lahore, Pakistan" class="w-full bg-slate-950 border border-slate-850 pl-10 pr-4 py-2.5 rounded-xl text-sm focus:border-indigo-500/80 outline-none transition">
                                <i data-lucide="map-pin" class="w-4 h-4 text-slate-500 absolute left-3.5 top-3"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-[10.5px] font-mono tracking-wider text-slate-400 uppercase mb-1.5">Room Password</label>
                        <input id="auth-password" type="password" placeholder="••••••••" class="w-full bg-slate-950 border border-slate-850 px-3.5 py-2.5 rounded-xl text-sm focus:border-indigo-500/80 outline-none transition" required>
                    </div>

                    <button id="auth-btn" type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 border border-indigo-400/20 text-white font-bold rounded-xl text-sm flex items-center justify-center gap-2 transition active:scale-98 cursor-pointer mt-5">
                        <span id="auth-btn-text">Cast Astro Settings</span>
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </form>

                <div class="mt-6 text-center border-t border-slate-850 pt-4">
                    <p class="text-xs text-slate-400">
                        <span id="toggle-label-text">Already configured birth map?</span>
                        <button onclick="toggleAuthMode()" id="toggle-auth-btn" class="text-amber-400 hover:underline font-bold focus:outline-none ml-1">Load Session Login</button>
                    </p>
                </div>
            </div>
        </section>

        <!-- CORE DASHBOARD -->
        <section id="dashboard-screen" class="hidden flex-1 grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6 items-stretch">
            
            <!-- LEFT COLUMN (7 cols) -->
            <div class="lg:col-span-7 flex flex-col space-y-4">
                
                <!-- NAVIGATION TABS -->
                <div class="bg-slate-900/50 backdrop-blur-md rounded-2xl border border-indigo-950/40 p-2 flex gap-1 font-mono text-xs overflow-x-auto shrink-0">
                    <button onclick="switchTab('chart')" id="tab-btn-chart" class="min-w-[92px] flex-1 px-3 py-2 rounded-xl bg-indigo-650 text-white font-extrabold flex items-center justify-center gap-1.5 transition">
                        <i data-lucide="compass" class="w-4 h-4"></i>
                        <span>KUNDLI</span>
                    </button>
                    <button onclick="switchTab('strengths')" id="tab-btn-strengths" class="min-w-[112px] flex-1 px-3 py-2 rounded-xl text-slate-400 hover:text-slate-100 font-extrabold flex items-center justify-center gap-1.5 transition">
                        <i data-lucide="bar-chart-2" class="w-4 h-4"></i>
                        <span>STRENGTHS</span>
                    </button>
                    <button onclick="switchTab('horoscope')" id="tab-btn-horoscope" class="min-w-[150px] flex-1 px-3 py-2 rounded-xl text-slate-400 hover:text-slate-100 font-extrabold flex items-center justify-center gap-1.5 transition">
                        <i data-lucide="moon" class="w-4 h-4"></i>
                        <span>DAILY FORECAST</span>
                    </button>
                    <button onclick="switchTab('admin')" id="tab-btn-admin" class="hidden px-3 py-2 rounded-xl text-amber-400/80 hover:text-amber-300 font-extrabold flex items-center justify-center gap-1.5 transition border border-amber-500/10">
                        <i data-lucide="crown" class="w-4 h-4"></i>
                        <span>HQ HUB</span>
                    </button>
                </div>

                <!-- CONTENT VIEW CONTAINER -->
                <div class="flex-1 bg-slate-900/30 backdrop-blur-md rounded-3xl border border-indigo-500/10 p-3 sm:p-5 shadow-xl flex flex-col justify-center min-h-[360px] lg:min-h-[460px]">
                    
                    <!-- TAB 1: KUNDLI CHART -->
                    <div id="view-chart" class="space-y-4">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 bg-slate-950/60 p-3 rounded-2xl border border-indigo-950/40">
                            <div>
                                <h3 class="font-outfit font-extrabold text-white text-sm">Vedic Birth Star Map</h3>
                                <p class="text-[10px] text-slate-400 mt-0.5 leading-none">Chart placements dynamically generated</p>
                            </div>
                            <button onclick="toggleChartStyle()" class="px-3.5 py-1.5 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 font-bold text-xs flex items-center gap-1 bg-slate-900 transition active:scale-95 cursor-pointer">
                                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                <span id="chart-style-label">NORTH INDEX</span>
                            </button>
                        </div>
                        
                        <!-- Chart SVG stage -->
                        <div id="chart-svg-container" class="w-full flex justify-center py-2 overflow-x-auto">
                        </div>
                    </div>

                    <!-- TAB 2: PLANET STRENGTHS -->
                    <div id="view-strengths" class="hidden space-y-4">
                        <div class="bg-indigo-950/20 border border-indigo-500/10 p-4 rounded-2xl flex items-start gap-3 mb-2">
                            <i data-lucide="info" class="w-5 h-5 text-indigo-400 shrink-0 mt-0.5"></i>
                            <p class="text-xs text-slate-350 leading-relaxed">
                                Planetary strengths are calculated relative to their signs, degrees, and placement houses. High strengths indicate beneficial influence.
                            </p>
                        </div>
                        <div id="strengths-indicators" class="h-full overflow-y-auto max-h-[380px] custom-scrollbar">
                        </div>
                    </div>

                    <!-- TAB 3: DAILY FORECAST -->
                    <div id="view-horoscope" class="hidden flex flex-col h-full space-y-4">
                        <div class="bg-gradient-to-r from-amber-500/10 to-indigo-500/5 border border-amber-500/15 p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400">
                                    <i data-lucide="sun" class="w-4 h-4"></i>
                                </span>
                                <div>
                                    <h3 class="font-outfit font-extrabold text-amber-200 text-sm">Zodiac daily Horoscopes</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Calculated by moon zodiac transits</p>
                                </div>
                            </div>
                            <span id="forecast-moon-sign" class="px-3 py-1 rounded-full text-xs font-mono bg-indigo-950 border border-indigo-500/20 text-indigo-300 font-bold uppercase tracking-widest">—</span>
                        </div>

                        <div id="forecast-loading" class="hidden flex-1 flex flex-col justify-center items-center gap-2.5 py-12">
                            <i data-lucide="refresh-cw" class="w-6 h-6 animate-spin text-amber-400"></i>
                            <span class="text-xs text-slate-500 font-mono">Consulting stars...</span>
                        </div>

                        <div id="forecast-text" class="flex-1 overflow-y-auto max-h-[330px] custom-scrollbar text-xs md:text-sm text-slate-300 space-y-3.5 bg-slate-950/40 p-4 rounded-2xl border border-indigo-950/20 leading-relaxed leading-[1.6]">
                        </div>
                    </div>

                    <!-- TAB 4: ADMIN HUB -->
                    <div id="view-admin" class="hidden space-y-5">
                        <div class="bg-indigo-950/20 border border-amber-500/20 p-4 rounded-2xl flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                            <div>
                                <h3 class="font-outfit font-bold text-amber-300 text-sm uppercase tracking-wider">ADMIN MASTER OFFICE</h3>
                                <p class="text-[10px] text-slate-400 mt-0.5">Real-time stats auditing room</p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="syncAdminMetrics()" class="p-1 px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-800 text-xs font-mono font-bold text-slate-300 flex items-center gap-1 cursor-pointer">
                                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-amber-400"></i> Sync Stats
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-850">
                                <p class="text-[9px] text-slate-500 font-mono">TOTAL VISITORS</p>
                                <p id="admin-stat-users" class="text-xl font-bold font-mono text-slate-100 mt-1">0</p>
                            </div>
                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-850">
                                <p class="text-[9px] text-slate-500 font-mono">GUEST VISITORS</p>
                                <p id="admin-stat-vip" class="text-xl font-bold font-mono text-amber-400 mt-1">0</p>
                            </div>
                            <div class="bg-slate-950 p-3.5 rounded-xl border border-emerald-500/10 bg-gradient-to-tr from-emerald-500/5 to-slate-950">
                                <p class="text-[9px] text-emerald-500/70 font-mono">REVENUE</p>
                                <p id="admin-stat-revenue" class="text-xl font-bold font-mono text-emerald-400 mt-1">$0.00</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-[10px] font-mono text-slate-400 uppercase tracking-widest pl-1">Accounts Ledger</p>
                            <div id="admin-users-list" class="overflow-y-auto max-h-[190px] custom-scrollbar space-y-1.5 bg-slate-950/50 p-2.5 rounded-xl border border-indigo-950/20">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-[10px] font-mono text-slate-400 uppercase tracking-widest pl-1">Payment Ledger</p>
                            <div id="admin-transactions-list" class="overflow-y-auto max-h-[190px] custom-scrollbar space-y-1.5 bg-slate-950/50 p-2.5 rounded-xl border border-indigo-950/20">
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT COLUMN: CHAT PORTAL ENGINE (5 cols) -->
            <div class="lg:col-span-5 flex flex-col bg-slate-900/40 backdrop-blur-md rounded-3xl border border-indigo-500/10 p-3 sm:p-4 shadow-xl min-h-[440px] lg:min-h-[460px]">
                
                <!-- Chat Head -->
                <div class="flex items-center gap-3 border-b border-indigo-950/60 pb-3 mb-3 shrink-0">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-amber-500 to-indigo-600 p-0.5 relative shrink-0">
                        <div class="w-full h-full rounded-full bg-slate-950 flex items-center justify-center text-amber-400 font-bold select-none text-xs">SM</div>
                        <span class="absolute bottom-0.5 right-0.5 w-2.5 h-2.5 bg-emerald-400 border border-slate-900 rounded-full"></span>
                    </div>
                    <div>
                        <h2 class="font-outfit font-extrabold text-sm text-slate-100 flex items-center gap-1">
                            <span>Sufi Malik Assistant</span>
                            <span id="vip-user-badge" class="hidden text-[8px] bg-amber-500/10 text-amber-400 px-1.5 py-0.5 rounded-md border border-amber-500/25 uppercase font-mono font-bold tracking-wider shrink-0 select-none">PREM</span>
                        </h2>
                        <p class="text-[10px] text-slate-400 mt-0.5 leading-none font-mono">Speak with Malik Assistant (Urdu/Eng)</p>
                    </div>
                </div>

                <!-- Chat Messages container -->
                <div id="chat-messages-container" class="flex-1 overflow-y-auto custom-scrollbar space-y-4 p-2 max-h-[52vh] lg:max-h-[380px] min-h-[220px]">
                </div>

                <!-- Input area -->
                <form id="chat-input-form" onsubmit="handleSendChat(event)" class="mt-3 relative shrink-0">
                    <!-- Suggested pills -->
                    <div id="suggested-container" class="flex gap-2 overflow-x-auto pb-2 shrink-0 custom-scrollbar text-[10.5px]">
                        <button type="button" onclick="loadSuggested('Career me growth kab milegi sitaron ke mutabiq?')" class="px-3 py-1.5 rounded-full bg-slate-950 border border-slate-850 hover:border-indigo-500/30 text-slate-400 hover:text-indigo-300 shrink-0 transition text-left cursor-pointer font-medium leading-none">💼 Career growth?</button>
                        <button type="button" onclick="loadSuggested('Sitaron ke mutabiq foreign mukhtalif safar kab khulega?')" class="px-3 py-1.5 rounded-full bg-slate-950 border border-slate-850 hover:border-indigo-500/30 text-slate-400 hover:text-indigo-300 shrink-0 transition text-left cursor-pointer font-medium leading-none">✈️ Foreign safar?</button>
                        <button type="button" onclick="loadSuggested('Matrimonial match kab shuru ho rha hai sitaron se?')" class="px-3 py-1.5 rounded-full bg-slate-950 border border-slate-850 hover:border-indigo-500/30 text-slate-400 hover:text-indigo-300 shrink-0 transition text-left cursor-pointer font-medium leading-none">💍 Shadi kab hogi?</button>
                    </div>

                    <div id="chat-alert-box" class="hidden text-[10px] text-amber-300/95 font-semibold bg-amber-500/10 p-2.5 rounded-xl border border-amber-500/15 mb-3 select-none leading-relaxed flex items-center justify-between gap-2 shrink-0">
                        <span>Your 5 free questions are complete. Purchase coins to continue chatting.</span>
                        <button type="button" onclick="openBillingModal()" class="text-amber-400 underline font-bold uppercase shrink-0">Top-Up</button>
                    </div>

                    <div class="relative flex items-center">
                        <input id="chat-input-text" type="text" placeholder="Ask your astrology question in any language..." class="w-full bg-slate-950 border border-slate-850 pl-4 pr-12 py-3 rounded-2xl text-xs md:text-sm focus:border-indigo-500/80 outline-none transition text-slate-100">
                        <button id="chat-send-btn" type="submit" class="w-9 h-9 absolute right-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white flex items-center justify-center transition active:scale-95 cursor-pointer">
                            <i data-lucide="send" class="w-4 h-4"></i>
                        </button>
                    </div>
                </form>

                <!-- Sandbox testing toolbar -->
                <div class="mt-4 border-t border-slate-850 pt-2.5 flex justify-between items-center text-[10px] font-mono text-slate-450 z-10 relative">
                    <button onclick="openBillingModal()" class="hover:text-amber-300 flex items-center gap-1 transition">
                        <i data-lucide="credit-card" class="w-3 h-3 text-amber-400"></i> Buy Pack
                    </button>
                    <button onclick="resetSession()" class="hover:text-slate-200 flex items-center gap-1 transition">
                        <i data-lucide="rotate-ccw" class="w-3 h-3 text-red-400"></i> Reset Room
                    </button>
                </div>
            </div>
        </section>
    </main>

    <!-- FLOATING BILLING MODAL -->
    <div id="billing-modal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
        <div class="w-full max-w-xl bg-slate-900 border border-indigo-500/20 rounded-3xl p-4 sm:p-6 shadow-2xl relative overflow-hidden mobile-safe-panel">
            <div class="absolute -top-12 -right-12 w-32 h-32 bg-indigo-500/15 rounded-full blur-2xl"></div>
            
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="font-outfit font-extrabold text-white text-lg">Load Astrology Coins Pack</h3>
                    <p class="text-xs text-slate-400 mt-1">Acquire coin configurations for direct Sufi cosmic consultation answers.</p>
                </div>
                <button onclick="closeBillingModal()" class="p-1.5 rounded-xl bg-slate-950 border border-slate-850 text-slate-400 hover:text-white transition active:scale-95">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Standard Packs Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Pack 1 -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-850 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-mono text-indigo-400 font-bold uppercase tracking-wider">Basic Pack</span>
                            <span class="text-xs bg-indigo-950 text-indigo-300 px-2.5 py-0.5 rounded-md font-bold border border-indigo-500/20 select-none">250 COINS</span>
                        </div>
                        <p class="text-2xl font-outfit font-extrabold text-white mt-2">$11.00 <span class="text-xs font-medium text-slate-505">One-time</span></p>
                        <p class="text-[11px] text-slate-450 mt-1.5 leading-relaxed">Perfect for getting quick remedies for immediate business or travel blocks.</p>
                    </div>
                    <button id="pay-pack-basic" onclick="purchasePack('basic')" class="w-full mt-4 py-2 rounded-xl bg-indigo-650 hover:bg-indigo-600 font-bold text-xs text-white transition active:scale-95 cursor-pointer">Confirm Top-Up</button>
                </div>

                <!-- Pack 2 -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-amber-500/15 flex flex-col justify-between relative bg-gradient-to-tr from-amber-500/5 to-slate-950">
                    <span class="absolute -top-2.5 right-4 text-[8px] bg-amber-500 text-slate-950 px-2 py-0.5 rounded-full font-mono font-extrabold uppercase tracking-widest select-none">BEST RATIO</span>
                    <div>
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-mono text-amber-400 font-bold uppercase tracking-wider">Medium Pack</span>
                            <span class="text-xs bg-amber-950 text-amber-300 px-2.5 py-0.5 rounded-md font-bold border border-amber-500/25 select-none">500 COINS</span>
                        </div>
                        <p class="text-2xl font-outfit font-extrabold text-white mt-2">$30.00 <span class="text-xs font-medium text-slate-505">One-time</span></p>
                        <p class="text-[11px] text-slate-450 mt-1.5 leading-relaxed">Extended support for families to map out child careers and marriage matching details.</p>
                    </div>
                    <button id="pay-pack-medium" onclick="purchasePack('medium')" class="w-full mt-4 py-2 rounded-xl bg-amber-500 text-slate-950 hover:bg-amber-400 font-extrabold text-xs transition active:scale-95 cursor-pointer">Confirm Top-Up</button>
                </div>

                <!-- Pack 3 -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-850 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-mono text-indigo-400 font-bold uppercase tracking-wider">Ultra Pack</span>
                            <span class="text-xs bg-indigo-950 text-indigo-300 px-2.5 py-0.5 rounded-md font-bold border border-indigo-500/20 select-none">1000 COINS</span>
                        </div>
                        <p class="text-2xl font-outfit font-extrabold text-white mt-2">$60.00 <span class="text-xs font-medium text-slate-505">One-time</span></p>
                        <p class="text-[11px] text-slate-450 mt-1.5 leading-relaxed">Comprehensive tracking of life phases, Sade Sati transits, and detailed cosmic remedies.</p>
                    </div>
                    <button id="pay-pack-ultra" onclick="purchasePack('ultra')" class="w-full mt-4 py-2 rounded-xl bg-indigo-650 hover:bg-indigo-600 font-bold text-xs text-white transition active:scale-95 cursor-pointer">Confirm Top-Up</button>
                </div>

                <!-- Pack 4 -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-850 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-mono text-indigo-400 font-bold uppercase tracking-wider">Max Pack</span>
                            <span class="text-xs bg-indigo-950 text-indigo-300 px-2.5 py-0.5 rounded-md font-bold border border-indigo-500/20 select-none">1500 COINS</span>
                        </div>
                        <p class="text-2xl font-outfit font-extrabold text-white mt-2">$100.00 <span class="text-xs font-medium text-slate-505">One-time</span></p>
                        <p class="text-[11px] text-slate-450 mt-1.5 leading-relaxed">Unlimited readings for small businesses seeking astrological timeline consultations.</p>
                    </div>
                    <button id="pay-pack-max" onclick="purchasePack('max')" class="w-full mt-4 py-2 rounded-xl bg-indigo-650 hover:bg-indigo-600 font-bold text-xs text-white transition active:scale-95 cursor-pointer">Confirm Top-Up</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PROFILE MODAL -->
    <div id="profile-modal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
        <div class="w-full max-w-md bg-slate-900 border border-indigo-500/20 rounded-3xl p-4 sm:p-6 shadow-2xl relative overflow-hidden mobile-safe-panel">
            <div class="flex justify-between items-start mb-5">
                <div>
                    <h3 class="font-outfit font-extrabold text-white text-lg">My Profile</h3>
                    <p class="text-xs text-slate-400 mt-1">Your current plan, coins, and account details.</p>
                </div>
                <button onclick="closeProfileModal()" class="p-1.5 rounded-xl bg-slate-950 border border-slate-850 text-slate-400 hover:text-white transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div id="profile-details" class="space-y-3 text-sm"></div>
            <button onclick="openBillingModal(); closeProfileModal();" class="w-full mt-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs">View Plans / Buy Coins</button>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        window.ASTRO_API_ENDPOINT = '<?= htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'ai.php') ?>';
    </script>
    <script src="<?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'ai.php') ? 'astrobot/' : '' ?>kundli.js"></script>
    <script src="<?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'ai.php') ? 'astrobot/' : '' ?>app.js"></script>
</body>
</html>