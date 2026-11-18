import React from "react";
import { 
  Crown, Users, Star, DollarSign, Zap, RefreshCw, AlertCircle 
} from "lucide-react";

interface AdminDashboardProps {
  adminMetrics: {
    totalUsers: number;
    totalRevenue: number;
    transactions: any[];
    users: any[];
  } | null;
  adminMetricsLoading: boolean;
  fetchAdminMetrics: () => Promise<void>;
}

export default function AdminDashboard({ 
  adminMetrics, 
  adminMetricsLoading, 
  fetchAdminMetrics 
}: AdminDashboardProps) {

  return (
    <div className="space-y-6" id="admin-hq-dashboard">
      
      {/* Welcome Header bar */}
      <div className="bg-slate-900/40 backdrop-blur-md rounded-2xl border border-amber-500/15 p-5 shadow-xl relative overflow-hidden flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div className="absolute top-0 right-0 w-32 h-32 bg-amber-500/5 rounded-full blur-2xl pointer-events-none" />
        <div className="flex items-center gap-3.5">
          <div className="w-11 h-11 rounded-xl bg-amber-500/10 border border-amber-500/35 flex items-center justify-center shrink-0 shadow-lg">
            <Crown className="w-5.5 h-5.5 text-amber-400 fill-amber-500/20" />
          </div>
          <div>
            <div className="flex items-center gap-1.5">
              <h2 className="text-base font-black tracking-wider text-amber-100 uppercase font-mono">ASTROBOT CONTROL PANEL</h2>
              <span className="px-2 py-0.5 rounded-full text-[8px] bg-emerald-400/10 text-emerald-400 border border-emerald-450/20 font-bold uppercase tracking-wider">SUPER USER</span>
            </div>
            <p className="text-[11px] text-slate-400 leading-normal font-sans">
              Complete real-time spiritual billing metrics, registered client emails list, and transaction database.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2 font-mono">
          <button
            type="button"
            onClick={fetchAdminMetrics}
            disabled={adminMetricsLoading}
            className="px-3.5 py-1.5 rounded-xl bg-slate-950 border border-slate-850 text-slate-300 hover:text-slate-100 font-bold text-xs flex items-center gap-2 transition duration-150 cursor-pointer active:translate-y-0.5"
          >
            <RefreshCw className={`w-3.5 h-3.5 text-amber-400 ${adminMetricsLoading ? "animate-spin" : ""}`} />
            <span>SYNC ACCOUNT DATA</span>
          </button>
        </div>
      </div>

      {/* Stats summary list row (Mockup design style requested with live metrics sum) */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        
        {/* USERS CARD */}
        <div className="p-4 rounded-2xl bg-slate-900/40 border border-slate-850 shadow-xl relative overflow-hidden">
          <div className="absolute -top-6 -right-6 w-16 h-16 bg-indigo-500/5 rounded-full blur-xl pointer-events-none" />
          <div className="flex justify-between items-center text-slate-500 text-[10px] font-mono uppercase tracking-wider font-bold">
            <span>USERS REGISTERED</span>
            <Users className="w-3.5 h-3.5 text-indigo-400" />
          </div>
          <div className="text-2xl sm:text-3xl font-black font-mono text-slate-100 mt-2">
            {adminMetrics?.totalUsers ?? 0}
          </div>
          <p className="text-[9.5px] text-slate-500 mt-1 font-mono">Total customer accounts</p>
        </div>

        {/* PREMIUM CONVERTS CARD */}
        <div className="p-4 rounded-2xl bg-slate-900/40 border border-slate-850 shadow-xl relative overflow-hidden">
          <div className="absolute -top-6 -right-6 w-16 h-16 bg-amber-500/5 rounded-full blur-xl pointer-events-none" />
          <div className="flex justify-between items-center text-slate-500 text-[10px] font-mono uppercase tracking-wider font-bold">
            <span>VIP PURCHASES</span>
            <Star className="w-3.5 h-3.5 text-amber-400" />
          </div>
          <div className="text-2xl sm:text-3xl font-black font-mono text-amber-400 mt-2">
            {adminMetrics?.users?.filter(u => u.plan === "premium").length ?? 0}
          </div>
          <p className="text-[9.5px] text-slate-500 mt-1 font-mono">Paid membership conversions</p>
        </div>

        {/* TOTAL REVENUE COLLECTED - SPECIFIED IN REQUEST */}
        <div className="p-4 rounded-2xl bg-slate-900/40 border border-emerald-500/15 shadow-emerald-950/5 shadow-xl relative overflow-hidden bg-gradient-to-tr from-emerald-500/5 to-slate-900/40">
          <div className="absolute -top-6 -right-6 w-16 h-16 bg-emerald-500/10 rounded-full blur-xl pointer-events-none" />
          <div className="flex justify-between items-center text-emerald-400/70 text-[10px] font-mono uppercase tracking-wider font-bold">
            <span>TOTAL REVENUE</span>
            <DollarSign className="w-3.5 h-3.5 text-emerald-400" />
          </div>
          <div className="text-2xl sm:text-3xl font-black font-mono text-emerald-400 mt-2">
            ${adminMetrics?.totalRevenue?.toFixed(2) ?? "0.00"}
          </div>
          <p className="text-[9.5px] text-emerald-500/60 mt-1 font-mono font-semibold">Total revenue from chatbot</p>
        </div>

        {/* ACTIVE COIN FLOW CARD */}
        <div className="p-4 rounded-2xl bg-slate-900/40 border border-slate-850 shadow-xl relative overflow-hidden">
          <div className="absolute -top-6 -right-6 w-16 h-16 bg-teal-500/5 rounded-full blur-xl pointer-events-none" />
          <div className="flex justify-between items-center text-slate-500 text-[10px] font-mono uppercase tracking-wider font-bold">
            <span>ACTIVE COINS OUT</span>
            <Zap className="w-3.5 h-3.5 text-teal-400" />
          </div>
          <div className="text-2xl sm:text-3xl font-black font-mono text-slate-100 mt-2">
            {adminMetrics?.users?.reduce((sum, u) => sum + (u.coins || 0), 0) ?? 0}
          </div>
          <p className="text-[9.5px] text-slate-500 mt-1 font-mono">Cosmo coin circulation ledger</p>
        </div>

      </div>

      {/* Side-by-Side Account List & Payments ledger */}
      <div className="grid grid-cols-1 xl:grid-cols-12 gap-5 items-stretch">
        
        {/* FIRST COLUMN: REGISTERED USERS DETAIL */}
        <div className="xl:col-span-6 bg-slate-900/40 backdrop-blur-md rounded-3xl border border-slate-850 shadow-xl flex flex-col overflow-hidden min-h-[460px]">
          
          <div className="px-5 py-4 border-b border-slate-800 bg-slate-950/60 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Users className="w-4 h-4 text-indigo-400" />
              <div>
                <h3 className="font-extrabold text-xs text-indigo-200 uppercase tracking-widest font-mono">Registered Astro Accounts</h3>
                <span className="text-[9px] text-slate-500 font-mono">List of client profiles on database</span>
              </div>
            </div>
            <span className="text-[10px] bg-indigo-500/10 border border-indigo-500/20 px-2.5 py-0.5 rounded text-indigo-300 font-bold font-mono">
              {adminMetrics?.users?.length ?? 0} Accounts
            </span>
          </div>

          {/* List area container */}
          <div className="flex-1 overflow-y-auto p-4 space-y-2 max-h-[420px]">
            {adminMetricsLoading ? (
              <div className="h-64 flex flex-col justify-center items-center gap-2">
                <RefreshCw className="w-5 h-5 animate-spin text-indigo-400" />
                <span className="text-xs text-slate-500 font-mono">Loading data stream...</span>
              </div>
            ) : adminMetrics?.users && adminMetrics.users.length > 0 ? (
              adminMetrics.users.map((u, ui) => (
                <div 
                  key={ui} 
                  className="p-3.5 rounded-xl bg-slate-950/80 border border-slate-850/70 hover:border-slate-800 transition flex items-center justify-between gap-3 text-xs"
                >
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="font-black text-slate-200 truncate block text-[12.5px]">{u.name || "Client"}</span>
                      {u.role === "admin" && (
                        <span className="text-[7.5px] bg-red-500/10 text-red-400 px-1.5 py-0.5 rounded-md font-black uppercase shrink-0 border border-red-500/10">ADMIN</span>
                      )}
                    </div>
                    
                    {/* Verified email address shown literally here */}
                    <p className="text-[10.5px] font-mono text-emerald-450 mt-1 truncate selection:bg-emerald-500/30 select-all">
                      {u.email}
                    </p>
                    
                    <div className="mt-1.5 text-[9px] text-slate-500 font-mono flex flex-wrap gap-x-2 gap-y-1">
                      <span>DOB: {u.dob || "N/A"}</span>
                      <span>•</span>
                      <span className="truncate max-w-[130px]" title={u.place}>Place: {u.place?.split(",")[0] || "N/A"}</span>
                    </div>
                  </div>

                  <div className="text-right shrink-0">
                    <span className={`px-2 py-0.5 rounded text-[8.5px] font-mono font-bold tracking-wider inline-block border ${
                      u.plan === "premium" 
                        ? "bg-amber-500/15 text-amber-300 border-amber-500/20" 
                        : "bg-slate-900 text-slate-500 border-slate-850"
                    }`}>
                      {u.plan.toUpperCase()}
                    </span>
                    <p className="text-[10.5px] font-mono text-slate-350 mt-1.5 font-bold">
                      {u.coins} <span className="text-[9px] text-slate-500 font-normal">coins</span>
                    </p>
                  </div>
                </div>
              ))
            ) : (
              <div className="py-20 text-center text-xs font-mono text-slate-500">No registered customers found.</div>
            )}
          </div>

        </div>

        {/* SECOND COLUMN: PAYMENTS RECEIVED LEDGER */}
        <div className="xl:col-span-6 bg-slate-900/40 backdrop-blur-md rounded-3xl border border-slate-850 shadow-xl flex flex-col overflow-hidden min-h-[460px]">
          
          <div className="px-5 py-4 border-b border-slate-800 bg-slate-950/60 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <DollarSign className="w-4 h-4 text-emerald-400" />
              <div>
                <h3 className="font-extrabold text-xs text-emerald-200 uppercase tracking-widest font-mono">Chatbot Payments Ledger</h3>
                <span className="text-[9px] text-slate-500 font-mono">Real-time payment audit details</span>
              </div>
            </div>
            <span className="text-[10px] bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 rounded text-emerald-300 font-bold font-mono">
              {adminMetrics?.transactions?.length ?? 0} Sales
            </span>
          </div>

          {/* Transactions area container */}
          <div className="flex-1 overflow-y-auto p-4 space-y-2 max-h-[420px]">
            {adminMetricsLoading ? (
              <div className="h-64 flex flex-col justify-center items-center gap-2">
                <RefreshCw className="w-5 h-5 animate-spin text-emerald-400" />
                <span className="text-xs text-slate-500 font-mono">Loading payment records...</span>
              </div>
            ) : adminMetrics?.transactions && adminMetrics.transactions.length > 0 ? (
              adminMetrics.transactions.map((t: any, ti: number) => (
                <div 
                  key={ti} 
                  className="p-3.5 rounded-xl bg-slate-950 border border-slate-850/70 hover:border-slate-800 transition flex items-center justify-between gap-3 text-xs"
                >
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="font-black text-emerald-400 text-[13px] font-mono">+${t.amount?.toFixed(2) ?? "11.00"}</span>
                      {t.planKey && (
                        <span className="text-[8.5px] bg-slate-900 text-amber-300 px-1.5 py-0.5 rounded font-black uppercase tracking-wider border border-amber-500/10">
                          {t.planKey} pack
                        </span>
                      )}
                    </div>
                    
                    {/* Verified payment email shown literally */}
                    <p className="text-[10.5px] font-mono text-slate-200 mt-1 truncate selection:bg-emerald-500/30 select-all">
                      Client: {t.email}
                    </p>

                    <div className="mt-1.5 flex items-center gap-1.5 text-[8.5px] text-slate-500 font-mono truncate">
                      <span>Ref: {t.sessionId ? t.sessionId.slice(-16) : "MANUAL_BYPASS"}</span>
                    </div>
                  </div>

                  <div className="text-right shrink-0">
                    <span className="px-2 py-0.5 rounded text-[8px] font-mono font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/10 uppercase tracking-widest inline-block">
                      SUCCESS
                    </span>
                    <p className="mt-1.5 text-[9px] text-slate-500 font-mono">
                      {new Date(t.timestamp).toLocaleDateString()}
                    </p>
                  </div>
                </div>
              ))
            ) : (
              <div className="py-20 text-center text-xs font-mono text-slate-500">No payment transaction records found on live database.</div>
            )}
          </div>

        </div>

      </div>

    </div>
  );
}
