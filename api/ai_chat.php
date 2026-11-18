<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;
$userMessage = trim($data['message'] ?? '');

if (empty($userMessage)) {
    echo json_encode(['success' => false, 'reply' => 'Please enter a cosmic question.']);
    exit;
}

$pdo = getDBConnection();

// Check if Gemini or OpenAI API key is set in system_settings
$apiKeyStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_api_key' LIMIT 1");
$customApiKey = $apiKeyStmt ? $apiKeyStmt->fetchColumn() : null;

// System prompt context
$systemPrompt = "You are Malik AI, the official Cosmic AI Astrologer and assistant for The Astro Healer (founded by Malik Mehboob, Master Vedic & BNN Astrologer).
You provide wise, respectful, and grounded guidance based on ancient Vedic astrology, Bhrigu Nandi Nadi (BNN), Pythagorean numerology, and cosmic timing.
Key Offerings:
1. Academy Courses:
   - Bhrigu Nandi Nadi (BNN) Astrology Course ($800) - Master predictive technique without complex dasha calculations.
   - Basic Vedic Astrology Foundation Course ($299) - A-to-Z Guide to 12 Houses, 9 Planets, 12 Signs & Navamsha.
   - Comprehensive Numerology Masterclass ($199) - Destiny numbers, Name correction, Loshu Grid.
2. 1-on-1 Consultations:
   - Horoscope Reading ($199)
   - AstroVasto Consult ($299)
   - Tarot Card Reading ($199)
   - Marital Solutions & Compatibility ($250)
   - Name Numerology Correction ($150)
   - Annual Zaicha Consultation ($199)
Keep answers concise, spiritual, uplifting, and actionable. Recommend relevant courses or consultations when appropriate.";

// If real custom API key is provided
if (!empty($customApiKey) && strlen($customApiKey) > 15) {
    try {
        // Example: Gemini or OpenAI call
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($customApiKey));
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\nUser Question: " . $userMessage]
                    ]
                ]
            ]
        ];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $parsed = json_decode($res, true);
            $text = $parsed['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (!empty($text)) {
                echo json_encode(['success' => true, 'reply' => $text]);
                exit;
            }
        }
    } catch (\Exception $e) {
        // Fallback to intelligent local oracle engine
    }
}

// Built-in Intelligent Vedic Oracle Engine (Contextual fallback)
$msg = strtolower($userMessage);
$reply = '';

if (strpos($msg, 'bnn') !== false || strpos($msg, 'bhrigu') !== false) {
    $reply = "🌟 **Bhrigu Nandi Nadi (BNN) Astrology:**\nBNN is one of ancient India's most accurate predictive systems. Unlike traditional systems that require complex birth time rectifications and dasha calculations, BNN uses planetary transit combinations and directional alignment to make rapid, laser-accurate life predictions.\n\nMalik Mehboob's **BNN Master Course ($800)** covers 14 comprehensive classes with lifetime access and personal mentorship.";
} elseif (strpos($msg, 'course') !== false || strpos($msg, 'learn') !== false || strpos($msg, 'class') !== false) {
    $reply = "🎓 **The Astro Healer Academy offers 3 Certification Programs:**\n\n1. **Basic Vedic Astrology Course ($299):** Perfect for beginners. Learn 12 Houses (Bhavas), 9 Grahas, Rashis, and chart decoding.\n2. **Numerology Masterclass ($199):** Master Pythagorean & Chaldean numbers, Name corrections, and Loshu Grid.\n3. **BNN Astrology Course ($800):** Advanced predictive masterclass with real client case studies.\n\nAll courses include lifetime lecture access, student portal, and certificate upon completion!";
} elseif (strpos($msg, 'numerology') !== false || strpos($msg, 'number') !== false || strpos($msg, 'name correction') !== false) {
    $reply = "🔢 **Sacred Numerology Insight:**\nYour birth date carries your **Destiny Number (Bhagyank)** and your day of birth represents your **Psychic Number (Mulank)**. When your name spelling vibrates in disharmony with your destiny number, career obstacles and financial stagnation can occur.\n\nWe offer **Name Numerology Correction ($150)** and the complete **Numerology Masterclass ($199)** to align your vibration with success.";
} elseif (strpos($msg, 'kundali') !== false || strpos($msg, 'marriage') !== false || strpos($msg, 'marital') !== false || strpos($msg, 'compatibility') !== false) {
    $reply = "💍 **Marital Harmony & Kundali Matching:**\nVedic Kundali Milan analyzes the 36 Gunas (Ashtakoota), Manglik Dosha, emotional sync via the Moon sign, and longevity of the relationship. Malik Mehboob provides practical remedies for marital friction and dispute resolution.\n\nYou can book a dedicated **Marital Solutions & Compatibility Consultation ($250)** directly with Malik Mehboob.";
} elseif (strpos($msg, 'horoscope') !== false || strpos($msg, 'reading') !== false || strpos($msg, 'birth chart') !== false) {
    $reply = "✨ **Complete Natal Horoscope Reading ($199):**\nIn a private 1-on-1 session, Malik Mehboob decodes your Lagna chart, planetary dasha timeline for the next 3 to 5 years, career potentials, financial yoga, and personalized Vedic remedies (gemstones, mantras, charity).";
} elseif (strpos($msg, 'gemstone') !== false || strpos($msg, 'ruby') !== false || strpos($msg, 'emerald') !== false || strpos($msg, 'sapphire') !== false || strpos($msg, 'pearl') !== false) {
    $reply = "💎 **Vedic Gemstone Science (Ratna Shastra):**\nGemstones are cosmic prisms that amplify beneficial planetary frequencies:\n- **Surya (Sun):** Ruby (Manikya) for vitality & leadership.\n- **Chandra (Moon):** Natural Pearl (Moti) for peace of mind.\n- **Brihaspati (Jupiter):** Yellow Sapphire (Pukhraj) for wisdom & wealth.\n- **Budha (Mercury):** Emerald (Panna) for intellect & commerce.\n\n*Important:* Never wear gemstones for malefic or enemy planets without a chart analysis!";
} elseif (strpos($msg, 'book') !== false || strpos($msg, 'consult') !== false || strpos($msg, 'appointment') !== false) {
    $reply = "📅 **Booking a Session:**\nYou can reserve your private session right away on our **Book Session** page. Simply provide your Date, Time, and City of Birth, and you will be redirected directly to secure Stripe checkout. We confirm your appointment time within 24 hours!";
} elseif (strpos($msg, 'price') !== false || strpos($msg, 'cost') !== false || strpos($msg, 'fee') !== false) {
    $reply = "💰 **Consultation & Course Pricing:**\n- Horoscope Reading: $199\n- Tarot Card Reading: $199\n- Annual Zaicha Consultation: $199\n- AstroVasto Consult: $299\n- Marital Solutions: $250\n- Name Numerology: $150\n- BNN Astrology Course: $800\n- Basic Vedic Astrology: $299\n- Numerology Masterclass: $199";
} else {
    $reply = "✨ **Celestial Insight:**\nThe cosmic forces of the 9 Grahas (Planets) and 12 Rashis (Constellations) constantly shape our daily experiences. Whether you seek guidance on career milestones, relationship harmony, or wish to master Vedic astrology, Malik Mehboob and The Astro Healer are here to guide you.\n\nFeel free to ask about specific Zodiac signs, Numerology Life Path numbers, or explore our Academy Courses!";
}

echo json_encode([
    'success' => true,
    'reply' => $reply
]);
