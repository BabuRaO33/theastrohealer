// --- KUNDLI CHART DRAWING ENGINE ---
// Renders North or South Indian style Vedic Kundli SVG inside the container.

function renderKundli(containerId, planets, style) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Reset container
    container.innerHTML = "";

    // 1. Group planets by house (1 to 12)
    const houseGroup = {};
    for (let i = 1; i <= 12; i++) {
        houseGroup[i] = [];
    }

    const abbrev = {
        "Ascendant": "As",
        "Sun": "Su",
        "Moon": "Mo",
        "Mars": "Ma",
        "Mercury": "Me",
        "Jupiter": "Ju",
        "Venus": "Ve",
        "Saturn": "Sa",
        "Rahu": "Ra",
        "Ketu": "Ke"
    };

    planets.forEach((p) => {
        const nameKey = p.name;
        const abbrevName = abbrev[nameKey] || nameKey.substring(0, 2);
        const itemStr = abbrevName + (p.retrograde ? " (R)" : "");
        const idx = parseInt(p.house);
        if (idx >= 1 && idx <= 12) {
            houseGroup[idx].push({
                text: itemStr,
                isAsc: nameKey === "Ascendant"
            });
        }
    });

    if (style === "north") {
        // --- NORTH STYLE DIAMOND COORDINATES (Square 400x400) ---
        const housePositions = {
            1: { x: 200, y: 140, titleY: 100 },      // Center Top Diamond
            2: { x: 110, y: 70, titleY: 50 },        // Upper-Left
            3: { x: 70, y: 110, titleY: 90 },        // Lower-Left-Top
            4: { x: 140, y: 200, titleY: 175 },      // Center Left Diamond
            5: { x: 70, y: 290, titleY: 270 },       // Lower-Left-Bottom
            6: { x: 110, y: 330, titleY: 310 },      // Bottom-Left
            7: { x: 200, y: 260, titleY: 235 },      // Center Bottom Diamond
            8: { x: 290, y: 330, titleY: 310 },      // Bottom-Right
            9: { x: 330, y: 290, titleY: 270 },      // Lower-Right-Bottom
            10: { x: 260, y: 200, titleY: 175 },     // Center Right Diamond
            11: { x: 330, y: 110, titleY: 90 },      // Upper-Right-Top
            12: { x: 290, y: 70, titleY: 50 }        // Upper-Right-Bottom
        };

        let svgHtml = `
        <div class="relative w-full max-w-[380px] aspect-square bg-slate-950/60 rounded-xl border border-indigo-500/30 p-4 mx-auto flex flex-col justify-center items-center shadow-lg shadow-indigo-950/20">
            <svg viewBox="0 0 400 400" class="w-full h-full text-indigo-400">
                <!-- Main outer boundary -->
                <rect x="10" y="10" width="380" height="380" fill="none" stroke="currentColor" stroke-width="2.5" class="stroke-indigo-500" />

                <!-- Diagonals -->
                <line x1="10" y1="10" x2="390" y2="390" stroke="currentColor" stroke-width="1.5" class="stroke-indigo-600/60" />
                <line x1="390" y1="10" x2="10" y2="390" stroke="currentColor" stroke-width="1.5" class="stroke-indigo-600/60" />

                <!-- Inner Diamond -->
                <polygon points="200,10 390,200 200,390 10,200" fill="none" stroke="currentColor" stroke-width="2" class="stroke-indigo-500" />
        `;

        Object.keys(housePositions).forEach((hKey) => {
            const hNum = parseInt(hKey);
            const pos = housePositions[hNum];
            const planetsInHouse = houseGroup[hNum];

            svgHtml += `
            <g>
                <!-- House Number -->
                <text x="${pos.x}" y="${pos.titleY}" text-anchor="middle" class="fill-amber-400/80 text-[11px] font-mono font-bold select-none">${hNum}</text>
                <!-- Planets list -->
                <text x="${pos.x}" y="${pos.y + 10}" text-anchor="middle" class="text-[12px] font-sans font-medium">
            `;

            if (planetsInHouse.length > 0) {
                planetsInHouse.forEach((p, pIdx) => {
                    const colorClass = p.isAsc ? "fill-amber-300 font-semibold" : "fill-slate-100 font-medium";
                    svgHtml += `<tspan x="${pos.x}" dy="${pIdx === 0 ? 0 : 13}" class="${colorClass}">${p.text}</tspan>`;
                });
            } else {
                svgHtml += `<tspan class="fill-slate-600/40 text-[9px] font-light">—</tspan>`;
            }

            svgHtml += `
                </text>
            </g>
            `;
        });

        svgHtml += `
            </svg>
            <div class="absolute top-2 right-2 text-[9px] font-mono tracking-wider px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 select-none">
                NORTH STYLE
            </div>
        </div>
        `;

        container.innerHTML = svgHtml;

    } else {
        // --- SOUTH STYLE 4x4 GRID Placements (Square 400x400) ---
        const boxPositions = {
            12: { x: 10, y: 10 },    // Pisces
            1: { x: 105, y: 10 },    // Aries
            2: { x: 200, y: 10 },    // Taurus
            3: { x: 295, y: 10 },    // Gemini
            4: { x: 295, y: 105 },   // Cancer
            5: { x: 295, y: 200 },   // Leo
            6: { x: 295, y: 295 },   // Virgo
            7: { x: 200, y: 295 },   // Libra
            8: { x: 105, y: 295 },   // Scorpio
            9: { x: 10, y: 295 },    // Sagittarius
            10: { x: 10, y: 200 },   // Capricorn
            11: { x: 10, y: 105 }    // Aquarius
        };

        let svgHtml = `
        <div class="relative w-full max-w-[380px] aspect-square bg-slate-950/60 rounded-xl border border-indigo-500/30 p-4 mx-auto flex flex-col justify-center items-center shadow-lg shadow-indigo-950/20">
            <svg viewBox="0 0 400 400" class="w-full h-full text-indigo-400">
                <!-- Main outer frame -->
                <rect x="10" y="10" width="380" height="380" fill="none" stroke="currentColor" stroke-width="2.5" class="stroke-indigo-500" />

                <!-- Vertical inner lines -->
                <line x1="105" y1="10" x2="105" y2="390" stroke="currentColor" stroke-width="1.5" class="stroke-indigo-600/40" />
                <line x1="200" y1="10" x2="200" y2="390" stroke="currentColor" stroke-width="1.5" class="stroke-indigo-600/40" />
                <line x1="295" y1="10" x2="295" y2="390" stroke="currentColor" stroke-width="1.5" class="stroke-indigo-600/40" />

                <!-- Horizontal inner lines -->
                <line x1="10" y1="105" x2="390" y2="105" stroke="currentColor" stroke-width="1.5" class="stroke-indigo-600/40" />
                <line x1="10" y1="200" x2="390" y2="200" stroke="currentColor" stroke-width="1.5" class="stroke-indigo-600/40" />
                <line x1="10" y1="295" x2="390" y2="295" stroke="currentColor" stroke-width="1.5" class="stroke-indigo-600/40" />

                <!-- Center Block Fill -->
                <rect x="105" y="105" width="190" height="190" fill="rgba(15, 23, 42, 0.85)" class="stroke-indigo-500/25" stroke-width="1" />
                <text x="200" y="200" text-anchor="middle" class="fill-indigo-300 text-sm font-medium tracking-widest leading-relaxed">JANAM KUNDLI</text>
        `;

        Object.keys(boxPositions).forEach((bKey) => {
            const bNum = parseInt(bKey);
            const pos = boxPositions[bNum];
            const planetsInHouse = houseGroup[bNum];

            svgHtml += `
            <g>
                <!-- Box House Index -->
                <text x="${pos.x + 8}" y="${pos.y + 16}" class="fill-amber-400/80 text-[10px] font-mono font-bold select-none">${bNum}</text>
                <!-- Planets list inside coordinates -->
                <text x="${pos.x + 47}" y="${pos.y + 40}" text-anchor="middle" class="text-[11px] font-sans">
            `;

            if (planetsInHouse.length > 0) {
                planetsInHouse.forEach((p, pIdx) => {
                    const colorClass = p.isAsc ? "fill-amber-300 font-semibold" : "fill-slate-100 font-medium";
                    svgHtml += `<tspan x="${pos.x + 47}" dy="${pIdx === 0 ? 0 : 12}" class="${colorClass}">${p.text}</tspan>`;
                });
            } else {
                svgHtml += `<tspan class="fill-slate-600/35 text-[8px] font-light">—</tspan>`;
            }

            svgHtml += `
                </text>
            </g>
            `;
        });

        svgHtml += `
            </svg>
            <div class="absolute top-2 right-2 text-[9px] font-mono tracking-wider px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 select-none">
                SOUTH STYLE
            </div>
        </div>
        `;

        container.innerHTML = svgHtml;
    }
}
