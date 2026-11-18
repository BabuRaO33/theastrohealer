<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isset($_GET['action']) && $_GET['action'] === 'chat') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $message = trim((string) ($input['message'] ?? ''));
    $model = trim((string) ($input['model'] ?? 'grok-4-latest'));

    $allowedModels = ['grok-4-latest', 'grok-3-mini', 'grok-3', 'grok-2-1212'];
    if (!in_array($model, $allowedModels, true)) {
        $model = 'grok-4-latest';
    }

    if ($message === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Please type a test message.']);
        exit;
    }

    if (empty($xai_api_key)) {
        http_response_code(500);
        echo json_encode(['error' => 'XAI_API_KEY is missing in .env']);
        exit;
    }

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a concise astrology chatbot test assistant for The Astro Healer. Reply in the same language as the user. Mention that this is a Grok API test response.',
            ],
            [
                'role' => 'user',
                'content' => $message,
            ],
        ],
        'temperature' => 0.7,
        'max_tokens' => 800,
    ];

    $started = microtime(true);
    $ch = curl_init('https://api.x.ai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $xai_api_key,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $elapsedMs = (int) round((microtime(true) - $started) * 1000);
    $decoded = json_decode((string) $response, true);

    if ($curlError !== '') {
        http_response_code(502);
        echo json_encode(['error' => 'Connection failed: ' . $curlError, 'elapsed_ms' => $elapsedMs]);
        exit;
    }

    if ($status < 200 || $status >= 300) {
        http_response_code($status ?: 502);
        echo json_encode([
            'error' => $decoded['error']['message'] ?? ('xAI API returned HTTP ' . $status),
            'status' => $status,
            'elapsed_ms' => $elapsedMs,
            'raw' => $decoded ?: substr((string) $response, 0, 500),
        ]);
        exit;
    }

    echo json_encode([
        'answer' => $decoded['choices'][0]['message']['content'] ?? '',
        'model' => $decoded['model'] ?? $model,
        'usage' => $decoded['usage'] ?? null,
        'elapsed_ms' => $elapsedMs,
    ]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Grok API Test | The Astro Healer</title>
    <style>
        :root { color-scheme: dark; font-family: Arial, sans-serif; }
        body { margin: 0; min-height: 100vh; background: #090b16; color: #e5e7eb; display: grid; place-items: center; padding: 24px; }
        .card { width: min(760px, 100%); background: #111827; border: 1px solid #243047; border-radius: 22px; padding: 24px; box-shadow: 0 20px 70px rgba(0,0,0,.35); }
        h1 { margin: 0 0 8px; font-size: 28px; }
        p { color: #aab3c5; line-height: 1.6; }
        label { display: block; margin: 18px 0 8px; color: #cbd5e1; font-size: 14px; }
        textarea, select { width: 100%; box-sizing: border-box; border-radius: 14px; border: 1px solid #334155; background: #070b14; color: #f8fafc; padding: 14px; font-size: 15px; }
        textarea { min-height: 130px; resize: vertical; }
        button { margin-top: 18px; border: 0; border-radius: 14px; padding: 13px 18px; background: #6366f1; color: white; font-weight: 700; cursor: pointer; }
        button:disabled { opacity: .6; cursor: wait; }
        .result { white-space: pre-wrap; margin-top: 20px; padding: 16px; background: #070b14; border: 1px solid #263244; border-radius: 16px; min-height: 80px; }
        .meta { margin-top: 10px; color: #8ea0ba; font-size: 13px; }
        .ok { color: #86efac; }
        .err { color: #fca5a5; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Grok API Test</h1>
        <p>Yeh page sirf testing ke liye hai. API key server-side <code>.env</code> se use ho rahi hai, browser mein expose nahi hoti.</p>

        <form id="test-form">
            <label for="model">Model</label>
            <select id="model">
                <option value="grok-4-latest">grok-4-latest</option>
                <option value="grok-3-mini">grok-3-mini</option>
                <option value="grok-3">grok-3</option>
                <option value="grok-2-1212">grok-2-1212</option>
            </select>

            <label for="message">Test Question</label>
            <textarea id="message">Meri kundli ke hisaab se career ke liye short guidance do.</textarea>

            <button id="send-btn" type="submit">Test Grok API</button>
        </form>

        <div id="result" class="result">Result yahan show hoga.</div>
        <div id="meta" class="meta"></div>
    </main>

    <script>
        const form = document.getElementById('test-form');
        const result = document.getElementById('result');
        const meta = document.getElementById('meta');
        const button = document.getElementById('send-btn');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            button.disabled = true;
            result.className = 'result';
            result.textContent = 'Calling Grok...';
            meta.textContent = '';

            try {
                const response = await fetch(`${window.location.pathname}?action=chat`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        model: document.getElementById('model').value,
                        message: document.getElementById('message').value
                    })
                });
                const data = await response.json();
                if (!response.ok) {
                    result.className = 'result err';
                    result.textContent = data.error || 'Grok API failed.';
                    meta.textContent = data.status ? `HTTP ${data.status}` : '';
                    return;
                }
                result.className = 'result ok';
                result.textContent = data.answer || 'No answer text returned.';
                meta.textContent = `Model: ${data.model || '-'} | Time: ${data.elapsed_ms || '-'}ms | Usage: ${JSON.stringify(data.usage || {})}`;
            } catch (error) {
                result.className = 'result err';
                result.textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
    </script>
</body>
</html>
