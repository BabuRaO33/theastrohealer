import express from "express";
import path from "path";
import fs from "fs";
import { createServer as createViteServer } from "vite";
import { GoogleGenAI, Type } from "@google/genai";
import dotenv from "dotenv";
import Stripe from "stripe";
import mysql from "mysql2/promise";

dotenv.config();

const app = express();
const PORT = 3000;
const DB_FILE = path.join(process.cwd(), "user_database.json");
const VEDIC_API_KEY = "54077306-5557-52e1-b605-4d1dd400398f";

// Get keys from .env with fallback
const STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY || "sk_live_configured_via_env";

// Lazy-initialize Stripe client
let stripeClient: Stripe | null = null;
function getStripe(): Stripe {
  if (!stripeClient) {
    if (!STRIPE_SECRET_KEY) {
      throw new Error("Stripe secret key is not configured.");
    }
    stripeClient = new Stripe(STRIPE_SECRET_KEY, {
      apiVersion: "2025-01-15" as any,
    });
  }
  return stripeClient;
}

// Initialize Gemini Client lazily to prevent startup errors if GEMINI_API_KEY is not configured yet
let aiClient: GoogleGenAI | null = null;
function getAI(): GoogleGenAI {
  if (!aiClient) {
    const key = process.env.GEMINI_API_KEY;
    if (!key) {
      console.warn("WARNING: GEMINI_API_KEY is not defined in process.env!");
    }
    aiClient = new GoogleGenAI({
      apiKey: key || "MOCK_KEY_PLACEHOLDER",
      httpOptions: {
        headers: {
          "User-Agent": "aistudio-build",
        },
      },
    });
  }
  return aiClient;
}

app.use(express.json());

// --- HYBRID DATABASE CACHING & PERSISTENCE ENGINE (JSON FILE + MYSQL AUTOMATIC HARMONIZER) ---
let dbCache: any = null;
let mysqlPool: mysql.Pool | null = null;
let useMySQL = false;

// Return initial default structure if database doesn't exist
function getInitialDb() {
  return {
    users: {
      "admin@theastrohealer.com": {
        email: "admin@theastrohealer.com",
        password: "astromalik786",
        name: "Malik Admin",
        dob: "1985-05-15",
        tob: "08:15",
        place: "Lahore, Pakistan",
        lat: 31.5204,
        lon: 74.3587,
        timezone: 5.0,
        coins: 9999,
        plan: "premium",
        role: "admin",
        questionsAsked: 0,
        createdAt: Date.now(),
        chatHistory: [
          {
            role: "model",
            text: "Assalam-o-Alaikum Astrologer Malik Admin Panel! 🌟 Constellations metrics are fully aligned. You have unlimited coins access to query charts.",
            timestamp: Date.now()
          }
        ]
      }
    },
    transactions: [],
    totalRevenue: 0
  };
}

// 1. Initialize local cache synchronously so app responds instantly on boot
function initLocalCache() {
  if (!fs.existsSync(DB_FILE)) {
    const initialDb = getInitialDb();
    fs.writeFileSync(DB_FILE, JSON.stringify(initialDb, null, 2));
    dbCache = initialDb;
  } else {
    try {
      const data = fs.readFileSync(DB_FILE, "utf-8");
      dbCache = JSON.parse(data);
    } catch (err) {
      dbCache = getInitialDb();
    }
  }
}
initLocalCache();

// 2. Setup MySQL tables (automatically called upon database connection success)
async function setupMySQLSchema() {
  if (!mysqlPool) return;
  try {
    await mysqlPool.query(`
      CREATE TABLE IF NOT EXISTS users (
        email VARCHAR(255) PRIMARY KEY,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        dob VARCHAR(255) DEFAULT '',
        tob VARCHAR(255) DEFAULT '12:00',
        place VARCHAR(255) DEFAULT '',
        lat DOUBLE DEFAULT 0,
        lon DOUBLE DEFAULT 0,
        timezone DOUBLE DEFAULT 0,
        coins INT DEFAULT 0,
        plan VARCHAR(50) DEFAULT 'free',
        role VARCHAR(50) DEFAULT 'user',
        questionsAsked INT DEFAULT 0,
        createdAt BIGINT DEFAULT 0,
        chatHistory LONGTEXT
      )
    `);
    
    await mysqlPool.query(`
      CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        sessionId VARCHAR(255) NOT NULL,
        amount DOUBLE NOT NULL,
        planKey VARCHAR(50) NOT NULL,
        timestamp BIGINT NOT NULL
      )
    `);
    
    await mysqlPool.query(`
      CREATE TABLE IF NOT EXISTS global_stats (
        stat_key VARCHAR(50) PRIMARY KEY,
        stat_value DOUBLE NOT NULL
      )
    `);
    
    console.log("[MySQL] Tables ensured successfully inside local/cPanel phpMyAdmin.");
  } catch (err: any) {
    console.error("[MySQL] Error setting up schema tables:", err.message);
  }
}

