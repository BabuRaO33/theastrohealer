<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/stripe.php';

$pdo = getDBConnection();
$preselected = trim($_GET['service'] ?? '');
$bookingError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_session') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');
    $birthTime = trim($_POST['birth_time'] ?? '');
    $birthPlace = trim($_POST['birth_place'] ?? '');
    $userNote = trim($_POST['message'] ?? '');

    if (empty($firstName) || empty($email) || empty($service)) {
        $bookingError = 'Please fill out all required fields marked with an asterisk (*).';
    } else {
        $combinedMessage = "Birth Date: " . ($birthDate ?: 'Not provided') . "\n"
                         . "Birth Time: " . ($birthTime ?: 'Not provided') . "\n"
                         . "Birth Place: " . ($birthPlace ?: 'Not provided') . "\n\n"
                         . "Notes: " . $userNote;

        $amount = getServicePrice($service);
        $stmt = $pdo->prepare("INSERT INTO bookings (first_name, last_name, email, service, amount, message, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, 'unpaid', 'pending')");
        
        if ($stmt->execute([$firstName, $lastName, $email, $service, $amount, $combinedMessage])) {
            $bookingId = $pdo->lastInsertId();
            $bookingData = [
                'id' => $bookingId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'service' => $service,
                'amount' => $amount
            ];

            $successUrl = BASE_URL . '/booking_success.php?booking_id=' . $bookingId;
            $cancelUrl = BASE_URL . '/booking.php?service=' . urlencode($service);

            $session = createStripeBookingSession($bookingData, $successUrl, $cancelUrl);
            if ($session['success'] && !empty($session['url'])) {
                header('Location: ' . $session['url']);
                exit;
            } else {
                $bookingError = 'Unable to initiate Stripe payment. Please try again.';
            }
        } else {
            $bookingError = 'Database error. Please try again.';
        }
    }
}

$pageTitle = 'Book Session & Direct Stripe Payment — The Astro Healer';
$pageDescription = 'Reserve your private 1-on-1 Zaicha reading, horoscope analysis, or AstroVasto consultation with Malik Mehboob. Secure Stripe checkout & instant confirmation.';
$pageKeywords = 'book astrology session, reserve zaicha reading, horoscope appointment, Malik Mehboob booking';
$canonicalUrl = BASE_URL . '/booking.php';

$bookingSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => 'Book Astrology Consultation — The Astro Healer',
    'description' => 'Reserve private 1-on-1 Vedic consultation with Malik Mehboob with secure Stripe checkout.',
    'potentialAction' => [
        '@type' => 'ReserveAction',
        'target' => [
            '@type' => 'EntryPoint',
            'urlTemplate' => BASE_URL . '/booking.php',
            'actionPlatform' => [
                'http://schema.org/DesktopWebPlatform',
                'http://schema.org/MobileWebPlatform'
            ]
        ],
        'result' => [
            '@type' => 'Reservation',
            'name' => 'Astrology Consultation Session'
        ]
    ]
];
$customJsonLd = json_encode($bookingSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
require_once __DIR__ . '/includes/header.php';
?>

<!-- BREADCRUMB / HERO -->
<section style="padding: 7.5rem 1.5rem 3.5rem; background: linear-gradient(180deg, var(--deep2) 0%, var(--deep) 100%); text-align: center; position: relative; z-index: 1;">
  <div class="container">
    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; font-size: 0.84rem; color: var(--muted); margin-bottom: 1.2rem;">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <span>/</span>
      <span style="color: var(--gold-light);">Book Consultation</span>
    </div>
    <span class="section-label">Direct Stripe Checkout · Instant Confirmation</span>
    <h1 style="font-size: clamp(2.4rem, 4.5vw, 3.8rem); margin-bottom: 0.8rem;">Reserve Your Session</h1>
    <div class="gold-divider"><span>✦</span></div>
    <p style="max-width: 680px; margin: 0 auto; font-size: 1.05rem; color: var(--text-secondary); line-height: 1.7;">
      Enter your birth coordinates below and proceed directly to secure Stripe checkout. No waiting for admin approval—your session is confirmed instantly.
    </p>
  </div>
</section>

<!-- BOOKING FORM SECTION -->
<section style="padding: 4.5rem 0 6rem; background: var(--deep); position: relative; z-index: 1;">
  <div class="container">
    <div class="booking-box" style="max-width: 840px;">
      
      <?php if (!empty($bookingError)): ?>
        <div class="alert-box alert-danger">
          <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($bookingError) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="booking.php" id="booking-form">
        <input type="hidden" name="action" value="book_session">

        <h3 style="font-size: 1.4rem; color: #fff; margin-bottom: 1.4rem; font-family: var(--font-heading);">
          <i class="fa-regular fa-user" style="color:var(--gold);"></i> 1. Personal Contact Information
        </h3>

        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">First Name *</label>
            <input type="text" name="first_name" id="booking_first_name" class="form-control" required placeholder="e.g. Imran">
          </div>
          <div class="form-group">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" id="booking_last_name" class="form-control" placeholder="e.g. Malik">
          </div>
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Email Address * (For Zoom Link &amp; Recording)</label>
            <input type="email" name="email" id="booking_email" class="form-control" required placeholder="name@example.com">
          </div>
          <div class="form-group">
            <label class="form-label">Select Consultation or Course *</label>
            <select name="service" class="form-control" required id="service-select">
              <option value="" disabled <?= empty($preselected) ? 'selected' : '' ?>>Choose service...</option>
              <optgroup label="Personal Consultations">
                <option value="Horoscope Reading ($199)" <?= (strpos($preselected, 'Horoscope') !== false) ? 'selected' : '' ?>>Horoscope Reading — $199</option>
                <option value="AstroVasto Consult ($299)" <?= (strpos($preselected, 'AstroVasto') !== false) ? 'selected' : '' ?>>AstroVasto Consult — $299</option>
                <option value="Tarot Card Reading ($199)" <?= (strpos($preselected, 'Tarot') !== false) ? 'selected' : '' ?>>Tarot Card Reading — $199</option>
                <option value="Marital Solutions ($250)" <?= (strpos($preselected, 'Marital') !== false) ? 'selected' : '' ?>>Marital Solutions &amp; Compatibility — $250</option>
                <option value="Numerology Consultation ($150)" <?= (strpos($preselected, 'Numerology Consultation') !== false) ? 'selected' : '' ?>>Name Numerology Correction — $150</option>
                <option value="Zaicha Consultation ($199)" <?= (strpos($preselected, 'Zaicha') !== false) ? 'selected' : '' ?>>Annual Zaicha Consultation — $199</option>
              </optgroup>
              <optgroup label="Academy Courses">
                <option value="Course: BNN Astrology ($800)" <?= (strpos($preselected, 'BNN') !== false) ? 'selected' : '' ?>>BNN Astrology Masterclass — $800</option>
                <option value="Course: Basic Vedic Astrology ($299)" <?= (strpos($preselected, 'Basic') !== false) ? 'selected' : '' ?>>Basic Vedic Astrology Course — $299</option>
                <option value="Course: Numerology ($199)" <?= (strpos($preselected, 'Numerology Masterclass') !== false) ? 'selected' : '' ?>>Numerology Masterclass — $199</option>
              </optgroup>
            </select>
          </div>
        </div>

        <div style="border-top: 1px solid rgba(212, 175, 55, 0.18); margin: 2rem 0 1.6rem;"></div>

        <h3 style="font-size: 1.4rem; color: #fff; margin-bottom: 1.4rem; font-family: var(--font-heading);">
          <i class="fa-solid fa-compass" style="color:var(--gold);"></i> 2. Sacred Birth Coordinates
        </h3>

        <div class="birth-coords-row">
          <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="birth_date" id="booking_birth_date" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Exact Time of Birth</label>
            <input type="time" name="birth_time" id="booking_birth_time" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">City &amp; Country of Birth</label>
            <input type="text" name="birth_place" id="booking_birth_place" class="form-control" placeholder="e.g. Lahore, Pakistan">
          </div>
        </div>

        <div class="form-group" style="margin-top: 0.8rem;">
          <label class="form-label">Specific Concerns / Life Questions for Malik Mehboob</label>
          <textarea name="message" id="booking_message" rows="4" class="form-control" placeholder="Mention any specific areas: career, marriage timing, health concerns, business transitions, or name corrections..."></textarea>
        </div>

        <div style="background: rgba(212,175,55,0.06); border: 1px solid var(--glass-border); padding: 1.2rem 1.4rem; border-radius: 8px; margin-bottom: 1.8rem; display: flex; align-items: center; gap: 1rem;">
          <i class="fa-brands fa-stripe" style="font-size: 2.6rem; color: #635bff;"></i>
          <div style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.5;">
            Secured by <strong>Stripe Checkout</strong> with 256-bit SSL encryption. Accepts all major Credit/Debit Cards, Apple Pay &amp; Google Pay.
          </div>
        </div>

        <button type="submit" id="btn-stripe-booking" name="submit_booking" class="btn-primary" style="width: 100%; font-size: 1rem; padding: 1rem 2rem;">
          <i class="fa-solid fa-lock"></i> Proceed to Secure Stripe Payment
        </button>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
