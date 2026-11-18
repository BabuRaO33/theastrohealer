<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'The Celestial Zodiac Wheel & 12 Signs — The Astro Healer';
$pageDescription = 'Explore the 12 sacred zodiac signs, ruling planets, elements, and cosmic archetypes with the interactive Zodiac Wheel from The Astro Healer.';
$pageKeywords = 'zodiac wheel, 12 zodiac signs, astrology signs, sun signs, Vedic rashi, ruling planets, cosmic elements, Mesha, Vrishabha, Mithuna, Karka, Simha, Kanya, Tula, Vrishchika, Dhanu, Makara, Kumbha, Meena';
$canonicalUrl = BASE_URL . '/zodiac.php';

$signs = ['Aries (Mesha)', 'Taurus (Vrishabha)', 'Gemini (Mithuna)', 'Cancer (Karka)', 'Leo (Simha)', 'Virgo (Kanya)', 'Libra (Tula)', 'Scorpio (Vrishchika)', 'Sagittarius (Dhanu)', 'Capricorn (Makara)', 'Aquarius (Kumbha)', 'Pisces (Meena)'];
$zodiacList = [];
foreach ($signs as $idx => $s) {
    $zodiacList[] = [
        '@type' => 'ListItem',
        'position' => $idx + 1,
        'name' => $s
    ];
}
$zodiacSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => '12 Sacred Zodiac Rashis',
    'description' => 'The 12 Vedic Astrological Signs mapped across the 360-degree celestial belt.',
    'itemListElement' => $zodiacList
];
$customJsonLd = json_encode($zodiacSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

require_once __DIR__ . '/includes/header.php';
?>

<!-- BREADCRUMB / HERO -->
<section style="padding: 7.5rem 1.5rem 3.5rem; background: linear-gradient(180deg, var(--deep2) 0%, var(--deep) 100%); text-align: center; position: relative; z-index: 1;">
  <div class="container">
    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; font-size: 0.84rem; color: var(--muted); margin-bottom: 1.2rem;">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <span>/</span>
      <span style="color: var(--gold-light);">Zodiac Wheel</span>
    </div>
    <span class="section-label">Ancient Archetypes · Celestial Geometry</span>
    <h1 style="font-size: clamp(2.4rem, 4.5vw, 3.8rem); margin-bottom: 0.8rem;">The Celestial Wheel of Destiny</h1>
    <div class="gold-divider"><span>✦</span></div>
    <p style="max-width: 740px; margin: 0 auto; font-size: 1.05rem; color: var(--text-secondary); line-height: 1.7;">
      The 360-degree belt of the cosmos mapped at the precise instant of your first breath. Explore the 12 sacred signs, elemental forces, and planetary rulerships.
    </p>
  </div>
</section>

<!-- ASTROLABE WHEEL & 12 SIGNS -->
<section class="zodiac-section" style="padding: 5rem 0 6rem;">
  <div class="container">
    <div class="zodiac-layout">
      <div class="zodiac-text-col">
        <span class="section-label">Cosmic Mechanics</span>
        <h2 class="section-title">The Astrolabe<br>&amp; The Soul’s Karma</h2>
        <div class="gold-divider" style="margin: 1rem 0 1.8rem; justify-content: flex-start;"><span>✦</span></div>
        <p class="zodiac-lead">
          The zodiac belt is divided into twelve 30-degree sectors, each pulsing with a unique elemental vibration, psychological archetype, and ruling planet.
        </p>
        <p style="color: var(--text-secondary); font-size: 0.96rem; line-height: 1.75; margin-bottom: 2rem;">
          In Vedic astrology, your Moon sign (Rashi) governs the emotional mind, your Ascendant (Lagna) dictates your physical incarnation and health, and your Sun sign (Surya) guides your core soul purpose. Click any sign or wheel node below to unlock its Vedic mysteries.
        </p>
        <a href="<?= BASE_URL ?>/booking.php?service=Horoscope+Reading+($199)" class="btn-primary">
          <i class="fa-solid fa-compass"></i> Calculate Your Natal Kundali
        </a>
      </div>

      <div class="zodiac-wheel-box">
        <div class="wheel-svg-wrap">
          <svg viewBox="0 0 520 520" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; overflow: visible;">
            <defs>
              <radialGradient id="goldRadialZodiac" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#ffd700" stop-opacity="0.28"/>
                <stop offset="50%" stop-color="#d4af37" stop-opacity="0.10"/>
                <stop offset="100%" stop-color="#050716" stop-opacity="0"/>
              </radialGradient>
              <filter id="centerGlowZodiac" x="-30%" y="-30%" width="160%" height="160%">
                <feGaussianBlur stdDeviation="4" result="blur"/>
                <feComposite in="SourceGraphic" in2="blur" operator="over"/>
              </filter>
            </defs>
            
            <!-- OUTER DEGREES RING -->
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

            <!-- ROTATING CELESTIAL SPOKES TRACK -->
            <g class="rotating-celestial-track">
              <circle cx="260" cy="260" r="236" fill="url(#goldRadialZodiac)" stroke="rgba(212,175,55,0.35)" stroke-width="1.5" stroke-dasharray="8,4"/>
              <circle cx="260" cy="260" r="190" fill="none" stroke="rgba(212,175,55,0.22)" stroke-width="1"/>
              <circle cx="260" cy="260" r="130" fill="none" stroke="rgba(212,175,55,0.18)" stroke-width="1" stroke-dasharray="4,4"/>
              
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

            <!-- STATIC ZODIAC HOUSES -->
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

            <!-- STATIC CENTER HUB -->
            <g class="static-astrolabe-center">
              <circle cx="260" cy="260" r="70" fill="rgba(9, 13, 38, 0.96)" stroke="#d4af37" stroke-width="2" filter="url(#centerGlowZodiac)"/>
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

<!-- THE 12 HOUSES (BHAVAS) GUIDE -->
<section style="padding: 5rem 0; background: var(--deep); position: relative; z-index: 1;">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Vedic Kundali Anatomy</span>
      <h2 class="section-title">The 12 Houses of Life (Bhavas)</h2>
      <div class="gold-divider"><span>✦</span></div>
      <p class="section-desc">Each house in your horoscope governs a distinct department of human experience.</p>
    </div>

    <div class="bhavas-grid">
      <?php
      $houses = [
        ['1st House (Lagna)', 'Self, physical constitution, vitality, temperament & general destiny.'],
        ['2nd House (Dhana)', 'Accumulated wealth, speech, family lineage, food habits & savings.'],
        ['3rd House (Sahaja)', 'Courage, younger siblings, short journeys, communication & willpower.'],
        ['4th House (Sukha)', 'Mother, inner peace, land, property, vehicles & domestic happiness.'],
        ['5th House (Putra)', 'Intellect, children, creativity, past-life karma (Purva Punya) & romance.'],
        ['6th House (Ari)', 'Debts, disputes, health issues, daily routine, competitors & service.'],
        ['7th House (Kalatra)', 'Spouse, marriage, business partnerships, public relations & trade.'],
        ['8th House (Randhra)', 'Longevity, sudden transformations, occult secrets, inheritance & research.'],
        ['9th House (Dharma)', 'Father, guru, higher wisdom, spiritual pilgrimages, fortune & morality.'],
        ['10th House (Karma)', 'Career, authority, social standing, profession, public fame & achievements.'],
        ['11th House (Labha)', 'Gains, cash flow, fulfillment of desires, influential networks & elder siblings.'],
        ['12th House (Moksha)', 'Spiritual liberation, subconscious dreams, foreign residency, meditation & losses.']
      ];
      foreach ($houses as $h):
      ?>
        <div class="bhava-card">
          <div style="color: var(--gold-bright); font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700; margin-bottom: 0.4rem;">
            <?= $h[0] ?>
          </div>
          <p style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;"><?= $h[1] ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CELESTIAL ZODIAC DETAILS MODAL -->
<div class="zodiac-modal-backdrop" id="zodiac-modal" aria-hidden="true">
  <div class="zodiac-modal-container">
    <button class="zodiac-modal-close" id="zodiac-modal-close" aria-label="Close modal">&times;</button>
    <div id="zodiac-modal-content"></div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
