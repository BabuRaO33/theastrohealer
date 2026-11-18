<?php
/**
 * Admin & Client Notification Services
 * Handles Email & WhatsApp (CallMeBot) Alerts
 * The Astro Healer
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Get notification settings (combines DB system_settings & config constants)
 */
function getNotificationSettings() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('contact_email', 'whatsapp_number', 'whatsapp_callmebot_apikey')");
    $settings = [
        'admin_email' => defined('ASTRO_ADMIN_EMAIL') ? ASTRO_ADMIN_EMAIL : 'admin@theastrohealer.com',
        'whatsapp_phone' => defined('ASTRO_WHATSAPP_PHONE') ? ASTRO_WHATSAPP_PHONE : '14706505315',
        'callmebot_apikey' => defined('ASTRO_WHATSAPP_CALLMEBOT_APIKEY') ? ASTRO_WHATSAPP_CALLMEBOT_APIKEY : '',
    ];

    while ($row = $stmt->fetch()) {
        if ($row['setting_key'] === 'contact_email' && !empty($row['setting_value'])) {
            $settings['admin_email'] = $row['setting_value'];
        } elseif ($row['setting_key'] === 'whatsapp_number' && !empty($row['setting_value'])) {
            // Strip any non-digits
            $cleaned = preg_replace('/\D/', '', $row['setting_value']);
            if (!empty($cleaned)) {
                $settings['whatsapp_phone'] = $cleaned;
            }
        } elseif ($row['setting_key'] === 'whatsapp_callmebot_apikey' && !empty($row['setting_value'])) {
            $settings['callmebot_apikey'] = trim($row['setting_value']);
        }
    }

    return $settings;
}

/**
 * Dispatch payment alerts for both Courses & Bookings
 * 
 * @param string $type 'course' or 'booking'
 * @param array $details Associative array of order details
 */
