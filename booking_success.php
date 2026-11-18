<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/stripe.php';

$pdo = getDBConnection();
$bookingId = intval($_GET['booking_id'] ?? 0);
$sessionId = trim($_GET['session_id'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$wasAlreadyPaid = ($booking['payment_status'] === 'paid');

// Mark booking as paid and confirmed
$updateStmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid', status = 'confirmed' WHERE id = ?");
$updateStmt->execute([$bookingId]);
$booking['payment_status'] = 'paid';
$booking['status'] = 'confirmed';

// Send Admin Notification (Email to admin@theastrohealer.com & WhatsApp) only once
if (!$wasAlreadyPaid) {
    require_once __DIR__ . '/includes/notifications.php';
    try {
        sendAdminPaymentAlert('booking', [
            'name' => $booking['first_name'] . ' ' . $booking['last_name'],
            'email' => $booking['email'],
            'item_title' => $booking['service'],
            'amount' => $booking['amount'],
            'session_id' => $sessionId,
            'booking_id' => $booking['id']
        ]);

        require_once __DIR__ . '/includes/audit.php';
        logAuditTrail('BOOKING_PAID_STRIPE', 'booking', "Session consultation #AH-" . str_pad($booking['id'], 5, '0', STR_PAD_LEFT) . " paid for {$booking['service']} (\${$booking['amount']}) via Stripe", 'success', null, $booking['email'], $booking['first_name'] . ' ' . $booking['last_name']);
    } catch (\Throwable $e) {

        error_log('Notification error: ' . $e->getMessage());
    }
}


$pageTitle = 'Booking Confirmed — The Astro Healer';
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: calc(100vh - 120px); display: flex; align-items: center; justify-content: center; padding: 7rem 1.5rem 5rem; position: relative; z-index: 1;">
  <div style="width: 100%; max-width: 620px; background: var(--card-bg); border: 1.5px solid var(--gold); border-radius: 18px; padding: 3rem 2.5rem; text-align: center; box-shadow: 0 25px 60px rgba(0,0,0,0.85), 0 0 35px var(--gold-glow); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);">
    
    <div style="width: 76px; height: 76px; border-radius: 50%; background: rgba(76, 175, 80, 0.18); border: 2px solid #4caf50; display: inline-flex; align-items: center; justify-content: center; color: #81c784; font-size: 2.2rem; margin-bottom: 1.5rem; box-shadow: 0 0 25px rgba(76, 175, 80, 0.4);">
      <i class="fa-solid fa-check"></i>
    </div>

    <span class="section-label">Payment Successful · Stripe Verified</span>
    <h1 style="font-size: 2.3rem; color: #ffffff; margin-bottom: 0.8rem; font-family: var(--font-heading);">
      Your Session is Confirmed!
    </h1>
    <div class="gold-divider"><span>✦</span></div>

    <p style="font-size: 1.02rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 2rem;">
      Thank you, <strong><?= htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']) ?></strong>. Your payment of <strong style="color:var(--gold-bright);"><?= formatPrice($booking['amount']) ?></strong> has been received successfully through Stripe.
    </p>

    <!-- Session Details Box -->
    <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1.5rem 1.8rem; text-align: left; margin-bottom: 2.2rem;">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.92rem;">
        <div>
          <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--muted); letter-spacing: 0.08em;">Booking ID</div>
          <strong style="color: var(--gold-light);">#AH-<?= str_pad($booking['id'], 5, '0', STR_PAD_LEFT) ?></strong>
        </div>
        <div>
          <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--muted); letter-spacing: 0.08em;">Service</div>
          <strong style="color: #ffffff;"><?= htmlspecialchars($booking['service']) ?></strong>
        </div>
        <div>
          <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--muted); letter-spacing: 0.08em;">Client Email</div>
          <span style="color: var(--text-primary);"><?= htmlspecialchars($booking['email']) ?></span>
        </div>
        <div>
          <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--muted); letter-spacing: 0.08em;">Payment Status</div>
          <span style="color: #81c784; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Paid via Stripe</span>
        </div>
      </div>
    </div>

    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
      <a href="https://wa.me/14706505315?text=<?= urlencode("Hello Malik Mehboob, I have booked a session (#AH-" . str_pad($booking['id'], 5, '0', STR_PAD_LEFT) . ") for " . $booking['service'] . ". Please confirm my appointment time.") ?>" target="_blank" class="btn-primary">
        <i class="fa-brands fa-whatsapp"></i> Confirm Time on WhatsApp
      </a>
      <a href="<?= BASE_URL ?>/index.php" class="btn-outline">
        <i class="fa-solid fa-house"></i> Return Home
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