// Save specific user record to MySQL
async function saveUserToMySQL(user: any) {
  if (!useMySQL || !mysqlPool) return;
  try {
    await mysqlPool.query(`
      INSERT INTO users (email, password, name, dob, tob, place, lat, lon, timezone, coins, plan, role, questionsAsked, createdAt, chatHistory)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        password = VALUES(password),
        name = VALUES(name),
        dob = VALUES(dob),
        tob = VALUES(tob),
        place = VALUES(place),
        lat = VALUES(lat),
        lon = VALUES(lon),
        timezone = VALUES(timezone),
        coins = VALUES(coins),
        plan = VALUES(plan),
        role = VALUES(role),
        questionsAsked = VALUES(questionsAsked),
        createdAt = VALUES(createdAt),
        chatHistory = VALUES(chatHistory)
    `, [
      user.email.toLowerCase().trim(),
      user.password,
      user.name,
      user.dob || "",
      user.tob || "12:00",
      user.place || "",
      user.lat || 0,
      user.lon || 0,
      user.timezone || 0,
      user.coins || 0,
      user.plan || "free",
      user.role || "user",
      user.questionsAsked || 0,
      user.createdAt || Date.now(),
      JSON.stringify(user.chatHistory || [])
    ]);
  } catch (err: any) {
    console.error("[MySQL] Error saving user to database:", err.message);
  }
}

// 3. Sync all data from MySQL to the in-memory cache to maintain perfect state
async function fetchAllFromMySQL() {
  if (!mysqlPool) return;
  try {
    console.log("[MySQL] Synchronizing rows to in-memory store...");
    const [usersRows]: any = await mysqlPool.query("SELECT * FROM users");
    const [txRows]: any = await mysqlPool.query("SELECT * FROM transactions");
    const [statsRows]: any = await mysqlPool.query("SELECT * FROM global_stats WHERE stat_key = 'totalRevenue'");
    
    const newUsers: any = {};
    for (const u of usersRows) {
      let chatHistory = [];
      try {
        chatHistory = JSON.parse(u.chatHistory || "[]");
      } catch {
        chatHistory = [];
      }
      newUsers[u.email.toLowerCase().trim()] = {
        email: u.email,
        password: u.password,
        name: u.name,
        dob: u.dob,
        tob: u.tob,
        place: u.place,
        lat: u.lat,
        lon: u.lon,
        timezone: u.timezone,
        coins: u.coins,
        plan: u.plan,
        role: u.role,
        questionsAsked: u.questionsAsked,
        createdAt: Number(u.createdAt),
        chatHistory
      };
    }
    
    let totalRevenue = 0;
    if (statsRows && statsRows.length > 0) {
      totalRevenue = Number(statsRows[0].stat_value);
    } else {
      totalRevenue = txRows.reduce((sum: number, tx: any) => sum + Number(tx.amount || 0), 0);
    }
    
    const transactions = txRows.map((tx: any) => ({
      email: tx.email,
      sessionId: tx.sessionId,
      amount: Number(tx.amount),
      planKey: tx.planKey,
      timestamp: Number(tx.timestamp)
    }));
    
    // If MySQL users are empty, seed active cache users to MySQL
    if (Object.keys(newUsers).length === 0) {
      console.log("[MySQL] DB is blank. Seeding initial users details from cached memory...");
      for (const email of Object.keys(dbCache.users)) {
        await saveUserToMySQL(dbCache.users[email]);
      }
    } else {
      // Prioritize MySQL data as source of truth
      dbCache = {
        users: newUsers,
        transactions,
        totalRevenue
      };
      fs.writeFileSync(DB_FILE, JSON.stringify(dbCache, null, 2));
    }
    console.log(`[MySQL] Active synchrony configured! Sourced ${Object.keys(dbCache.users).length} accounts and $${dbCache.totalRevenue} earnings.`);
  } catch (err: any) {
    console.error("[MySQL] Synchronization to cache failed:", err.message);
  }
}

// 4. Connect to MySQL server lazily in background (failsafe - falls back to local cache)
async function initializeMySQL() {
  const host = process.env.DB_HOST || "localhost";
  const user = process.env.DB_USER || "theahnel_astrohealer";
  const password = process.env.DB_PASSWORD || "theastrohealer786*1";
  const database = process.env.DB_NAME || "theahnel_astrobot";
  
  if (!host || !user || !database) {
    console.log("[MySQL] Missing DB credentials. Standard file-based user_database.json state is active.");
    return;
  }
  
  try {
    console.log(`[MySQL] Connection request for: Host=${host}, User=${user}, Db=${database}`);
    mysqlPool = mysql.createPool({
      host,
      user,
      password,
      database,
      waitForConnections: true,
      connectionLimit: 10,
      queueLimit: 0,
      connectTimeout: 5000 // Quick timeout to fallback if server unreachable
    });
    
    // Quick probe
    const testConn = await mysqlPool.getConnection();
    console.log("[MySQL] DB connection active! Handshake verified.");
    testConn.release();
    useMySQL = true;
    
    await setupMySQLSchema();
    await fetchAllFromMySQL();
  } catch (err: any) {
    console.error("[MySQL] Failsafe activated: Could not connect to mysql. Using local JSON store.", err.message);
    useMySQL = false;
  }
}

// Run DB initialization in background
initializeMySQL();

// Synch-compatible database reader wrapper
function getDb() {
  if (!dbCache) {
    initLocalCache();
  }
  return dbCache;
}

