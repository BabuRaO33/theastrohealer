<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();
$pdo = getDBConnection();

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $keys = [
        'site_title', 'site_tagline', 'contact_email', 'whatsapp_number', 'whatsapp_callmebot_apikey',
        'stripe_publishable_key', 'stripe_secret_key', 'stripe_currency', 'currency_symbol'
    ];


    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($keys as $k) {
        $val = trim($_POST[$k] ?? '');
        $stmt->execute([$k, $val]);
    }

    setFlash('success', 'Settings and Stripe configuration updated successfully!');
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

// Fetch all current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$current = [];
while ($row = $stmt->fetch()) {
    $current[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = 'Stripe & Platform Settings';
$adminPage = 'settings';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="margin-bottom: 2rem;">
  <h2 style="font-size:1.6rem; color:#fff; margin-bottom:0.3rem;">Platform & Payment Settings</h2>
  <p style="font-size:0.85rem; color:var(--dash-muted);">Configure Stripe API keys, payment currencies, and site contact information</p>
</div>

<form method="POST">
  <input type="hidden" name="action" value="save_settings">

  <!-- Stripe Gateway Card -->
  <div class="dash-panel" style="border-color: rgba(99, 91, 255, 0.4); margin-bottom: 2rem;">
    <div class="panel-header">
      <h3 class="panel-title" style="color: #a39cfd;">
        <i class="fa-brands fa-stripe" style="font-size:1.8rem; vertical-align:middle;"></i> 
        Stripe Payment Gateway Configuration
      </h3>
      <span class="badge badge-active"><i class="fa-solid fa-shield-halved"></i> SSL Encrypted</span>
    </div>
    <p style="font-size:0.85rem; color:var(--dash-muted); margin-bottom:1.5rem;">
      Enter your live or test Stripe API keys below. When a student purchases any of the 3 courses, Stripe Checkout handles the transaction and automatically unlocks the course in their student portal.
    </p>

    <div class="form-group">
      <label class="form-label">Stripe Publishable Key *</label>
      <input type="text" name="stripe_publishable_key" class="form-control" value="<?= htmlspecialchars($current['stripe_publishable_key'] ?? '') ?>" placeholder="pk_live_... or pk_test_...">
      <small style="color:var(--dash-muted); font-size:0.75rem; margin-top:0.3rem; display:block;">Found in your Stripe Dashboard &gt; Developers &gt; API keys.</small>
    </div>

    <div class="form-group">
      <label class="form-label">Stripe Secret Key *</label>
      <input type="password" name="stripe_secret_key" class="form-control" value="<?= htmlspecialchars($current['stripe_secret_key'] ?? '') ?>" placeholder="sk_live_... or sk_test_...">
      <small style="color:var(--dash-muted); font-size:0.75rem; margin-top:0.3rem; display:block;">Your private restricted key. Kept secure on server.</small>
    </div>

    <div class="form-row-2">
      <div class="form-group">
        <label class="form-label">Currency Code</label>
        <select name="stripe_currency" class="form-control">
          <?php $curr = strtolower($current['stripe_currency'] ?? 'usd'); ?>
          <option value="usd" <?= $curr === 'usd' ? 'selected' : '' ?>>USD — United States Dollar ($)</option>
          <option value="pkr" <?= $curr === 'pkr' ? 'selected' : '' ?>>PKR — Pakistani Rupee (Rs)</option>
          <option value="eur" <?= $curr === 'eur' ? 'selected' : '' ?>>EUR — Euro (€)</option>
          <option value="gbp" <?= $curr === 'gbp' ? 'selected' : '' ?>>GBP — British Pound (£)</option>
          <option value="aed" <?= $curr === 'aed' ? 'selected' : '' ?>>AED — UAE Dirham</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Currency Display Symbol</label>
        <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($current['currency_symbol'] ?? '$') ?>" placeholder="e.g. $ or Rs">
      </div>
    </div>
  </div>

  <!-- General Platform Settings -->
  <div class="dash-panel">
    <div class="panel-header">
      <h3 class="panel-title"><i class="fa-solid fa-sliders" style="color:var(--dash-gold-bright);"></i> General Academy Settings</h3>
    </div>

    <div class="form-row-2">
      <div class="form-group">
        <label class="form-label">Academy Title</label>
        <input type="text" name="site_title" class="form-control" value="<?= htmlspecialchars($current['site_title'] ?? 'The Astro Healer') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Tagline</label>
        <input type="text" name="site_tagline" class="form-control" value="<?= htmlspecialchars($current['site_tagline'] ?? 'Moon and Stars Solutions Provider') ?>">
      </div>
    </div>

    <div class="form-row-2">
      <div class="form-group">
        <label class="form-label">Admin Notification Email *</label>
        <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($current['contact_email'] ?? 'admin@theastrohealer.com') ?>">
        <small style="color:var(--dash-muted); font-size:0.75rem; margin-top:0.3rem; display:block;">Receives instant email alerts on Stripe payments & course enrollments.</small>
      </div>
      <div class="form-group">
        <label class="form-label">Official WhatsApp Number *</label>
        <input type="text" name="whatsapp_number" class="form-control" value="<?= htmlspecialchars($current['whatsapp_number'] ?? '+14706505315') ?>" placeholder="+14706505315">
        <small style="color:var(--dash-muted); font-size:0.75rem; margin-top:0.3rem; display:block;">Target WhatsApp number for automated alerts & client inquiries.</small>
      </div>
    </div>

    <div class="form-group" style="margin-top: 0.5rem; background: rgba(37, 211, 102, 0.05); border: 1px dashed rgba(37, 211, 102, 0.35); border-radius: 8px; padding: 1.2rem;">
      <label class="form-label" style="color: #81c784;">
        <i class="fa-brands fa-whatsapp"></i> CallMeBot WhatsApp API Key (Optional for Instant WhatsApp Alerts)
      </label>
      <input type="text" name="whatsapp_callmebot_apikey" class="form-control" value="<?= htmlspecialchars($current['whatsapp_callmebot_apikey'] ?? '') ?>" placeholder="e.g. 849201">
      <small style="color:var(--dash-muted); font-size:0.78rem; line-height:1.5; margin-top:0.5rem; display:block;">
        <strong>How to get free WhatsApp alerts on +1 (470) 650-5315:</strong><br>
        1. From your WhatsApp (+14706505315), message contact <code>+34 644 71 81 99</code><br>
        2. Send message: <code>I allow callmebot to send me messages</code><br>
        3. Bot replies with your personal API Key. Paste it above and save!
      </small>
    </div>

    <button type="submit" class="btn-primary" style="margin-top: 1rem;">
      <i class="fa-solid fa-floppy-disk"></i> Save All Settings
    </button>
  </div>
</form>


<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
