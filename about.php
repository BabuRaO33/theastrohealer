<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$testimonials = $pdo->query("SELECT * FROM testimonials ORDER BY id DESC LIMIT 4")->fetchAll();

$pageTitle = 'About Malik Mehboob — Master Vedic & Nadi Astrologer | The Astro Healer';
$pageDescription = 'Discover the legacy of Malik Mehboob, founder of The Astro Healer. 15+ years decoding Vedic astrology, Bhrigu Nandi Nadi (BNN), and Pythagorean numerology for 500+ seekers.';
$pageKeywords = 'Malik Mehboob, Master Vedic Astrologer, BNN astrology teacher, numerologist Pakistan, AstroVastu consultant, The Astro Healer biography';
$canonicalUrl = BASE_URL . '/about.php';
$ogImage = BASE_URL . '/assets/images/malik-mehboob.jpg';
$ogType = 'profile';

$aboutSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'AboutPage',
    'mainEntity' => [
        '@type' => 'Person',
        'name' => 'Malik Mehboob',
        'jobTitle' => 'Master Vedic Astrologer & Numerologist',
        'description' => 'Founder of The Astro Healer, certified Vedic Astrologer and Pythagorean Numerologist with over 15+ years of practical experience and 500+ consultations worldwide.',
        'image' => $ogImage,
        'sameAs' => [
            'https://www.youtube.com/@theastromalik',
            'https://www.instagram.com/theastromalik/',
            'https://www.facebook.com/NewMoonAstroHealer',
            'https://wa.me/14706505315'
        ],
        'worksFor' => [
            '@type' => 'Organization',
            'name' => 'The Astro Healer',
            'url' => BASE_URL . '/'
        ]
    ]
];
$customJsonLd = json_encode($aboutSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
require_once __DIR__ . '/includes/header.php';
?>

<!-- BREADCRUMB / HERO -->
<section style="padding: 7.5rem 1.5rem 3.5rem; background: linear-gradient(180deg, var(--deep2) 0%, var(--deep) 100%); text-align: center; position: relative; z-index: 1;">
  <div class="container">
    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; font-size: 0.84rem; color: var(--muted); margin-bottom: 1.2rem;">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <span>/</span>
      <span style="color: var(--gold-light);">About Guide</span>
    </div>
    <span class="section-label">Ancient Lineage · Modern Precision</span>
    <h1 style="font-size: clamp(2.4rem, 4.5vw, 3.8rem); margin-bottom: 0.8rem;">About Malik Mehboob &amp; The Astro Healer</h1>
    <div class="gold-divider"><span>✦</span></div>
    <p style="max-width: 720px; margin: 0 auto; font-size: 1.05rem; color: var(--text-secondary); line-height: 1.7;">
      Where ancient Vedic wisdom, Bhrigu Nandi Nadi predictive mastery, and sacred Pythagorean numerology converge to illuminate your destiny.
    </p>
  </div>
</section>

<!-- MAIN BIO & FOUNDER SHOWCASE -->
<section class="founder-section" style="padding: 5rem 0;">
  <div class="container">
    <div class="founder-grid">
      <div class="founder-img-wrap">
        <div class="founder-img-box">
          <img src="<?= BASE_URL ?>/assets/images/malik-mehboob.jpg?v=2" alt="Malik Mehboob — Master Vedic Astrologer" class="founder-img">
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
        <span class="founder-role">Lead Astrologer, Teacher &amp; Mentor</span>
        <h2 class="founder-title">A Living Bridge to <span class="gold-gradient">Sacred Vedic Insight</span></h2>
        <div class="gold-divider" style="margin: 1rem 0 1.5rem; justify-content: flex-start;"><span>✦</span></div>
        
        <p>
          Behind <strong>The Astro Healer</strong> is an enduring life conviction: astrology should never inflict fear, superstition, or helplessness. Instead, when decoded with mathematical precision and spiritual integrity, it serves as the ultimate cosmic compass for human self-realization.
        </p>
        <p>
          With over a decade of devoted study and consultation practice, Malik Mehboob synthesizes the profound philosophy of classical <em>Brihat Parashara Hora Shastra</em> with the lightning-fast predictive technique of <strong>Bhrigu Nandi Nadi (BNN)</strong> and sacred Pythagorean numerology.
        </p>
        <p>
          Having guided more than 5,000 clients across the globe—from Fortune executives and entrepreneurs to couples seeking matrimonial harmony—Malik Mehboob delivers actionable clarity, exact timing forecasting, and grounded remedies that fit modern living.
        </p>

        <div class="founder-stats-grid">
          <div class="founder-stat-card">
            <div class="founder-stat-num">10+</div>
            <div class="founder-stat-label">Years Practice</div>
          </div>
          <div class="founder-stat-card">
            <div class="founder-stat-num">5,000+</div>
            <div class="founder-stat-label">Clients Guided</div>
          </div>
          <div class="founder-stat-card">
            <div class="founder-stat-num">60%–70%</div>
            <div class="founder-stat-label">Satisfaction Rate</div>
          </div>
        </div>

        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
          <a href="<?= BASE_URL ?>/booking.php" class="btn-primary"><i class="fa-regular fa-calendar-check"></i> Book Consultation</a>
          <a href="https://wa.me/14706505315" target="_blank" class="btn-outline"><i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- THE 4 SACRED PILLARS -->
<section style="padding: 6rem 0; background: var(--deep2); position: relative; z-index: 1;">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Our Philosophical Foundations</span>
      <h2 class="section-title">The Four Cosmic Pillars</h2>
      <div class="gold-divider"><span>✦</span></div>
      <p class="section-desc">
        How The Astro Healer decodes the energetic map of your existence with authenticity and compassion.
      </p>
    </div>

    <div class="services-grid pillars-grid">
      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-star-and-crescent"></i></div>
        <h3 class="service-title">1. Classical Parashari Jyotish</h3>
        <p class="service-desc">The foundational science of Vedic astrology. Analyzing your Lagna (ascendant), 12 Bhavas (houses), 9 Grahas (planetary forces), and Mahadashas to reveal karmic blueprints.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-atom"></i></div>
        <h3 class="service-title">2. Bhrigu Nandi Nadi (BNN)</h3>
        <p class="service-desc">Ancient predictive method from Sage Bhrigu. Bypasses tedious rectifications by tracking planetary conjunctions and directional coordinates for exact life timeline forecasting.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-hashtag"></i></div>
        <h3 class="service-title">3. Sacred Numerology</h3>
        <p class="service-desc">Pythagorean and Chaldean mathematical frequencies. Decoding Destiny Numbers, Psychic Numbers, and correcting business and personal names to attract success and peace.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-gem"></i></div>
        <h3 class="service-title">4. Grounded Remedies</h3>
        <p class="service-desc">Practical, sattvic remedies including planetary color therapy, gemstone recommendation (Ratna Shastra), directional balancing (AstroVastu), and charitable karmic corrections.</p>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials-section" style="padding: 6rem 0;">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Verified Client Voices</span>
      <h2 class="section-title">Words from Seekers Worldwide</h2>
      <div class="gold-divider"><span>✦</span></div>
    </div>

    <div class="testimonials-grid">
      <?php foreach ($testimonials as $t): ?>
        <div class="testimonial-card">
          <div class="testimonial-stars">
            <?php for ($i = 0; $i < $t['rating']; $i++): ?><i class="fa-solid fa-star"></i><?php endfor; ?>
          </div>
          <p class="testimonial-text">"<?= htmlspecialchars($t['feedback']) ?>"</p>
          <div class="testimonial-author">
            <div class="testimonial-name"><?= htmlspecialchars($t['client_name']) ?></div>
            <div class="testimonial-meta"><?= htmlspecialchars($t['service_taken'] ?? 'Consultation Client') ?> · <?= htmlspecialchars($t['location'] ?? 'Worldwide') ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
