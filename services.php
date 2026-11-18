<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Cosmic Consultation Services — The Astro Healer | Horoscope, Tarot & Numerology';
$pageDescription = 'Book private 1-on-1 consultations with Master Astrologer Malik Mehboob: Horoscope Reading, AstroVasto Consult, Tarot Card Reading, Marital Solutions, and Annual Zaicha.';
$pageKeywords = 'astrology consultation, horoscope reading, zaicha analysis, astrovastu consult, tarot reading, marital solutions astrology, Malik Mehboob';
$canonicalUrl = BASE_URL . '/services.php';

$servicesList = [
    [
        'title' => 'Horoscope Reading',
        'price' => 199,
        'duration' => '60 Minutes',
        'icon' => 'fa-solid fa-sun',
        'desc' => 'Comprehensive natal Kundali deep-dive. Examines Lagna, Navamsha (D9), Dashas, upcoming planetary transits, career milestones, health yogas, and actionable Vedic remedies.',
        'deliverables' => ['Complete Natal Birth Chart PDF', '3-Year Future Dasha Forecast', 'Gemstone & Mantra Remedies', 'Private Recorded Zoom Audio/Video']
    ],
    [
        'title' => 'AstroVasto Consult',
        'price' => 299,
        'duration' => '75 Minutes',
        'icon' => 'fa-solid fa-house-chimney',
        'desc' => 'Harmonizing the directional electromagnetic energies of your residence or commercial workspace with your personal horoscope for maximum prosperity, health, and peace.',
        'deliverables' => ['Floor Plan Energy Mapping', 'Directional Planetary Alignment', 'Non-demolition Remedies', 'AstroVastu Correction Report']
    ],
    [
        'title' => 'Tarot Card Reading',
        'price' => 199,
        'duration' => '45 Minutes',
        'icon' => 'fa-solid fa-hand-sparkles',
        'desc' => 'Intuitive archetypal pulls addressing acute crossroads, urgent dilemmas, relationship crossroads, or high-stakes business choices with clarity and spiritual validation.',
        'deliverables' => ['Celtic Cross & 12-House Spread', 'Direct Answers to Urgent Dilemmas', 'Energetic Blockage Identification', 'Timeline Probability Forecast']
    ],
    [
        'title' => 'Marital Solutions & Compatibility',
        'price' => 250,
        'duration' => '60 Minutes',
        'icon' => 'fa-solid fa-heart',
        'desc' => 'In-depth Synastry Kundali Milan. Analyzes 36 Guna Ashtakoota, Manglik Dosha, psychological temperament sync, emotional resilience, and resolving marital friction.',
        'deliverables' => ['Dual Horoscope Compatibility Matrix', 'Manglik & Nadi Dosha Assessment', 'Marital Dispute Remedies', 'Long-term Harmony Guidance']
    ],
    [
        'title' => 'Name Numerology Correction',
        'price' => 150,
        'duration' => 'Written Report + 30 Min Call',
        'icon' => 'fa-solid fa-hashtag',
        'desc' => 'Harmonizing your personal spelling or commercial brand name with your core Destiny Number (Bhagyank) and Psychic Number (Mulank) to dissolve unseen karmic friction.',
        'deliverables' => ['Chaldean & Pythagorean Analysis', 'Auspicious Letter Spelling Options', 'Lucky Signature Vibrations', 'Bank/ID Transition Advice']
    ],
    [
        'title' => 'Annual Zaicha Consultation',
        'price' => 199,
        'duration' => '60 Minutes',
        'icon' => 'fa-solid fa-moon',
        'desc' => 'Traditional Zaicha synthesis analyzing planetary transits (Gochar), Varshphal solar return, Sade Sati timeline, and month-by-month forecasting for the upcoming 12 months.',
        'deliverables' => ['12-Month Month-by-Month Guide', 'Saturn/Rahu/Ketu Transit Impact', 'Auspicious Timing (Muhurata) Windows', 'Annual Preventive Remedies']
    ]
];

// Generate JSON-LD Service Catalog Schema
$catalogItems = [];
foreach ($servicesList as $s) {
    $catalogItems[] = [
        '@type' => 'Service',
        'name' => $s['title'],
        'description' => $s['desc'],
        'provider' => [
            '@type' => 'Person',
            'name' => 'Malik Mehboob',
            'jobTitle' => 'Master Vedic Astrologer'
        ],
        'offers' => [
            '@type' => 'Offer',
            'price' => (float)$s['price'],
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
            'url' => BASE_URL . '/booking.php?service=' . urlencode($s['title'])
        ]
    ];
}

$servicesSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'ServiceChannel',
    'name' => 'The Astro Healer Consultations',
    'serviceLocation' => [
        '@type' => 'VirtualLocation',
        'name' => 'Worldwide Online (Zoom & WhatsApp)'
    ],
    'hasOfferCatalog' => [
        '@type' => 'OfferCatalog',
        'name' => 'Cosmic Consultation Offerings',
        'itemListElement' => $catalogItems
    ]
];
$customJsonLd = json_encode($servicesSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

require_once __DIR__ . '/includes/header.php';
?>

<!-- BREADCRUMB / HERO -->
<section style="padding: 7.5rem 1.5rem 3.5rem; background: linear-gradient(180deg, var(--deep2) 0%, var(--deep) 100%); text-align: center; position: relative; z-index: 1;">
  <div class="container">
    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; font-size: 0.84rem; color: var(--muted); margin-bottom: 1.2rem;">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <span>/</span>
      <span style="color: var(--gold-light);">Cosmic Services</span>
    </div>
    <span class="section-label">One-on-One Private Consultations</span>
    <h1 style="font-size: clamp(2.4rem, 4.5vw, 3.8rem); margin-bottom: 0.8rem;">Sacred Cosmic Healing Services</h1>
    <div class="gold-divider"><span>✦</span></div>
    <p style="max-width: 720px; margin: 0 auto; font-size: 1.05rem; color: var(--text-secondary); line-height: 1.7;">
      Direct, confidential sessions with Malik Mehboob designed to provide authentic clarity, timeline forecasting, and actionable remedies.
    </p>
  </div>
</section>

<!-- SERVICES SHOWCASE -->
<section style="padding: 5rem 0 6rem; background: var(--deep); position: relative; z-index: 1;">
  <div class="container">
    <div class="services-grid">
      <?php foreach ($servicesList as $s): ?>
        <div class="service-card">
          <div>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
              <div class="service-icon" style="margin-bottom: 0;">
                <i class="<?= $s['icon'] ?>"></i>
              </div>
              <div style="text-align: right;">
                <div style="font-family: var(--font-heading); font-size: 1.9rem; color: var(--gold-bright); font-weight: 700; line-height: 1;">
                  $<?= $s['price'] ?>
                </div>
                <div style="font-size: 0.74rem; color: var(--muted); text-transform: uppercase; margin-top: 0.2rem;">
                  <?= $s['duration'] ?>
                </div>
              </div>
            </div>

            <h3 class="service-title" style="font-size: 1.55rem; margin-bottom: 0.8rem;"><?= htmlspecialchars($s['title']) ?></h3>
            <p class="service-desc" style="margin-bottom: 1.6rem;"><?= htmlspecialchars($s['desc']) ?></p>

            <div style="border-top: 1px solid rgba(212, 175, 55, 0.16); padding-top: 1.2rem; margin-bottom: 1.8rem;">
              <strong style="color: var(--gold-light); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.12em; display: block; margin-bottom: 0.8rem;">
                What is Included:
              </strong>
              <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.88rem; color: var(--text-secondary);">
                <?php foreach ($s['deliverables'] as $d): ?>
                  <li style="display: flex; align-items: flex-start; gap: 0.5rem;">
                    <i class="fa-solid fa-check" style="color: var(--gold-bright); margin-top: 0.25rem;"></i>
                    <span><?= htmlspecialchars($d) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>

          <a href="<?= BASE_URL ?>/booking.php?service=<?= urlencode($s['title'] . ' ($' . $s['price'] . ')') ?>" class="btn-primary" style="width: 100%; text-align: center;">
            <i class="fa-solid fa-credit-card"></i> Book &amp; Pay via Stripe ($<?= $s['price'] ?>)
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CONSULTATION PROCESS FAQ -->
<section style="padding: 5rem 0; background: var(--deep2); position: relative; z-index: 1;">
  <div class="container" style="max-width: 860px;">
    <div class="section-header">
      <span class="section-label">Preparation &amp; Process</span>
      <h2 class="section-title">How Your Session Works</h2>
      <div class="gold-divider"><span>✦</span></div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1.4rem;">
      <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.6rem 1.8rem; border-radius: 12px;">
        <h4 style="color: var(--gold-light); margin-bottom: 0.5rem;"><i class="fa-regular fa-clock" style="color:var(--gold);"></i> 1. What details are required?</h4>
        <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.65;">
          For Vedic and Zaicha readings, please provide your exact <strong>Date of Birth</strong>, <strong>Time of Birth (as precise as possible)</strong>, and <strong>City/Country of Birth</strong>. If exact time is unknown, Tarot and Numerology sessions can be conducted with ease.
        </p>
      </div>

      <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.6rem 1.8rem; border-radius: 12px;">
        <h4 style="color: var(--gold-light); margin-bottom: 0.5rem;"><i class="fa-solid fa-video" style="color:var(--gold);"></i> 2. How is the consultation delivered?</h4>
        <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.65;">
          Sessions are conducted 1-on-1 via private Zoom audio/video or WhatsApp Call with Malik Mehboob. A full recording and your birth chart diagram will be shared after the call.
        </p>
      </div>

      <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.6rem 1.8rem; border-radius: 12px;">
        <h4 style="color: var(--gold-light); margin-bottom: 0.5rem;"><i class="fa-solid fa-lock" style="color:var(--gold);"></i> 3. Direct Stripe Payment &amp; Scheduling</h4>
        <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.65;">
          Your booking is confirmed immediately via secure Stripe checkout with bank-grade encryption. Our appointment desk reaches out via WhatsApp/Email within 24 hours to align your preferred time slot.
        </p>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