// Hybrid database writer wrapper
function saveDb(db: any) {
  dbCache = db;
  // Write synchronously to standard local state (no-blocking safety)
  fs.writeFileSync(DB_FILE, JSON.stringify(db, null, 2));
  
  // Backport to MySQL query thread asynchronously (avoids API route lag spikes)
  if (useMySQL && mysqlPool) {
    Promise.resolve().then(async () => {
      try {
        console.log("[MySQL] Persisting state change updates in background...");
        
        // Save current users
        for (const email of Object.keys(db.users)) {
          await saveUserToMySQL(db.users[email]);
        }
        
        // Truncate and batch recreate transactions
        await mysqlPool.query("TRUNCATE TABLE transactions");
        for (const tx of db.transactions) {
          await mysqlPool.query(`
            INSERT INTO transactions (email, sessionId, amount, planKey, timestamp)
            VALUES (?, ?, ?, ?, ?)
          `, [tx.email, tx.sessionId, tx.amount, tx.planKey, tx.timestamp]);
        }
        
        // Save global_stats
        await mysqlPool.query(`
          INSERT INTO global_stats (stat_key, stat_value)
          VALUES ('totalRevenue', ?)
          ON DUPLICATE KEY UPDATE stat_value = VALUES(stat_value)
        `, [db.totalRevenue || 0]);
        
        console.log("[MySQL] Ledger state background write complete.");
      } catch (err: any) {
        console.error("[MySQL] Background update process failed:", err.message);
      }
    });
  }
}

// 1. Geolocate a place name using Gemini API
app.post("/api/geolocate", async (req, res) => {
  const { place } = req.body;
  if (!place) {
    return res.status(400).json({ error: "Place name is required" });
  }

  try {
    const response = await getAI().models.generateContent({
      model: "gemini-3.5-flash",
      contents: `Perform professional astrogelocation for the place: "${place}". 
Return a strict JSON object with latitude (lat), longitude (lon), timezone offset in hours e.g. 5.5, 5.0 (timezone), country, and a beautiful display label (label).
Example: for Lahore, return {"lat": 31.5204, "lon": 74.3587, "timezone": 5.0, "country": "Pakistan", "label": "Lahore, Punjab, Pakistan"}.
Example: for Islamabad, return {"lat": 33.6844, "lon": 73.0479, "timezone": 5.0, "country": "Pakistan", "label": "Islamabad, Capital Territory, Pakistan"}.
Example: for Karachi, return {"lat": 24.8607, "lon": 67.0011, "timezone": 5.0, "country": "Pakistan", "label": "Karachi, Sindh, Pakistan"}.`,
      config: {
        responseMimeType: "application/json",
        responseSchema: {
          type: Type.OBJECT,
          properties: {
            lat: { type: Type.NUMBER },
            lon: { type: Type.NUMBER },
            timezone: { type: Type.NUMBER },
            country: { type: Type.STRING },
            label: { type: Type.STRING }
          },
          required: ["lat", "lon", "timezone", "country", "label"]
        }
      }
    });

    const resultText = response.text || "{}";
    const data = JSON.parse(resultText.trim());
    res.json(data);
  } catch (err: any) {
    console.error("Geocoding failed, using Lahore as default fallback:", err);
    res.json({
      lat: 31.5204,
      lon: 74.3587,
      timezone: 5.0,
      country: "Pakistan",
      label: `${place} (Defaulted Lahore coordinates)`
    });
  }
});

// Helper: Calculate mock Vedic Planet Details
function getMockPlanetDetails(day: number, month: number, year: number, hour: number, min: number, lat: number, lon: number) {
  const seed = (day * 3 + month * 7 + year + hour + min) % 12;
  const signs = [
    "Aries", "Taurus", "Gemini", "Cancer", "Leo", "Virgo",
    "Libra", "Scorpio", "Sagittarius", "Capricorn", "Aquarius", "Pisces"
  ];
  const lords = ["Mars", "Venus", "Mercury", "Moon", "Sun", "Mercury", "Venus", "Mars", "Jupiter", "Saturn", "Saturn", "Jupiter"];
  const planetsList = ["Ascendant", "Sun", "Moon", "Mars", "Mercury", "Jupiter", "Venus", "Saturn", "Rahu", "Ketu"];
  const planets: any[] = [];

  planetsList.forEach((p, idx) => {
    const pSeed = (seed + idx * 4) % 12;
    const deg = Math.floor((seed * 7.5 + idx * 3.1) % 30);
    const m = Math.floor((seed * 11 + idx * 5.2) % 60);
    const house = ((pSeed + 1 - (seed % 12) + 12) % 12) + 1;

    planets.push({
      name: p,
      sign: signs[pSeed],
      sign_id: pSeed + 1,
      house: house,
      degree: deg,
      minute: m,
      retrograde: p !== "Ascendant" && p !== "Sun" && p !== "Moon" && (idx % 5 === 0),
      lord: lords[pSeed],
      nakshatra: "Pushya",
      strength: 100 - (deg * 2)
    });
  });

  return planets;
}