function sendAdminPaymentAlert($type, $details) {
    $settings = getNotificationSettings();
    $adminEmail = $settings['admin_email'];
    $phone = $settings['whatsapp_phone'];
    $apiKey = $settings['callmebot_apikey'];

    $isCourse = ($type === 'course');
    $title = $isCourse ? 'Course Enrollment' : 'Consultation Booking';
    $customerName = htmlspecialchars($details['name'] ?? 'Valued Client');
    $customerEmail = htmlspecialchars($details['email'] ?? 'N/A');
    $itemName = htmlspecialchars($details['item_title'] ?? 'Astrology Service');
    $amount = number_format(floatval($details['amount'] ?? 0), 2);
    $currency = strtoupper($details['currency'] ?? 'USD');
    $sessionId = htmlspecialchars($details['session_id'] ?? 'N/A');
    $bookingId = isset($details['booking_id']) ? '#AH-' . str_pad($details['booking_id'], 5, '0', STR_PAD_LEFT) : 'N/A';
    $date = date('d M Y, h:i A');

    // 1. Send Admin Email Notification
    $subject = "🌟 [The Astro Healer] New Paid {$title}: \${$amount} {$currency} - {$itemName}";
    
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='utf-8'>
      <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #0b0c10; color: #e0e0e0; margin: 0; padding: 20px; }
        .wrapper { max-width: 600px; margin: 0 auto; background: #12131a; border: 1px solid #d4af37; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.6); }
        .header { background: linear-gradient(135deg, #1f1b2e 0%, #0d0c18 100%); padding: 30px 20px; text-align: center; border-bottom: 2px solid #d4af37; }
        .header h1 { color: #f5d77f; margin: 0; font-size: 24px; letter-spacing: 1px; }
        .header p { color: #aaa; margin: 5px 0 0; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; }
        .content { padding: 30px 25px; }
        .alert-pill { display: inline-block; background: rgba(76, 175, 80, 0.2); color: #81c784; border: 1px solid #4caf50; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 20px; }
        .table-info { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .table-info td { padding: 12px 14px; border-bottom: 1px solid #232533; font-size: 14px; }
        .table-info td.label { color: #8f92a1; width: 38%; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .table-info td.val { color: #ffffff; font-weight: 500; }
        .amount-highlight { color: #f5d77f; font-size: 22px; font-weight: bold; }
        .footer { background: #0b0c10; padding: 20px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #222; }
      </style>
    </head>
    <body>
      <div class='wrapper'>
        <div class='header'>
          <h1>THE ASTRO HEALER</h1>
          <p>Instant Payment & Booking Alert</p>
        </div>
        <div class='content'>
          <span class='alert-pill'>✓ Verified Stripe Payment Received</span>
          <h2 style='color:#fff; margin-top:0; font-size: 20px;'>New {$title} Received</h2>
          <p style='color:#bbb; line-height: 1.6;'>A student/client has successfully completed payment via Stripe. Details are outlined below:</p>

          <table class='table-info'>
            <tr>
              <td class='label'>Payment Status</td>
              <td class='val' style='color:#81c784; font-weight:bold;'>PAID via Stripe (Live)</td>
            </tr>
            <tr>
              <td class='label'>Amount Paid</td>
              <td class='val amount-highlight'>\${$amount} {$currency}</td>
            </tr>
            <tr>
              <td class='label'>Order / Item</td>
              <td class='val'><strong style='color:#f5d77f;'>{$itemName}</strong></td>
            </tr>
            <tr>
              <td class='label'>Customer Name</td>
              <td class='val'>{$customerName}</td>
            </tr>
            <tr>
              <td class='label'>Customer Email</td>
              <td class='val'><a href='mailto:{$customerEmail}' style='color:#81c784; text-decoration:none;'>{$customerEmail}</a></td>
            </tr>";

    if (!$isCourse && $bookingId !== 'N/A') {
        $htmlBody .= "
            <tr>
              <td class='label'>Booking Reference</td>
              <td class='val'>{$bookingId}</td>
            </tr>";
    }

    $htmlBody .= "
            <tr>
              <td class='label'>Stripe Session ID</td>
              <td class='val' style='font-family:monospace; font-size:12px; word-break:break-all; color:#ccc;'>{$sessionId}</td>
            </tr>
            <tr>
              <td class='label'>Transaction Date</td>
              <td class='val'>{$date}</td>
            </tr>
          </table>

          <div style='background: #1a1b24; padding: 15px; border-radius: 8px; border-left: 4px solid #d4af37; margin-top: 25px;'>
            <p style='margin:0; font-size: 13px; color:#bbb;'>
              " . ($isCourse ? "✨ The student's course access has been activated automatically in their student portal." : "📞 The client has been advised to connect on WhatsApp or await confirmation.") . "
            </p>
          </div>
        </div>
        <div class='footer'>
          &copy; " . date('Y') . " The Astro Healer | Moon and Stars Solutions Provider<br>
          Sent automatically to {$adminEmail}
        </div>
      </div>
    </body>
    </html>
    ";

    sendEmailMessage($adminEmail, $subject, $htmlBody, $customerEmail);

    // 2. Send Admin WhatsApp Notification (CallMeBot)
    if (!empty($apiKey) && !empty($phone)) {
        $waText = "🌟 *THE ASTRO HEALER — NEW PAYMENT* 🌟\n\n";
        $waText .= "💰 *Amount:* \${$amount} {$currency}\n";
        $waText .= "🏷️ *Item:* {$details['item_title']}\n";
        $waText .= "👤 *Client:* " . ($details['name'] ?? 'Client') . "\n";
        $waText .= "📧 *Email:* " . ($details['email'] ?? 'N/A') . "\n";
        if (!$isCourse && isset($details['booking_id'])) {
            $waText .= "🔖 *Booking ID:* #AH-" . str_pad($details['booking_id'], 5, '0', STR_PAD_LEFT) . "\n";
        }
        $waText .= "⏰ *Date:* {$date}\n\n";
        $waText .= ($isCourse ? "✅ Student portal access granted automatically." : "📲 Please check WhatsApp desk for client connection.");

        sendCallMeBotWhatsApp($phone, $waText, $apiKey);
    }
}

/**
 * Sends HTML Email via standard mail() with proper MIME headers
 */
function sendEmailMessage($to, $subject, $htmlBody, $replyTo = '') {
    $fromEmail = 'notifications@theastrohealer.com';
    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=UTF-8";
    $headers[] = "From: The Astro Healer <{$fromEmail}>";
    if (!empty($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = "Reply-To: {$replyTo}";
    }
    $headers[] = "X-Mailer: PHP/" . phpversion();

    @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * Sends WhatsApp message via CallMeBot API
 */
function sendCallMeBotWhatsApp($phone, $message, $apiKey) {
    if (empty($phone) || empty($message) || empty($apiKey)) {
        return false;
    }

    $encodedText = urlencode($message);
    $url = "https://api.callmebot.com/whatsapp.php?phone={$phone}&text={$encodedText}&apikey={$apiKey}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4); // Quick timeout so it never blocks page load
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
