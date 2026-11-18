<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();

// Fetch Courses
$courseStmt = $pdo->query("SELECT * FROM courses WHERE status = 'published' ORDER BY price DESC");
$courses = $courseStmt->fetchAll();

// Fetch Testimonials
$testStmt = $pdo->query("SELECT * FROM testimonials ORDER BY id DESC LIMIT 4");
$testimonials = $testStmt->fetchAll();

// Handle Booking Form Submission
$bookingSuccess = false;
$bookingError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_session') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($firstName) || empty($email) || empty($service)) {
        $bookingError = 'Please fill in all required fields.';
    } else {
        require_once __DIR__ . '/includes/stripe.php';
        $amount = getServicePrice($service);
        $stmt = $pdo->prepare("INSERT INTO bookings (first_name, last_name, email, service, amount, message, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, 'unpaid', 'pending')");
        if ($stmt->execute([$firstName, $lastName, $email, $service, $amount, $message])) {
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
            $cancelUrl = BASE_URL . '/index.php#booking';
            $session = createStripeBookingSession($bookingData, $successUrl, $cancelUrl);
            if ($session['success'] && !empty($session['url'])) {
                header('Location: ' . $session['url']);
                exit;
            } else {
                $bookingError = 'Unable to initiate Stripe payment. Please try again.';
            }
        } else {
            $bookingError = 'Failed to submit booking. Please try again.';
        }
    }
}


$pageTitle = 'The Astro Healer — Moon and Stars Solutions | Vedic Astrology, Numerology & Vastu by Malik Mehboob';
$pageDescription = 'Ancient wisdom meets predictive precision. Experience authentic Vedic astrology, Bhrigu Nandi Nadi (BNN) consultations, Pythagorean numerology, daily cosmic blogs, and certified courses by Malik Mehboob.';
$pageKeywords = 'Vedic astrology, BNN astrology, learn astrology, numerology courses, horoscope reading, Zaicha analysis, Malik Mehboob, daily cosmic blog, AstroVasto space healing, Pakistan, USA, UK, UAE';
$canonicalUrl = BASE_URL . '/';
$ogImage = BASE_URL . '/assets/images/logo.png';
$ogType = 'website';

$homeSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    '@id' => BASE_URL . '/#webpage',
    'url' => BASE_URL . '/',
    'name' => 'The Astro Healer — Moon and Stars Solutions Provider',
    'description' => 'Ancient wisdom meets modern insight. Expert Vedic astrology, Numerology, Bhrigu Nandi Nadi courses and personalized cosmic consultations by Malik Mehboob.',
    'breadcrumb' => [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => BASE_URL . '/'
            ]
        ]
    ]
];
$customJsonLd = json_encode($homeSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO SECTION -->
<section class="hero-section" id="home">
  <div class="hero-nebula-glow"></div>
  <div class="hero-nebula-glow-secondary"></div>
  
  <div class="hero-orbit-wrap">
    <div class="orbit-circle orbit-circle-1">
      <div class="orbit-planet planet-1" title="Sun - Cosmic Soul"></div>
    </div>
    <div class="orbit-circle orbit-circle-2">
      <div class="orbit-planet planet-2" title="Moon - Intuition & Mind"></div>
    </div>
    <div class="orbit-circle orbit-circle-3">
      <div class="orbit-planet planet-3" title="Jupiter - Wisdom & Expansion"></div>
    </div>
  </div>

  <div class="hero-content">
    <div class="hero-badge">
      <i class="fa-solid fa-sparkles"></i>
      <span>The Astro Healer · Moon &amp; Stars Solutions</span>
    </div>
    <h1 class="hero-title">
      <span class="hero-kicker">The Cosmos</span>
      <span class="gold-gradient">Speaks to You.</span>
      <span class="hero-tag">Listen to the Stars</span>
    </h1>
    <p class="hero-sub">
      Ancient Vedic wisdom meets modern insight. Decode the sacred planetary alignments and numerical destiny written at your birth.
    </p>
    <div class="hero-cta-group">
      <a href="#courses" class="btn-primary"><i class="fa-solid fa-graduation-cap"></i> Explore 3 Master Courses</a>
      <a href="#booking" class="btn-outline"><i class="fa-regular fa-calendar-check"></i> Book Consultation</a>
    </div>
  </div>
</section>

<!-- THE 3 COURSES SECTION -->
<section class="courses-section" id="courses">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Cosmic Education</span>
      <h2 class="section-title">Master the Sacred Sciences</h2>
      <div class="gold-divider"><span>✦</span></div>
      <p class="section-desc">
        Three structured, in-depth certificate courses taught with clarity and real-world predictive methods by Malik Mehboob.
      </p>
    </div>

    <div class="courses-grid">
      <?php foreach ($courses as $c): ?>
        <div class="course-card">
          <div class="course-thumb-wrap">
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($c['image'] ?: 'assets/images/courses/numerology.jpg') ?>" alt="<?= htmlspecialchars($c['title']) ?>" class="course-thumb">
            <span class="course-badge"><?= htmlspecialchars($c['badge']) ?></span>
          </div>

          <div class="course-body">
            <div class="course-meta">
              <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($c['duration']) ?></span>
              <span><i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($c['level']) ?></span>
            </div>

            <h3 class="course-card-title"><?= htmlspecialchars($c['title']) ?></h3>
            <p class="course-card-sub"><?= htmlspecialchars($c['subtitle']) ?></p>
            <p class="course-desc"><?= htmlspecialchars(substr($c['description'], 0, 160)) ?>...</p>

            <div class="course-footer">
              <div class="course-price">
                <?= formatPrice($c['price']) ?>
                <small>/ lifetime access</small>
              </div>
              <a href="<?= BASE_URL ?>/course.php?slug=<?= htmlspecialchars($c['slug']) ?>" class="btn-primary btn-sm">
                Enroll Now <i class="fa-solid fa-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ZODIAC WHEEL & SIGNS SECTION -->
<section class="zodiac-section" id="zodiac">
  <div class="container">
    <div class="zodiac-layout">
      <div class="zodiac-text-col">
        <span class="section-label">Ancient Archetypes</span>
        <h2 class="section-title">The Celestial<br>Wheel of Life</h2>
        <div class="gold-divider" style="margin: 1rem 0 1.8rem; justify-content: flex-start;"><span>✦</span></div>
        <p class="zodiac-lead">
          The zodiac — from the ancient Greek <em>zōdiakos kyklos</em> ("circle of little animals") — is a 360-degree belt of the cosmos divided into twelve distinct constellations. Each carries an archetypal vibration, an elemental force, and a ruling planet.
        </p>
        <p style="color: var(--text-secondary); font-size: 0.96rem; line-height: 1.75; margin-bottom: 2rem;">
          In Vedic and Nadi traditions, your birth chart (Kundali) maps this exact celestial arrangement at the precise moment you took your first breath, charting the trajectory of your soul's karma, challenges, and divine purpose. Click any sign to discover its Vedic secrets.
        </p>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
          <a href="#booking" class="btn-outline"><i class="fa-solid fa-compass"></i> Calculate Your Chart</a>
        </div>
      </div>

      <div class="zodiac-wheel-box">
        <div class="wheel-svg-wrap">
          <svg viewBox="0 0 520 520" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; overflow: visible;">
            <defs>
              <radialGradient id="goldRadial" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#ffd700" stop-opacity="0.28"/>
                <stop offset="50%" stop-color="#d4af37" stop-opacity="0.10"/>
                <stop offset="100%" stop-color="#050716" stop-opacity="0"/>
              </radialGradient>
              <filter id="centerGlow" x="-30%" y="-30%" width="160%" height="160%">
                <feGaussianBlur stdDeviation="4" result="blur"/>
                <feComposite in="SourceGraphic" in2="blur" operator="over"/>
              </filter>
              <filter id="nodeGlow" x="-20%" y="-20%" width="140%" height="140%">
                <feGaussianBlur stdDeviation="2.5" result="blur"/>
                <feComposite in="SourceGraphic" in2="blur" operator="over"/>
              </filter>
            </defs>
            
            <!-- OUTER DEGREES RING (Counter-rotates smoothly like an antique astrolabe) -->
            <g class="counter-rotating-ring">
              <circle cx="260" cy="260" r="252" fill="none" stroke="rgba(212,175,55,0.22)" stroke-width="1.5"/>
              <circle cx="260" cy="260" r="246" fill="none" stroke="rgba(212,175,55,0.15)" stroke-width="1" stroke-dasharray="2,6"/>
              <?php for ($d = 0; $d < 72; $d++): 
                $ang = $d * 5 * (M_PI / 180);
                $isMajor = ($d % 6 === 0);
                $len = $isMajor ? 8 : 4;
                $rx1 = 260 + (252 - $len) * cos($ang);
                $ry1 = 260 + (252 - $len) * sin($ang);
                $rx2 = 260 + 252 * cos($ang);
                $ry2 = 260 + 252 * sin($ang);
              ?>
                <line x1="<?= $rx1 ?>" y1="<?= $ry1 ?>" x2="<?= $rx2 ?>" y2="<?= $ry2 ?>" stroke="rgba(212,175,55,<?= $isMajor ? '0.45' : '0.2' ?>)" stroke-width="<?= $isMajor ? '1.2' : '0.75' ?>"/>
              <?php endfor; ?>
            </g>

            <!-- ROTATING CELESTIAL SPOKES TRACK (Spins centered on 260, 260 without translate bug) -->
            <g class="rotating-celestial-track">
              <circle cx="260" cy="260" r="236" fill="url(#goldRadial)" stroke="rgba(212,175,55,0.35)" stroke-width="1.5" stroke-dasharray="8,4"/>
              <circle cx="260" cy="260" r="190" fill="none" stroke="rgba(212,175,55,0.22)" stroke-width="1"/>
              <circle cx="260" cy="260" r="130" fill="none" stroke="rgba(212,175,55,0.18)" stroke-width="1" stroke-dasharray="4,4"/>
              
              <!-- 12 Spokes & Constellation Star Nodes -->
              <?php for ($i = 0; $i < 12; $i++):
                $angle = $i * 30 * (M_PI / 180);
                $x1 = 260 + 72 * cos($angle);
                $y1 = 260 + 72 * sin($angle);
                $x2 = 260 + 200 * cos($angle);
                $y2 = 260 + 200 * sin($angle);
                $nodeX = 260 + 190 * cos($angle);
                $nodeY = 260 + 190 * sin($angle);
              ?>
                <line x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>" stroke="rgba(212,175,55,0.2)" stroke-width="1"/>
                <circle cx="<?= $nodeX ?>" cy="<?= $nodeY ?>" r="2.5" fill="#ffd700" opacity="0.85"/>
              <?php endfor; ?>
            </g>

            <!-- STATIC ZODIAC HOUSES (Symbols stay upright, perfectly positioned, never clipped) -->
            <g class="static-zodiac-houses">
              <?php
              $zodiacSymbols = ['♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓'];
              for ($i = 0; $i < 12; $i++):
                $angle = ($i * 30 - 90) * (M_PI / 180);
                $symX = 260 + 212 * cos($angle);
                $symY = 260 + 212 * sin($angle) + 6.5;
                $circleX = 260 + 212 * cos($angle);
                $circleY = 260 + 212 * sin($angle);
              ?>
                <circle cx="<?= $circleX ?>" cy="<?= $circleY ?>" r="18" fill="rgba(8, 12, 36, 0.92)" stroke="rgba(212,175,55,0.3)" stroke-width="1.2" class="wheel-node" data-sign-index="<?= $i ?>"/>
                <text x="<?= $symX ?>" y="<?= $symY ?>" text-anchor="middle" fill="#ffd700" font-family="'Cinzel', serif" font-size="16" font-weight="600" class="wheel-sym" data-sign-index="<?= $i ?>"><?= $zodiacSymbols[$i] ?></text>
              <?php endfor; ?>
            </g>

            <!-- STATIC CENTER HUB (The Astro Healer Medallion) -->
            <g class="static-astrolabe-center">
              <circle cx="260" cy="260" r="70" fill="rgba(9, 13, 38, 0.96)" stroke="#d4af37" stroke-width="2" filter="url(#centerGlow)"/>
              <circle cx="260" cy="260" r="63" fill="none" stroke="rgba(212,175,55,0.35)" stroke-width="1" stroke-dasharray="3,3"/>
              
              <text x="260" y="248" text-anchor="middle" fill="#ffd700" font-family="'Cinzel', serif" font-size="11" font-weight="700" letter-spacing="2.2">THE ASTRO</text>
              <text x="260" y="264" text-anchor="middle" fill="#d4af37" font-family="'Cinzel', serif" font-size="9" font-weight="600" letter-spacing="3.5">HEALER</text>
              <circle cx="260" cy="275" r="2.5" fill="#ffd700"/>
            </g>
          </svg>
        </div>
      </div>
    </div>

    <!-- 12 Signs Grid -->
    <div class="signs-grid" id="dynamic-signs-grid"></div>
  </div>
</section>

<!-- COSMIC SERVICES SECTION -->
<section class="services-section" id="services">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Personal Consultations</span>
      <h2 class="section-title">Cosmic Healing Services</h2>
      <div class="gold-divider"><span>✦</span></div>
      <p class="section-desc">
        One-on-one sessions designed to provide authentic clarity, timeline forecasting, and actionable remedies.
      </p>
    </div>

    <div class="services-grid">
      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-sun"></i></div>
        <h3 class="service-title">Horoscope Reading</h3>
        <p class="service-desc">Complete natal birth chart breakdown detailing planetary yogas, dasha periods, career potentials, and life milestones.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-house-chimney"></i></div>
        <h3 class="service-title">AstroVasto Consult</h3>
        <p class="service-desc">Aligning the directional energy of your home and workplace with your personal horoscope for abundance and peace.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-hand-sparkles"></i></div>
        <h3 class="service-title">Tarot Card Reading</h3>
        <p class="service-desc">Intuitive archetypal card pulls providing immediate clarity on urgent life dilemmas, relationships, and decisions.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-heart"></i></div>
        <h3 class="service-title">Marital Solutions</h3>
        <p class="service-desc">Synastry Kundali matching, emotional compatibility, Manglik analysis, and dispute resolution through astrological remedies.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-hashtag"></i></div>
        <h3 class="service-title">Numerology Analysis</h3>
        <p class="service-desc">Correction of personal or business names to harmonize with your destiny number, attracting wealth and opportunities.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-moon"></i></div>
        <h3 class="service-title">Zaicha Consultation</h3>
        <p class="service-desc">Traditional astrological Zaicha synthesis analyzing planetary transits for the upcoming 12 months with precise remedies.</p>
      </div>
    </div>
  </div>
</section>

<!-- FOUNDER & ABOUT SECTION -->
<section class="founder-section" id="about">
  <div class="container">
    <div class="founder-grid">
      <div class="founder-img-wrap">
        <div class="founder-img-box">
          <img src="<?= BASE_URL ?>/assets/images/malik-mehboob.jpg?v=2" alt="Malik Mehboob — Master Vedic Astrologer & Guide" class="founder-img">
          <div class="founder-badge-seal">
            <i class="fa-solid fa-certificate"></i>
            <div>
              <strong>Malik Mehboob</strong>
              <small>Master Vedic &amp; Nadi Astrologer</small>
            </div>
          </div>
        </div>
      </div>

      <div class="founder-info">
        <span class="founder-role">Lead Astrologer &amp; Mentor</span>
        <h2 class="founder-title">Grounded Wisdom in <span class="gold-gradient">Cosmic Guidance</span></h2>
        <div class="gold-divider" style="margin: 1rem 0 1.5rem; justify-content: flex-start;"><span>✦</span></div>
        <p>
          Behind The Astro Healer is a simple, enduring conviction: astrology should feel clear, respectful, and genuinely useful in daily life. Malik Mehboob combines classical Vedic texts with the rapid precision of Bhrigu Nandi Nadi and Pythagorean numerology.
        </p>
        <p>
          Whether you join our student learning academy or book an intimate reading, our approach replaces dread and superstitious fear with self-awareness, timing mastery, and grounded spiritual solutions.
        </p>
        
        <div class="founder-stats-grid">
          <div class="founder-stat-card">
            <div class="founder-stat-num">10+</div>
            <div class="founder-stat-label">Years Experience</div>
          </div>
          <div class="founder-stat-card">
            <div class="founder-stat-num">5,000+</div>
            <div class="founder-stat-label">Clients Guided</div>
          </div>
          <div class="founder-stat-card">
            <div class="founder-stat-num">60%–70%</div>
            <div class="founder-stat-label">Positive Outcomes</div>
          </div>
        </div>

        <a href="#booking" class="btn-primary"><i class="fa-regular fa-envelope"></i> Book Consultation with Malik Mehboob</a>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS SECTION -->
<section class="testimonials-section">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Student &amp; Client Reviews</span>
      <h2 class="section-title">Voices from the Stars</h2>
      <div class="gold-divider"><span>✦</span></div>
      <p class="section-desc">
        Hear how planetary alignment and sacred numbers transformed lives and opened clearer paths.
      </p>
    </div>

    <div class="testimonials-grid">
      <?php foreach ($testimonials as $t): ?>
        <div class="testimonial-card">
          <div class="testimonial-stars">
            <?php for ($i = 0; $i < $t['rating']; $i++): ?><i class="fa-solid fa-star"></i><?php endfor; ?>
          </div>
          <p class="testimonial-text">
            "<?= htmlspecialchars($t['feedback']) ?>"
          </p>
          <div class="testimonial-author">
            <div class="testimonial-name"><?= htmlspecialchars($t['client_name']) ?></div>
            <div class="testimonial-meta"><?= htmlspecialchars($t['service_taken'] ?? 'Course Student') ?> · <?= htmlspecialchars($t['location'] ?? 'Online') ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- BOOKING / STRIPE SECTION -->
<section class="booking-section" id="booking">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Reserve Your Seat</span>
      <h2 class="section-title">Book Your Session or Course</h2>
      <div class="gold-divider"><span>✦</span></div>
      <p class="section-desc">
        Complete the form below to secure your consultation or register for our courses. We respond within 24 hours.
      </p>
    </div>

    <div class="booking-box">
      <?php if ($bookingSuccess): ?>
        <div class="alert-box alert-success">
          <i class="fa-solid fa-circle-check" style="font-size: 1.4rem;"></i>
          <div>
            <strong>Booking Request Received!</strong><br>
            Thank you for connecting with The Astro Healer. Malik Mehboob and our team will contact you shortly via email or WhatsApp.
          </div>
        </div>
      <?php elseif ($bookingError): ?>
        <div class="alert-box alert-danger">
          <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($bookingError) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="#booking">
        <input type="hidden" name="action" value="book_session">
        
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">First Name *</label>
            <input type="text" name="first_name" class="form-control" required placeholder="e.g. Ali">
          </div>
          <div class="form-group">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" placeholder="e.g. Khan">
          </div>
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" required placeholder="name@example.com">
          </div>
          <div class="form-group">
            <label class="form-label">Select Service or Course *</label>
            <select name="service" class="form-control" required>
              <option value="" disabled selected>Choose consultation or course...</option>
              <optgroup label="Academy Courses">
                <option value="Course: BNN Astrology ($800)">BNN Astrology Course ($800)</option>
                <option value="Course: Numerology ($199)">Numerology Masterclass ($199)</option>
                <option value="Course: Basic Vedic Astrology ($299)">Basic Vedic Astrology Course ($299)</option>
              </optgroup>
              <optgroup label="Personal Consultations">
                <option value="Horoscope Reading ($199)">Horoscope Reading ($199)</option>
                <option value="Tarot Card Reading ($199)">Tarot Card Reading ($199)</option>
                <option value="Marital Solutions ($250)">Marital Solutions & Compatibility ($250)</option>
                <option value="Numerology Consultation ($150)">Name Numerology Correction ($150)</option>
                <option value="Zaicha Consultation ($199)">Annual Zaicha Consultation ($199)</option>
              </optgroup>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Your Message or Birth Details (Date, Time, Place)</label>
          <textarea name="message" rows="4" class="form-control" placeholder="Provide your birth date, time, city of birth, or any specific questions you have for Malik Mehboob..."></textarea>
        </div>

        <button type="submit" class="btn-primary" style="width: 100%;">
          <i class="fa-solid fa-paper-plane"></i> Submit Booking Request
        </button>
      </form>
    </div>
  </div>
</section>

<!-- CELESTIAL ZODIAC DETAILS MODAL -->
<div class="zodiac-modal-backdrop" id="zodiac-modal" aria-hidden="true">
  <div class="zodiac-modal-container">
    <button class="zodiac-modal-close" id="zodiac-modal-close" aria-label="Close modal">&times;</button>
    <div id="zodiac-modal-content">
      <!-- Populated dynamically by main.js -->
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