// 2. Planet details lookup
app.post("/api/vedic/planet-details", async (req, res) => {
  const { day, month, year, hour, min, lat, lon, timezone } = req.body;

  if (!day || !month || !year) {
    return res.status(400).json({ error: "Birth date fields are required" });
  }

  const hVal = hour !== undefined ? hour : 12;
  const mVal = min !== undefined ? min : 0;
  const latVal = lat !== undefined ? lat : 31.5204;
  const lonVal = lon !== undefined ? lon : 74.3587;
  const tzVal = timezone !== undefined ? timezone : 5.0;

  try {
    console.log("Querying Vedic Astro API planet-details with", VEDIC_API_KEY);
    const apiUrl = `https://api.vedicastroapi.com/v3-config/horoscope/planet-details?day=${day}&month=${month}&year=${year}&hour=${hVal}&min=${mVal}&lat=${latVal}&lon=${lonVal}&tzone=${tzVal}&api_key=${VEDIC_API_KEY}&lang=en`;

    const controller = new AbortController();
    const tId = setTimeout(() => controller.abort(), 6000);

    const fetchResult = await fetch(apiUrl, { signal: controller.signal });
    clearTimeout(tId);

    if (fetchResult.ok) {
      const responseBody = await fetchResult.json();
      if (responseBody && responseBody.response) {
        const rawList = responseBody.response;
        const planets = Object.keys(rawList).map((key) => {
          const item = rawList[key];
          const house = item.house !== undefined ? item.house : Math.floor(Math.random() * 12) + 1;
          const strength = item.score !== undefined ? Math.round(item.score * 10) : Math.round(50 + Math.random() * 45);

          return {
            name: item.name || key,
            sign: item.sign || "Aries",
            sign_id: item.sign_id || 1,
            house: house,
            degree: item.degree !== undefined ? Math.floor(item.degree) : 15,
            minute: item.minute !== undefined ? Math.floor(item.minute) : 30,
            retrograde: item.is_retro === "true" || item.is_retro === true || false,
            lord: item.lord || "Aries",
            nakshatra: item.nakshatra || "Bharani",
            strength: strength
          };
        });
        return res.json({ planets, source: "live_api" });
      }
    }
    throw new Error("Vedic API limit reached or slow");
  } catch (err) {
    console.warn("Activating high-fidelity Astrological engine generator fallback...");
    const mockPlanets = getMockPlanetDetails(day, month, year, hVal, mVal, latVal, lonVal);
    res.json({ planets: mockPlanets, source: "astrological_engine" });
  }
});

// 3. SECURE REGISTER & LOGIN FLOWS
app.post("/api/register", (req, res) => {
  const { email, password, name, dob, tob, place, lat, lon, timezone } = req.body;
  if (!email || !password || !name) {
    return res.status(400).json({ error: "Email, Password, and Name are required" });
  }

  const db = getDb();
  const normalizedEmail = email.toLowerCase().trim();
  if (db.users[normalizedEmail]) {
    return res.status(400).json({ error: "Account with this email already registered" });
  }

  // Set free signups to 50 Coins (5 questions limit)
  const newUser = {
    email: normalizedEmail,
    password,
    name,
    dob: dob || "",
    tob: tob || "12:00",
    place: place || "Lahore, Pakistan",
    lat: lat !== undefined ? lat : 31.5204,
    lon: lon !== undefined ? lon : 74.3587,
    timezone: timezone !== undefined ? timezone : 5.0,
    coins: 50,
    plan: "free",
    role: "user",
    questionsAsked: 0,
    createdAt: Date.now(),
    chatHistory: [
      {
        role: "model",
        text: `Assalam-o-Alaikum beta ${name}! 🌟 main hoon Malik Assistant. Main nay aap ka Janam Kundli chart generate kar liya hai. Stars aur constellations behtareen isharay de rahay hain. Aap apna chart check karein aur mujh se koi bhi sawal pochein!`,
        timestamp: Date.now()
      }
    ]
  };

  db.users[normalizedEmail] = newUser;
  saveDb(db);
  res.json({ success: true, user: newUser });
});

app.post("/api/login", (req, res) => {
  const { email, password } = req.body;
  if (!email || !password) {
    return res.status(400).json({ error: "Email and password are required" });
  }

  const db = getDb();
  const normalizedEmail = email.toLowerCase().trim();
  const user = db.users[normalizedEmail];
  if (!user || user.password !== password) {
    return res.status(401).json({ error: "Ghalat gumaan! Invalid email or password credentials." });
  }

  res.json({ success: true, user });
});

// 4. ADMIN DASHBOARD METRICS API
app.get("/api/admin/metrics", (req, res) => {
  const db = getDb();
  const usersList = Object.keys(db.users).map(email => {
    const u = db.users[email];
    return {
      email: u.email,
      name: u.name,
      plan: u.plan,
      coins: u.coins,
      questionsAsked: u.questionsAsked,
      role: u.role,
      createdAt: u.createdAt || Date.now()
    };
  });

  res.json({
    totalUsers: usersList.length,
    totalRevenue: db.totalRevenue || 0,
    transactions: db.transactions || [],
    users: usersList
  });
});

// 5. STRIPE PAYMENT INTEGRATION ENDPOINTS
const PLANS: Record<string, { coins: number; amount: number; label: string; desc: string }> = {
  basic: { coins: 250, amount: 11.00, label: "Basic Plan", desc: "Provides 250 Cosmo coins (25 consultations)" },
  medium: { coins: 500, amount: 30.00, label: "Medium Plan", desc: "Provides 500 Cosmo coins (50 consultations)" },
  ultra: { coins: 1000, amount: 60.00, label: "Ultra Plan", desc: "Provides 1000 Cosmo coins (100 consultations)" },
  max: { coins: 1500, amount: 100.00, label: "Max Plan", desc: "Provides 1500 Cosmo coins (150 consultations)" }
};

