/**
 * THE ASTRO HEALER — Interactive Frontend Engine
 * Features:
 * 1. Deep Space Starfield & Shooting Stars (Cosmic Meteors)
 * 2. Responsive Mobile Celestial Drawer & Backdrop
 * 3. Interactive Astrolabe & 12 Signs Cosmic Modal
 * 4. Bidirectional Wheel-to-Card Hover Synchronization
 * 5. Smooth Scroll & Intersection Observers
 */

document.addEventListener('DOMContentLoaded', () => {
  // ==========================================================================
  // 1. DEEP SPACE STARFIELD & COSMIC SHOOTING STARS ENGINE
  // ==========================================================================
  const canvas = document.getElementById('starfield');
  if (canvas) {
    const ctx = canvas.getContext('2d');
    let width = (canvas.width = window.innerWidth);
    let height = (canvas.height = window.innerHeight);

    window.addEventListener('resize', () => {
      width = canvas.width = window.innerWidth;
      height = canvas.height = window.innerHeight;
      initStars();
    });

    const stars = [];
    const numStars = Math.min(220, Math.floor((width * height) / 4500));
    const meteors = [];

    function initStars() {
      stars.length = 0;
      for (let i = 0; i < numStars; i++) {
        stars.push({
          x: Math.random() * width,
          y: Math.random() * height,
          size: Math.random() * 1.8 + 0.4,
          speed: Math.random() * 0.22 + 0.04,
          alpha: Math.random() * 0.75 + 0.25,
          alphaDir: Math.random() > 0.5 ? 0.005 : -0.005,
          color:
            Math.random() > 0.8
              ? '#ffd700' // Solar Gold
              : Math.random() > 0.65
              ? '#c49aff' // Mystic Amethyst
              : Math.random() > 0.45
              ? '#80d8ff' // Celestial Cyan
              : '#ffffff'  // Pure Starlight
        });
      }
    }

    // Spawn a shooting star at natural intervals
    function maybeSpawnMeteor() {
      if (meteors.length < 2 && Math.random() < 0.015) {
        meteors.push({
          x: Math.random() * (width * 0.7),
          y: Math.random() * (height * 0.4),
          length: Math.random() * 90 + 70,
          speed: Math.random() * 9 + 11,
          angle: Math.PI / 4 + (Math.random() * 0.2 - 0.1), // ~45 degrees
          alpha: 1,
          decay: Math.random() * 0.02 + 0.015,
          color: Math.random() > 0.5 ? '#ffd700' : '#ffffff'
        });
      }
    }

    function animateCosmos() {
      ctx.clearRect(0, 0, width, height);

      // Render Static Twinkling Stars
      stars.forEach(star => {
        star.y -= star.speed;
        if (star.y < 0) {
          star.y = height;
          star.x = Math.random() * width;
        }

        star.alpha += star.alphaDir;
        if (star.alpha <= 0.18 || star.alpha >= 0.95) {
          star.alphaDir = -star.alphaDir;
        }

        ctx.beginPath();
        ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
        ctx.fillStyle = star.color;
        ctx.globalAlpha = star.alpha;
        ctx.shadowBlur = star.size * 3.5;
        ctx.shadowColor = star.color;
        ctx.fill();
      });

      // Render Shooting Stars / Meteors
      maybeSpawnMeteor();
      for (let i = meteors.length - 1; i >= 0; i--) {
        const m = meteors[i];
        m.x += Math.cos(m.angle) * m.speed;
        m.y += Math.sin(m.angle) * m.speed;
        m.alpha -= m.decay;

        if (m.alpha <= 0 || m.x > width || m.y > height) {
          meteors.splice(i, 1);
          continue;
        }

        const tailX = m.x - Math.cos(m.angle) * m.length;
        const tailY = m.y - Math.sin(m.angle) * m.length;

        const grad = ctx.createLinearGradient(tailX, tailY, m.x, m.y);
        grad.addColorStop(0, 'transparent');
        grad.addColorStop(0.7, m.color === '#ffd700' ? 'rgba(212, 175, 55, 0.4)' : 'rgba(157, 104, 247, 0.4)');
        grad.addColorStop(1, '#ffffff');

        ctx.beginPath();
        ctx.moveTo(tailX, tailY);
        ctx.lineTo(m.x, m.y);
        ctx.strokeStyle = grad;
        ctx.lineWidth = 1.8;
        ctx.globalAlpha = m.alpha;
        ctx.shadowBlur = 10;
        ctx.shadowColor = m.color;
        ctx.stroke();

        // Glowing Meteor Head
        ctx.beginPath();
        ctx.arc(m.x, m.y, 2.2, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.shadowBlur = 12;
        ctx.shadowColor = '#ffd700';
        ctx.fill();
      }

      ctx.globalAlpha = 1;
      requestAnimationFrame(animateCosmos);
    }

    initStars();
    animateCosmos();
  }

  // ==========================================================================
  // 2. NAVBAR SCROLL ELEVATION
  // ==========================================================================
  const nav = document.querySelector('.main-nav');
  if (nav) {
    const handleScroll = () => {
      if (window.scrollY > 30) {
        nav.classList.add('scrolled');
      } else {
        nav.classList.remove('scrolled');
      }
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
  }

  // ==========================================================================
  // 3. RESPONSIVE MOBILE CELESTIAL DRAWER & USER DROPDOWN
  // ==========================================================================
  const hamburger = document.getElementById('hamburger-btn');
  const navMenu = document.getElementById('nav-menu');
  const navBackdrop = document.getElementById('nav-backdrop');
  const mobileCloseBtn = document.getElementById('mobile-close-btn');

  function openMobileNav() {
    if (navMenu && navBackdrop) {
      document.body.classList.add('mobile-nav-open');
      const aiCard = document.getElementById('ai-widget-card');
      const aiTrig = document.getElementById('ai-floating-trigger');
      if (aiCard) {
        aiCard.classList.remove('active');
      }
      if (aiTrig) {
        aiTrig.style.display = 'none';
      }

      navMenu.classList.add('open');
      navBackdrop.classList.add('open');
      if (hamburger) {
        hamburger.classList.add('active');
        hamburger.setAttribute('aria-expanded', 'true');
      }
      document.body.style.overflow = 'hidden';
    }
  }

  function closeMobileNav() {
    if (navMenu && navBackdrop) {
      document.body.classList.remove('mobile-nav-open');
      const aiTrig = document.getElementById('ai-floating-trigger');
      if (aiTrig) {
        aiTrig.style.display = 'flex';
      }

      navMenu.classList.remove('open');
      navBackdrop.classList.remove('open');
      if (hamburger) {
        hamburger.classList.remove('active');
        hamburger.setAttribute('aria-expanded', 'false');
      }
      document.body.style.overflow = '';
    }
  }

  if (hamburger && navMenu) {
    hamburger.addEventListener('click', (e) => {
      e.stopPropagation();
      if (navMenu.classList.contains('open')) {
        closeMobileNav();
      } else {
        openMobileNav();
      }
    });

    if (mobileCloseBtn) {
      mobileCloseBtn.addEventListener('click', closeMobileNav);
    }

    if (navBackdrop) {
      navBackdrop.addEventListener('click', closeMobileNav);
    }

    // Auto-close on link click inside drawer & smooth scroll anchors
    navMenu.querySelectorAll('.nav-link, .btn-primary, .btn-outline').forEach(link => {
      link.addEventListener('click', (e) => {
        const href = link.getAttribute('href');
        closeMobileNav();
        if (href && href.startsWith('#')) {
          const target = document.querySelector(href);
          if (target) {
            e.preventDefault();
            setTimeout(() => {
              const navH = document.querySelector('.main-nav')?.offsetHeight || 70;
              const pos = target.getBoundingClientRect().top + window.pageYOffset - navH - 10;
              window.scrollTo({ top: pos, behavior: 'smooth' });
            }, 280);
          }
        }
      });
    });

    // Close on Escape key
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && navMenu.classList.contains('open')) {
        closeMobileNav();
      }
    });
  }

  // User Profile Dropdown Menu in Desktop Navigation
  const navUserBtn = document.getElementById('navUserBtn');
  const navUserDropdown = document.getElementById('navUserDropdown');
  if (navUserBtn && navUserDropdown) {
    navUserBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = navUserDropdown.classList.toggle('open');
      navUserBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Dismiss on click outside
    document.addEventListener('click', (e) => {
      if (!navUserDropdown.contains(e.target)) {
        navUserDropdown.classList.remove('open');
        navUserBtn.setAttribute('aria-expanded', 'false');
      }
    });

    // Dismiss on Escape
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && navUserDropdown.classList.contains('open')) {
        navUserDropdown.classList.remove('open');
        navUserBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // ==========================================================================
  // 4. ZODIAC SIGNS DATA, MODAL & INTERACTIVE ASTROLABE
  // ==========================================================================
  const zodiacs = [
    {
      sym: '♈',
      name: 'Aries',
      vedic: 'Mesha (मेष)',
      dates: 'Mar 21 – Apr 19',
      el: 'Fire',
      cls: 'el-fire',
      planet: 'Mars (Mangal)',
      modality: 'Cardinal Fire',
      luckyNum: '1, 9',
      luckyGem: 'Red Coral (Moonga)',
      luckyColor: 'Scarlet & Gold',
      guidance: 'Ruled by Mars, Aries embodies initiating cosmic spark, courage, and pioneering willpower. Harness planetary dasha cycles to channel passion into long-term master creations rather than impulsive fires.'
    },
    {
      sym: '♉',
      name: 'Taurus',
      vedic: 'Vrishabha (वृषभ)',
      dates: 'Apr 20 – May 20',
      el: 'Earth',
      cls: 'el-earth',
      planet: 'Venus (Shukra)',
      modality: 'Fixed Earth',
      luckyNum: '2, 6',
      luckyGem: 'Diamond / White Sapphire',
      luckyColor: 'Lotus White & Emerald',
      guidance: 'Sanctuary of peace, perseverance, and prosperity under Venusian blessings. Build enduring foundations with patient sacred alignment and trust divine cosmic timing for wealth generation.'
    },
    {
      sym: '♊',
      name: 'Gemini',
      vedic: 'Mithuna (मिथुन)',
      dates: 'May 21 – Jun 20',
      el: 'Air',
      cls: 'el-air',
      planet: 'Mercury (Budha)',
      modality: 'Mutable Air',
      luckyNum: '3, 5',
      luckyGem: 'Emerald (Panna)',
      luckyColor: 'Mint Green & Solar Yellow',
      guidance: 'The bridge between heaven and mind. Ruled by Mercury, Geminis possess multifaceted intellectual brilliance and communication wizardry. Ground your versatile thoughts with daily meditation.'
    },
    {
      sym: '♋',
      name: 'Cancer',
      vedic: 'Karka (कर्क)',
      dates: 'Jun 21 – Jul 22',
      el: 'Water',
      cls: 'el-water',
      planet: 'Moon (Chandra)',
      modality: 'Cardinal Water',
      luckyNum: '2, 7',
      luckyGem: 'Natural Pearl (Moti)',
      luckyColor: 'Silver & Moonlit White',
      guidance: 'Deep intuitive emotional wisdom guided directly by the Moon. Protect your sacred inner aura while nurturing community and trusting the ebb and flow of planetary tides.'
    },
    {
      sym: '♌',
      name: 'Leo',
      vedic: 'Simha (सिंह)',
      dates: 'Jul 23 – Aug 22',
      el: 'Fire',
      cls: 'el-fire',
      planet: 'Sun (Surya)',
      modality: 'Fixed Fire',
      luckyNum: '1, 4',
      luckyGem: 'Ruby (Manikya)',
      luckyColor: 'Royal Gold & Copper',
      guidance: 'The radiant heart of the zodiac, carrying the supreme soul vitality of Surya. Lead with dignified generosity, authentic nobility, and uplift all who enter your gravitational warmth.'
    },
    {
      sym: '♍',
      name: 'Virgo',
      vedic: 'Kanya (कन्या)',
      dates: 'Aug 23 – Sep 22',
      el: 'Earth',
      cls: 'el-earth',
      planet: 'Mercury (Budha)',
      modality: 'Mutable Earth',
      luckyNum: '5, 6',
      luckyGem: 'Emerald (Panna)',
      luckyColor: 'Forest Jade & Champagne',
      guidance: 'The alchemical healer and master craftsman. Analytical clarity combined with spiritual devotion unlocks divine solutions. Remember self-compassion is the highest medicine.'
    },
    {
      sym: '♎',
      name: 'Libra',
      vedic: 'Tula (तुला)',
      dates: 'Sep 23 – Oct 22',
      el: 'Air',
      cls: 'el-air',
      planet: 'Venus (Shukra)',
      modality: 'Cardinal Air',
      luckyNum: '6, 8',
      luckyGem: 'Diamond / Opal',
      luckyColor: 'Rose Pastel & Sky Blue',
      guidance: 'The scales of cosmic balance, diplomacy, and aesthetics. When making pivotal karmic decisions, consult your deep inner spiritual compass rather than seeking external validation.'
    },
    {
      sym: '♏',
      name: 'Scorpio',
      vedic: 'Vrishchika (वृश्चिक)',
      dates: 'Oct 23 – Nov 21',
      el: 'Water',
      cls: 'el-water',
      planet: 'Mars & Ketu',
      modality: 'Fixed Water',
      luckyNum: '9, 18',
      luckyGem: 'Red Coral / Cat’s Eye',
      luckyColor: 'Maroon & Deep Indigo',
      guidance: 'Master of rebirth, occult depth, and transformative intuition. Transmute shadow fears into golden spiritual power; your ability to regenerate is unmatched in the celestial sphere.'
    },
    {
      sym: '♐',
      name: 'Sagittarius',
      vedic: 'Dhanu (धनु)',
      dates: 'Nov 22 – Dec 21',
      el: 'Fire',
      cls: 'el-fire',
      planet: 'Jupiter (Guru)',
      modality: 'Mutable Fire',
      luckyNum: '3, 12',
      luckyGem: 'Yellow Sapphire (Pukhraj)',
      luckyColor: 'Saffron & Imperial Violet',
      guidance: 'The cosmic philosopher and truth-seeker propelled by Brihaspati’s grace. Aim your karmic arrow at the highest wisdom, global education, and philosophical enlightenment.'
    },
    {
      sym: '♑',
      name: 'Capricorn',
      vedic: 'Makara (मकर)',
      dates: 'Dec 22 – Jan 19',
      el: 'Earth',
      cls: 'el-earth',
      planet: 'Saturn (Shani)',
      modality: 'Cardinal Earth',
      luckyNum: '8, 17',
      luckyGem: 'Blue Sapphire (Neelam)',
      luckyColor: 'Charcoal Black & Royal Navy',
      guidance: 'The mountain summit builder guided by disciplined Lord Shani. Enduring karma, structural mastery, and generational legacy reward those who practice integrity at every step.'
    },
    {
      sym: '♒',
      name: 'Aquarius',
      vedic: 'Kumbha (कुम्भ)',
      dates: 'Jan 20 – Feb 18',
      el: 'Air',
      cls: 'el-air',
      planet: 'Saturn & Rahu',
      modality: 'Fixed Air',
      luckyNum: '4, 8',
      luckyGem: 'Amethyst / Blue Sapphire',
      luckyColor: 'Electric Cyan & Metallic Silver',
      guidance: 'The cosmic visionary and bearer of the water of universal knowledge. Awaken innovative systems and humanitarian progress while remaining anchored in your timeless soul roots.'
    },
    {
      sym: '♓',
      name: 'Pisces',
      vedic: 'Meena (मीन)',
      dates: 'Feb 19 – Mar 20',
      el: 'Water',
      cls: 'el-water',
      planet: 'Jupiter (Guru)',
      modality: 'Mutable Water',
      luckyNum: '3, 7',
      luckyGem: 'Yellow Sapphire / Moonstone',
      luckyColor: 'Seafoam Aquamarine & Golden Amber',
      guidance: 'The transcendent ocean of divine empathy and mystical oneness. Ground your boundless dreams into creative sacred art and let divine surrender dissolve all earthly burdens.'
    }
  ];

  // Render 12 Signs Cards
  const signsContainer = document.getElementById('dynamic-signs-grid');
  if (signsContainer) {
    signsContainer.innerHTML = zodiacs
      .map(
        (z, idx) => `
        <div class="sign-card" data-sign-card="${idx}" tabindex="0" role="button" aria-label="Explore ${z.name}">
          <span class="sign-symbol">${z.sym}</span>
          <div class="sign-name">${z.name}</div>
          <div class="sign-dates">${z.dates}</div>
          <span class="sign-element ${z.cls}">${z.el}</span>
        </div>
      `
      )
      .join('');

    // Modal Interaction Logic
    const modalBackdrop = document.getElementById('zodiac-modal');
    const modalContent = document.getElementById('zodiac-modal-content');
    const modalClose = document.getElementById('zodiac-modal-close');

    function openZodiacModal(index) {
      const z = zodiacs[index];
      if (!z || !modalContent || !modalBackdrop) return;

      modalContent.innerHTML = `
        <div class="modal-zodiac-header">
          <span class="modal-zodiac-glyph">${z.sym}</span>
          <h3 class="modal-zodiac-title">${z.name}</h3>
          <div class="modal-zodiac-vedic">${z.vedic} · ${z.dates}</div>
        </div>

        <div class="modal-zodiac-grid">
          <div class="modal-zodiac-stat">
            <div class="stat-lbl"><i class="fa-solid fa-planet-ringed" style="color:var(--gold);"></i> Ruling Planet</div>
            <div class="stat-val">${z.planet}</div>
          </div>
          <div class="modal-zodiac-stat">
            <div class="stat-lbl"><i class="fa-solid fa-shapes" style="color:var(--gold);"></i> Element &amp; Modality</div>
            <div class="stat-val">${z.modality}</div>
          </div>
          <div class="modal-zodiac-stat">
            <div class="stat-lbl"><i class="fa-solid fa-gem" style="color:var(--gold);"></i> Sacred Gemstone</div>
            <div class="stat-val">${z.luckyGem}</div>
          </div>
          <div class="modal-zodiac-stat">
            <div class="stat-lbl"><i class="fa-solid fa-hashtag" style="color:var(--gold);"></i> Lucky Numbers &amp; Color</div>
            <div class="stat-val">${z.luckyNum} · ${z.luckyColor}</div>
          </div>
        </div>

        <div class="modal-zodiac-desc">
          <strong style="color: var(--gold-bright); display: block; margin-bottom: 0.4rem; font-family: var(--font-serif); font-size: 0.85rem; letter-spacing: 0.12em; text-transform: uppercase;">
            Vedic Karmic Blueprint:
          </strong>
          <p>${z.guidance}</p>
        </div>

        <div style="margin-top: 1.6rem; text-align: center;">
          <a href="#booking" class="btn-primary btn-sm" onclick="document.getElementById('zodiac-modal').classList.remove('open'); document.body.style.overflow='';">
            <i class="fa-solid fa-compass"></i> Book Personalized ${z.name} Horoscope
          </a>
        </div>
      `;

      modalBackdrop.classList.add('open');
      modalBackdrop.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeZodiacModal() {
      if (modalBackdrop) {
        modalBackdrop.classList.remove('open');
        modalBackdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
      }
    }

    if (modalClose) {
      modalClose.addEventListener('click', closeZodiacModal);
    }
    if (modalBackdrop) {
      modalBackdrop.addEventListener('click', (e) => {
        if (e.target === modalBackdrop) closeZodiacModal();
      });
    }
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modalBackdrop && modalBackdrop.classList.contains('open')) {
        closeZodiacModal();
      }
    });

    // Connect Sign Cards to SVG Astrolabe Nodes & Click handlers
    const signCards = signsContainer.querySelectorAll('.sign-card');
    const wheelNodes = document.querySelectorAll('.wheel-node');
    const wheelSyms = document.querySelectorAll('.wheel-sym');

    signCards.forEach(card => {
      const idx = parseInt(card.getAttribute('data-sign-card'), 10);
      const targetNode = document.querySelector(`.wheel-node[data-sign-index="${idx}"]`);
      const targetSym = document.querySelector(`.wheel-sym[data-sign-index="${idx}"]`);

      // Click opens modal
      card.addEventListener('click', () => openZodiacModal(idx));
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openZodiacModal(idx);
        }
      });

      // Hover synchronizes with Astrolabe SVG
      card.addEventListener('mouseenter', () => {
        if (targetNode) {
          targetNode.setAttribute('fill', 'rgba(212, 175, 55, 0.55)');
          targetNode.setAttribute('stroke', '#ffd700');
          targetNode.setAttribute('stroke-width', '2.5');
          targetNode.setAttribute('r', '22');
        }
        if (targetSym) {
          targetSym.setAttribute('fill', '#ffffff');
          targetSym.setAttribute('font-size', '19');
        }
      });

      card.addEventListener('mouseleave', () => {
        if (targetNode) {
          targetNode.setAttribute('fill', 'rgba(8, 12, 36, 0.92)');
          targetNode.setAttribute('stroke', 'rgba(212,175,55,0.3)');
          targetNode.setAttribute('stroke-width', '1.2');
          targetNode.setAttribute('r', '18');
        }
        if (targetSym) {
          targetSym.setAttribute('fill', '#ffd700');
          targetSym.setAttribute('font-size', '16');
        }
      });
    });

    // Clicking Astrolabe Wheel nodes also opens modal & synchronizes hover
    wheelNodes.forEach(node => {
      const idx = parseInt(node.getAttribute('data-sign-index'), 10);
      const matchingCard = document.querySelector(`.sign-card[data-sign-card="${idx}"]`);
      const matchingSym = document.querySelector(`.wheel-sym[data-sign-index="${idx}"]`);

      node.addEventListener('click', () => openZodiacModal(idx));
      if (matchingSym) {
        matchingSym.addEventListener('click', () => openZodiacModal(idx));
        matchingSym.style.cursor = 'pointer';
      }

      node.addEventListener('mouseenter', () => {
        node.setAttribute('fill', 'rgba(212, 175, 55, 0.55)');
        node.setAttribute('stroke', '#ffd700');
        node.setAttribute('stroke-width', '2.5');
        node.setAttribute('r', '22');
        if (matchingCard) {
          matchingCard.style.borderColor = 'var(--gold-bright)';
          matchingCard.style.transform = 'translateY(-6px)';
          matchingCard.style.boxShadow = '0 12px 28px rgba(212, 175, 55, 0.35)';
        }
      });

      node.addEventListener('mouseleave', () => {
        node.setAttribute('fill', 'rgba(8, 12, 36, 0.92)');
        node.setAttribute('stroke', 'rgba(212,175,55,0.3)');
        node.setAttribute('stroke-width', '1.2');
        node.setAttribute('r', '18');
        if (matchingCard) {
          matchingCard.style.borderColor = '';
          matchingCard.style.transform = '';
          matchingCard.style.boxShadow = '';
        }
      });
    });
  }

  // ==========================================================================
  // 6. MALIK AI ASSISTANT — AUTO-OPEN & CELESTIAL CHAT ENGINE
  // ==========================================================================
  const aiWidgetCard = document.getElementById('ai-widget-card');
  const aiFloatingTrigger = document.getElementById('ai-floating-trigger');
  const aiMinimizeBtn = document.getElementById('ai-minimize-btn');
  const aiWidgetForm = document.getElementById('ai-widget-form');
  const aiUserInput = document.getElementById('ai-user-input');
  const aiMessages = document.getElementById('ai-messages');
  const aiPills = document.querySelectorAll('.ai-pill');

  function formatAiText(text) {
    if (!text) return '';
    // Format bold **text**
    let formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    // Format numbered bullet points or newlines
    formatted = formatted.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>');
    return '<p>' + formatted + '</p>';
  }

  function appendAiMessage(container, role, text, tag = '') {
    if (!container) return;
    const msgDiv = document.createElement('div');
    msgDiv.className = `ai-msg ai-msg-${role}`;

    const logoSrc = (window.BASE_URL ? window.BASE_URL : '') + '/assets/images/logo.png';

    if (role === 'bot') {
      msgDiv.style.display = 'flex';
      msgDiv.style.flexDirection = 'row';
      msgDiv.style.alignItems = 'flex-start';
      msgDiv.style.gap = '0.5rem';

      const avatarDiv = document.createElement('div');
      avatarDiv.className = 'ai-msg-avatar';
      avatarDiv.style.cssText = 'width:28px; height:28px; border-radius:50%; border:1.5px solid #d4af37; overflow:hidden; flex-shrink:0; background:#0b071e; margin-top:2px;';
      avatarDiv.innerHTML = `<img src="${logoSrc}" alt="Malik AI" style="width:100%; height:100%; object-fit:cover; display:block;">`;
      msgDiv.appendChild(avatarDiv);
    }

    const bubble = document.createElement('div');
    bubble.className = 'ai-bubble';
    if (role === 'bot') bubble.style.flex = '1';

    if (role === 'bot') {
      const tagSpan = document.createElement('span');
      tagSpan.className = 'ai-bubble-tag';
      tagSpan.innerHTML = `<i class="fa-solid fa-robot"></i> ${tag || 'Vedic AI Oracle'}`;
      bubble.appendChild(tagSpan);
    }

    const contentDiv = document.createElement('div');
    contentDiv.innerHTML = formatAiText(text);
    bubble.appendChild(contentDiv);

    msgDiv.appendChild(bubble);
    container.appendChild(msgDiv);
    container.scrollTop = container.scrollHeight;
  }

  function showAiTyping(container) {
    if (!container) return null;
    const typingDiv = document.createElement('div');
    typingDiv.className = 'ai-msg ai-msg-bot ai-typing-wrapper';
    typingDiv.style.display = 'flex';
    typingDiv.style.flexDirection = 'row';
    typingDiv.style.alignItems = 'flex-start';
    typingDiv.style.gap = '0.5rem';

    const logoSrc = (window.BASE_URL ? window.BASE_URL : '') + '/assets/images/logo.png';
    typingDiv.innerHTML = `
      <div class="ai-msg-avatar" style="width:28px; height:28px; border-radius:50%; border:1.5px solid #d4af37; overflow:hidden; flex-shrink:0; background:#0b071e; margin-top:2px;">
        <img src="${logoSrc}" alt="Malik AI" style="width:100%; height:100%; object-fit:cover; display:block;">
      </div>
      <div class="ai-bubble" style="flex:1;">
        <div class="ai-typing-indicator">
          <span></span><span></span><span></span>
        </div>
      </div>
    `;
    container.appendChild(typingDiv);
    container.scrollTop = container.scrollHeight;
    return typingDiv;
  }

  function openAiWidget() {
    if (aiWidgetCard && aiFloatingTrigger) {
      aiWidgetCard.classList.add('active');
      aiFloatingTrigger.style.display = 'none';
      if (aiUserInput) {
        setTimeout(() => aiUserInput.focus(), 300);
      }
    }
  }

  function closeAiWidget() {
    if (aiWidgetCard && aiFloatingTrigger) {
      aiWidgetCard.classList.remove('active');
      setTimeout(() => {
        aiFloatingTrigger.style.display = 'flex';
      }, 200);
    }
  }

  // AUTO-OPEN ON PAGE LOAD (Desktop only; keep minimized on all mobile & touch devices)
  if (aiWidgetCard) {
    const pathname = window.location.pathname.toLowerCase();
    const isHomePage = pathname.endsWith('index.php') || pathname.endsWith('/theastrohealer') || pathname.endsWith('/theastrohealer/') || pathname === '/' || pathname === '';
    const isBookingOrAi = pathname.includes('booking.php') || pathname.includes('ai.php') || pathname.includes('login.php') || pathname.includes('signup.php');
    const isMobileDevice = window.innerWidth <= 860 || ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

    if (isHomePage && !isMobileDevice) {
      setTimeout(() => {
        openAiWidget();
      }, 1000);
    } else if (!isBookingOrAi && !isMobileDevice) {
      setTimeout(() => {
        openAiWidget();
      }, 1200);
    } else {
      // Keep strictly minimized with neat floating trigger pill on mobile & booking/auth forms
      if (aiFloatingTrigger) {
        aiFloatingTrigger.style.display = 'flex';
      }
    }

    if (aiFloatingTrigger) {
      aiFloatingTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        openAiWidget();
      });
    }

    if (aiMinimizeBtn) {
      aiMinimizeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        closeAiWidget();
      });
    }

    // Open from header or mobile menu buttons
    document.querySelectorAll('.open-ai-widget-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        openAiWidget();
        const mobileMenu = document.getElementById('nav-menu');
        const backdrop = document.getElementById('nav-backdrop');
        if (mobileMenu) mobileMenu.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
      });
    });

    // Handle Quick Pills click
    aiPills.forEach(pill => {
      pill.addEventListener('click', () => {
        const query = pill.getAttribute('data-query');
        if (query && aiUserInput) {
          aiUserInput.value = query;
          aiWidgetForm.dispatchEvent(new Event('submit'));
        }
      });
    });

    // Floating Widget Form Submission
    if (aiWidgetForm && aiUserInput) {
      aiWidgetForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = aiUserInput.value.trim();
        if (!message) return;

        // Display user's question
        appendAiMessage(aiMessages, 'user', message);
        aiUserInput.value = '';

        // Display typing indicator
        const typingIndicator = showAiTyping(aiMessages);

        try {
          const endpoint = (window.BASE_URL ? window.BASE_URL : '') + '/api/ai_chat.php';
          const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: message })
          });
          const data = await res.json();
          if (typingIndicator) typingIndicator.remove();

          if (data && data.reply) {
            appendAiMessage(aiMessages, 'bot', data.reply, 'Malik AI');
          } else {
            appendAiMessage(aiMessages, 'bot', 'The celestial frequencies are aligning. Please ask again.', 'Malik AI');
          }
        } catch (err) {
          if (typingIndicator) typingIndicator.remove();
          appendAiMessage(aiMessages, 'bot', 'Unable to reach the celestial oracle right now. Please check your connection or contact Malik Mehboob on WhatsApp.', 'System');
        }
      });
    }
  }

  // ==========================================================================
  // 7. FULL-PAGE AI SANCTUARY (ai.php) ENGINE
  // ==========================================================================
  const fullpageForm = document.getElementById('fullpage-ai-form');
  const fullpageInput = document.getElementById('fullpage-ai-input');
  const fullpageMessages = document.getElementById('fullpage-ai-messages');
  const fullpageClearBtn = document.getElementById('fullpage-ai-clear');
  const fullpagePills = document.querySelectorAll('.fullpage-pill, .ai-topic-btn');

  if (fullpageForm && fullpageInput && fullpageMessages) {
    fullpagePills.forEach(btn => {
      btn.addEventListener('click', () => {
        const q = btn.getAttribute('data-query');
        if (q) {
          fullpageInput.value = q;
          fullpageForm.dispatchEvent(new Event('submit'));
        }
      });
    });

    if (fullpageClearBtn) {
      fullpageClearBtn.addEventListener('click', () => {
        const logoSrc = (window.BASE_URL ? window.BASE_URL : '') + '/assets/images/logo.png';
        fullpageMessages.innerHTML = `
          <div class="ai-msg ai-msg-bot">
            <div class="ai-avatar-small" style="width:32px; height:32px; border-radius:50%; overflow:hidden; border:1.5px solid #d4af37; background:#0b071e; flex-shrink:0;">
              <img src="${logoSrc}" alt="AI" style="width:100%; height:100%; object-fit:cover; display:block;">
            </div>
            <div class="ai-bubble">
              <span class="ai-bubble-tag"><i class="fa-solid fa-robot"></i> Malik AI Oracle</span>
              <p>Conversation reset. The cosmic slate is clean. How may the stars illuminate your path now?</p>
            </div>
          </div>
        `;
      });
    }

    fullpageForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const text = fullpageInput.value.trim();
      if (!text) return;

      // Append user msg
      const userMsgDiv = document.createElement('div');
      userMsgDiv.className = 'ai-msg ai-msg-user';
      userMsgDiv.innerHTML = `<div class="ai-bubble">${formatAiText(text)}</div>`;
      fullpageMessages.appendChild(userMsgDiv);
      fullpageInput.value = '';
      fullpageMessages.scrollTop = fullpageMessages.scrollHeight;

      const logoSrc = (window.BASE_URL ? window.BASE_URL : '') + '/assets/images/logo.png';
      // Typing indicator
      const typingDiv = document.createElement('div');
      typingDiv.className = 'ai-msg ai-msg-bot';
      typingDiv.innerHTML = `
        <div class="ai-avatar-small" style="width:32px; height:32px; border-radius:50%; overflow:hidden; border:1.5px solid #d4af37; background:#0b071e; flex-shrink:0;">
          <img src="${logoSrc}" alt="AI" style="width:100%; height:100%; object-fit:cover; display:block;">
        </div>
        <div class="ai-bubble">
          <div class="ai-typing-indicator"><span></span><span></span><span></span></div>
        </div>
      `;
      fullpageMessages.appendChild(typingDiv);
      fullpageMessages.scrollTop = fullpageMessages.scrollHeight;

      try {
        const endpoint = (window.BASE_URL ? window.BASE_URL : '') + '/api/ai_chat.php';
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: text })
        });
        const data = await res.json();
        typingDiv.remove();

        const botMsgDiv = document.createElement('div');
        botMsgDiv.className = 'ai-msg ai-msg-bot';
        botMsgDiv.innerHTML = `
          <div class="ai-avatar-small" style="width:32px; height:32px; border-radius:50%; overflow:hidden; border:1.5px solid #d4af37; background:#0b071e; flex-shrink:0;">
            <img src="${logoSrc}" alt="AI" style="width:100%; height:100%; object-fit:cover; display:block;">
          </div>
          <div class="ai-bubble">
            <span class="ai-bubble-tag"><i class="fa-solid fa-robot"></i> Malik AI Oracle</span>
            ${formatAiText(data.reply || 'Guidance received.')}
          </div>
        `;
        fullpageMessages.appendChild(botMsgDiv);
        fullpageMessages.scrollTop = fullpageMessages.scrollHeight;
      } catch (err) {
        typingDiv.remove();
        const errDiv = document.createElement('div');
        errDiv.className = 'ai-msg ai-msg-bot';
        errDiv.innerHTML = `
          <div class="ai-avatar-small"><i class="fa-solid fa-sparkles"></i></div>
          <div class="ai-bubble">
            <span class="ai-bubble-tag"><i class="fa-solid fa-triangle-exclamation"></i> Notice</span>
            <p>Unable to connect to the cosmic server. Please verify your connection or reach Malik Mehboob directly on WhatsApp.</p>
          </div>
        `;
        fullpageMessages.appendChild(errDiv);
        fullpageMessages.scrollTop = fullpageMessages.scrollHeight;
      }
    });
  }

  // ==========================================================================
  // 8. UNIVERSAL SMOOTH ANCHOR SCROLLING (With Sticky Nav Offset)
  // ==========================================================================
  document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const targetId = this.getAttribute('href');
      if (targetId && targetId.length > 1) {
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          e.preventDefault();
          const navH = document.querySelector('.main-nav')?.offsetHeight || 70;
          const pos = targetElement.getBoundingClientRect().top + window.pageYOffset - navH - 12;
          window.scrollTo({
            top: pos,
            behavior: 'smooth'
          });
          if (history.pushState) {
            history.pushState(null, null, targetId);
          }
        }
      }
    });
  });

  // Handle URL hash on initial page load (e.g. #booking)
  if (window.location.hash) {
    setTimeout(() => {
      const target = document.querySelector(window.location.hash);
      if (target) {
        const navH = document.querySelector('.main-nav')?.offsetHeight || 70;
        const pos = target.getBoundingClientRect().top + window.pageYOffset - navH - 12;
        window.scrollTo({ top: pos, behavior: 'smooth' });
      }
    }, 380);
  }
});
