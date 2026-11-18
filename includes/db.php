<?php
require_once __DIR__ . '/config.php';

function getDBConnection($silentFail = false) {
    static $pdo = null;
    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
            return $pdo;
        } catch (\Throwable $e) {
            $pdo = null;
        }
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    // Prepare candidate connection DSNs (Unix Socket first for cPanel Linux, TCP fallback)
    $dsnCandidates = [
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        "mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : 3306) . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    ];

    if (PHP_OS_FAMILY === 'Linux') {
        $dsnCandidates[] = "mysql:unix_socket=/var/lib/mysql/mysql.sock;dbname=" . DB_NAME . ";charset=utf8mb4";
        $dsnCandidates[] = "mysql:unix_socket=/tmp/mysql.sock;dbname=" . DB_NAME . ";charset=utf8mb4";
    }

    if (DB_HOST === 'localhost') {
        $dsnCandidates[] = "mysql:host=127.0.0.1;port=3306;dbname=" . DB_NAME . ";charset=utf8mb4";
    }

    // Candidate user credentials in case cPanel username is slightly different
    $userCandidates = [DB_USER];
    if (PHP_OS_FAMILY === 'Linux' || !empty($_SERVER['HTTP_HOST'])) {
        $userCandidates[] = 'theahnel_astrohealer';
        $userCandidates[] = 'theahnel_theastrohealer';
    }
    if (PHP_OS_FAMILY !== 'Linux' && (($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || php_sapi_name() === 'cli')) {
        $userCandidates[] = 'root';
    }
    $userCandidates = array_unique($userCandidates);

    $lastError = null;
    $failedAttempts = [];

    foreach ($dsnCandidates as $dsnDb) {
        foreach ($userCandidates as $candidateUser) {
            $pass = ($candidateUser === 'root') ? '' : DB_PASS;
            try {
                $pdo = new PDO($dsnDb, $candidateUser, $pass, $options);

                // Auto-initialize tables & seed data if database is newly created
                try {
                    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
                    if (!$tableCheck) {
                        initializeSchema($pdo);
                    }
                } catch (\Throwable $initErr) {}

                return $pdo;
            } catch (PDOException $e) {
                $lastError = $e;
                // If database does not exist (error 1049) and user has CREATE permission (local dev)
                if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                    try {
                        $rootDsn = preg_replace('/dbname=[^;]+;?/', '', $dsnDb);
                        $rootPdo = new PDO($rootDsn, $candidateUser, $pass, $options);
                        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                        $pdo = new PDO($dsnDb, $candidateUser, $pass, $options);
                        initializeSchema($pdo);
                        return $pdo;
                    } catch (\Throwable $subE) {
                        $lastError = $subE;
                    }
                }
            }
        }
    }

    if ($silentFail) {
        return null;
    }

    die("<div style='background:#0d0a1a; color:#e8d5a3; font-family:sans-serif; padding:2.5rem; border:1px solid #c9a84c; margin:2rem auto; max-width:650px; border-radius:12px; box-shadow:0 15px 40px rgba(0,0,0,0.8); text-align:center;'>
        <h3 style='color:#c9a84c; margin-top:0; font-size:1.5rem;'>The Astro Healer — Database Connection Notice</h3>
        <p style='color:#bbb; line-height:1.6;'>Could not connect to the MySQL database server. Please verify your database credentials in <code>includes/config.php</code> or the <code>.env</code> file.</p>
        <div style='background:rgba(255,255,255,0.05); padding:1rem; border-radius:6px; font-family:monospace; font-size:0.85rem; color:#ff9800; text-align:left; word-break:break-all; margin-top:1.5rem;'>
            <strong>Details:</strong> " . htmlspecialchars($lastError ? $lastError->getMessage() : 'Unknown database error') . "<br><br>
            <span style='color:#bbb;'>Target Database: <code>" . htmlspecialchars(DB_NAME) . "</code> | Attempted Users: <code>" . htmlspecialchars(implode(', ', $userCandidates)) . "</code></span>
        </div>
    </div>");
}

function initializeSchema(PDO $pdo) {
    // Check if users table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($tableCheck) {
        return; // Schema already created
    }

    // 1. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `role` ENUM('admin', 'student') DEFAULT 'student',
        `status` ENUM('active', 'pending', 'suspended') DEFAULT 'active',
        `avatar` VARCHAR(255) DEFAULT 'assets/images/default-avatar.png',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Courses Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `courses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `slug` VARCHAR(100) NOT NULL UNIQUE,
        `title` VARCHAR(200) NOT NULL,
        `subtitle` VARCHAR(255) DEFAULT NULL,
        `description` TEXT NOT NULL,
        `curriculum_summary` TEXT DEFAULT NULL,
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `duration` VARCHAR(50) DEFAULT '6 Weeks',
        `total_classes` INT DEFAULT 12,
        `level` VARCHAR(50) DEFAULT 'Beginner to Advanced',
        `image` VARCHAR(255) DEFAULT NULL,
        `badge` VARCHAR(50) DEFAULT 'Best Seller',
        `status` ENUM('published', 'draft') DEFAULT 'published',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Enrollments Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `enrollments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `status` ENUM('active', 'pending', 'revoked', 'expired') DEFAULT 'pending',
        `access_type` ENUM('paid_stripe', 'admin_granted', 'manual_request') DEFAULT 'manual_request',
        `stripe_payment_intent` VARCHAR(255) DEFAULT NULL,
        `amount_paid` DECIMAL(10,2) DEFAULT 0.00,
        `request_note` TEXT DEFAULT NULL,
        `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `approved_at` DATETIME DEFAULT NULL,
        UNIQUE KEY `unique_user_course` (`user_id`, `course_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Lectures / Classes Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `lectures` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `course_id` INT NOT NULL,
        `class_number` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `video_type` ENUM('file', 'url', 'embed') DEFAULT 'url',
        `video_source` TEXT NOT NULL,
        `duration` VARCHAR(50) DEFAULT '45 mins',
        `resources_pdf` VARCHAR(255) DEFAULT NULL,
        `is_free_preview` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. Lecture Progress Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `lecture_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `lecture_id` INT NOT NULL,
        `completed` TINYINT(1) DEFAULT 0,
        `completed_at` DATETIME DEFAULT NULL,
        UNIQUE KEY `unique_user_lecture` (`user_id`, `lecture_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`lecture_id`) REFERENCES `lectures`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 6. System Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT DEFAULT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 7. Bookings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bookings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL,
        `service` VARCHAR(150) NOT NULL,
        `amount` DECIMAL(10,2) DEFAULT 0.00,
        `message` TEXT DEFAULT NULL,
        `payment_status` ENUM('unpaid', 'paid') DEFAULT 'unpaid',
        `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 8. Testimonials Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `testimonials` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `client_name` VARCHAR(100) NOT NULL,
        `location` VARCHAR(100) DEFAULT NULL,
        `service_taken` VARCHAR(100) DEFAULT NULL,
        `feedback` TEXT NOT NULL,
        `rating` INT DEFAULT 5,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Seed Initial Data
    seedDatabase($pdo);
}

function seedDatabase(PDO $pdo) {
    // 1. Seed Users (Admin & Sample Student)
    $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
    $studentPass = password_hash('student123', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`) VALUES 
        (?, ?, ?, ?, 'admin', 'active'),
        (?, ?, ?, ?, 'student', 'active')");
    $stmt->execute([
        'Malik Mehboob (Admin)', 'admin@theastrohealer.com', $adminPass, '+1 (470) 650-5315',
        'Zeeshan Ali', 'student@theastrohealer.com', $studentPass, '+92 300 1234567'
    ]);

    $adminId = 1;
    $studentId = 2;

    // 2. Seed Settings
    $settings = [
        'site_title' => 'The Astro Healer - Moon and Stars Solutions Provider',
        'site_tagline' => 'Ancient Wisdom Meets Modern Insight',
        'contact_email' => 'admin@theastrohealer.com',
        'whatsapp_number' => '+14706505315',
        'stripe_publishable_key' => function_exists('astroEnv') ? astroEnv('STRIPE_PUB_KEY', 'pk_live_configured_via_env') : 'pk_live_configured_via_env',
        'stripe_secret_key' => function_exists('astroEnv') ? astroEnv('STRIPE_SECRET_KEY', 'sk_live_configured_via_env') : 'sk_live_configured_via_env',
        'stripe_currency' => 'usd',
        'currency_symbol' => '$',
        'admin_approval_required' => '1'
    ];

    $stmt = $pdo->prepare("INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
    foreach ($settings as $key => $val) {
        $stmt->execute([$key, $val]);
    }

    // 3. Seed 3 Courses
    $courses = [
        [
            'slug' => 'numerology',
            'title' => 'Comprehensive Numerology Masterclass',
            'subtitle' => 'Decode Life Path, Master Numbers, Destiny & Name Vibrations',
            'description' => 'Unlock the divine secrets encoded in numbers. Learn how your birth date, name numbers, soul urge, and destiny vibration shape career, marriage, health, and life cycles. Includes practical name correction remedies.',
            'curriculum_summary' => 'Introduction to Pythagorean & Chaldean Numerology, Life Path calculations, Master Numbers 11/22/33, Name vibration correction, Lucky mobile & house numbers, Annual transit numbers.',
            'price' => 199.00,
            'duration' => '6 Weeks',
            'total_classes' => 8,
            'level' => 'Beginner to Advanced',
            'image' => 'assets/images/courses/numerology.jpg',
            'badge' => 'Popular'
        ],
        [
            'slug' => 'bnn-astrology',
            'title' => 'Bhrigu Nandi Nadi (BNN) Astrology Course',
            'subtitle' => 'Master the Ancient Classical Predictive Nadi System with Precision',
            'description' => 'The revered Bhrigu Nandi Nadi system allows you to make swift, jaw-dropping astrological predictions without tedious mathematical charts. Learn planet-to-planet aspects, karakatwas, karmic directional connections, and exact life events timing.',
            'curriculum_summary' => 'Core tenets of Bhrigu Nandi Nadi, Significator planets (Jupiter as Jiva, Saturn as Karma), 1-5-9 and 3-7-11 directional combinations, Transit predictions (Gochar), Timing of marriage, job, wealth & foreign travel.',
            'price' => 800.00,
            'duration' => '10 Weeks',
            'total_classes' => 14,
            'level' => 'Intermediate to Master',
            'image' => 'assets/images/courses/bnn-astrology.jpg',
            'badge' => 'Mastery Program'
        ],
        [
            'slug' => 'basic-astrology',
            'title' => 'Basic Vedic Astrology Foundation Course',
            'subtitle' => 'The Complete A-to-Z Guide to 12 Houses, 9 Planets & Kundali Reading',
            'description' => 'Step into the celestial world with clarity. Understand the 12 Rashis (Zodiac Signs), 12 Bhavas (Houses), the 9 Navagrahas (Planets), planetary dignities (Exalted, Debilitated), Aspects (Drishti), and how to read any birth chart from scratch.',
            'curriculum_summary' => 'Cosmology and the 12 Signs, The 12 Houses and their life significations, The 9 Planets nature and strengths, Planetary aspects and conjunctions, Step-by-step birth chart (Lagna Kundali) analysis guide.',
            'price' => 299.00,
            'duration' => '8 Weeks',
            'total_classes' => 10,
            'level' => 'Foundation / Beginner',
            'image' => 'assets/images/courses/basic-astrology.jpg',
            'badge' => 'Foundational'
        ]
    ];

    $courseStmt = $pdo->prepare("INSERT INTO `courses` 
        (`slug`, `title`, `subtitle`, `description`, `curriculum_summary`, `price`, `duration`, `total_classes`, `level`, `image`, `badge`, `status`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published')");

    foreach ($courses as $c) {
        $courseStmt->execute([
            $c['slug'], $c['title'], $c['subtitle'], $c['description'], 
            $c['curriculum_summary'], $c['price'], $c['duration'], 
            $c['total_classes'], $c['level'], $c['image'], $c['badge']
        ]);
    }

    // 4. Seed Lectures / Classes for each course
    // Course 1: Numerology (ID 1)
    $numLectures = [
        [1, 1, 'Class 1: Introduction to Esoteric Numerology', 'Overview of numbers 1 to 9, their planetary rulers, and the cosmic science of vibration.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '48 mins', 1],
        [1, 2, 'Class 2: Calculating Your Life Path & Destiny Number', 'Mastering the core formula to calculate exact life path numbers and understanding primary traits.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '52 mins', 0],
        [1, 3, 'Class 3: Master Numbers 11, 22, and 33', 'The spiritual significance and karmic tests of Master Numbers in a person’s chart.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '45 mins', 0],
        [1, 4, 'Class 4: Name Numerology & Vibration Balancing', 'How spelling adjustments and sound vibrations can alter career and financial luck.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '60 mins', 0],
        [1, 5, 'Class 5: Lucky Phone Numbers, Vehicle & House Numbers', 'Practical real-world application of harmonious numbers for business and residence.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '40 mins', 0],
        [1, 6, 'Class 6: Annual Year Number & Forecasting Cycles', 'Predicting the theme of the current year (Personal Year 1 through 9) with accuracy.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '55 mins', 0]
    ];

    // Course 2: BNN Astrology (ID 2)
    $bnnLectures = [
        [2, 1, 'Class 1: Secrets of Bhrigu Nandi Nadi', 'The foundation principles of Nadi astrology and why it differs from Parashari systems.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '55 mins', 1],
        [2, 2, 'Class 2: Karakattwas of 9 Planets in Nadi', 'Deep dive into Jiva (Jupiter), Karma (Saturn), Mind (Moon), and Ego (Sun) combinations.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '65 mins', 0],
        [2, 3, 'Class 3: Directional Alignment (1-5-9, 3-7-11, 2-12)', 'Connecting planets through trinal and directional aspects without mathematical charts.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '70 mins', 0],
        [2, 4, 'Class 4: Timing Marriage & Career in BNN', 'Identifying planetary intersections that trigger marriage, promotions, and major life changes.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '60 mins', 0],
        [2, 5, 'Class 5: Saturn Transit (Karma Triggering Events)', 'How the transit of Saturn over natal planets activates karma and career milestones.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '58 mins', 0]
    ];

    // Course 3: Basic Astrology (ID 3)
    $basicLectures = [
        [3, 1, 'Class 1: The Cosmic Canvas & 12 Rashis', 'Introduction to the 12 signs of the zodiac, their elements, modalities, and rulers.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '45 mins', 1],
        [3, 2, 'Class 2: The 12 Bhavas (Houses) Decoded', 'Exploring each house from 1st (Ascendant / Self) to 12th (Moksha / Losses).', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '50 mins', 0],
        [3, 3, 'Class 3: The 9 Navagrahas (Planets) & Their Strengths', 'Exaltation, debilitation, own signs, friendly, and inimical planetary relationships.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '55 mins', 0],
        [3, 4, 'Class 4: Planetary Aspects (Drishti) & Conjunctions', 'Special aspects of Mars, Jupiter, and Saturn and how they influence other houses.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '52 mins', 0],
        [3, 5, 'Class 5: Step-by-Step Birth Chart Reading', 'How to synthesize lagna, moon sign, dasha, and transits to answer client queries.', 'https://www.youtube.com/embed/dQw4w9WgXcQ', '60 mins', 0]
    ];

    $allLectures = array_merge($numLectures, $bnnLectures, $basicLectures);
    $lecStmt = $pdo->prepare("INSERT INTO `lectures` 
        (`course_id`, `class_number`, `title`, `description`, `video_type`, `video_source`, `duration`, `is_free_preview`) 
        VALUES (?, ?, ?, ?, 'url', ?, ?, ?)");

    foreach ($allLectures as $l) {
        $lecStmt->execute([$l[0], $l[1], $l[2], $l[3], $l[4], $l[5], $l[6]]);
    }

    // 5. Grant Student enrollment in Numerology for testing
    $pdo->exec("INSERT INTO `enrollments` (`user_id`, `course_id`, `status`, `access_type`, `amount_paid`, `approved_at`) 
        VALUES (2, 1, 'active', 'paid_stripe', 199.00, NOW())");

    // Sample pending enrollment in BNN Astrology
    $pdo->exec("INSERT INTO `enrollments` (`user_id`, `course_id`, `status`, `access_type`, `request_note`) 
        VALUES (2, 2, 'pending', 'manual_request', 'I registered and paid via bank transfer. Please approve access.')");

    // 6. Seed Testimonials
    $testimonials = [
        ['Ayesha Khan', 'Lahore, Pakistan', 'BNN Astrology Course', 'The BNN Astrology course by Malik Mehboob completely transformed how I analyze charts. The directional rules are so crisp and accurate!', 5],
        ['Dr. Tariq Mahmood', 'Karachi, Pakistan', 'Numerology Course', 'I corrected my business name spelling based on the numerology masterclass and noticed an immediate positive shift in clientele.', 5],
        ['Fatima Zahra', 'Islamabad, Pakistan', 'Basic Vedic Astrology', 'The video lectures are structured with incredible care. The student portal is beautiful, easy to navigate, and the class notes are gold.', 5],
        ['Imran Rafiq', 'Dubai, UAE', 'Horoscope & BNN', 'Truly authentic cosmic guidance. No superstitious fear, just grounded spiritual science. Highly recommend to everyone.', 5]
    ];

    $testStmt = $pdo->prepare("INSERT INTO `testimonials` (`client_name`, `location`, `service_taken`, `feedback`, `rating`) VALUES (?, ?, ?, ?, ?)");
    foreach ($testimonials as $t) {
        $testStmt->execute($t);
    }
}