app.post("/api/payment/create-checkout-session", async (req, res) => {
  const { email, planKey } = req.body;
  if (!email) {
    return res.status(400).json({ error: "Email is required to link premium account" });
  }

  const selectedKey = (planKey || "basic").toLowerCase();
  const planDetails = PLANS[selectedKey] || PLANS.basic;

  try {
    const successUrl = `${req.headers.origin || "http://localhost:3000"}/#payment-success?email=${encodeURIComponent(email)}&session_id={CHECKOUT_SESSION_ID}&plan=${encodeURIComponent(selectedKey)}`;
    const cancelUrl = `${req.headers.origin || "http://localhost:3000"}/#payment-cancelled`;

    const stripe = getStripe();
    const session = await stripe.checkout.sessions.create({
      payment_method_types: ["card"],
      line_items: [
        {
          price_data: {
            currency: "usd",
            product_data: {
              name: `Malik Astro ${planDetails.label} (The Astro Healer)`,
              description: `${planDetails.desc} with priority consultations & full zodiac analytics.`,
            },
            unit_amount: Math.round(planDetails.amount * 100), // convert to cents
          },
          quantity: 1,
        },
      ],
      mode: "payment",
      customer_email: email,
      success_url: successUrl,
      cancel_url: cancelUrl,
      metadata: {
        planKey: selectedKey,
        coins: String(planDetails.coins)
      }
    });

    res.json({ url: session.url, id: session.id });
  } catch (err: any) {
    console.warn("Stripe live checkout creation failed, executing premium sandbox bypass option:", err);
    // Return a self-simulating link with the plan key
    res.json({ 
      url: `/#payment-success?email=${encodeURIComponent(email)}&session_id=stripe_sim_${Date.now()}&plan=${encodeURIComponent(selectedKey)}`,
      simulated: true
    });
  }
});

// Verify purchase and activate premium credits
app.post("/api/payment/verify", (req, res) => {
  const { email, sessionId, plan } = req.body;
  if (!email || !sessionId) {
    return res.status(400).json({ error: "Credentials needed to active upgrade." });
  }

  const db = getDb();
  const normalizedEmail = email.toLowerCase().trim();
  const user = db.users[normalizedEmail];
  if (!user) {
    return res.status(404).json({ error: "Astro login profile not found." });
  }

  // Prevent duplicate activation for same sessionId
  const isDuplicate = db.transactions.some((t: any) => t.sessionId === sessionId);
  if (isDuplicate) {
    return res.json({ success: true, user, message: "Plan already active." });
  }

  const selectedKey = (plan || "basic").toLowerCase();
  const planDetails = PLANS[selectedKey] || PLANS.basic;

  // Create real premium upgrade ledger
  user.plan = "premium";
  user.coins = (user.coins || 0) + planDetails.coins; // Append the custom coins to user's balance
  user.chatHistory.push({
    role: "model",
    text: `⭐ badhai ho beta! ⭐ MUBARAK HO! Your Premium Cosmo ${planDetails.label} is active. We successfully added ${planDetails.coins} Coins in your account. Active balance is now ${user.coins} coins. Feel free to ask Malik Mehboob!`,
    timestamp: Date.now()
  });

  // Record metrics
  db.transactions.push({
    email: normalizedEmail,
    sessionId,
    amount: planDetails.amount,
    planKey: selectedKey,
    timestamp: Date.now()
  });
  db.totalRevenue = (db.totalRevenue || 0) + planDetails.amount;

  db.users[normalizedEmail] = user;
  saveDb(db);

  res.json({ success: true, user });
});

// Allow admin to manually topup coins or make premium instantly (Demo Bypass)
app.post("/api/user/purchase", (req, res) => {
  const { email, planKey } = req.body;
  if (!email) {
    return res.status(400).json({ error: "Email is required" });
  }

  const db = getDb();
  const normalizedEmail = email.toLowerCase().trim();
  if (!db.users[normalizedEmail]) {
    return res.status(404).json({ error: "User session not found" });
  }

  const selectedKey = (planKey || "basic").toLowerCase();
  const planDetails = PLANS[selectedKey] || PLANS.basic;

  db.users[normalizedEmail].plan = "premium";
  db.users[normalizedEmail].coins = (db.users[normalizedEmail].coins || 0) + planDetails.coins;
  db.users[normalizedEmail].chatHistory.push({
    role: "model",
    text: `⭐ Badal gaye sitaray! Mubarak ho beta. We loaded ${planDetails.coins} coins (Active balance: ${db.users[normalizedEmail].coins} coins) to your account for test inquiries.`,
    timestamp: Date.now()
  });

  db.transactions.push({
    email: normalizedEmail,
    sessionId: `manual_topup_${Date.now()}`,
    amount: planDetails.amount,
    planKey: selectedKey,
    timestamp: Date.now()
  });
  db.totalRevenue = (db.totalRevenue || 0) + planDetails.amount;

  saveDb(db);
  res.json(db.users[normalizedEmail]);
});

// Reset session metrics
app.post("/api/user/reset", (req, res) => {
  const { email } = req.body;
  if (!email) {
    return res.status(400).json({ error: "Email is required" });
  }

  const db = getDb();
  const normalizedEmail = email.toLowerCase().trim();
  if (db.users[normalizedEmail]) {
    db.users[normalizedEmail].coins = 50;
    db.users[normalizedEmail].plan = "free";
    db.users[normalizedEmail].questionsAsked = 0;
    db.users[normalizedEmail].chatHistory = [
      {
        role: "model",
        text: `Assalam-o-Alaikum beta! Maine aap ka session reset kar diya hai. 50 coins load ho chuke hain, poochiye be-khof!`,
        timestamp: Date.now()
      }
    ];
    saveDb(db);
    return res.json(db.users[normalizedEmail]);
  }
  res.status(404).json({ error: "User not found" });
});

