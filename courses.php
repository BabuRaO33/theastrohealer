<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$courses = $pdo->query("SELECT * FROM courses WHERE status = 'published' ORDER BY price DESC")->fetchAll();

$pageTitle = 'Sacred Astrology & Numerology Courses — The Astro Healer Academy';
$pageDescription = 'Enroll in certified Vedic Astrology, Bhrigu Nandi Nadi (BNN), and Professional Numerology courses with Malik Mehboob. Lifetime lecture access and practical chart analysis.';
$pageKeywords = 'astrology courses, learn BNN astrology, numerology certification, Vedic astrology classes, online astrology course, Malik Mehboob academy';
$canonicalUrl = BASE_URL . '/courses.php';
$courseItems = [];
foreach ($courses as $idx => $c) {
    $courseItems[] = [
        '@type' => 'ListItem',
        'position' => $idx + 1,
        'item' => [
            '@type' => 'Course',
            'name' => $c['title'],
            'description' => $c['subtitle'] ?: substr(strip_tags($c['description']), 0, 160),
            'url' => BASE_URL . '/course.php?slug=' . urlencode($c['slug']),
            'provider' => [
                '@type' => 'Organization',
                'name' => 'The Astro Healer Academy'
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => (float)$c['price'],
                'priceCurrency' => 'USD'
            ]
        ]
    ];
}
$coursesSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => 'The Astro Healer Academy Courses',
    'itemListElement' => $courseItems
];
$customJsonLd = json_encode($coursesSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
require_once __DIR__ . '/includes/header.php';
?>

<!-- BREADCRUMB / HERO -->
<section style="padding: 7.5rem 1.5rem 3.5rem; background: linear-gradient(180deg, var(--deep2) 0%, var(--deep) 100%); text-align: center; position: relative; z-index: 1;">
  <div class="container">
    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; font-size: 0.84rem; color: var(--muted); margin-bottom: 1.2rem;">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <span>/</span>
      <span style="color: var(--gold-light);">Academy Courses</span>
    </div>
    <span class="section-label">Cosmic Education &amp; Certification</span>
    <h1 style="font-size: clamp(2.4rem, 4.5vw, 3.8rem); margin-bottom: 0.8rem;">Master the Sacred Sciences</h1>
    <div class="gold-divider"><span>✦</span></div>
    <p style="max-width: 720px; margin: 0 auto; font-size: 1.05rem; color: var(--text-secondary); line-height: 1.7;">
      Structured, in-depth certificate courses taught with clarity and real-world predictive methods by Malik Mehboob. Lifetime access included.
    </p>
  </div>
</section>

<!-- COURSES GRID -->
<section style="padding: 5rem 0 6rem; background: var(--deep); position: relative; z-index: 1;">
  <div class="container">
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
            <p class="course-desc"><?= htmlspecialchars(substr($c['description'], 0, 180)) ?>...</p>

            <div style="background: rgba(212,175,55,0.05); border-left: 3px solid var(--gold); padding: 0.8rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.84rem; color: var(--muted);">
              <strong style="color:var(--gold-light);">Curriculum:</strong> <?= htmlspecialchars($c['curriculum_summary'] ?: 'Comprehensive lectures, practical charts, and live Q&A sessions.') ?>
            </div>

            <div class="course-footer">
              <div class="course-price">
                <?= formatPrice($c['price']) ?>
                <small>/ lifetime access</small>
              </div>
              <div class="course-actions">
                <a href="<?= BASE_URL ?>/course.php?slug=<?= htmlspecialchars($c['slug']) ?>" class="btn-outline btn-sm">
                  Curriculum
                </a>
                <a href="<?= BASE_URL ?>/checkout_stripe.php?course_id=<?= $c['id'] ?>" class="btn-primary btn-sm">
                  Enroll via Stripe <i class="fa-solid fa-arrow-right"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ACADEMY PERKS -->
<section style="padding: 5rem 0; background: var(--deep2); position: relative; z-index: 1;">
  <div class="container">
    <div class="section-header">
      <span class="section-label">The Student Experience</span>
      <h2 class="section-title">Why Learn with The Astro Healer?</h2>
      <div class="gold-divider"><span>✦</span></div>
    </div>

    <div class="services-grid perks-grid">
      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-infinity"></i></div>
        <h3 class="service-title" style="font-size: 1.3rem;">Lifetime Portal Access</h3>
        <p class="service-desc">Never lose access to your study lectures. Rewatch recorded lessons, download lecture PDF notes, and track your progress 24/7 on any device.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-certificate"></i></div>
        <h3 class="service-title" style="font-size: 1.3rem;">Verified Certificate</h3>
        <p class="service-desc">Receive an authentic completion certificate signed by Malik Mehboob, recognizing your expertise in Vedic and Nadi astrological sciences.</p>
      </div>

      <div class="service-card">
        <div class="service-icon"><i class="fa-solid fa-headset"></i></div>
        <h3 class="service-title" style="font-size: 1.3rem;">Direct Student Support</h3>
        <p class="service-desc">Exclusive access to Malik Mehboob's student mentorship group for chart discussions, homework rectifications, and real-case studies.</p>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
