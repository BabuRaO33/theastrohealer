// --- THE ASTRO HEALER - PORTABLE CLIENT STATE ENGINE ---

// Global App State
const state = {
    user: null,
    planets: [],
    chartStyle: 'north', // 'north' or 'south'
    activeTab: 'chart', // 'chart', 'strengths', 'horoscope', 'admin'
    currentForecastSignId: 1,
    chatLoading: false,
    forecastLoading: false
};

document.addEventListener("DOMContentLoaded", () => {
    handleHashCallback();

    const cachedUser = sessionStorage.getItem("astro_user");
    if (cachedUser) {
        try {
            state.user = JSON.parse(cachedUser);
            setupSessionUI();
        } catch (e) {
            sessionStorage.removeItem("astro_user");
            showAuthScreen();
        }
    } else {
        showAuthScreen();
    }
});

function showAuthScreen() {
    document.getElementById("auth-screen").classList.remove("hidden");
    document.getElementById("dashboard-screen").classList.add("hidden");
    document.getElementById("user-header-badges").classList.add("hidden");
    isLoginMode = false;
    toggleAuthMode();
    toggleAuthMode();
    lucide.createIcons();
}

// Switch active tabs
function switchTab(tabId) {
    state.activeTab = tabId;
    
    // Hide all views
    document.getElementById("view-chart").classList.add("hidden");
    document.getElementById("view-strengths").classList.add("hidden");
    document.getElementById("view-horoscope").classList.add("hidden");
    document.getElementById("view-admin").classList.add("hidden");
    
    // De-activate all tab buttons
    document.getElementById("tab-btn-chart").className = "flex-1 px-3 py-2 rounded-xl text-slate-400 hover:text-slate-100 font-extrabold flex items-center justify-center gap-1.5 transition";
    document.getElementById("tab-btn-strengths").className = "flex-1 px-3 py-2 rounded-xl text-slate-400 hover:text-slate-100 font-extrabold flex items-center justify-center gap-1.5 transition";
    document.getElementById("tab-btn-horoscope").className = "flex-1 px-3 py-2 rounded-xl text-slate-400 hover:text-slate-100 font-extrabold flex items-center justify-center gap-1.5 transition";
    document.getElementById("tab-btn-admin").className = "hidden px-3 py-2 rounded-xl text-slate-400 hover:text-slate-100 font-extrabold flex items-center justify-center gap-1.5 transition border border-amber-500/10";
    
    // Enable visible Admin tab tab button if user is administrator
    if (state.user && state.user.role === 'admin') {
        document.getElementById("tab-btn-admin").classList.remove("hidden");
    }

    // Activate selected
    document.getElementById(`view-${tabId}`).classList.remove("hidden");
    const activeBtn = document.getElementById(`tab-btn-${tabId}`);
    if (activeBtn) {
        if (tabId === 'admin') {
            activeBtn.className = "flex-1 px-3 py-2 rounded-xl bg-amber-500/10 border border-amber-400/30 text-amber-300 font-extrabold flex items-center justify-center gap-1.5 transition";
        } else {
            activeBtn.className = "flex-1 px-3 py-2 rounded-xl bg-indigo-650 text-white font-extrabold flex items-center justify-center gap-1.5 transition";
        }
    }
    
    // Handle special render actions
    if (tabId === 'chart') {
        triggerChartDraw();
    } else if (tabId === 'strengths') {
        renderStrengths();
    } else if (tabId === 'horoscope') {
        loadDailyForecast();
    } else if (tabId === 'admin') {
        syncAdminMetrics();
    }
}

// Toggle North/South Indian Chart style
function toggleChartStyle() {
    state.chartStyle = state.chartStyle === 'north' ? 'south' : 'north';
    document.getElementById("chart-style-label").innerText = state.chartStyle === 'north' ? "NORTH INDEX" : "SOUTH INDEX";
    triggerChartDraw();
}

