import React from "react";
import { PlanetDetail } from "../types";

interface KundliChartProps {
  planets: PlanetDetail[];
  style: "north" | "south";
}

export default function KundliChart({ planets, style }: KundliChartProps) {
  // Group planets by house number (1-12)
  const houseGroup: { [key: number]: string[] } = {};
  for (let i = 1; i <= 12; i++) {
    houseGroup[i] = [];
  }

  // Abbreviate planet names
  const abbrev: { [key: string]: string } = {
    Ascendant: "As",
    Sun: "Su",
    Moon: "Mo",
    Mars: "Ma",
    Mercury: "Me",
    Jupiter: "Ju",
    Venus: "Ve",
    Saturn: "Sa",
    Rahu: "Ra",
    Ketu: "Ke"
  };

  planets.forEach((p) => {
    const key = p.name;
    const nameStr = abbrev[key] || key.substring(0, 2);
    const itemStr = `${nameStr}${p.retrograde ? " (R)" : ""}`;
    if (p.house >= 1 && p.house <= 12) {
      houseGroup[p.house].push(itemStr);
    }
  });

  if (style === "north") {
    // North Indian Chart Coordinates (Square 400x400)
    // House coordinates for placing text labels and grouped planets
    const housePositions: { [key: number]: { x: number; y: number; titleY: number } } = {
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

    return (
      <div className="relative w-full max-w-[380px] aspect-square bg-slate-950/60 rounded-xl border border-indigo-500/30 p-4 mx-auto flex flex-col justify-center items-center shadow-lg shadow-indigo-950/20">
        <svg viewBox="0 0 400 400" className="w-full h-full text-indigo-400">
          {/* Main outer boundary */}
          <rect x="10" y="10" width="380" height="380" fill="none" stroke="currentColor" strokeWidth="2.5" className="stroke-indigo-500" />

          {/* Diagonals */}
          <line x1="10" y1="10" x2="390" y2="390" stroke="currentColor" strokeWidth="1.5" className="stroke-indigo-600/60" />
          <line x1="390" y1="10" x2="10" y2="390" stroke="currentColor" strokeWidth="1.5" className="stroke-indigo-600/60" />

          {/* Inner Diamond (connect midpoints of square) */}
          <polygon points="200,10 390,200 200,390 10,200" fill="none" stroke="currentColor" strokeWidth="2" className="stroke-indigo-500" />

          {/* House Numbers & Planetary content placement */}
          {Object.keys(housePositions).map((hKey) => {
            const hNum = parseInt(hKey);
            const pos = housePositions[hNum];
            const planetsInHouse = houseGroup[hNum];

            return (
              <g key={hNum}>
                {/* House numeric label helper */}
                <text
                  x={pos.x}
                  y={pos.titleY}
                  textAnchor="middle"
                  className="fill-amber-400/80 text-[11px] font-mono font-bold select-none"
                >
                  {hNum}
                </text>

                {/* Planets text container */}
                <text
                  x={pos.x}
                  y={pos.y + 10}
                  textAnchor="middle"
                  className="fill-slate-100 text-[12px] font-medium tracking-tight font-sans"
                >
                  {planetsInHouse.length > 0 ? (
                    planetsInHouse.map((p, pIdx) => (
                      <tspan
                        key={pIdx}
                        x={pos.x}
                        dy={pIdx === 0 ? 0 : 13}
                        className={`${p.startsWith("As") ? "fill-amber-300 font-semibold" : "fill-slate-100 font-medium"}`}
                      >
                        {p}
                      </tspan>
                    ))
                  ) : (
                    <tspan className="fill-slate-600/40 text-[9px] font-light">—</tspan>
                  )}
                </text>
              </g>
            );
          })}
        </svg>

        <div className="absolute top-2 right-2 text-[9px] font-mono tracking-wider px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">
          NORTH STYLE
        </div>
      </div>
    );
  } else {
    // South Indian Style Chart: 4x4 Grid boxes
    // Outer boxes coordinates (indexes from 1 to 12 along the clockwise boundary)
    // Box indices mapping:
    // Row 1: [12, 1, 2, 3]
    // Row 2: [11, null, null, 4]
    // Row 3: [10, null, null, 5]
    // Row 4: [9, 8, 7, 6]
    // Coordinates structure: 400x400. Each square box width = 95px.
    const boxPositions: { [key: number]: { x: number; y: number } } = {
      12: { x: 10, y: 10 },    // Pisces starting
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

    const blockWidth = 95;

    return (
      <div className="relative w-full max-w-[380px] aspect-square bg-slate-950/60 rounded-xl border border-indigo-500/30 p-4 mx-auto flex flex-col justify-center items-center shadow-lg shadow-indigo-950/20">
        <svg viewBox="0 0 400 400" className="w-full h-full text-indigo-400">
          {/* Main outer frame */}
          <rect x="10" y="10" width="380" height="380" fill="none" stroke="currentColor" strokeWidth="2.5" className="stroke-indigo-500" />

          {/* Column lines */}
          <line x1="105" y1="10" x2="105" y2="390" stroke="currentColor" strokeWidth="1.5" className="stroke-indigo-600/40" />
          <line x1="200" y1="10" x2="200" y2="390" stroke="currentColor" strokeWidth="1.5" className="stroke-indigo-600/40" />
          <line x1="295" y1="10" x2="295" y2="390" stroke="currentColor" strokeWidth="1.5" className="stroke-indigo-600/40" />

          {/* Row lines */}
          <line x1="10" y1="105" x2="390" y2="105" stroke="currentColor" strokeWidth="1.5" className="stroke-indigo-600/40" />
          <line x1="10" y1="200" x2="390" y2="200" stroke="currentColor" strokeWidth="1.5" className="stroke-indigo-600/40" />
          <line x1="10" y1="295" x2="390" y2="295" stroke="currentColor" strokeWidth="1.5" className="stroke-indigo-600/40" />

          {/* Solid fill in the empty 2x2 center block */}
          <rect x="105" y="105" width="190" height="190" fill="rgba(15, 23, 42, 0.85)" className="stroke-indigo-500/25" strokeWidth="1" />
          <text x="200" y="200" textAnchor="middle" className="fill-indigo-300 text-sm font-medium tracking-widest leading-relaxed">
            JANAM KUNDLI
          </text>

          {/* Compartments rendering */}
          {Object.keys(boxPositions).map((bKey) => {
            const bNum = parseInt(bKey);
            const pos = boxPositions[bNum];
            const planetsInHouse = houseGroup[bNum];

            return (
              <g key={bNum}>
                {/* Numeric index display for House Reference */}
                <text
                  x={pos.x + 8}
                  y={pos.y + 16}
                  className="fill-amber-400/80 text-[10px] font-mono font-bold select-none"
                >
                  {bNum}
                </text>

                {/* Planets listing text */}
                <text
                  x={pos.x + 47}
                  y={pos.y + 40}
                  textAnchor="middle"
                  className="fill-slate-100 text-[11px] font-sans"
                >
                  {planetsInHouse.length > 0 ? (
                    planetsInHouse.map((p, pIdx) => (
                      <tspan
                        key={pIdx}
                        x={pos.x + 47}
                        dy={pIdx === 0 ? 0 : 12}
                        className={`${p.startsWith("As") ? "fill-amber-300 font-semibold" : "fill-slate-100 font-medium"}`}
                      >
                        {p}
                      </tspan>
                    ))
                  ) : (
                    <tspan className="fill-slate-600/30 text-[8px] font-light">—</tspan>
                  )}
                </text>
              </g>
            );
          })}
        </svg>

        <div className="absolute top-2 right-2 text-[9px] font-mono tracking-wider px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">
          SOUTH STYLE
        </div>
      </div>
    );
  }
}