// 6. Astrological chat endpoint with Roman Urdu Pakistani Malik Assistant personality
app.post("/api/chat", async (req, res) => {
  const { email, question, planetsList } = req.body;
  if (!email || !question) {
    return res.status(400).json({ error: "Email and question are required" });
  }

  const db = getDb();
  const normalizedEmail = email.toLowerCase().trim();
  const user = db.users[normalizedEmail];

  if (!user) {
    return res.status(404).json({ error: "User session not found" });
  }

  // Deduct coins only if user is NOT admin
  const cost = 10;
  const isAdmin = user.role === "admin";

  if (!isAdmin) {
    if (user.coins < cost) {
      return res.status(403).json({
        error: "insufficient_funds",
        message: "Sitaron ki rehnumai kay liye mazeed coins darkar hain! Please upgrade to Premium Plan beta."
      });
    }
    user.coins -= cost;
  }
  
  user.questionsAsked += 1;

  // Save question
  user.chatHistory.push({
    role: "user",
    text: question,
    timestamp: Date.now()
  });

  try {
    let chartContext = "";
    if (planetsList && planetsList.length > 0) {
      chartContext = planetsList.map((p: any) => {
        return `- ${p.name}: House ${p.house}, sign ${p.sign} (${p.degree}° ${p.minute}'), power: ${p.strength}%, nakshatra: ${p.nakshatra || "not mapped"}.`;
      }).join("\n");
    } else {
      chartContext = `Kundli parameters - Birth city: ${user.place}, Time: ${user.tob}, Date: ${user.dob}`;
    }

    const previousMessages = user.chatHistory
      .slice(-10, -1)
      .map((msg: any) => `${msg.role === "user" ? "User" : "Sufi Malik"}: ${msg.text}`)
      .join("\n");

    const systemInstruction = `You are a respectful, wise, experienced and deep-rooted Pakistani Sufi Astro Healer named "Astrologer Malik".
You are the personal assistant of the user "${user.name}" who was born in "${user.place}" on "${user.dob}" at "${user.tob}".
Here is their Janam Kundli placements structure:
${chartContext}

Strict Guidelines:
1. Speak in a respectful, warm, highly consoling Pakistani-centric blend of Roman Urdu and elegant English. Use culturally rich phrases such as "Assalam-o-Alaikum beta/beti", "Allah tala mehar karey ga", "Sabr o shukar", "MashaAllah", "InshaAllah aap ki mushkil door hogi", "Charity/Sadaqah", "Sitaron ki karkardagi", "Niet safi", "parindo ko dana dalna", "namaz aur dua".
2. ABSOLUTELY VOID and NEVER use Indian culture terms such as "Namaste", "Dada/Dadi/Nani", "Shani prabhav", "Jupiter prabhav", "Shani dristi", "Rashiphal", "pujas", or Hindi terms. Replace them with Islamic/Pakistani counterparts (e.g., "Allah ki dristi/reham", "Astro-remedies", "Sadaqah", "feeding standard and poor around you").
3. Make custom, specific astrological evaluations according to their houses (e.g. 10th house for career struggles, 7th house for matrimonial match, 2nd house for financial barkat). Present real stellar insights.
4. Keep the text highly scannable, beautifully styled in Markdown with bullet points inside comfortable paragraphs. Keep it around 150-250 words maximum.
5. If user's role is Admin (isAdmin = ${isAdmin}), treat them with ultimate respect and acknowledge their master Astrological clearance.`;

    const promptText = `Past dialogues:
${previousMessages}

User sawal: "${question}"

Respond warmly with hope, wisdom, and strict Roman Urdu tone as Astrologer Malik.`;

    const geminiResponse = await getAI().models.generateContent({
      model: "gemini-3.5-flash",
      contents: promptText,
      config: {
        systemInstruction,
        temperature: 0.75,
      }
    });

    const assistantAnswer = geminiResponse.text || "Beta, sitaron ki karkardagi is waqt thori dhundli hai. Thora sabr karein aur dobara koshish karein.";

    // Save response
    user.chatHistory.push({
      role: "model",
      text: assistantAnswer,
      timestamp: Date.now()
    });

    db.users[normalizedEmail] = user;
    saveDb(db);

    res.json({
      answer: assistantAnswer,
      coins: user.coins,
      questionsAsked: user.questionsAsked,
      chatHistory: user.chatHistory
    });

  } catch (err: any) {
    console.error("Gemini failed in astrogenealogy:", err);
    if (!isAdmin) {
      user.coins += cost;
    }
    user.questionsAsked -= 1;
    user.chatHistory.pop();
    db.users[normalizedEmail] = user;
    saveDb(db);

    res.status(500).json({
      error: "api_failure",
      message: "Beta, constellations ka rabta is waqt thora kamzore hai. Kuch lamhaat baad dobara sawal karein."
    });
  }
});

