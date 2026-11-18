import React, { useState, useEffect, useRef } from "react";
import { 
  Sparkles, Moon, Sun, Compass, Zap, MessageSquare, RefreshCw, 
  Star, Info, Crown, Lock, Globe, MapPin, Calendar, Clock,
  ChevronRight, Send, AlertCircle, HelpCircle, Phone, Download,
  Users, DollarSign, BarChart2, CheckCircle2, ShieldAlert, LogOut, Check
} from "lucide-react";
import { AnimatePresence, motion } from "motion/react";
import { 
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell 
} from "recharts";
import { PlanetDetail, ChatMessage, UserSession } from "./types";
import KundliChart from "./components/KundliChart";
import AdminDashboard from "./components/AdminDashboard";

export default function App() {
  // Authentication & Onboarding state variables
  const [authMode, setAuthMode] = useState<"login" | "signup">("signup");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [name, setName] = useState("");
  const [dob, setDob] = useState("");
  const [tob, setTob] = useState("12:00");
  const [place, setPlace] = useState("");

  const [step, setStep] = useState<"auth" | "dashboard">("auth");
  const [chartStyle, setChartStyle] = useState<"north" | "south">("north");
  const [activeTab, setActiveTab] = useState<"chart" | "strengths" | "horoscope" | "admin">("chart");
  const [adminSubTab, setAdminSubTab] = useState<"users" | "payments">("users");
  const [adminSection, setAdminSection] = useState<"chat" | "dashboard">("chat");
  const [clickCount, setClickCount] = useState(0);
  const [selectedPlanKey, setSelectedPlanKey] = useState<"basic" | "medium" | "ultra" | "max">("basic");

  // Server state tracking
  const [isAuthLoading, setIsAuthLoading] = useState(false);
  const [isLoadingChart, setIsLoadingChart] = useState(false);
  const [isChatSending, setIsChatSending] = useState(false);
  const [isResetUpdating, setIsResetUpdating] = useState(false);
  const [isPurchaseLoading, setIsPurchaseLoading] = useState(false);

  // User Profile and horoscope results
  const [currentUser, setCurrentUser] = useState<UserSession | null>(null);
  const [planetsList, setPlanetsList] = useState<PlanetDetail[]>([]);
  const [sourceEngine, setSourceEngine] = useState("astrological_engine");
  const [dailyForecast, setDailyForecast] = useState<string>("");
  const [dailyForecastLoading, setDailyForecastLoading] = useState(false);

  // Admin section metrics
  const [adminMetrics, setAdminMetrics] = useState<{
    totalUsers: number;
    totalRevenue: number;
    transactions: any[];
    users: any[];
  } | null>(null);
  const [adminMetricsLoading, setAdminMetricsLoading] = useState(false);

  // Chat input
  const [chatInput, setChatInput] = useState("");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [authMessage, setAuthMessage] = useState<string | null>(null);

  // Success overlays & modals
  const [showPricingModal, setShowPricingModal] = useState(false);
  const [isSuccessPurchaseOverlay, setIsSuccessPurchaseOverlay] = useState(false);

  const chatContainerRef = useRef<HTMLDivElement>(null);

  // Auto scroll to bottom of chat screen
  useEffect(() => {
    if (chatContainerRef.current) {
      chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
    }
  }, [currentUser?.chatHistory, isChatSending]);

  // Handle Stripe Redirection URL hash values
  useEffect(() => {
    const handlePaymentRedirect = async () => {
      const hash = window.location.hash;
      if (hash.startsWith("#payment-success")) {
        const queryStr = hash.substring(17); // remove "#payment-success?"
        const params = new URLSearchParams(queryStr);
        const redirectEmail = params.get("email");
        const sessionId = params.get("session_id");
        const plan = params.get("plan") || "basic";

        if (redirectEmail && sessionId) {
          try {
            const res = await fetch("/api/payment/verify", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ email: redirectEmail, sessionId, plan })
            });
            const data = await res.json();
            if (data.success && data.user) {
              setCurrentUser(data.user);
              setIsSuccessPurchaseOverlay(true);
              // Clean has URL
              window.location.hash = "";
            }
          } catch (e) {
            console.error("Payment verification failed:", e);
          }
        }
      }
    };
    handlePaymentRedirect();
  }, []);

  // Sync admin metrics when admin is logged in
  useEffect(() => {
    if (currentUser?.role === "admin") {
      fetchAdminMetrics();
    }
  }, [currentUser, activeTab]);

  const fetchAdminMetrics = async () => {
    setAdminMetricsLoading(true);
    try {
      const res = await fetch("/api/admin/metrics");
      const data = await res.json();
      setAdminMetrics(data);
    } catch (err) {
      console.error("Failed to query metrics:", err);
    }
    setAdminMetricsLoading(false);
  };

  // Submit Authentication form (Signup/Login)
  const handleAuthSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);
    setAuthMessage(null);
    setIsAuthLoading(true);

    if (authMode === "signup") {
      if (!email || !password || !name || !dob || !place) {
        setErrorMessage("Aap se iltija hai ke tamam khaney fill karein.");
        setIsAuthLoading(false);
        return;
      }

      try {
        // Geolocate birth place
        const geoRes = await fetch("/api/geolocate", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ place })
        });
        const geoData = await geoRes.json();

        // Register User Account
        const regRes = await fetch("/api/register", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            email,
            password,
            name,
            dob,
            tob,
            place: geoData.label || place,
            lat: geoData.lat,
            lon: geoData.lon,
            timezone: geoData.timezone
          })
        });

        const regData = await regRes.json();
        if (regRes.status !== 200 || regData.error) {
          setErrorMessage(regData.error || "Registration failed.");
          setIsAuthLoading(false);
          return;
        }

        // Auto load planetary charts
        await loadPlanetsAndHoroscope(regData.user);
        setCurrentUser(regData.user);
        setStep("dashboard");
      } catch (err) {
        console.error("Registration error:", err);
        setErrorMessage("Kalyan sitaray disturbed! Please check your network connection.");
      }
    } else {
      // Login flow
      if (!email || !password) {
        setErrorMessage("Meharbani karke email aur password darj karein.");
        setIsAuthLoading(false);
        return;
      }

      try {
        const logRes = await fetch("/api/login", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email, password })
        });
        const logData = await logRes.json();

        if (logRes.status !== 200 || logData.error) {
          setErrorMessage(logData.error || "Login credentials failed.");
          setIsAuthLoading(false);
          return;
        }

        await loadPlanetsAndHoroscope(logData.user);
        setCurrentUser(logData.user);
        setStep("dashboard");
      } catch (err) {
        console.error("Login failure:", err);
        setErrorMessage("Login process interrupted. Server is reloading.");
      }
    }
    setIsAuthLoading(false);
  };

  // Load user star chart parameters & predictions
  const loadPlanetsAndHoroscope = async (user: UserSession) => {
    setIsLoadingChart(true);
    try {
      const [yearStr, monthStr, dayStr] = user.dob.split("-");
      const [hourStr, minStr] = user.tob.split(":");

      const planetRes = await fetch("/api/vedic/planet-details", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          day: parseInt(dayStr),
          month: parseInt(monthStr),
          year: parseInt(yearStr),
          hour: parseInt(hourStr),
          min: parseInt(minStr),
          lat: user.lat,
          lon: user.lon,
          timezone: user.timezone
        })
      });

      const planetData = await planetRes.json();
      setPlanetsList(planetData.planets);
      setSourceEngine(planetData.source);

      // Fetch daily predictions
      setDailyForecastLoading(true);
      const moonPlanet = planetData.planets.find((p: any) => p.name.toLowerCase() === "moon") || planetData.planets.find((p: any) => p.name.toLowerCase() === "ascendant");
      const signId = moonPlanet ? moonPlanet.sign_id : 1;

      const horoRes = await fetch(`/api/vedic/daily-forecast?sign_id=${signId}`);
      const horoData = await horoRes.json();
      setDailyForecast(horoData.prediction);
      setDailyForecastLoading(false);

    } catch (err) {
      console.error("Failed loading star chart:", err);
      setDailyForecast("Zindagi normal rahegi. Sabar barqarar rakhein, Allah meharbaan hoga.");
    }
    setIsLoadingChart(false);
  };

  // Send query to Malik Assistant chatbot
  const handleSendMessage = async (e?: React.FormEvent, customQuestion?: string) => {
    if (e) e.preventDefault();
    const query = customQuestion || chatInput;
    if (!query.trim() || !currentUser) return;

    // Check coins balance unless admin
    if (currentUser.role !== "admin" && currentUser.coins < 10) {
      setShowPricingModal(true);
      return;
    }

    setChatInput("");
    setIsChatSending(true);
    setErrorMessage(null);

    try {
      const chatRes = await fetch("/api/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: currentUser.email,
          question: query,
          planetsList: planetsList
        })
      });

      if (chatRes.status === 403) {
        setShowPricingModal(true);
        setIsChatSending(false);
        return;
      }

      const resData = await chatRes.json();
      if (resData.error === "insufficient_funds") {
        setShowPricingModal(true);
        setIsChatSending(false);
        return;
      }

      setCurrentUser(prev => prev ? {
        ...prev,
        coins: resData.coins,
        questionsAsked: resData.questionsAsked,
        chatHistory: resData.chatHistory
      } : null);

      setIsChatSending(false);
    } catch (err) {
      console.error(err);
      setErrorMessage("Rabta toot gaya beta. Check network and type again.");
      setIsChatSending(false);
    }
  };

  // Trigger Stripe Payment Checkout Integration with selected plan
  const handlePurchasePlan = async (planKey: string = "basic") => {
    if (!currentUser) return;
    setIsPurchaseLoading(true);
    setErrorMessage(null);

    try {
      const res = await fetch("/api/payment/create-checkout-session", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: currentUser.email, planKey })
      });
      const data = await res.json();
      if (data.url) {
        // Redirect to live checkout
        window.location.href = data.url;
      } else {
        throw new Error("No URL returned from checkout generator.");
      }
    } catch (err) {
      console.warn("Stripe redirect failed, activating manual bypass topup fallback:", err);
      // Sandbox bypass topup
      handleSandboxBypassActivation(planKey);
    }
    setIsPurchaseLoading(false);
  };

  // Fast Test Sandbox Bypass (For test validations without live stripe activation requirements)
  const handleSandboxBypassActivation = async (planKey: string = "basic") => {
    if (!currentUser) return;
    setIsPurchaseLoading(true);
    try {
      const res = await fetch("/api/user/purchase", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: currentUser.email, planKey })
      });
      const updatedUser = await res.json();
      setCurrentUser(updatedUser);
      setShowPricingModal(false);
      setIsSuccessPurchaseOverlay(true);
    } catch (err) {
      console.error(err);
    }
    setIsPurchaseLoading(false);
  };

  // Reset current coins to Free (50 Coins)
  const handleResetCredits = async () => {
    if (!currentUser) return;
    setIsResetUpdating(true);
    try {
      const res = await fetch("/api/user/reset", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: currentUser.email })
      });
      const updatedUser = await res.json();
      setCurrentUser(updatedUser);
    } catch (err) {
      console.error(err);
    }
    setIsResetUpdating(false);
  };

  // Download stand-alone PHP Widget File helper
  const handleDownloadWidgetPHP = () => {
    window.open("/api/download/widget-php", "_blank");
  };

  const handleLogout = () => {
    setCurrentUser(null);
    setStep("auth");
  };

  // Suggested Astrological Questions (Roman Urdu Pakistani Style)
  const suggestQuestions = [
    "Malik Mehboob, ghar ke masail aur ruzgar me barkat ke liye rohani tips btayein?",
    "Mubarak saal konsa hoga shadi ke lihaaz se meri Kundli ke mutabiq?",
    "Khaas sadaqah ya parindo ko dana dalney se dushwari kam ho sakti hai?",
    "Sitaron ke mutabiq foreign safar ka darwaza kab khulega?"
  ];

  // Colors for planetary strengths graph (emerald and indigo feels for Sufi Astro healer styling)
  const planetaryStrengthsData = planetsList
    .filter(p => p.name !== "Ascendant")
    .map(p => ({
      name: p.name,
      strength: p.strength || Math.round(50 + Math.random() * 45),
      sign: p.sign
    }));

  return (
    <div id="app-root" className="min-h-screen bg-neutral-950 text-slate-100 font-sans stars-bg relative overflow-x-hidden selection:bg-emerald-500/30 selection:text-emerald-200">
      
      {/* Background ambient radial cosmic glows */}
      <div className="absolute top-0 left-1/4 w-[600px] h-[600px] bg-emerald-900/10 rounded-full blur-[140px] pointer-events-none pulse-glow" />
      <div className="absolute bottom-1/3 right-1/4 w-[500px] h-[500px] bg-indigo-950/20 rounded-full blur-[160px] pointer-events-none" />

      {/* Header Bar */}
      <header className="sticky top-0 z-40 backdrop-blur-md bg-neutral-950/80 border-b border-emerald-500/15">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 via-emerald-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-emerald-950/45">
              <Sparkles className="w-5.5 h-5.5 text-slate-950 font-bold" />
            </div>
            <div>
              <h1 className="text-md sm:text-lg font-bold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-emerald-300 via-indigo-200 to-slate-200 uppercase">
                THE ASTRO HEALER
              </h1>
              <p className="text-[10px] text-slate-400 leading-none">Astrologer Malik AI Assistant</p>
            </div>
          </div>

          <div className="flex items-center gap-2 sm:gap-4">
            {/* Direct Support Contact info */}
            <div className="hidden md:flex items-center gap-1.5 text-xs text-slate-300 font-mono bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-800">
              <Phone className="w-3.5 h-3.5 text-emerald-400" />
              <span>Contact: +1 (470) 650-5315</span>
            </div>

            {currentUser && (
              <div className="flex items-center gap-2">
                {/* Reset test coins button helper */}
                <button
                  onClick={handleResetCredits}
                  title="Reset to 50 Free coins limit"
                  className="p-1 px-2.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-slate-100 hover:bg-slate-800 transition text-[10px] font-mono flex items-center gap-1 cursor-pointer"
                  disabled={isResetUpdating}
                >
                  <RefreshCw className={`w-3 h-3 ${isResetUpdating ? "animate-spin" : ""}`} />
                  <span className="hidden sm:inline">Reset Free Coins</span>
                </button>

                {/* Coins capsule pill */}
                <button 
                  onClick={() => setShowPricingModal(true)}
                  className="flex items-center gap-1 px-2.5 py-1 rounded-xl bg-gradient-to-r from-emerald-500/10 to-indigo-500/10 hover:from-emerald-500/20 hover:to-indigo-500/20 transition-all border border-emerald-500/20 shadow-md flex-shrink-0 cursor-pointer"
                >
                  <Zap className="w-3.5 h-3.5 text-emerald-300 fill-emerald-300/30" />
                  <span className="text-xs font-mono font-bold text-emerald-200">
                    {currentUser.coins} coins
                  </span>
                </button>

                {/* Logout Button */}
                <button 
                  onClick={handleLogout}
                  title="Log out session"
                  className="p-2 rounded-lg bg-slate-900 border border-slate-800 hover:bg-red-950/30 hover:text-red-300 text-slate-400 transition cursor-pointer"
                >
                  <LogOut className="w-3.5 h-3.5" />
                </button>
              </div>
            )}
          </div>
        </div>
      </header>

      {/* Main container view wrapper */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 py-6 min-h-[calc(100vh-140px)] flex items-center justify-center">
        
        <AnimatePresence mode="wait">
          {step === "auth" ? (
            /* ================= AUTH MODE PANEL (LOGIN & SIGN UP REQUIRED) ================= */
            <motion.div
              key="auth-mode"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              className="w-full max-w-lg bg-slate-900/60 backdrop-blur-lg rounded-3xl border border-emerald-500/15 p-6 sm:p-8 shadow-2xl relative overflow-hidden"
            >
              <div className="absolute top-0 right-0 w-36 h-36 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none" />

              {/* Header Title with Brand info */}
              <div className="text-center mb-6">
                <span 
                  onClick={() => setClickCount(p => p + 1)}
                  className="px-3 py-1 rounded-full text-[10px] font-bold font-mono text-emerald-300 bg-emerald-500/10 border border-emerald-500/25 tracking-widest uppercase cursor-pointer select-none"
                >
                  Consultation Gate {clickCount >= 2 && clickCount < 5 && `(${5 - clickCount})`}
                </span>
                <h2 className="text-2xl sm:text-3xl font-extrabold tracking-tight mt-3 bg-clip-text text-transparent bg-gradient-to-r from-emerald-300 via-indigo-100 to-slate-100 uppercase">
                  The Astro Healer
                </h2>
                <p className="mt-1 text-slate-400 text-xs">
                  Aastro remedy matrix of Astrologer Malik. Accounts require login. 50 Free coins loaded immediately!
                </p>

                {/* Mode toggle selectors */}
                <div className="flex bg-slate-950 rounded-xl p-1 mt-5 border border-slate-800 overflow-hidden">
                  <button
                    onClick={() => { setAuthMode("signup"); setErrorMessage(null); }}
                    className={`flex-1 py-2 text-xs font-mono tracking-wider rounded-lg font-bold transition ${
                      authMode === "signup" ? "bg-emerald-500 text-slate-950" : "text-slate-400 hover:text-slate-200"
                    }`}
                  >
                    Create Account
                  </button>
                  <button
                    onClick={() => { setAuthMode("login"); setErrorMessage(null); }}
                    className={`flex-1 py-2 text-xs font-mono tracking-wider rounded-lg font-bold transition ${
                      authMode === "login" ? "bg-emerald-500 text-slate-950" : "text-slate-400 hover:text-slate-200"
                    }`}
                  >
                    Sign In
                  </button>
                </div>
              </div>

              {errorMessage && (
                <div className="mb-4 p-3.5 rounded-xl bg-red-950/30 border border-red-500/20 text-red-200 text-xs font-medium flex items-start gap-2.5">
                  <ShieldAlert className="w-4 h-4 text-red-400 shrink-0 mt-0.5" />
                  <span>{errorMessage}</span>
                </div>
              )}

              <form onSubmit={handleAuthSubmit} className="space-y-4">
                
                {/* Full name (Required only in signup) */}
                {authMode === "signup" && (
                  <div>
                    <label className="block text-[10px] font-mono uppercase tracking-wider text-emerald-300 font-bold mb-1.5">
                      Your Full Name
                    </label>
                    <input
                      required
                      type="text"
                      placeholder="e.g. Hammad Malik / Saima Khan"
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      className="w-full px-4 py-2.5 bg-slate-950 rounded-xl border border-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 text-sm placeholder:text-slate-600 font-medium transition text-slate-200"
                    />
                  </div>
                )}

                {/* Email (Required on both) */}
                <div>
                  <label className="block text-[10px] font-mono uppercase tracking-wider text-emerald-300 font-bold mb-1.5">
                    Email address
                  </label>
                  <input
                    required
                    type="email"
                    placeholder="address@example.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="w-full px-4 py-2.5 bg-slate-950 rounded-xl border border-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 text-sm placeholder:text-slate-600 font-medium transition text-slate-200 font-mono"
                  />
                </div>

                {/* Password (Required on both) */}
                <div>
                  <label className="block text-[10px] font-mono uppercase tracking-wider text-emerald-300 font-bold mb-1.5">
                    Account Password
                  </label>
                  <input
                    required
                    type="password"
                    placeholder="••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="w-full px-4 py-2.5 bg-slate-950 rounded-xl border border-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 text-sm placeholder:text-slate-600 font-medium transition text-slate-200"
                  />
                </div>

                {/* Additional Birth attributes (Required ONLY in signup) */}
                {authMode === "signup" && (
                  <div className="grid grid-cols-2 gap-3.5 pt-1">
                    <div>
                      <label className="block text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1.5">
                        <Calendar className="w-3 h-3 text-emerald-400" />
                        Birth Date
                      </label>
                      <input
                        required
                        type="date"
                        value={dob}
                        onChange={(e) => setDob(e.target.value)}
                        className="w-full px-4.5 py-2.5 bg-slate-950 rounded-xl border border-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 text-xs font-mono transition text-slate-300"
                      />
                    </div>

                    <div>
                      <label className="block text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1.5">
                        <Clock className="w-3 h-3 text-emerald-400" />
                        Birth Time
                      </label>
                      <input
                        required
                        type="time"
                        value={tob}
                        onChange={(e) => setTob(e.target.value)}
                        className="w-full px-4.5 py-2.5 bg-slate-950 rounded-xl border border-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 text-xs font-mono transition text-slate-300"
                      />
                    </div>

                    <div className="col-span-2">
                      <label className="block text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1.5">
                        <MapPin className="w-3 h-3 text-emerald-400" />
                        Place Of Birth (City, Country)
                      </label>
                      <input
                        required
                        type="text"
                        placeholder="e.g. Lahore, Pakistan"
                        value={place}
                        onChange={(e) => setPlace(e.target.value)}
                        className="w-full px-4 py-2.5 bg-slate-950 rounded-xl border border-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 text-xs transition text-slate-300"
                      />
                    </div>
                  </div>
                )}

                <button
                  type="submit"
                  disabled={isAuthLoading}
                  className="w-full py-3 mt-4 rounded-xl bg-gradient-to-r from-emerald-500 via-emerald-600 to-indigo-600 hover:from-emerald-400 font-bold font-mono tracking-wider text-slate-950 uppercase transition shadow-md hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2"
                >
                  {isAuthLoading ? (
                    <>
                      <RefreshCw className="w-4 h-4 animate-spin text-slate-950" />
                      <span>Aligning Stars...</span>
                    </>
                  ) : authMode === "signup" ? (
                    <>
                      <Sparkles className="w-4 h-4 text-slate-950" />
                      <span>Register & Calculate Kundli</span>
                    </>
                  ) : (
                    <>
                      <Lock className="w-4 h-4 text-slate-950" />
                      <span>Secure Login</span>
                    </>
                  )}
                </button>
              </form>

              {/* Phone footer banner inside onboarding auth */}
              <div className="mt-6 pt-5 border-t border-slate-800/60 font-mono text-[10px] text-center text-slate-500 flex flex-col justify-center gap-1 items-center">
                <span>The Astro Healer Support: +1 (470) 650-5315</span>
                <span>Powered by Astrologer Malik & Google Gemini 3.5</span>
              </div>
            </motion.div>
          ) : (
            /* ================= CORE ASTRO PORTAL DASHBOARD AND INTERACTIVE AI CHAT ================= */
            <motion.div
              key="portal-dashboard"
              initial={{ opacity: 0, scale: 0.98 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.98 }}
              className={`w-full ${currentUser?.role === "admin" ? "flex flex-col lg:flex-row gap-5" : "block"} items-stretch`}
            >
              
              {/* LEFT SLENDER SIDEBAR FOR MASTER ADMINS ONLY */}
              {currentUser?.role === "admin" && (
                <div id="admin-left-sidebar" className="w-full lg:w-20 shrink-0 bg-slate-900/40 backdrop-blur-md rounded-2xl border border-emerald-500/10 flex flex-row lg:flex-col items-center py-4 lg:py-6 px-4 lg:px-2 gap-4 lg:gap-6 shadow-xl shadow-slate-950/20 justify-between lg:justify-start">
                  
                  {/* Brand logo */}
                  <div className="hidden lg:flex flex-col items-center gap-1.5 select-none text-center">
                    <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 via-emerald-600 to-indigo-600 flex items-center justify-center shadow-lg">
                      <Crown className="w-5 h-5 text-slate-950" />
                    </div>
                    <span className="text-[7.5px] font-mono text-emerald-300 font-extrabold uppercase tracking-widest leading-none mt-1">MALIK HQ</span>
                  </div>

                  {/* Sidebar switches */}
                  <div className="flex flex-row lg:flex-col gap-3 flex-1 lg:flex-none justify-start lg:justify-center items-center w-full px-1 lg:px-0">
                    
                    {/* Live chat */}
                    <button
                      type="button"
                      onClick={() => setAdminSection("chat")}
                      className={`flex-1 lg:flex-none p-2 rounded-xl flex flex-col lg:flex-col items-center gap-1.5 justify-center transition-all border duration-150 cursor-pointer w-full lg:w-14 lg:h-14 ${
                        adminSection === "chat" 
                          ? "bg-gradient-to-tr from-emerald-500/15 to-emerald-600/15 border-emerald-500/30 text-emerald-300 shadow-md animate-pulse"
                          : "bg-slate-950/40 border-transparent text-slate-500 hover:text-slate-350 hover:bg-slate-900/60"
                      }`}
                      title="Kundli & Chat Room"
                    >
                      <MessageSquare className="w-4 h-4" />
                      <span className="text-[8px] font-mono font-bold uppercase tracking-wider">Chat</span>
                    </button>

                    {/* New dashboard page */}
                    <button
                      type="button"
                      onClick={() => {
                        setAdminSection("dashboard");
                        fetchAdminMetrics();
                      }}
                      className={`flex-1 lg:flex-none p-2 rounded-xl flex flex-col lg:flex-col items-center gap-1.5 justify-center transition-all border duration-150 cursor-pointer w-full lg:w-14 lg:h-14 ${
                        adminSection === "dashboard"
                          ? "bg-gradient-to-tr from-amber-500/15 to-amber-600/15 border-amber-500/30 text-amber-300 shadow-md"
                          : "bg-slate-950/40 border-transparent text-slate-500 hover:text-slate-350 hover:bg-slate-900/60"
                      }`}
                      title="HQ Earnings & Signups Insights"
                    >
                      <BarChart2 className="w-4 h-4" />
                      <span className="text-[8px] font-mono font-bold uppercase tracking-wider">Dash</span>
                    </button>

                  </div>

                  {/* LIVE DOT */}
                  <div className="lg:mt-auto hidden lg:flex flex-col items-center gap-1 w-full text-center">
                    <div className="flex items-center gap-1 justify-center">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                      <span className="text-[7.5px] font-mono text-emerald-400 uppercase font-black tracking-widest">LIVE</span>
                    </div>
                  </div>

                </div>
              )}

              {/* CORE VIEW SECTION */}
              <div className="flex-1 min-w-0">
                {currentUser?.role === "admin" && adminSection === "dashboard" ? (
                  <AdminDashboard 
                    adminMetrics={adminMetrics} 
                    adminMetricsLoading={adminMetricsLoading} 
                    fetchAdminMetrics={fetchAdminMetrics} 
                  />
                ) : (
                  <div className={`w-full grid grid-cols-1 ${currentUser?.role === "admin" ? "xl:grid-cols-12 gap-5" : "lg:grid-cols-12 gap-6"} items-stretch`}>
                    
                    {/* LEFT COLUMN: Kundli chart visual, Daily Horoscopes, Planet list, Admin tools */}
                    <div className={`${currentUser?.role === "admin" ? "xl:col-span-3 lg:col-span-4" : "lg:col-span-12"} flex flex-col gap-5`}>
                
                {/* User mini badge state row */}
                <div className="bg-slate-900/40 backdrop-blur-md rounded-2xl border border-emerald-500/15 p-4.5 shadow-md shadow-slate-950/10 relative overflow-hidden flex items-center justify-between">
                  <div className="flex items-start gap-3">
                    <div className="w-11 h-11 rounded-lg bg-emerald-500/10 border border-emerald-500/25 flex items-center justify-center shrink-0">
                      <Star className="w-5.5 h-5.5 text-emerald-400 fill-emerald-400/25" />
                    </div>
                    <div>
                      <h3 className="font-extrabold text-sm text-emerald-200">
                        {currentUser?.name}
                      </h3>
                      <p className="text-[10px] font-mono text-slate-400 uppercase mt-0.5">
                        DOB: {currentUser?.dob} • City: {currentUser?.place.split(",")[0]}
                      </p>
                      <p className="text-[10px] text-indigo-300 font-mono mt-0.5 uppercase tracking-wide">
                        Plan: <span className="text-amber-400 font-bold">{currentUser?.plan.toUpperCase()}</span>
                      </p>
                    </div>
                  </div>

                  {/* Super admin visual badge banner */}
                  {currentUser?.role === "admin" && (
                    <div className="px-2.5 py-1 rounded bg-amber-500/15 border border-amber-500/30 text-[9px] font-mono font-bold text-amber-300 tracking-wider">
                      MASTER ADMIN ACTIVE
                    </div>
                  )}
                </div>

                {/* Astro Tabbed Panels frame */}
                <div className="bg-slate-900/40 backdrop-blur-md rounded-3xl border border-emerald-500/15 shadow-xl flex flex-col overflow-hidden min-h-[440px]">
                  
                  {/* Tab heads selector */}
                  <div className="flex border-b border-slate-800 bg-slate-950/40 overflow-x-auto">
                    <button
                      onClick={() => setActiveTab("chart")}
                      className={`flex-1 py-3 text-xs font-mono font-bold uppercase tracking-wider text-center flex items-center justify-center gap-1.5 transition ${
                        activeTab === "chart" 
                          ? "text-emerald-300 border-b-2 border-emerald-400 bg-indigo-500/5" 
                          : "text-slate-400 hover:text-slate-200"
                      }`}
                    >
                      <MapPin className="w-3.5 h-3.5" />
                      <span>Chart</span>
                    </button>
                    <button
                      onClick={() => setActiveTab("strengths")}
                      className={`flex-1 py-3 text-xs font-mono font-bold uppercase tracking-wider text-center flex items-center justify-center gap-1.5 transition ${
                        activeTab === "strengths" 
                          ? "text-emerald-300 border-b-2 border-emerald-400 bg-indigo-500/5" 
                          : "text-slate-400 hover:text-slate-200"
                      }`}
                    >
                      <BarChart2 className="w-3.5 h-3.5" />
                      <span>Strengths</span>
                    </button>
                    <button
                      onClick={() => setActiveTab("horoscope")}
                      className={`flex-1 py-3 text-xs font-mono font-bold uppercase tracking-wider text-center flex items-center justify-center gap-1.5 transition ${
                        activeTab === "horoscope" 
                          ? "text-emerald-300 border-b-2 border-emerald-400 bg-indigo-500/5" 
                          : "text-slate-400 hover:text-slate-200"
                      }`}
                    >
                      <Moon className="w-3.5 h-3.5" />
                      <span>Daily Sitara</span>
                    </button>

                    {/* Show Admin tools only for authorized users */}
                    {currentUser?.role === "admin" && (
                      <button
                        onClick={() => setActiveTab("admin")}
                        className={`flex-1 py-3 text-xs font-mono font-bold uppercase tracking-wider text-center flex items-center justify-center gap-1.5 transition ${
                          activeTab === "admin" 
                            ? "text-amber-300 border-b-2 border-amber-400 bg-amber-500/5" 
                            : "text-amber-400 hover:text-amber-100"
                        }`}
                      >
                        <Crown className="w-3.5 h-3.5" />
                        <span>Admin ({adminMetrics?.totalUsers || 1})</span>
                      </button>
                    )}
                  </div>

                  {/* Tab Body Contents */}
                  <div className="p-5 flex-1 flex flex-col justify-center">

                    {/* KUNDLI CHART CANVAS DESIGN */}
                    {activeTab === "chart" && (
                      <div className="space-y-4">
                        <div className="flex justify-between items-center bg-slate-950 p-1 rounded-lg border border-slate-800">
                          <span className="text-[10px] font-mono text-slate-500 pl-2">LAYOUT DIRECTIVES:</span>
                          <div className="flex gap-1">
                            <button
                              onClick={() => setChartStyle("north")}
                              className={`px-3 py-1 rounded text-[10px] font-mono tracking-wider transition font-medium ${
                                chartStyle === "north" 
                                  ? "bg-emerald-400 text-slate-950 font-bold" 
                                  : "text-slate-400 hover:text-slate-200"
                              }`}
                            >
                              North style
                            </button>
                            <button
                              onClick={() => setChartStyle("south")}
                              className={`px-3 py-1 rounded text-[10px] font-mono tracking-wider transition font-medium ${
                                chartStyle === "south" 
                                  ? "bg-emerald-400 text-slate-950 font-bold" 
                                  : "text-slate-400 hover:text-slate-200"
                              }`}
                            >
                              South style
                            </button>
                          </div>
                        </div>

                        {/* Kundli SVG */}
                        <KundliChart planets={planetsList} style={chartStyle} />
                        
                        <div className="text-[10px] text-center text-slate-500 font-mono">
                          CONSTELLATIONS MAP SOURCE: <span className="text-emerald-400 font-semibold">{sourceEngine.toUpperCase()}</span>
                        </div>
                      </div>
                    )}

                    {/* PLANET STRENGTHS GRAF */}
                    {activeTab === "strengths" && (
                      <div className="space-y-4 flex flex-col justify-between h-full min-h-[350px]">
                        <div>
                          <h4 className="text-xs font-bold font-mono tracking-wider text-emerald-300 uppercase flex items-center gap-1">
                            Planetary Position Strength Matrix
                          </h4>
                          <p className="text-[11px] text-slate-400 mt-1 leading-tight">
                            Mathematical analysis of star weights and home locations compiled dynamically.
                          </p>
                        </div>

                        {/* Standard Recharts bar graphs */}
                        <div className="w-full h-[220px] bg-slate-950 p-2 rounded-xl border border-slate-8 w-full mt-2">
                          <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={planetaryStrengthsData} margin={{ top: 10, right: 10, left: -25, bottom: 0 }}>
                              <XAxis 
                                dataKey="name" 
                                tick={{ fill: '#94a3b8', fontSize: 10, fontFamily: 'monospace' }} 
                                axisLine={{ stroke: 'rgba(16, 185, 129, 0.2)' }}
                                tickLine={false}
                              />
                              <YAxis 
                                domain={[0, 100]} 
                                tick={{ fill: '#94a3b8', fontSize: 9 }} 
                                axisLine={{ stroke: 'rgba(16, 185, 129, 0.2)' }}
                                tickLine={false}
                              />
                              <Tooltip 
                                contentStyle={{ backgroundColor: '#0f172a', border: '1px solid rgba(16, 185, 129, 0.4)', borderRadius: '8px' }}
                                labelStyle={{ color: '#10b981', fontSize: 11, fontFamily: 'monospace', fontWeight: 'bold' }}
                                itemStyle={{ color: '#f1f5f9', fontSize: 12 }}
                              />
                              <Bar dataKey="strength" radius={[4, 4, 0, 0]}>
                                {planetaryStrengthsData.map((entry, index) => (
                                  <Cell 
                                    key={`cell-${index}`} 
                                    fill={index % 2 === 0 ? '#10b981' : '#6366f1'} 
                                  />
                                ))}
                              </Bar>
                            </BarChart>
                          </ResponsiveContainer>
                        </div>

                        <div className="grid grid-cols-2 gap-2 text-[10px] font-mono mt-1">
                          <div className="p-2 rounded bg-slate-950 border border-slate-800">
                            <span className="text-slate-500 uppercase block leading-none">Astro Ruler:</span>
                            <span className="font-bold text-emerald-400 text-xs block mt-1">
                              {planetaryStrengthsData.length > 0 
                                ? [...planetaryStrengthsData].sort((a,b)=>b.strength-a.strength)[0].name 
                                : "N/A"}
                            </span>
                          </div>
                          <div className="p-2 rounded bg-slate-950 border border-slate-800">
                            <span className="text-slate-500 uppercase block leading-none">Affiliation:</span>
                            <span className="font-bold text-indigo-400 text-xs block mt-1">Sikandar Yog</span>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* DAILY HOROSCOPE DESCRIPTION (Gentle Sufi tone prediction display) */}
                    {activeTab === "horoscope" && (
                      <div className="space-y-4">
                        <div className="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 p-3 rounded-xl animate-pulse">
                          <Moon className="w-8 h-8 text-emerald-400 shrink-0" />
                          <div>
                            <span className="text-[9px] font-mono text-emerald-400 block tracking-wider uppercase">ROSHNI ZODIAC SITARA</span>
                            <h4 className="font-extrabold text-slate-100 text-md uppercase font-mono tracking-wider">
                              {planetsList.find(p => p.name.toLowerCase() === "moon")?.sign || "Aries"} (Moon Rashi)
                            </h4>
                          </div>
                        </div>

                        <div className="bg-slate-950 rounded-xl border border-slate-850 p-4 min-h-[220px] max-h-[260px] overflow-y-auto">
                          {dailyForecastLoading ? (
                            <div className="h-44 flex flex-col justify-center items-center gap-2">
                              <RefreshCw className="w-5 h-5 animate-spin text-emerald-400" />
                              <span className="text-xs text-slate-500 font-mono">Whispering with stars...</span>
                            </div>
                          ) : (
                            <div className="text-xs text-slate-300 leading-relaxed font-sans space-y-2 whitespace-pre-line">
                              {dailyForecast}
                            </div>
                          )}
                        </div>

                        <p className="text-[9px] text-center text-slate-600 font-mono">
                          Updates automatically based on Moon & Ascendant placements
                        </p>
                      </div>
                    )}

                    {/* ================= ADMIN EXCLUSIVE CONTROL TOOL PANEL ================= */}
                    {currentUser?.role === "admin" && activeTab === "admin" && (
                      <div className="space-y-4 h-full min-h-[350px] flex flex-col justify-between">
                        
                        {/* Metrics Cards */}
                        <div className="grid grid-cols-3 gap-2 mt-1">
                          <div className="p-2 rounded-xl bg-slate-950 border border-slate-800 text-left">
                            <span className="text-[8px] font-mono text-slate-500 block uppercase">Signups</span>
                            <span className="text-md font-black font-mono text-indigo-400">{adminMetrics?.totalUsers || 0}</span>
                          </div>
                          <div className="p-2 rounded-xl bg-slate-950 border border-slate-800 text-left">
                            <span className="text-[8px] font-mono text-slate-500 block uppercase">Purchases</span>
                            <span className="text-md font-black font-mono text-amber-400">
                              {adminMetrics?.users.filter(u => u.plan === "premium").length || 0}
                            </span>
                          </div>
                          <div className="p-2 rounded-xl bg-slate-950 border border-slate-800 text-left">
                            <span className="text-[8px] font-mono text-slate-500 block uppercase">Revenue</span>
                            <span className="text-md font-black font-mono text-emerald-400">
                              ${adminMetrics?.totalRevenue.toFixed(2) || "0.00"}
                            </span>
                          </div>
                        </div>

                        {/* Sub Tab Selection */}
                        <div className="flex bg-slate-950 p-1 rounded-xl border border-slate-850">
                          <button
                            type="button"
                            onClick={() => setAdminSubTab("users")}
                            className={`flex-1 py-1 rounded-lg text-[10px] font-mono font-bold transition-all duration-150 ${
                              adminSubTab === "users" ? "bg-emerald-400 text-slate-950" : "text-slate-400 hover:text-slate-200"
                            }`}
                          >
                            Users ({adminMetrics?.totalUsers || 0})
                          </button>
                          <button
                            type="button"
                            onClick={() => setAdminSubTab("payments")}
                            className={`flex-1 py-1 rounded-lg text-[10px] font-mono font-bold transition-all duration-150 ${
                              adminSubTab === "payments" ? "bg-emerald-400 text-slate-950" : "text-slate-400 hover:text-slate-200"
                            }`}
                          >
                            Payments ({adminMetrics?.transactions?.length || 0})
                          </button>
                        </div>

                        {/* Registered users and payments table stream */}
                        <div className="bg-slate-950 rounded-xl border border-slate-850 p-3 flex-1 overflow-y-auto max-h-[180px] text-left">
                          {adminMetricsLoading ? (
                            <div className="p-6 text-center text-xs font-mono text-slate-500">Querying DB metrics...</div>
                          ) : adminSubTab === "users" ? (
                            <div className="space-y-2">
                              <span className="text-[9px] font-mono text-emerald-300 block mb-1 uppercase tracking-widest font-bold">User Ledger:</span>
                              {adminMetrics?.users.map((u, ui) => (
                                <div key={ui} className="p-2 rounded bg-slate-900 border border-slate-800/60 text-[11px] font-mono flex items-center justify-between">
                                  <div>
                                    <p className="font-bold text-slate-200 truncate max-w-[120px]">{u.name}</p>
                                    <p className="text-[9px] text-slate-500 mt-0.5 truncate max-w-[140px]">{u.email}</p>
                                  </div>
                                  <div className="text-right">
                                    <span className={`px-1 rounded text-[9px] font-bold ${u.plan === "premium" ? "bg-amber-400/10 text-amber-300 border border-amber-500/20" : "bg-slate-850 text-slate-400"}`}>
                                      {u.plan.toUpperCase()}
                                    </span>
                                    <p className="text-[9px] text-slate-400 mt-0.5">{u.coins} coins left</p>
                                  </div>
                                </div>
                              ))}
                            </div>
                          ) : (
                            <div className="space-y-2">
                              <span className="text-[9px] font-mono text-amber-300 block mb-1 uppercase tracking-widest font-bold">Payment Transactions:</span>
                              {adminMetrics?.transactions && adminMetrics.transactions.length > 0 ? (
                                adminMetrics.transactions.map((t: any, ti: number) => (
                                  <div key={ti} className="p-2 rounded bg-slate-900 border border-slate-800/60 text-[11px] font-mono flex items-center justify-between">
                                    <div>
                                      <p className="font-bold text-emerald-400">${t.amount?.toFixed(2) || "11.00"}</p>
                                      <p className="text-[9px] text-slate-400 truncate max-w-[140px]">{t.email}</p>
                                    </div>
                                    <div className="text-right text-[9px] text-slate-500 font-mono">
                                      <span className="px-1 rounded text-[8px] font-bold bg-emerald-500/10 text-emerald-400">SUCCESS</span>
                                      <p className="mt-0.5 text-[8px] text-slate-500">{new Date(t.timestamp).toLocaleDateString()}</p>
                                    </div>
                                  </div>
                                ))
                              ) : (
                                <div className="p-6 text-center text-xs font-mono text-slate-500">No transactions recorded yet.</div>
                              )}
                            </div>
                          )}
                        </div>

                        {/* Download dynamic WP PHP widget widget installer script */}
                        <div className="pt-2">
                          <button
                            onClick={handleDownloadWidgetPHP}
                            className="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 text-slate-950 font-bold font-mono text-xs tracking-wider flex items-center justify-center gap-2 hover:-translate-y-0.5 transition cursor-pointer"
                          >
                            <Download className="w-4 h-4 text-slate-950" />
                            <span>DOWNLOAD CPANEL PHP WIDGET</span>
                          </button>
                          <p className="text-[9px] text-center text-slate-500 font-mono mt-1 leading-tight">
                            Standalone WordPress dynamic shortcode proxy script with login & SQLite!
                          </p>
                        </div>

                      </div>
                    )}

                  </div>
                </div>

              </div>

              {/* MIDDLE COLUMN: Standing Chat Box panel of Astrologer Malik */}
              <div className={`${currentUser?.role === "admin" ? "xl:col-span-5 lg:col-span-8" : "lg:col-span-7"} flex flex-col items-stretch min-h-[500px]`}>
                
                <div className="bg-slate-900/40 backdrop-blur-md rounded-3xl border border-emerald-500/15 shadow-xl flex flex-col h-full flex-1">
                  
                  {/* Chatbox Ribbon metadata */}
                  <div className="px-5 py-4 border-b border-slate-800 bg-slate-950/60 flex items-center justify-between rounded-t-3xl">
                    <div className="flex items-center gap-2.5">
                      <div className="relative">
                        <MessageSquare className="w-5 h-5 text-emerald-400" />
                        <div className="absolute -bottom-0.5 -right-0.5 w-2 h-2 bg-emerald-500 border border-slate-950 rounded-full" />
                      </div>
                      <div>
                        <h4 className="font-extrabold text-sm text-slate-200">Consultation Terminal</h4>
                        <p className="text-[10px] text-slate-400 flex items-center gap-1">
                          <span>Sufi Malik Assistant</span>
                          <span>•</span>
                          <span className="text-emerald-400 font-mono font-semibold">+1 (470) 650-5315</span>
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 text-[11px] font-mono font-bold bg-emerald-500/10 border border-emerald-400/20 px-2.5 py-1 rounded-lg text-emerald-300">
                      <span>{currentUser?.role === "admin" ? "UNLIMITED QUESTIONS (ADMIN MODE)" : `${currentUser ? Math.floor(currentUser.coins / 10) : 0} sawal left`}</span>
                    </div>
                  </div>

                  {/* Chat stream area */}
                  <div 
                    ref={chatContainerRef}
                    className="flex-1 overflow-y-auto px-5 py-6 space-y-4 max-h-[460px] min-h-[380px]"
                  >
                    {currentUser?.chatHistory && currentUser.chatHistory.map((msg, idx) => (
                      <div 
                        key={idx}
                        className={`flex items-start gap-3 ${msg.role === "user" ? "flex-row-reverse" : "flex-row"}`}
                      >
                        {/* Avatar tag icon */}
                        <div className={`w-8 h-8 rounded-lg shrink-0 flex items-center justify-center font-bold text-xs ${
                          msg.role === "user" 
                            ? "bg-emerald-500/10 border border-emerald-500/35 text-emerald-300" 
                            : "bg-indigo-500/10 border border-indigo-500/30 text-indigo-300"
                        }`}>
                          {msg.role === "user" ? "ME" : <Sparkles className="w-3.5 h-3.5 text-emerald-400" />}
                        </div>

                        {/* Text balloon bubble content */}
                        <div className={`max-w-[85%] rounded-2xl px-4 py-3 leading-relaxed text-xs shadow-md border ${
                          msg.role === "user" 
                            ? "bg-emerald-500/10 border-emerald-500/15 text-slate-100 rounded-tr-none" 
                            : "bg-slate-950/70 border-slate-850 text-slate-200 rounded-tl-none font-medium whitespace-pre-line"
                        }`}>
                          {msg.text}
                          <span className="block text-[8px] text-slate-500/70 text-right mt-1.5 font-mono">
                            {new Date(msg.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          </span>
                        </div>
                      </div>
                    ))}

                    {/* Chat sending/resolving constellations spinner bubble */}
                    {isChatSending && (
                      <div className="flex items-start gap-3">
                        <div className="w-8 h-8 rounded-lg shrink-0 flex items-center justify-center bg-indigo-500/10 border border-indigo-500/30">
                          <Sparkles className="w-3.5 h-3.5 text-indigo-300 animate-spin" />
                        </div>
                        <div className="rounded-2xl px-4 py-3.5 bg-slate-950/70 border border-slate-850 shadow-md rounded-tl-none flex items-center gap-1">
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-450 animate-bounce" style={{ animationDelay: "0ms" }} />
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-450 animate-bounce" style={{ animationDelay: "150ms" }} />
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-450 animate-bounce" style={{ animationDelay: "300ms" }} />
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Suggestion questions fast tabs tags stream */}
                  <div className="px-5 pb-3.5 pt-2 bg-slate-950/30 border-t border-slate-800">
                    <span className="text-[9px] uppercase font-mono tracking-widest text-slate-500 mb-2 block font-bold">Fast Custom Sawals:</span>
                    <div className="flex flex-col sm:flex-row flex-wrap gap-2">
                      {suggestQuestions.map((q, qidx) => (
                        <button
                          key={qidx}
                          onClick={() => handleSendMessage(undefined, q)}
                          disabled={isChatSending}
                          className="px-3 py-1.5 rounded-xl bg-slate-950 hover:bg-slate-900 text-slate-300 border border-slate-850 hover:border-emerald-500/30 text-[10px] font-medium leading-tight max-w-full truncate text-left transition cursor-pointer"
                        >
                          {q}
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Enter/Send form input box */}
                  <div className="p-4 border-t border-slate-800 bg-slate-950/60 rounded-b-3xl">
                    <form onSubmit={handleSendMessage} className="flex gap-2">
                      <input
                        type="text"
                        placeholder={
                          currentUser?.role !== "admin" && currentUser?.coins === 0 
                            ? "Credits completed beta. Plz activate Premium Plan to query further sawals!" 
                            : "E.g. Malik Mehboob - mera is saal ruzgar barhaiga ya nuzul hoga?"
                        }
                        value={chatInput}
                        onChange={(e) => setChatInput(e.target.value)}
                        disabled={isChatSending || (currentUser?.role !== "admin" && currentUser?.coins === 0)}
                        className="flex-1 px-4 py-2.5 bg-slate-900 rounded-xl border border-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 text-[12.5px] text-slate-200 font-medium placeholder:text-slate-600 font-sans"
                      />
                      <button
                        type="submit"
                        disabled={isChatSending || !chatInput.trim() || (currentUser?.role !== "admin" && currentUser?.coins === 0)}
                        className="p-3 bg-gradient-to-r from-emerald-500 to-indigo-600 hover:from-emerald-400 text-slate-950 font-extrabold rounded-xl transition duration-200 disabled:opacity-45 flex items-center justify-center shadow-md shadow-indigo-950/40 cursor-pointer"
                      >
                        <Send className="w-3.5 h-3.5 text-slate-950" />
                      </button>
                    </form>
                  </div>

                </div>

              </div>

              {/* RIGHT COLUMN (ADMIN SIDEBAR): Live User Accounts & Payments Monitoring */}
              {currentUser?.role === "admin" && (
                <div className="xl:col-span-4 lg:col-span-12 flex flex-col gap-5">
                  <div className="bg-slate-900/40 backdrop-blur-md rounded-3xl border border-amber-500/15 shadow-xl flex flex-col h-full overflow-hidden min-h-[440px]">
                    
                    {/* Header Ribbon */}
                    <div className="px-5 py-4 border-b border-slate-800 bg-slate-950/60 flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Users className="w-4 h-4 text-amber-400" />
                        <div>
                          <h4 className="font-extrabold text-xs text-amber-200 uppercase tracking-wider">Live Admin Desk</h4>
                          <span className="text-[9px] text-slate-400 font-mono">Real-time Astro Account Feed</span>
                        </div>
                      </div>
                      <button
                        type="button"
                        onClick={fetchAdminMetrics}
                        disabled={adminMetricsLoading}
                        className="p-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/20 hover:border-amber-500/40 transition cursor-pointer flex items-center justify-center"
                        title="Sync metrics"
                      >
                        <RefreshCw className={`w-3.5 h-3.5 ${adminMetricsLoading ? "animate-spin" : ""}`} />
                      </button>
                    </div>

                    {/* Stats summary banner */}
                    <div className="p-4 grid grid-cols-3 gap-2 bg-slate-950/40 border-b border-slate-850">
                      <div className="p-2 rounded-xl bg-slate-950 border border-slate-850/60 text-center">
                        <span className="text-[7.5px] font-mono font-bold text-slate-500 block uppercase">Accounts</span>
                        <span className="text-sm font-extrabold font-mono text-indigo-400 mt-0.5 block">
                          {adminMetrics?.totalUsers || 0}
                        </span>
                      </div>
                      <div className="p-2 rounded-xl bg-slate-950 border border-slate-850/60 text-center">
                        <span className="text-[7.5px] font-mono font-bold text-slate-500 block uppercase">Premium</span>
                        <span className="text-sm font-extrabold font-mono text-amber-400 mt-0.5 block">
                          {adminMetrics?.users?.filter(u => u.plan === "premium").length || 0}
                        </span>
                      </div>
                      <div className="p-2 rounded-xl bg-slate-950 border border-slate-850/60 text-center">
                        <span className="text-[7.5px] font-mono font-bold text-slate-500 block uppercase">Revenue</span>
                        <span className="text-sm font-extrabold font-mono text-emerald-400 mt-0.5 block">
                          ${adminMetrics?.totalRevenue?.toFixed(1) || "0"}
                        </span>
                      </div>
                    </div>

                    {/* Quick filter sub-tabs selection */}
                    <div className="px-4 py-2 flex gap-1.5 border-b border-slate-850 bg-slate-950/20">
                      <button
                        type="button"
                        onClick={() => setAdminSubTab("users")}
                        className={`flex-1 py-1 px-2 rounded-lg text-[9px] font-mono font-bold tracking-wider transition-all duration-150 ${
                          adminSubTab === "users" ? "bg-amber-450 text-slate-950" : "text-slate-400 hover:text-slate-200"
                        }`}
                      >
                        Signups ({adminMetrics?.totalUsers || 0})
                      </button>
                      <button
                        type="button"
                        onClick={() => setAdminSubTab("payments")}
                        className={`flex-1 py-1 px-2 rounded-lg text-[9px] font-mono font-bold tracking-wider transition-all duration-150 ${
                          adminSubTab === "payments" ? "bg-amber-450 text-slate-950" : "text-slate-400 hover:text-slate-200"
                        }`}
                      >
                        Payments ({adminMetrics?.transactions?.length || 0})
                      </button>
                    </div>

                    {/* Quick stream items area */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-2.5 max-h-[300px]">
                      {adminMetricsLoading ? (
                        <div className="py-8 text-center text-xs font-mono text-slate-500 flex flex-col justify-center items-center gap-1.5">
                          <RefreshCw className="w-4 h-4 animate-spin text-amber-400" />
                          <span>Updating metrics ledger...</span>
                        </div>
                      ) : adminSubTab === "users" ? (
                        <div className="space-y-2">
                          {adminMetrics?.users && adminMetrics.users.length > 0 ? (
                            adminMetrics.users.map((u, ui) => (
                              <div key={ui} className="p-2.5 rounded-xl bg-slate-950 border border-slate-850/65 text-[10px] font-mono flex items-center justify-between hover:border-slate-800 transition">
                                <div className="min-w-0 flex-1 pr-2">
                                  <div className="flex items-center gap-1.5 min-w-0">
                                    <span className="font-extrabold text-slate-200 truncate block text-[10.5px]">{u.name}</span>
                                    {u.role === "admin" && (
                                      <span className="scale-75 text-[7px] bg-red-500/10 text-red-400 px-1 py-0.5 rounded font-bold uppercase shrink-0">ADMIN</span>
                                    )}
                                  </div>
                                  <p className="text-[9px] text-slate-500 mt-0.5 truncate">{u.email}</p>
                                  <p className="text-[8.5px] text-slate-450 mt-1 truncate">DOB: {u.dob}</p>
                                </div>
                                <div className="text-right shrink-0">
                                  <span className={`px-1 rounded text-[7.5px] font-bold inline-block border ${
                                    u.plan === "premium" 
                                      ? "bg-amber-500/10 text-amber-300 border-amber-500/20" 
                                      : "bg-slate-850 text-slate-500 border-slate-800"
                                  }`}>
                                    {u.plan.toUpperCase()}
                                  </span>
                                  <p className="text-[9px] text-slate-400 mt-1 font-bold">{u.coins} coins</p>
                                </div>
                              </div>
                            ))
                          ) : (
                            <div className="p-6 text-center text-xs font-mono text-slate-500">No registered users on record.</div>
                          )}
                        </div>
                      ) : (
                        <div className="space-y-2">
                          {adminMetrics?.transactions && adminMetrics.transactions.length > 0 ? (
                            adminMetrics.transactions.map((t: any, ti: number) => (
                              <div key={ti} className="p-2.5 rounded-xl bg-slate-950 border border-slate-850/65 text-[10px] font-mono flex items-center justify-between hover:border-slate-800 transition">
                                <div className="min-w-0 flex-1 pr-2">
                                  <span className="font-black text-emerald-400 text-xs block">${t.amount?.toFixed(2) || "11.00"}</span>
                                  <p className="text-[9px] text-slate-500 mt-0.5 truncate">{t.email}</p>
                                  {t.planKey && (
                                    <p className="text-[8px] text-amber-400/80 mt-0.5 uppercase font-medium">{t.planKey} pack</p>
                                  )}
                                </div>
                                <div className="text-right shrink-0">
                                  <span className="px-1 py-0.5 rounded text-[7px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/10 inline-block uppercase tracking-wider">SUCCESS</span>
                                  <p className="mt-1 text-[8px] text-slate-500">{new Date(t.timestamp).toLocaleDateString()}</p>
                                </div>
                              </div>
                            ))
                          ) : (
                            <div className="p-6 text-center text-xs font-mono text-slate-500">No payment records found.</div>
                          )}
                        </div>
                      )}
                    </div>

                    {/* Info helper footer inside admin desk */}
                    <div className="p-3 border-t border-slate-800 bg-slate-950/40 text-center">
                      <p className="text-[8.5px] font-mono text-amber-300/60 leading-normal">
                        Sufi Malik Astro HQ Live Audit Feed
                      </p>
                    </div>

                  </div>
                </div>
              )}
                </div>
                )}
              </div> {/* Close flex-1 container */}
            </motion.div>
          )}
        </AnimatePresence>

      </main>

      {/* FOOTER DESIGN BLOCKS */}
      <footer className="py-8 max-w-7xl mx-auto px-4 text-center border-t border-slate-900 text-[11px] text-slate-500 font-mono tracking-wider mt-12 bg-neutral-950">
        <div className="flex flex-col sm:flex-row justify-center items-center gap-2 sm:gap-4 mb-2">
          <span>THE ASTRO HEALER SUPPORT PORTAL</span>
          <span className="hidden sm:inline">•</span>
          <span className="text-emerald-400">PHONE: +1 (470) 650-5315</span>
          <span className="hidden sm:inline">•</span>
          <span>EMAIL: info@theastrohealer.com</span>
        </div>
        <p>© 2026 Cosmic Astro Healer AI • Created by Astrologer Malik. All birth coordinates processed securely via internal SQLite charts.</p>
      </footer>

      {/* PREMIUM LICENSING PRICE MODAL CHANGER */}
      <AnimatePresence>
        {showPricingModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setShowPricingModal(false)}
              className="absolute inset-0 bg-slate-950/85 backdrop-blur-sm"
            />

            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 15 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 15 }}
              className="relative w-full max-w-md bg-slate-900 rounded-3xl border border-emerald-500/30 p-6 shadow-2xl z-10 overflow-hidden"
            >
              <div className="absolute -top-12 -left-12 w-32 h-32 bg-emerald-500/5 rounded-full blur-2xl pointer-events-none" />

              <div className="text-center relative">
                <div className="w-12 h-12 rounded-xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center mx-auto mb-2.5">
                  <Crown className="w-6 h-6 text-emerald-400 fill-emerald-500/20" />
                </div>
                
                <h3 className="text-md font-black tracking-tight text-emerald-200 font-mono">
                  ACTIVATE MEMBERSHIP UNLOCK
                </h3>
                <p className="text-[11px] text-slate-400 mt-1 max-w-xs mx-auto leading-normal">
                  Sitaron ki rehnumai me barkat! Choose a bundle package to load coins directly into your spiritual account balance.
                </p>

                {/* Plan options grid selection */}
                <div className="my-4 grid grid-cols-2 gap-2 text-left">
                  {[
                    { key: "basic", label: "Basic", coins: 250, price: "$11" },
                    { key: "medium", label: "Medium", coins: 500, price: "$30" },
                    { key: "ultra", label: "Ultra", coins: 1000, price: "$60" },
                    { key: "max", label: "Max", coins: 1500, price: "$100" }
                  ].map((p) => {
                    const isSelected = selectedPlanKey === p.key;
                    return (
                      <button
                        key={p.key}
                        type="button"
                        onClick={() => setSelectedPlanKey(p.key as any)}
                        className={`p-3 rounded-xl border flex flex-col justify-between transition-all text-left cursor-pointer ${
                          isSelected 
                            ? "bg-emerald-500/15 border-emerald-450 text-slate-100 shadow-md shadow-emerald-950/50" 
                            : "bg-slate-950 border-slate-850 hover:border-slate-800 text-slate-400"
                        }`}
                      >
                        <div className="flex justify-between items-center w-full">
                          <span className={`text-[9px] font-mono font-bold uppercase tracking-wider ${isSelected ? "text-emerald-450" : "text-slate-500"}`}>
                            {p.label}
                          </span>
                          {isSelected && <Star className="w-2.5 h-2.5 text-emerald-400 fill-emerald-400" />}
                        </div>
                        <div className="mt-1 flex items-baseline gap-1">
                          <span className="text-xl font-black font-mono text-slate-100">{p.coins}</span>
                          <span className="text-[9px] text-slate-500 font-mono">coins</span>
                        </div>
                        <div className={`text-[11px] font-bold font-mono mt-0.5 ${isSelected ? "text-emerald-400" : "text-slate-350"}`}>
                          Price: {p.price}
                        </div>
                      </button>
                    );
                  })}
                </div>

                {/* Selected active summary */}
                <div className="mb-4 p-3 rounded-xl bg-slate-950 border border-slate-850 text-left text-xs font-mono leading-relaxed text-slate-300">
                  <p className="font-bold text-slate-200">Selected Plan: <span className="text-emerald-400 font-extrabold uppercase">{selectedPlanKey} Package</span></p>
                  <p className="text-[10px] text-slate-400 mt-1">
                    Adds <span className="text-emerald-300 font-bold">{selectedPlanKey === "basic" ? "250" : selectedPlanKey === "medium" ? "500" : selectedPlanKey === "ultra" ? "1000" : "1500"} coins</span> to your account instantly permitting up to <span className="text-emerald-300 font-bold">{selectedPlanKey === "basic" ? "25" : selectedPlanKey === "medium" ? "50" : selectedPlanKey === "ultra" ? "100" : "150"} detailed astrological consultations</span> with Sufi Malik assistant.
                  </p>
                </div>

                {/* Checkout selection actions */}
                <div className="flex flex-col gap-2">
                  <button
                    onClick={() => handlePurchasePlan(selectedPlanKey)}
                    disabled={isPurchaseLoading}
                    className="w-full py-2.5 px-5 rounded-xl bg-gradient-to-r from-emerald-500 via-emerald-600 to-indigo-650 hover:from-emerald-400 text-slate-950 font-bold font-mono tracking-wider transition-all shadow-md active:translate-y-0 text-sm cursor-pointer flex items-center justify-center gap-2"
                  >
                    {isPurchaseLoading ? (
                      <>
                        <RefreshCw className="w-4 h-4 animate-spin text-slate-950" />
                        <span>PROCESSING STRIPE PORTAL...</span>
                      </>
                    ) : (
                      <>
                        <Crown className="w-4 h-4 text-slate-950" />
                        <span>SECURE STRIPE CHECKOUT ({selectedPlanKey === "basic" ? "$11.00" : selectedPlanKey === "medium" ? "$30.00" : selectedPlanKey === "ultra" ? "$60.00" : "$100.00"})</span>
                      </>
                    )}
                  </button>

                  {/* Sandbox Instant Activation Test option */}
                  <button
                    onClick={() => handleSandboxBypassActivation(selectedPlanKey)}
                    className="w-full py-2 px-5 rounded-xl text-indigo-300 hover:text-indigo-100 transition text-[10px] font-mono tracking-wide bg-indigo-505/10 hover:bg-indigo-500/10 border border-indigo-500/20 font-bold cursor-pointer"
                  >
                    ⚡ SANDBOX FAST DEMO BYPASS (ACTIVATE {selectedPlanKey.toUpperCase()} FREE TEST)
                  </button>

                  <button
                    onClick={() => setShowPricingModal(false)}
                    className="w-full py-1.5 px-5 rounded-xl text-slate-500 hover:text-slate-350 transition text-xs font-mono font-bold hover:bg-slate-950/40"
                  >
                    CLOSE GATE
                  </button>
                </div>
              </div>

            </motion.div>
          </div>
        )}
      </AnimatePresence>

      {/* CELESTIAL PURCHASE SUCCESS STATE OVERLAY */}
      <AnimatePresence>
        {isSuccessPurchaseOverlay && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setIsSuccessPurchaseOverlay(false)}
              className="absolute inset-0 bg-slate-950/90 backdrop-blur-md"
            />

            <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.9 }}
              className="relative w-full max-w-sm bg-gradient-to-b from-slate-900 to-slate-950 rounded-3xl border border-emerald-500/40 p-6 text-center shadow-2xl z-10"
            >
              <div className="w-16 h-16 rounded-full bg-gradient-to-tr from-emerald-500 via-teal-500 to-indigo-500 p-0.5 mx-auto mb-4 animate-bounce">
                <div className="w-full h-full rounded-full bg-slate-950 flex items-center justify-center">
                  <Star className="w-8 h-8 text-emerald-300 fill-emerald-300/20" />
                </div>
              </div>

              <h3 className="text-xl font-extrabold text-emerald-200 tracking-tight font-mono uppercase">
                Mubarak Ho Beta! 🌟
              </h3>
              <p className="text-xs text-slate-300 mt-2.5 font-medium leading-relaxed">
                Your Cosmo VIP alignment key is validated! We successfully upgraded your account balance with your chosen coin package. Ask Astrologer Malik deep consultations and explore your dynamic star coordinates!
              </p>

              <button
                onClick={() => setIsSuccessPurchaseOverlay(false)}
                className="mt-5 w-full py-3 px-6 rounded-xl bg-gradient-to-r from-emerald-500 to-indigo-600 font-bold font-mono tracking-wider text-slate-950 hover:from-emerald-450 transition shadow-lg shadow-indigo-950/40 cursor-pointer text-xs"
              >
                MUBARAK HO! ENTER PORTAL
              </button>
            </motion.div>
          </div>
        )}
      </AnimatePresence>

    </div>
  );
}

// Visual star icon gradient helper
function StarsGradientIcon(props: React.SVGProps<SVGSVGElement>) {
  return (
    <svg 
      viewBox="0 0 24 24" 
      fill="currentColor" 
      stroke="none" 
      {...props}
    >
      <path d="M12 2l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4-3.9-3.8 5.4-.8z" />
    </svg>
  );
}
