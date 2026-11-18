<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$sentSuccess = false;
$sentError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_contact') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Website Inquiry');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $sentError = 'Please fill out all required fields.';
    } else {
        $sentSuccess = true;
    }
}

$pageTitle = 'Contact Us & Global Support — The Astro Healer';
$pageDescription = 'Get in touch with The Astro Healer concierge. Official email support, worldwide WhatsApp assistance, and consultation booking inquiries.';
$pageKeywords = 'contact the astro healer, astrologer contact, WhatsApp astrology consultation, Malik Mehboob contact';
$canonicalUrl = BASE_URL . '/contact.php';

$contactSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'ContactPage',
    'name' => 'Contact The Astro Healer',
    'description' => 'Official concierge support, WhatsApp live line, and email contact for Malik Mehboob and The Astro Healer.',
    'mainEntity' => [
        '@type' => 'Organization',
        'name' => 'The Astro Healer',
        'telephone' => '+1-470-650-5315',
        'email' => 'admin@theastrohealer.com',
        'url' => BASE_URL . '/'
    ]
];
$customJsonLd = json_encode($contactSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
require_once __DIR__ . '/includes/header.php';
?>

<!-- BREADCRUMB / HERO -->
<section style="padding: 7.5rem 1.5rem 3.5rem; background: linear-gradient(180deg, var(--deep2) 0%, var(--deep) 100%); text-align: center; position: relative; z-index: 1;">
  <div class="container">
    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; font-size: 0.84rem; color: var(--muted); margin-bottom: 1.2rem;">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <span>/</span>
      <span style="color: var(--gold-light);">Contact Us</span>
    </div>
    <span class="section-label">Connect Across the Cosmos</span>
    <h1 style="font-size: clamp(2.4rem, 4.5vw, 3.8rem); margin-bottom: 0.8rem;">We Are Here to Guide You</h1>
    <div class="gold-divider"><span>✦</span></div>
    <p style="max-width: 680px; margin: 0 auto; font-size: 1.05rem; color: var(--text-secondary); line-height: 1.7;">
      Have a question regarding our courses, custom readings, or corporate AstroVastu? Reach out directly via WhatsApp or send us an inquiry below.
    </p>
  </div>
</section>

<!-- CONTACT GRID -->
<section style="padding: 5rem 0 6rem; background: var(--deep); position: relative; z-index: 1;">
  <div class="container">
    <div class="contact-grid">
      
      <!-- Left: Contact Channels -->
      <div>
        <h3 style="font-size: 1.8rem; color: #ffffff; margin-bottom: 1.2rem;">Direct Channels</h3>
        <p style="font-size: 0.96rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 2rem;">
          Our appointment team responds promptly to all inquiries. For immediate consultations, WhatsApp is the fastest channel.
        </p>

        <div style="display: flex; flex-direction: column; gap: 1.4rem; margin-bottom: 2.5rem;">
          <a href="https://wa.me/14706505315" target="_blank" style="display: flex; align-items: center; gap: 1.2rem; background: rgba(37, 211, 102, 0.1); border: 1px solid rgba(37, 211, 102, 0.35); padding: 1.2rem 1.4rem; border-radius: 12px; color: #ffffff; transition: var(--transition);">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: #25d366; display: flex; align-items: center; justify-content: center; color: #000; font-size: 1.4rem; flex-shrink: 0;">
              <i class="fa-brands fa-whatsapp"></i>
            </div>
            <div>
              <div style="font-weight: 700; font-size: 1.05rem;">WhatsApp Direct Desk</div>
              <div style="color: #a5d6a7; font-size: 0.88rem;">+1 (470) 650-5315 · 24/7 International</div>
            </div>
          </a>

          <div style="display: flex; align-items: center; gap: 1.2rem; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--glass-border); padding: 1.2rem 1.4rem; border-radius: 12px;">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(212, 175, 55, 0.15); border: 1px solid var(--gold); display: flex; align-items: center; justify-content: center; color: var(--gold-bright); font-size: 1.3rem; flex-shrink: 0;">
              <i class="fa-regular fa-envelope"></i>
            </div>
            <div>
              <div style="font-weight: 700; font-size: 1.05rem; color: #ffffff;">Email Inquiry</div>
              <div style="color: var(--text-secondary); font-size: 0.88rem;">admin@theastrohealer.com</div>
            </div>
          </div>

          <div style="display: flex; align-items: center; gap: 1.2rem; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--glass-border); padding: 1.2rem 1.4rem; border-radius: 12px;">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(212, 175, 55, 0.15); border: 1px solid var(--gold); display: flex; align-items: center; justify-content: center; color: var(--gold-bright); font-size: 1.3rem; flex-shrink: 0;">
              <i class="fa-regular fa-clock"></i>
            </div>
            <div>
              <div style="font-weight: 700; font-size: 1.05rem; color: #ffffff;">Operating Hours</div>
              <div style="color: var(--text-secondary); font-size: 0.88rem;">Mon – Sat: 10:00 AM – 8:00 PM PKT</div>
            </div>
          </div>
        </div>

        <h4 style="font-size: 1.1rem; color: var(--gold-light); margin-bottom: 0.8rem;">Follow Our Cosmic Teachings</h4>
        <div class="footer-socials">
          <a href="https://wa.me/14706505315" target="_blank" class="social-icon" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
          <a href="https://www.youtube.com/@theastromalik" target="_blank" class="social-icon" title="YouTube"><i class="fa-brands fa-youtube"></i></a>
          <a href="https://www.instagram.com/theastromalik/" target="_blank" class="social-icon" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="https://www.facebook.com/NewMoonAstroHealer" target="_blank" class="social-icon" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        </div>
      </div>

      <!-- Right: Message Form -->
      <div class="contact-card">
        <h3 style="font-size: 1.6rem; color: #ffffff; margin-bottom: 0.4rem;">Send a Cosmic Message</h3>
        <p style="font-size: 0.88rem; color: var(--muted); margin-bottom: 2rem;">For custom academy questions or booking support.</p>

        <?php if ($sentSuccess): ?>
          <div class="alert-box alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <div>
              <strong>Message Received!</strong><br>
              Thank you for connecting with us. We will get back to you within 24 hours.
            </div>
          </div>
        <?php elseif ($sentError): ?>
          <div class="alert-box alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($sentError) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="contact.php">
          <input type="hidden" name="action" value="send_contact">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Imran Ali">
          </div>

          <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" required placeholder="name@example.com">
          </div>

          <div class="form-group">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control" placeholder="e.g. Question regarding BNN Astrology course">
          </div>

          <div class="form-group">
            <label class="form-label">Your Message *</label>
            <textarea name="message" rows="5" class="form-control" required placeholder="Type your message here..."></textarea>
          </div>

          <button type="submit" class="btn-primary" style="width: 100%;">
            <i class="fa-solid fa-paper-plane"></i> Send Inquiry
          </button>
        </form>
      </div>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