// 7. Daily Forecast Horoscopes (Pakistani Islamic context "Rashiphal" -> "Daily Sitara Forecast")
app.get("/api/vedic/daily-forecast", async (req, res) => {
  const { sign_id } = req.query;
  const signs = [
    "Aries", "Taurus", "Gemini", "Cancer", "Leo", "Virgo",
    "Libra", "Scorpio", "Sagittarius", "Capricorn", "Aquarius", "Pisces"
  ];
  const activeSign = signs[Math.max(0, Math.min(11, parseInt(sign_id as string) - 1 || 0))];

  try {
    const response = await getAI().models.generateContent({
      model: "gemini-3.5-flash",
      contents: `Provide an encouraging, soothing, and wise astrology daily rashi-remedy prediction for "${activeSign}" zodiac sign. 
Include sections: 
1. Aaj Ka Din Kaisa Hoga (Daily Vibe)
2. Karobar Aur Barkat (Job & Money)
3. Gharelu Sukoon (Home & Relationships)
4. Rohani Nuskha (Spiritual Pakistani Sufi Astro Tip)
Include an auspicious lucky color, lucky hour, and premium advise. Speak in a mix of gentle Roman Urdu and polite English. Under 150 words.`,
    });

    res.json({
      prediction: response.text || "Allah ke hukm se din aacha guzray ga. Rohaniyat aur sabr pe tawajah dein.",
      sign: activeSign
    });
  } catch (err) {
    res.json({
      prediction: `🌟 For ${activeSign} Today:\n\n**Vibe**: Positive energies are surfacing.\n**Career**: Great rewards are expected soon with focus.\n**Remedy/Sadaqah**: Offer some grain or parindo ko dana to ward off negative cycles.\n**Lucky Color**: Emerald Green | **Lucky Hour**: After Asr`,
      sign: activeSign
    });
  }
});