// Draw the Kundili Chart using the external engine
function triggerChartDraw() {
    if (state.planets && state.planets.length > 0) {
        renderKundli("chart-svg-container", state.planets, state.chartStyle);
    } else {
        document.getElementById("chart-svg-container").innerHTML = `
            <div class="py-12 text-center text-xs text-slate-500 font-mono">
                Casting Kundli chart placements...
            </div>
        `;
    }
}

// Renders the planet strengths
function renderStrengths() {
    const listContainer = document.getElementById("strengths-indicators");
    if (!listContainer) return;
    
    if (!state.planets || state.planets.length === 0) {
        listContainer.innerHTML = `<div class="text-center text-xs text-slate-500 py-6">Planetary configurations not verified yet.</div>`;
        return;
    }
    
    let html = '<div class="space-y-3.5">';
    state.planets.forEach(p => {
        const val = p.strength || 65;
        let colorClass = "bg-indigo-600";
        if (val > 80) colorClass = "bg-amber-500";
        else if (val < 50) colorClass = "bg-red-500";
        
        html += `
        <div class="bg-slate-950/40 p-3 rounded-xl border border-indigo-950/20">
            <div class="flex justify-between items-center mb-1.5">
                <div class="flex items-center gap-2">
                    <span class="font-outfit font-bold text-xs text-white">${p.name}</span>
                    <span class="text-[10px] font-mono text-slate-400">House ${p.house} (${p.sign})</span>
                </div>
                <span class="text-xs font-mono font-bold text-slate-200">${val}%</span>
            </div>
            <div class="w-full bg-slate-900 rounded-full h-1.5 overflow-hidden">
                <div class="${colorClass} h-1.5 rounded-full" style="width: ${val}%"></div>
            </div>
            <div class="flex justify-between text-[9px] text-slate-500 mt-1 font-mono">
                <span>Lord: ${p.lord}</span>
                <span>Nakshatra: ${p.nakshatra || 'Ashwini'}</span>
            </div>
        </div>
        `;
    });
    html += '</div>';
    listContainer.innerHTML = html;
}