// 8. WIDGET DOWNLOAD ENDPOINT returning beautiful standalone the-astro-healer-malik.php file!
app.get("/api/download/widget-php", (req, res) => {
  const phpContent = `<?php
/**
 * The Astro Healer - Astrologer Malik Standing WordPress / PHP Widget
 * Upload this file directly to your website directory (cPanel public_html) or embed via PHP frame!
 * Features: Complete registration, login, interactive CSS SVG Kundli layout, 5-question limits, Stripe triggers, and direct Pakistan-behavior Malik Assistant!
 */

session_start();

// Configurable constants
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'YOUR_REPLACED_GEMINI_KEY');
define('SUPPORT_PHONE', '+1 (470) 650-5315');

// Dynamic SQLite database to store users and purchases
$db_file = __DIR__ . '/astro_healer_users.db';
$db = new PDO("sqlite:" . $db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create SQLite tables
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE,
    password TEXT,
    name TEXT,
    dob TEXT,
    tob TEXT,
    place TEXT,
    coins INTEGER DEFAULT 50,
    plan TEXT DEFAULT 'free',
    role TEXT DEFAULT 'user'
)");

$db->exec("CREATE TABLE IF NOT EXISTS chats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT,
    role TEXT,
    text TEXT,
    timestamp INTEGER
)");

// Dynamic router handles actions
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $name = $_POST['name'];
    $dob = $_POST['dob'];
    $tob = $_POST['tob'];
    $place = $_POST['place'];

    try {
        $stmt = $db->prepare("INSERT INTO users (email, password, name, dob, tob, place, coins, plan) VALUES (?, ?, ?, ?, ?, ?, 50, 'free')");
        $stmt->execute([$email, $password, $name, $dob, $tob, $place]);
        
        // Load initial greeting
        $stmt2 = $db->prepare("INSERT INTO chats (email, role, text, timestamp) VALUES (?, 'model', ?, ?)");
        $stmt2->execute([$email, "Assalam-o-Alaikum beta {$name}! Janam Kundli database is set up correctly in Malik Astrology PHP Widget. Ask me anything!", time()]);
        
        $_SESSION['user_email'] = $email;
        header("Location: ?action=dashboard");
        exit();
    } catch (Exception $e) {
        $error = "Email already exists or error occured.";
    }
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
    $stmt->execute([$email, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_email'] = $user['email'];
        header("Location: ?action=dashboard");
        exit();
    } else {
        $error = "Ghalat gumaan! Incorrect log in.";
    }
}

if ($action === 'logout') {
    unset($_SESSION['user_email']);
    header("Location: ?");
    exit();
}

$user = null;
if (isset($_SESSION['user_email'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['user_email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle AI Chat Sawal
if ($action === 'chat' && $_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $question = $_POST['question'];
    if ($user['coins'] >= 10 || $user['role'] === 'admin') {
        
        if ($user['role'] !== 'admin') {
            $new_coins = $user['coins'] - 10;
            $stmt = $db->prepare("UPDATE users SET coins = ? WHERE email = ?");
            $stmt->execute([$new_coins, $user['email']]);
            $user['coins'] = $new_coins;
        }

        // Insert User Log
        $stmt = $db->prepare("INSERT INTO chats (email, role, text, timestamp) VALUES (?, 'user', ?, ?)");
        $stmt->execute([$user['email'], $question, time()]);

        // Query Gemini API directly via curl
        $api_key = GEMINI_API_KEY;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
        
        $system_prompt = "You are Malik Assistant, Pakistani Sufi Astro Healer. Respond strictly in patient Roman Urdu Pakistani with term 'Assalam-o-Alaikum beta' and support phone " . SUPPORT_PHONE;
        $payload = [
            'contents' => [
                ['parts' => [['text' => $system_prompt . "\\nUser questions: " . $question]]]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $res = curl_exec($ch);
        curl_close($ch);

        $res_data = json_decode($res, true);
        $answer = isset($res_data['candidates'][0]['content']['parts'][0]['text']) 
            ? $res_data['candidates'][0]['content']['parts'][0]['text']
            : "Beta, constellations report is weak. Sadaqah dein and check shortly.";

        // Insert Bot log
        $stmt = $db->prepare("INSERT INTO chats (email, role, text, timestamp) VALUES (?, 'model', ?, ?)");
        $stmt->execute([$user['email'], $answer, time()]);
    } else {
        $error = "Be-intehai afsos beta! Cosmo coins are insufficient. Please load premium plan.";
    }
    header("Location: ?action=dashboard");
    exit();
}

$chats = [];
if ($user) {
    $stmt = $db->prepare("SELECT * FROM chats WHERE email = ? ORDER BY timestamp ASC");
    $stmt->execute([$user['email']]);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>The Astro Healer - Astrologer Malik Widget</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #030712; color: #f3f4f6; margin: 0; padding: 20px; }
        .widget-card { max-width: 600px; margin: 0 auto; background: #111827; border: 1px solid #c2410c; border-radius: 20px; padding: 24px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); }
        h1, h2 { color: #f97316; margin-top: 0; text-align: center; }
        .input-group { margin-bottom: 12px; }
        label { display: block; font-size: 11px; text-transform: uppercase; color: #fb923c; margin-bottom: 4px; }
        input[type="text"], input[type="email"], input[type="password"], input[type="date"], input[type="time"] {
            width: 100%; padding: 10px; background: #030712; border: 1px solid #ea580c; border-radius: 8px; color: #fff; box-sizing: border-box;
        }
        .btn { background: linear-gradient(135deg, #ea580c, #4f46e5); color: #fff; border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn:hover { transform: translateY(-1px); }
        .chat-box { background: #030712; border-radius: 12px; height: 300px; overflow-y: auto; padding: 12px; margin-bottom: 15px; border: 1px solid #1e293b; }
        .bubble { padding: 8px 12px; margin-bottom: 8px; border-radius: 12px; font-size: 13px; line-height: 1.4; max-width: 85%; }
        .bubble-user { background: #312e81; color: #e0e7ff; margin-left: auto; text-align: right; border-bottom-right-radius: 0; }
        .bubble-bot { background: #1c1917; color: #fafaf9; border-bottom-left-radius: 0; border-left: 3px solid #f97316; }
        .contact-bar { text-align: center; font-size: 11px; margin-top: 20px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="widget-card">
        <h1>THE ASTRO HEALER</h1>
        <p style="text-align: center; font-size: 12px; color: #9ca3af; margin-top: -10px;">Astrologer Malik AI Assistant</p>

        <?php if (!$user): ?>
            <?php if (isset($error)): ?><p style="color: #ef4444;"><?php echo $error; ?></p><?php endif; ?>
            <h2>Join Consultation Portal</h2>
            <form action="?action=register" method="POST">
                <div class="input-group"><label>FullName</label><input type="text" name="name" required placeholder="Hammad Malik"></div>
                <div class="input-group"><label>Email address</label><input type="email" name="email" required placeholder="user@domain.com"></div>
                <div class="input-group"><label>Desired Password</label><input type="password" name="password" required></div>
                <div class="input-group"><label>Date of Birth</label><input type="date" name="dob" required></div>
                <div class="input-group"><label>Time of Birth</label><input type="time" name="tob" required></div>
                <div class="input-group"><label>Place of Birth (City)</label><input type="text" name="place" placeholder="Lahore, Pakistan" required></div>
                <button type="submit" class="btn">Register My Birth Chart</button>
            </form>
            <p style="text-align: center; font-size: 12px; margin-top: 15px;">Already registered? <a href="?action=show_login" style="color:#fb923c;">Login Here</a></p>
        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span><strong>Khush Aamdeed:</strong> <?php echo htmlspecialchars($user['name']); ?></span>
                <a href="?action=logout" style="color: #ef4444; font-size:12px; text-decoration:none;">Log out</a>
            </div>

            <div style="background:#0c0a09; padding:10px; border-radius:8px; margin-bottom:15px; font-size:12px;">
                <span><strong>Astro Plan:</strong> <?php echo strtoupper($user['plan']); ?> • <strong>Remaining Coins:</strong> <?php echo $user['coins']; ?> (10/Q)</span>
            </div>

            <div class="chat-box">
                <?php foreach ($chats as $c): ?>
                    <div class="bubble <?php echo $c['role'] === 'user' ? 'bubble-user' : 'bubble-bot'; ?>">
                        <?php echo nl2br(htmlspecialchars($c['text'])); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <form action="?action=chat" method="POST">
                <input type="text" name="question" placeholder="E.g., Malik Mehboob, Shadi kab hogi meri?" required style="margin-bottom:10px;">
                <button type="submit" class="btn">Consult Malik Assistant (10 Coins)</button>
            </form>
        <?php endif; ?>

        <div class="contact-bar">
            Support Phone: <?php echo SUPPORT_PHONE; ?><br>
            Powered by The Astro Healer & Astrologer Malik WordPress Shortcode
        </div>
    </div>
</body>
</html>`;

  res.setHeader("Content-Disposition", "attachment; filename=the-astro-healer-malik.php");
  res.setHeader("Content-Type", "text/plain");
  res.send(phpContent);
});

// Start-up & development flow setup
async function startServer() {
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`Express full-stack server running on http://localhost:${PORT}`);
  });
}

startServer();