// Call API endpoint
async function apiCall(action, payload = {}) {
    try {
        let endpoint = `api/${action}`;
        if (typeof window.ASTRO_API_ENDPOINT !== 'undefined' && window.ASTRO_API_ENDPOINT) {
            endpoint = `${window.ASTRO_API_ENDPOINT}?api=${action}`;
        } else if (window.location.pathname.endsWith('ai.php')) {
            endpoint = `ai.php?api=${action}`;
        } else if (window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/astrobot/')) {
            endpoint = `index.php?api=${action}`;
        }
        const response = await fetch(endpoint, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        return data;
    } catch(err) {
        console.error(`API Error [${action}]: `, err);
        throw err;
    }
}


// Authentication screen handler
let isLoginMode = false;
function toggleAuthMode() {
    isLoginMode = !isLoginMode;
    const sFields = document.getElementById("signup-fields");
    const authTitle = document.getElementById("auth-title");
    const authSub = document.getElementById("auth-subtitle");
    const labelText = document.getElementById("toggle-label-text");
    const toggleBtn = document.getElementById("toggle-auth-btn");
    const btnText = document.getElementById("auth-btn-text");
    
    if (isLoginMode) {
        sFields.classList.add("hidden");
        authTitle.innerText = "Enter Astrology Room";
        authSub.innerText = "Access your customized charts and session questions instantly.";
        labelText.innerText = "New client seeking readings?";
        toggleBtn.innerText = "Cast Birth Configurations";
        btnText.innerText = "Restore Spiritual Session";
    } else {
        sFields.classList.remove("hidden");
        authTitle.innerText = "Create Spiritual Session";
        authSub.innerText = "Enter your email and birth details to cast your Janam Kundli and begin consultations.";
        labelText.innerText = "Already configured birth map?";
        toggleBtn.innerText = "Load Session Login";
        btnText.innerText = "Cast Astro Settings";
    }
}

// Run Register or Login
async function handleAuth(e) {
    e.preventDefault();
    hideAuthAlert();
    
    const email = document.getElementById("auth-email").value.trim();
    const password = document.getElementById("auth-password").value;
    const btn = document.getElementById("auth-btn");
    
    btn.disabled = true;
    btn.classList.add("opacity-75");
    
    try {
        if (isLoginMode) {
            // Login
            const res = await apiCall("login", { email, password });
            if (res.success) {
                state.user = res.user;
                sessionStorage.setItem("astro_user", JSON.stringify(res.user));
                setupSessionUI();
            }
        } else {
            // Register - Geocode first
            const name = document.getElementById("reg-name").value.trim() || "Believer";
            const phone = document.getElementById("reg-phone").value.trim();
            const dob = document.getElementById("reg-dob").value;
            const tob = document.getElementById("reg-tob").value;
            const place = document.getElementById("reg-place").value.trim() || "Lahore";
            
            if (!name || !dob || !tob) {
                throw new Error("Please enter email, date of birth, and name.");
            }
            
            // Geolocate place
            let geoData = { lat: 31.5204, lon: 74.3587, timezone: 5.0, label: place };
            try {
                const geo = await apiCall("geolocate", { place });
                if (geo.lat) geoData = geo;
            } catch(e) {
                console.log("Using default coordinates for birth place");
            }
            
            const signupPayload = {
                email, password, name, phone, dob, tob, place: geoData.label || place,
                lat: geoData.lat, lon: geoData.lon, timezone: geoData.timezone
            };
            
            const res = await apiCall("register", signupPayload);
            if (res.success) {
                state.user = res.user;
                sessionStorage.setItem("astro_user", JSON.stringify(res.user));
                setupSessionUI();
            }
        }
    } catch(err) {
        showAuthAlert(err.message || "Failed to parse astronomical details.");
    } finally {
        btn.disabled = false;
        btn.classList.remove("opacity-75");
    }
}

// UI Alert Controls
function showAuthAlert(msg) {
    const alert = document.getElementById("auth-alert");
    document.getElementById("auth-alert-text").innerText = msg;
    alert.classList.remove("hidden");
    lucide.createIcons();
}
function hideAuthAlert() {
    document.getElementById("auth-alert").classList.add("hidden");
}

function showBotNotice(message) {
    if (state.user) {
        if (!state.user.chatHistory) state.user.chatHistory = [];
        state.user.chatHistory.push({ role: 'model', text: message, timestamp: Date.now() });
        renderChatMessages();
        sessionStorage.setItem("astro_user", JSON.stringify(state.user));
        return;
    }
    showAuthAlert(message);
}

// Configure post-authentication room
async function setupSessionUI() {
    if (!state.user) return;
    
    // Hide Auth Screen, Show Dashboard
    document.getElementById("auth-screen").classList.add("hidden");
    document.getElementById("dashboard-screen").classList.remove("hidden");
    document.getElementById("user-header-badges").classList.remove("hidden");
    
    // Fill Header details
    document.getElementById("header-coins").innerText = state.user.coins;
    
    // Render Chat history
    renderChatMessages();
    
    // Load astrology placements and forecasts
    await loadAstrologyPlacements();
    
    // Manage admin view selector tab status
    const adminTabBtn = document.getElementById("tab-btn-admin");
    if (state.user.role === 'admin') {
        adminTabBtn.classList.remove("hidden");
    } else {
        adminTabBtn.classList.add("hidden");
    }
    
    // Reset to Chart view
    switchTab("chart");
    lucide.createIcons();
}

// Logouts the current room
function logout() {
    state.user = null;
    state.planets = [];
    sessionStorage.removeItem("astro_user");
    sessionStorage.setItem("astro_logout", "true");
    location.reload();
}

// Loads astrology planetary details
async function loadAstrologyPlacements() {
    if (!state.user) return;
    
    try {
        const dobArr = state.user.dob.split("-");
        const tobArr = state.user.tob.split(":");
        const dPayload = {
            day: dobArr[2] ? parseInt(dobArr[2]) : 18,
            month: dobArr[1] ? parseInt(dobArr[1]) : 10,
            year: dobArr[0] ? parseInt(dobArr[0]) : 1995,
            hour: tobArr[0] ? parseInt(tobArr[0]) : 8,
            min: tobArr[1] ? parseInt(tobArr[1]) : 30,
            lat: state.user.lat || 31.5204,
            lon: state.user.lon || 74.3587,
            timezone: state.user.timezone || 5.0
        };
        
        const details = await apiCall("planet-details", dPayload);
        state.planets = details.planets || [];
        
        // Match dynamic moon sign ID for Daily forecasts
        const moon = state.planets.find(p => p.name === "Moon");
        if (moon) {
            state.currentForecastSignId = moon.sign_id || 1;
            document.getElementById("forecast-moon-sign").innerText = moon.sign || "Aries";
        }
        
        triggerChartDraw();
    } catch(e) {
        console.error("Error loading planet parameters", e);
    }
}

// Load dynamic daily horoscope forecasting
async function loadDailyForecast() {
    const txtContainer = document.getElementById("forecast-text");
    const loader = document.getElementById("forecast-loading");
    
    txtContainer.innerHTML = "";
    loader.classList.remove("hidden");
    
    try {
        const res = await apiCall("daily-forecast?sign_id=" + state.currentForecastSignId);
        txtContainer.innerHTML = `
            <div class="prose max-w-none prose-invert font-sans text-xs md:text-sm">
                ${res.prediction.replace(/\n/g, '<br>')}
            </div>
        `;
    } catch(err) {
        txtContainer.innerHTML = `
            <div class="text-center py-8 text-xs text-slate-400">
                ⭐ Forecast temporarily offline. Please try again shortly.
            </div>
        `;
    } finally {
        loader.classList.add("hidden");
    }
}

// Render local chat list messages
function renderChatMessages() {
    const container = document.getElementById("chat-messages-container");
    if (!container) return;
    
    const history = state.user ? (state.user.chatHistory || []) : [];
    
    if (history.length === 0) {
        container.innerHTML = `
            <div class="h-full flex flex-col items-center justify-center text-center p-6 py-12">
                <i data-lucide="compass" class="w-8 h-8 text-indigo-500 mb-3 animate-pulse"></i>
                <h4 class="font-outfit font-bold text-sm text-slate-300">Salaam-o-Alaikum</h4>
                <p class="text-[11px] text-slate-500 mt-1 max-w-[220px]">Kundli complete. Ask Malik Assistant about career, marriage, travel, or any life question.</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    let html = "";
    history.forEach(msg => {
        const isModel = msg.role === 'model';
        
        if (isModel) {
            html += `
            <div class="flex items-start gap-2.5 max-w-[90%]">
                <div class="w-7 h-7 rounded-full bg-indigo-950 border border-indigo-500/20 text-amber-400 flex items-center justify-center text-[10px] shrink-0 select-none">M</div>
                <div class="bg-indigo-950/40 border border-indigo-900/35 p-3 rounded-2xl rounded-tl-sm text-slate-200 text-xs md:text-[12.5px] leading-relaxed block break-words space-y-2 select-text font-sans">
                    ${msg.text.replace(/\n/g, '<br>')}
                </div>
            </div>
            `;
        } else {
            html += `
            <div class="flex items-start gap-2.5 justify-end max-w-[90%] ml-auto">
                <div class="bg-indigo-600 border border-indigo-500/10 p-3 rounded-2xl rounded-tr-sm text-white text-xs md:text-[12.5px] leading-relaxed block break-words select-text font-sans shadow-md">
                    ${msg.text}
                </div>
                <div class="w-7 h-7 rounded-full bg-slate-850 border border-slate-750 text-slate-300 flex items-center justify-center text-[10px] shrink-0 font-bold uppercase select-none">${state.user.name.substring(0, 2)}</div>
            </div>
            `;
        }
    });
    
    container.innerHTML = html;
    
    // Auto Scroll to bottom
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
    }, 50);
}

// Send chat message
async function handleSendChat(e) {
    if (e) e.preventDefault();
    if (state.chatLoading) return;
    
    const input = document.getElementById("chat-input-text");
    const q = input.value.trim();
    if (!q) return;
    
    input.value = "";
    state.chatLoading = true;
    
    // Optimistic UI insert
    if (!state.user.chatHistory) state.user.chatHistory = [];
    state.user.chatHistory.push({ role: 'user', text: q, timestamp: Date.now() });
    renderChatMessages();
    
    // Manage local coin deduction in preview
    const chatInputBtn = document.getElementById("chat-send-btn");
    chatInputBtn.disabled = true;
    chatInputBtn.innerHTML = `<i data-lucide="refresh-cw" class="w-4 h-4 animate-spin text-slate-400"></i>`;
    lucide.createIcons();
    
    try {
        const payload = {
            email: state.user.email,
            question: q,
            planetsList: state.planets
        };
        
        const res = await apiCall("chat", payload);
        
        // Update user state returned from server
        state.user.coins = res.coins;
        state.user.questionsAsked = res.questionsAsked;
        state.user.chatHistory = res.chatHistory;
        
        // Update UI coin displays
        document.getElementById("header-coins").innerText = res.coins;
        renderChatMessages();
        
        // Trigger alerts if funds empty
        if (res.coins < 10 && state.user.role !== 'admin') {
            document.getElementById("chat-alert-box").classList.remove("hidden");
            const alertText = document.querySelector("#chat-alert-box span");
            if (alertText && (res.questionsAsked || 0) >= 5) {
                alertText.innerText = "Your 5 free questions are complete. Purchase coins to continue chatting.";
            }
        } else {
            document.getElementById("chat-alert-box").classList.add("hidden");
        }
        
    } catch(err) {
        // If insufficient funds, trigger modal / alert
        if (err.message === 'insufficient_funds') {
            document.getElementById("chat-alert-box").classList.remove("hidden");
            const alertText = document.querySelector("#chat-alert-box span");
            if (alertText) {
                alertText.innerText = (state.user.questionsAsked || 0) >= 5
                    ? "Your 5 free questions are complete. Purchase coins to continue chatting."
                    : "Low balance: minimum 10 coins required per question.";
            }
            state.user.chatHistory.pop();
            renderChatMessages();
            openBillingModal();
        } else {
            console.error(err);
            state.user.chatHistory.push({ role: 'model', text: "Connection issue. Please check internet and try again.", timestamp: Date.now() });
            renderChatMessages();
        }
    } finally {
        state.chatLoading = false;
        chatInputBtn.disabled = false;
        chatInputBtn.innerHTML = `<i data-lucide="send" class="w-4 h-4"></i>`;
        lucide.createIcons();
        sessionStorage.setItem("astro_user", JSON.stringify(state.user));
    }
}

// Load suggested pill
function loadSuggested(txt) {
    document.getElementById("chat-input-text").value = txt;
    handleSendChat();
}

// Payment trigger checkout
async function purchasePack(planKey) {
    if (!state.user) return;
    
    const checkoutBtn = document.getElementById(`pay-pack-${planKey}`);
    if (checkoutBtn) {
        checkoutBtn.disabled = true;
        checkoutBtn.innerText = "Loading Gate...";
    }
    
    try {
        const res = await apiCall("payment-checkout", { email: state.user.email, planKey });
        if (res.url) {
            // Forward user directly to checkout
            window.location.href = res.url;
        }
    } catch(err) {
        showBotNotice("Payment could not start: " + err.message);
    } finally {
        if (checkoutBtn) {
            checkoutBtn.disabled = false;
            checkoutBtn.innerText = "Confirm Top-Up";
        }
    }
}

// Check url Hash on load to handle checkout callbacks safely
async function handleHashCallback() {
    const hash = window.location.hash || "";
    
    if (hash.includes("payment-success")) {
        // Query params in hash
        const params = new URLSearchParams(hash.split("?")[1] || "");
        const email = params.get("email");
        const sessionId = params.get("session_id");
        const plan = params.get("plan") || "basic";
        
        if (email && sessionId) {
            // Render verification status inside chat box optimistically
            document.getElementById("chat-messages-container").innerHTML = `
                <div class="text-center py-12 flex flex-col justify-center items-center gap-3">
                    <i data-lucide="refresh-cw" class="w-7 h-7 animate-spin text-amber-400"></i>
                    <p class="text-xs font-mono text-slate-450">Verifying secure spiritual donation top-up...</p>
                </div>
            `;
            lucide.createIcons();
            
            try {
                // Call verification API
                const verifyRes = await apiCall("payment-verify", { email, sessionId, plan });
                if (verifyRes.success) {
                    state.user = verifyRes.user;
                    sessionStorage.setItem("astro_user", JSON.stringify(verifyRes.user));
                    
                    // Setup rooms normally
                    setupSessionUI();
                    
                    showBotNotice("Payment verified. Your coins have been credited successfully.");
                }
            } catch(e) {
                showBotNotice("Top-up validation delayed. Please contact admin support: " + e.message);
                setupSessionUI();
            }
        }
        window.location.hash = "";
    } else if (hash.includes("payment-cancelled")) {
        showBotNotice("Payment was cancelled. You can top up anytime from Buy Pack.");
        window.location.hash = "";
    }
}

// Sync Admin metrics ledger from server
async function syncAdminMetrics() {
    if (!state.user || state.user.role !== 'admin') return;
    
    const userList = document.getElementById("admin-users-list");
    const txList = document.getElementById("admin-transactions-list");
    userList.innerHTML = `<div class="text-center text-xs text-slate-500 py-4 font-mono">Syncing database records...</div>`;
    if (txList) txList.innerHTML = `<div class="text-center text-xs text-slate-500 py-4 font-mono">Syncing payment records...</div>`;
    
    try {
        const metrics = await apiCall("admin-metrics");
        
        // Write metrics stats
        document.getElementById("admin-stat-users").innerText = metrics.totalUsers;
        document.getElementById("admin-stat-vip").innerText = metrics.totalGuests || 0;
        document.getElementById("admin-stat-revenue").innerText = `$${metrics.totalRevenue.toFixed(2)}`;
        
        // Write accounts list
        let html = "";
        metrics.users.forEach(u => {
            const timeAgo = new Date(parseInt(u.createdAt)).toLocaleDateString();
            const badgeClass = u.plan === 'premium' ? 'border-amber-500/25 text-amber-300' : 'border-slate-800 text-slate-500';
            
            html += `
            <div class="p-2.5 bg-slate-900 border border-slate-850 rounded-xl flex items-center justify-between text-[11px] font-sans">
                <div>
                    <h5 class="font-bold text-white leading-none">${u.name} <span class="text-[9px] text-slate-500 font-mono">(${u.role || 'user'})</span></h5>
                    <p class="text-[9.5px] text-slate-450 mt-1 font-mono">${u.email}${u.phone ? ' • ' + u.phone : ''} • Joined: ${timeAgo}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-md border text-[9px] font-mono leading-none ${badgeClass}">${u.plan.toUpperCase()}</span>
                    <span class="font-mono text-indigo-400 font-bold">${u.coins}c</span>
                    <button onclick="simulateAdminGift('${u.email}')" class="p-1 rounded bg-slate-950 hover:bg-slate-900 text-amber-400 hover:text-amber-300 border border-slate-850" title="Gift 250 Sandbox Coins">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>
            `;
        });
        
        userList.innerHTML = html;

        if (txList) {
            const planCoins = { basic: 250, medium: 500, ultra: 1000, max: 1500 };
            let txHtml = "";
            const transactions = metrics.transactions || [];
            transactions.forEach(tx => {
                const txDate = tx.timestamp ? new Date(parseInt(tx.timestamp)).toLocaleString() : "Unknown date";
                const plan = (tx.planKey || "basic").toLowerCase();
                const coins = planCoins[plan] || 250;
                txHtml += `
                <div class="p-2.5 bg-slate-900 border border-slate-850 rounded-xl text-[11px] font-sans">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <h5 class="font-bold text-white leading-none truncate">${tx.email || "unknown email"}</h5>
                            <p class="text-[9.5px] text-slate-450 mt-1 font-mono">${txDate}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="px-2 py-0.5 rounded-md border border-emerald-500/20 text-emerald-300 text-[9px] font-mono leading-none">$${Number(tx.amount || 0).toFixed(2)}</span>
                            <p class="text-[9.5px] text-indigo-300 mt-1 font-mono">${plan.toUpperCase()} • ${coins} coins</p>
                        </div>
                    </div>
                    <p class="text-[9px] text-slate-600 mt-1 font-mono break-all">Session: ${tx.sessionId || "n/a"}</p>
                </div>
                `;
            });
            txList.innerHTML = txHtml || `<div class="text-center text-xs text-slate-500 py-4 font-mono">No payments yet.</div>`;
        }
        lucide.createIcons();
    } catch(err) {
        userList.innerHTML = `<div class="text-xs text-red-400 p-4">Sync failed: ${err.message}</div>`;
        if (txList) txList.innerHTML = `<div class="text-xs text-red-400 p-4">Payment sync failed: ${err.message}</div>`;
    }
}

// Admin mock coin top-ups
async function simulateAdminGift(email) {
    if (!state.user || state.user.role !== 'admin') return;
    
    try {
        const res = await apiCall("manual-purchase", { email, planKey: "basic" });
        if (res.email) {
            // Auto Update admin himself if he gifted to himself
            if (state.user.email === email) {
                state.user = res;
                sessionStorage.setItem("astro_user", JSON.stringify(res));
                document.getElementById("header-coins").innerText = res.coins;
            }
            showBotNotice(`Awarded 250 coins to ${email}.`);
            syncAdminMetrics();
        }
    } catch(e) {
        showBotNotice("Operation blocked: " + e.message);
    }
}

// Reset current session for testing
async function resetSession() {
    if (!state.user) return;
    
    if (confirm("Reset current reading and coins to default list?")) {
        try {
            const res = await apiCall("reset", { email: state.user.email });
            state.user = res;
            sessionStorage.setItem("astro_user", JSON.stringify(res));
            setupSessionUI();
            showBotNotice("Room session reset.");
        } catch(e) { showBotNotice("Operation blocked: " + e.message); }
    }
}

// Billing Packages screen modal controls
function openBillingModal() {
    document.getElementById("billing-modal").classList.remove("hidden");
    lucide.createIcons();
}
function closeBillingModal() {
    document.getElementById("billing-modal").classList.add("hidden");
}

function openProfileModal() {
    if (!state.user) return;
    const el = document.getElementById("profile-details");
    el.innerHTML = `
        <div class="p-3 rounded-xl bg-slate-950 border border-slate-850">
            <p class="text-[10px] text-slate-500 uppercase font-mono">Name</p>
            <p class="text-slate-100 font-bold">${state.user.name || "Guest"}</p>
        </div>
        <div class="p-3 rounded-xl bg-slate-950 border border-slate-850">
            <p class="text-[10px] text-slate-500 uppercase font-mono">Email / Phone</p>
            <p class="text-slate-100 font-bold break-all">${state.user.email || "Guest"}</p>
            <p class="text-slate-400 text-xs mt-1">${state.user.phone || "Phone not added"}</p>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <div class="p-3 rounded-xl bg-slate-950 border border-slate-850 text-center">
                <p class="text-[10px] text-slate-500 uppercase font-mono">Plan</p>
                <p class="text-amber-300 font-black uppercase">${state.user.plan || "free"}</p>
            </div>
            <div class="p-3 rounded-xl bg-slate-950 border border-slate-850 text-center">
                <p class="text-[10px] text-slate-500 uppercase font-mono">Coins</p>
                <p class="text-indigo-300 font-black">${state.user.coins || 0}</p>
            </div>
            <div class="p-3 rounded-xl bg-slate-950 border border-slate-850 text-center">
                <p class="text-[10px] text-slate-500 uppercase font-mono">Questions</p>
                <p class="text-emerald-300 font-black">${state.user.questionsAsked || 0}</p>
            </div>
        </div>
    `;
    document.getElementById("profile-modal").classList.remove("hidden");
    lucide.createIcons();
}

function closeProfileModal() {
    document.getElementById("profile-modal").classList.add("hidden");
}
