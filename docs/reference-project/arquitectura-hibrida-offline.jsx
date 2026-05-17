import { useState } from "react";

const SECTIONS = {
  analisis: { label: "Análisis del problema", icon: "🔍" },
  arquitectura: { label: "Arquitectura híbrida", icon: "🏗️" },
  sincronizacion: { label: "Sincronización", icon: "🔄" },
  conflictos: { label: "Conflictos & CRDT", icon: "⚔️" },
  servidor: { label: "Server local Linux", icon: "🖥️" },
  implementacion: { label: "Implementación Laravel", icon: "🧩" },
  riesgos: { label: "Riesgos & mitigaciones", icon: "⚠️" },
};

const PRO_CON = [
  {
    titulo: "Modo offline real",
    tipo: "pro",
    desc: "La farmacia opera con total normalidad sin internet. Ventas, dispensación, consulta de stock: todo funciona desde el servidor local.",
  },
  {
    titulo: "Latencia cero en el POS",
    tipo: "pro",
    desc: "Las transacciones responden al instante porque van al servidor LAN, no a la nube. La caja nunca espera.",
  },
  {
    titulo: "Datos seguros localmente",
    tipo: "pro",
    desc: "Aunque el servidor en la nube caiga, la farmacia tiene su propia copia íntegra de los datos. Resiliencia real.",
  },
  {
    titulo: "Complejidad de sincronización",
    tipo: "con",
    desc: "La resolución de conflictos (edición simultánea online+offline del mismo registro) requiere lógica cuidadosa y pruebas exhaustivas.",
  },
  {
    titulo: "Hardware adicional",
    tipo: "con",
    desc: "Cada farmacia necesita un mini-servidor Linux local (un NUC o PC básico). Costo de ~$400-800 USD por farmacia.",
  },
  {
    titulo: "Facturación DIAN offline",
    tipo: "con",
    desc: "Las facturas electrónicas solo pueden enviarse a DIAN cuando hay internet. Offline se acumulan en cola local — riesgo de vencimiento de resolución.",
  },
];

const SYNC_FLOW = [
  {
    estado: "Online normal",
    color: "#4ade80",
    bg: "rgba(74,222,128,0.1)",
    desc: "El POS escribe en el servidor local LAN. Un agente de sincronización replica en tiempo casi real (WebSocket o polling cada 5s) hacia el servidor cloud. La nube es el estado authoritative.",
    icon: "✅",
  },
  {
    estado: "Conexión débil / inestable",
    color: "#f59e0b",
    bg: "rgba(245,158,11,0.1)",
    desc: "El agente detecta latencia alta (>2s timeout). Continúa escribiendo local. Encola las transacciones pendientes con timestamp y UUID para sincronizar cuando mejore.",
    icon: "⚡",
  },
  {
    estado: "Offline detectado",
    color: "#f87171",
    bg: "rgba(248,113,113,0.1)",
    desc: "El sistema muestra un banner 'Modo Local'. 100% del tráfico va al servidor local. Se activa el modo de contingencia DIAN (acumula facturas en cola local con consecutivo reservado).",
    icon: "🔴",
  },
  {
    estado: "Reconexión",
    color: "#60a5fa",
    bg: "rgba(96,165,250,0.1)",
    desc: "El agente detecta internet disponible. Inicia sync incremental: envía batch de transacciones offline ordenado por timestamp. Resuelve conflictos. Envía facturas DIAN acumuladas.",
    icon: "🔵",
  },
];

const CONFLICT_STRATEGIES = [
  {
    caso: "Venta / Dispensación",
    estrategia: "Append-only — sin conflictos",
    detalle: "Las ventas son inmutables al crearse. Dos ventas offline nunca colisionan porque cada una tiene UUID propio. Al sincronizar simplemente se insertan. El stock se recalcula derivado de los movimientos.",
    nivel: "Bajo",
    color: "#4ade80",
  },
  {
    caso: "Stock / Inventario",
    estrategia: "Event Sourcing con apply al reconectar",
    detalle: "El stock NO se almacena como número fijo. Se calcula de los movimientos (entradas, salidas). Al sincronizar se replayan los eventos en orden cronológico. El saldo resultante es siempre correcto.",
    nivel: "Medio",
    color: "#f59e0b",
  },
  {
    caso: "Datos maestros (producto, cliente)",
    estrategia: "Last-Write-Wins con vector clocks",
    detalle: "Si el cloud y el local editaron el mismo campo, gana el más reciente (timestamp + server_id). Para campos críticos (precio regulado, nivel de control) el cloud siempre tiene precedencia.",
    nivel: "Medio",
    color: "#f59e0b",
  },
  {
    caso: "Libro de controlados",
    estrategia: "Cloud-authoritative — nunca offline",
    detalle: "Los medicamentos nivel I y II del Decreto 0780 NUNCA se pueden dispensar offline. El sistema los bloquea. Demasiado riesgo legal para permitir divergencia en este registro.",
    nivel: "Alto → Bloqueado",
    color: "#f87171",
  },
  {
    caso: "Fórmulas médicas",
    estrategia: "Read-only offline",
    detalle: "Las fórmulas pueden consultarse offline (caché local). Solo se pueden registrar nuevas cuando hay conexión, o si ya estaban en caché local antes del corte.",
    nivel: "Bajo",
    color: "#4ade80",
  },
];

const SERVER_SPECS = [
  {
    componente: "Hardware recomendado",
    detalle: "Intel NUC / Mini PC con Ubuntu Server 24 LTS. 8GB RAM, SSD 256GB. UPS integrado (fundamental en la Costa). ~$400-600 USD.",
    icon: "🖥️",
  },
  {
    componente: "PostgreSQL local",
    detalle: "Réplica completa del schema del tenant. Logical Replication desde el cloud (slot dedicado por farmacia). Solo los datos del tenant — aislamiento garantizado.",
    icon: "🗄️",
  },
  {
    componente: "Laravel local (mismo código)",
    detalle: "Exactamente el mismo código de la app cloud. Configurado con APP_MODE=local. Inertia.js sirve la UI desde aquí cuando no hay internet.",
    icon: "🔧",
  },
  {
    componente: "Agente de sincronización",
    detalle: "Proceso Supervisor que corre Laravel Horizon con jobs especializados: SyncToCloud, ResolveConflicts, FlushDIANQueue.",
    icon: "🤖",
  },
  {
    componente: "DNS local + proxy",
    detalle: "Nginx en el server local. La URL de la farmacia (farmacia.local o la IP LAN) apunta al server local. El mismo hostname funciona online/offline.",
    icon: "🌐",
  },
  {
    componente: "Monitor de conectividad",
    detalle: "Script que hace ping cada 3s a 8.8.8.8 y a tu dominio cloud. Actualiza una flag en Redis que todos los servicios consultan para saber el estado actual.",
    icon: "📡",
  },
];

const IMPL_STEPS = [
  {
    fase: "Fase A",
    titulo: "Event Sourcing en el core de ventas",
    desc: "Lo más importante: cambiar el modelo de inventario a event-based. En lugar de UPDATE stock SET cantidad = cantidad - 1, hacer INSERT en movimientos_inventario. El stock se calcula always derivado de eventos. Esto hace la sincronización trivial.",
    code: `// Cada transacción genera un evento inmutable
class RegistrarVenta {
  public function handle(Venta $venta): void {
    foreach ($venta->items as $item) {
      MovimientoInventario::create([
        'uuid'        => Str::uuid(), // Clave para dedup en sync
        'tenant_id'   => $venta->tenant_id,
        'tipo'        => 'salida_venta',
        'producto_id' => $item->producto_id,
        'lote_id'     => $item->lote_id,
        'cantidad'    => $item->cantidad,
        'origen_id'   => $venta->id,
        'server_id'   => config('app.server_id'), // 'cloud' | 'local'
        'synced_at'   => null, // null = pendiente de sync
        'created_at'  => now(),
      ]);
    }
  }
}`,
  },
  {
    fase: "Fase B",
    titulo: "Agente de sincronización bidireccional",
    desc: "Un job de Horizon que corre cada 5s cuando hay internet. Sube los eventos locales no sincronizados al cloud y baja los cambios remotos que no están en local.",
    code: `class SyncToCloud implements ShouldQueue {
  public function handle(): void {
    if (!ConnectivityService::isOnline()) return;

    // SUBIR: eventos locales no sincronizados
    $pending = MovimientoInventario::whereNull('synced_at')
      ->where('server_id', 'local')
      ->orderBy('created_at')
      ->get();

    foreach ($pending->chunk(100) as $batch) {
      $response = Http::post(config('sync.cloud_url').'/api/sync/ingest', [
        'tenant_id' => tenant()->id,
        'events'    => $batch->toArray(),
      ]);
      if ($response->ok()) {
        $batch->each(fn($e) => $e->update(['synced_at' => now()]));
      }
    }

    // BAJAR: cambios del cloud más recientes
    $lastSync = SyncCheckpoint::latest()->value('synced_at');
    $remote = Http::get(config('sync.cloud_url').'/api/sync/delta', [
      'tenant_id' => tenant()->id,
      'since'     => $lastSync,
    ])->json('events');

    ConflictResolver::applyRemoteEvents($remote);
  }
}`,
  },
  {
    fase: "Fase C",
    titulo: "Detección de conectividad y cambio de modo",
    desc: "El frontend Svelte 5 y el backend Laravel deben saber en tiempo real si están online u offline para mostrar el estado correcto y bloquear operaciones prohibidas.",
    code: `// ConnectivityService.php
class ConnectivityService {
  public static function isOnline(): bool {
    return Cache::remember('connectivity_status', 3, function () {
      try {
        $response = Http::timeout(2)->get('https://dns.google/resolve?name=google.com');
        return $response->ok();
      } catch (\\Exception) {
        return false;
      }
    });
  }
}

// En el frontend Svelte 5 — connectivity.svelte.js
let online = $state(navigator.onLine);
let mode = $derived(online ? 'cloud' : 'local');

window.addEventListener('online',  () => online = true);
window.addEventListener('offline', () => online = false);

// Polling adicional para detectar internet real (no solo LAN)
setInterval(async () => {
  try {
    await fetch('/api/ping', { cache: 'no-store' });
    online = true;
  } catch { online = false; }
}, 5000);`,
  },
  {
    fase: "Fase D",
    titulo: "Cola de facturas DIAN con contingencia",
    desc: "Cuando no hay internet, las facturas se acumulan localmente con un número de contingencia. Al reconectar se envían en orden a NextPyme.",
    code: `// DianQueueService.php
class DianQueueService {
  public function emitir(Venta $venta): void {
    if (ConnectivityService::isOnline()) {
      // Envío inmediato
      EnviarFacturaDIAN::dispatch($venta);
    } else {
      // Contingencia offline — guardar con número temporal
      FacturaElectronica::create([
        'venta_id'           => $venta->id,
        'estado_dian'        => 'contingencia',
        'numero_contingencia'=> $this->nextContingencia(),
        'pendiente_envio'    => true,
        'datos_json'         => $this->buildJSON($venta),
      ]);
      // El agente de sync lo envía al reconectar
    }
  }

  // Límite legal DIAN: 48hs para enviar facturas en contingencia
  public function flushQueue(): void {
    FacturaElectronica::where('pendiente_envio', true)
      ->where('created_at', '>', now()->subHours(47))
      ->each(fn($f) => EnviarFacturaDIAN::dispatch($f));
  }
}`,
  },
];

const RISKS = [
  {
    riesgo: "Divergencia de datos prolongada",
    prob: "Media",
    impacto: "Alto",
    color: "#f87171",
    mitigacion: "Limit de operaciones offline: máximo 48h continuas en modo local antes de bloquear operaciones críticas (salvo ventas básicas). Alerta al regente.",
  },
  {
    riesgo: "Fallo del servidor local (disco, RAM)",
    prob: "Baja",
    impacto: "Alto",
    color: "#f87171",
    mitigacion: "Backup automático cada hora a USB dedicado con rsync. El cloud tiene el estado real. Recuperación: reinstalar server local y restaurar replicación.",
  },
  {
    riesgo: "Conflicto de UUID duplicados",
    prob: "Muy baja",
    impacto: "Medio",
    color: "#f59e0b",
    mitigacion: "UUIDs generados con Str::uuid() son estadísticamente únicos. Además, namespacing por server_id (cloud:uuid vs local:uuid) elimina colisiones.",
  },
  {
    riesgo: "Facturas DIAN fuera del límite 48h",
    prob: "Baja",
    impacto: "Muy alto",
    color: "#f87171",
    mitigacion: "Alerta en t=24h al regente. Bloqueo de ventas nuevas en t=46h si aún sin conexión (DIAN no acepta contingencias >48h). El usuario debe saber el riesgo.",
  },
  {
    riesgo: "Actualización de software desincronizada",
    prob: "Media",
    impacto: "Medio",
    color: "#f59e0b",
    mitigacion: "El server local se actualiza automáticamente desde el cloud cuando hay conexión. Control de versión: el local no puede ser más de 1 versión menor que el cloud.",
  },
  {
    riesgo: "Cambios de precios regulados offline",
    prob: "Baja",
    impacto: "Medio",
    color: "#f59e0b",
    mitigacion: "Los precios regulados son cloud-authoritative. Si cambian en el cloud, el local usa los últimos en caché. Se muestra alerta 'Precios desactualizados' con fecha del último sync.",
  },
];

export default function HibridoOffline() {
  const [active, setActive] = useState("analisis");

  return (
    <div style={{ fontFamily: "'IBM Plex Mono', monospace", background: "#0a0f1a", minHeight: "100vh", color: "#e2e8f0" }}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        ::-webkit-scrollbar { width: 5px; } ::-webkit-scrollbar-track { background: #0a0f1a; } ::-webkit-scrollbar-thumb { background: #1e3a5f; border-radius: 3px; }
        .nb { background: none; border: 1px solid #1e3a5f; color: #64748b; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-family: inherit; font-size: 12px; transition: all 0.2s; white-space: nowrap; }
        .nb:hover { border-color: #3b82f6; color: #93c5fd; background: rgba(59,130,246,0.08); }
        .nb.on { border-color: #3b82f6; color: #60a5fa; background: rgba(59,130,246,0.15); }
        .card { background: #0f1929; border: 1px solid #1e3a5f; border-radius: 10px; padding: 20px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
        .code-block { background: #060c14; border: 1px solid #1e3a5f; border-radius: 8px; padding: 16px; overflow-x: auto; font-size: 11px; color: #7dd3fc; line-height: 1.8; margin-top: 12px; }
        pre { white-space: pre-wrap; word-break: break-word; }
      `}</style>

      {/* Header */}
      <div style={{ background: "linear-gradient(135deg, #0f2233 0%, #0a1628 100%)", borderBottom: "1px solid #1e3a5f", padding: "20px 24px" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto" }}>
          <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
            <span style={{ fontSize: 28 }}>🌐</span>
            <div>
              <h1 style={{ fontSize: 19, fontWeight: 700, color: "#e2e8f0", fontFamily: "'IBM Plex Sans', sans-serif" }}>
                FarmaSystem — Modo Híbrido Offline-First
              </h1>
              <p style={{ fontSize: 11, color: "#475569" }}>Arquitectura para conectividad intermitente · Costa Caribe Colombiana</p>
            </div>
            <div style={{ marginLeft: "auto", display: "flex", gap: 6, flexWrap: "wrap" }}>
              {["Offline-first", "Event Sourcing", "CRDT-lite", "DIAN Contingencia"].map(t => (
                <span key={t} className="badge" style={{ background: "rgba(96,165,250,0.12)", color: "#60a5fa", border: "1px solid rgba(96,165,250,0.25)" }}>{t}</span>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Nav */}
      <div style={{ background: "#0a0f1a", borderBottom: "1px solid #1e3a5f", padding: "12px 24px", overflowX: "auto" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", display: "flex", gap: 8 }}>
          {Object.entries(SECTIONS).map(([key, s]) => (
            <button key={key} className={`nb ${active === key ? "on" : ""}`} onClick={() => setActive(key)}>
              {s.icon} {s.label}
            </button>
          ))}
        </div>
      </div>

      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "24px" }}>

        {/* ANÁLISIS */}
        {active === "analisis" && (
          <div>
            <div className="card" style={{ marginBottom: 20, borderColor: "#1e4d7b", background: "linear-gradient(135deg, #0f2744 0%, #0f1929 100%)" }}>
              <h2 style={{ fontSize: 15, fontWeight: 700, color: "#93c5fd", marginBottom: 10, fontFamily: "'IBM Plex Sans', sans-serif" }}>
                🎯 El problema real de la Costa Caribe
              </h2>
              <p style={{ fontSize: 13, color: "#94a3b8", lineHeight: 1.8 }}>
                La conectividad intermitente no es un edge case en la región — es la norma. Una farmacia no puede dejar de vender
                mientras Movistar o ETB resuelve una caída. La solución correcta no es "modo offline simplificado",
                sino una arquitectura <strong style={{ color: "#60a5fa" }}>genuinamente distribuida</strong> donde el servidor
                local es un ciudadano de primera clase, no un parche.
              </p>
            </div>

            {/* Diagram: topología */}
            <div className="card" style={{ marginBottom: 20 }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: "#f59e0b", marginBottom: 16, fontFamily: "'IBM Plex Sans', sans-serif" }}>
                📡 Topología del sistema
              </h3>
              <svg viewBox="0 0 680 320" width="100%" style={{ display: "block" }}>
                <defs>
                  <marker id="arr" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                    <path d="M2 1L8 5L2 9" fill="none" stroke="context-stroke" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </marker>
                </defs>

                {/* Cloud */}
                <rect x="260" y="20" width="160" height="60" rx="10" fill="rgba(59,130,246,0.12)" stroke="#3b82f6" strokeWidth="0.8"/>
                <text x="340" y="46" textAnchor="middle" fill="#60a5fa" fontSize="12" fontWeight="600">☁ Cloud Server</text>
                <text x="340" y="64" textAnchor="middle" fill="#475569" fontSize="10">PostgreSQL + Laravel</text>

                {/* Internet cloud shape */}
                <ellipse cx="340" cy="130" rx="60" ry="22" fill="rgba(100,116,139,0.08)" stroke="#334155" strokeWidth="0.5" strokeDasharray="4 3"/>
                <text x="340" y="134" textAnchor="middle" fill="#475569" fontSize="10">Internet</text>

                {/* Local server */}
                <rect x="100" y="200" width="160" height="70" rx="10" fill="rgba(74,222,128,0.08)" stroke="#4ade80" strokeWidth="0.8"/>
                <text x="180" y="226" textAnchor="middle" fill="#4ade80" fontSize="12" fontWeight="600">🖥 Server Local</text>
                <text x="180" y="242" textAnchor="middle" fill="#475569" fontSize="10">Ubuntu + Laravel</text>
                <text x="180" y="258" textAnchor="middle" fill="#475569" fontSize="10">PostgreSQL réplica</text>

                {/* POS */}
                <rect x="420" y="200" width="130" height="70" rx="10" fill="rgba(168,85,247,0.08)" stroke="#a78bfa" strokeWidth="0.8"/>
                <text x="485" y="226" textAnchor="middle" fill="#a78bfa" fontSize="12" fontWeight="600">🖱 POS / Caja</text>
                <text x="485" y="242" textAnchor="middle" fill="#475569" fontSize="10">Svelte 5 + Inertia</text>
                <text x="485" y="258" textAnchor="middle" fill="#475569" fontSize="10">Browser</text>

                {/* Cloud ↔ Internet */}
                <line x1="340" y1="80" x2="340" y2="108" stroke="#3b82f6" strokeWidth="1" markerEnd="url(#arr)" markerStart="url(#arr)"/>

                {/* Internet ↔ Local server (sync) */}
                <line x1="280" y1="140" x2="200" y2="198" stroke="#4ade80" strokeWidth="1" strokeDasharray="5 3" markerEnd="url(#arr)" markerStart="url(#arr)"/>
                <text x="218" y="176" textAnchor="middle" fill="#4ade80" fontSize="10">sync</text>

                {/* Local server ↔ POS (LAN) */}
                <line x1="262" y1="235" x2="418" y2="235" stroke="#f59e0b" strokeWidth="1.5" markerEnd="url(#arr)" markerStart="url(#arr)"/>
                <text x="340" y="225" textAnchor="middle" fill="#f59e0b" fontSize="10" fontWeight="600">LAN — siempre disponible</text>

                {/* Offline indicator */}
                <rect x="270" y="120" width="140" height="28" rx="6" fill="rgba(248,113,113,0.12)" stroke="#f87171" strokeWidth="0.5" strokeDasharray="3 2"/>
                <text x="340" y="138" textAnchor="middle" fill="#f87171" fontSize="10">❌ sin internet → corte aquí</text>

                {/* Labels */}
                <text x="180" y="298" textAnchor="middle" fill="#475569" fontSize="10">siempre operativo</text>
                <text x="485" y="298" textAnchor="middle" fill="#475569" fontSize="10">conectado por LAN</text>
              </svg>
            </div>

            <h3 style={{ fontSize: 13, fontWeight: 700, color: "#e2e8f0", marginBottom: 14, fontFamily: "'IBM Plex Sans', sans-serif" }}>Pros y contras honestos de este enfoque</h3>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              {PRO_CON.map(item => (
                <div key={item.titulo} style={{
                  background: item.tipo === "pro" ? "rgba(74,222,128,0.06)" : "rgba(248,113,113,0.06)",
                  border: `1px solid ${item.tipo === "pro" ? "rgba(74,222,128,0.2)" : "rgba(248,113,113,0.2)"}`,
                  borderRadius: 8, padding: 16
                }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8 }}>
                    <span style={{ fontSize: 16 }}>{item.tipo === "pro" ? "✅" : "⚠️"}</span>
                    <span style={{ fontSize: 12, fontWeight: 600, color: item.tipo === "pro" ? "#4ade80" : "#f87171", fontFamily: "'IBM Plex Sans', sans-serif" }}>{item.titulo}</span>
                  </div>
                  <p style={{ fontSize: 12, color: "#64748b", lineHeight: 1.7 }}>{item.desc}</p>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ARQUITECTURA */}
        {active === "arquitectura" && (
          <div>
            <h2 style={{ fontSize: 15, fontWeight: 700, color: "#93c5fd", marginBottom: 20, fontFamily: "'IBM Plex Sans', sans-serif" }}>🏗️ Arquitectura híbrida en detalle</h2>
            <div className="card" style={{ marginBottom: 20, borderColor: "#f59e0b40" }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: "#f59e0b", marginBottom: 12, fontFamily: "'IBM Plex Sans', sans-serif" }}>
                🧠 Principio fundamental: Event Sourcing para inventario
              </h3>
              <p style={{ fontSize: 13, color: "#94a3b8", lineHeight: 1.8, marginBottom: 12 }}>
                La clave que hace posible este esquema es <strong style={{ color: "#f59e0b" }}>no almacenar el stock como un número</strong>.
                En lugar de <code style={{ color: "#60a5fa" }}>UPDATE inventario SET cantidad = cantidad - 1</code>,
                cada operación genera un <strong style={{ color: "#f59e0b" }}>evento inmutable</strong> con UUID.
                El stock es siempre la suma de todos los eventos. Esto hace que la sincronización sea append-only — nunca hay conflictos en ventas.
              </p>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
                <div style={{ background: "rgba(248,113,113,0.06)", border: "1px solid rgba(248,113,113,0.2)", borderRadius: 8, padding: 14 }}>
                  <div style={{ fontSize: 11, color: "#f87171", fontWeight: 600, marginBottom: 8 }}>❌ Enfoque tradicional (conflictos)</div>
                  <code style={{ fontSize: 11, color: "#64748b", lineHeight: 1.8 }}>
                    {"// Local: stock = 10 → vende 1 → 9\n// Cloud: stock = 10 → vende 1 → 9\n// Al sincronizar: ¿cuál gana?\n// Si ambos hicieron UPDATE... 💥"}
                  </code>
                </div>
                <div style={{ background: "rgba(74,222,128,0.06)", border: "1px solid rgba(74,222,128,0.2)", borderRadius: 8, padding: 14 }}>
                  <div style={{ fontSize: 11, color: "#4ade80", fontWeight: 600, marginBottom: 8 }}>✅ Enfoque Event Sourcing (sin conflictos)</div>
                  <code style={{ fontSize: 11, color: "#64748b", lineHeight: 1.8 }}>
                    {"// Local:  [+salida uuid-A cant=1]\n// Cloud:  [+salida uuid-B cant=1]\n// Sync:   INSERT ambos eventos\n// Stock = 10 - 1 - 1 = 8 ✓"}
                  </code>
                </div>
              </div>
            </div>

            <h3 style={{ fontSize: 13, fontWeight: 700, color: "#e2e8f0", marginBottom: 14, fontFamily: "'IBM Plex Sans', sans-serif" }}>Capas de la arquitectura</h3>
            {[
              {
                capa: "Capa de datos (PostgreSQL)",
                color: "#60a5fa",
                items: [
                  "Cloud: base de datos principal del tenant — authoritative para datos maestros",
                  "Local: réplica completa vía PostgreSQL Logical Replication (slot dedicado)",
                  "Tabla sync_log: registro de todos los eventos pendientes de sincronizar",
                  "Tabla sync_checkpoints: último estado conocido de sincronización bidireccional",
                  "Tabla conflict_log: registro auditable de todos los conflictos resueltos",
                ],
              },
              {
                capa: "Capa de aplicación (Laravel)",
                color: "#4ade80",
                items: [
                  "Mismo codebase cloud y local — variable APP_MODE=cloud|local",
                  "ConnectivityService: fuente de verdad sobre el estado de conexión",
                  "SyncAgent (Horizon job): sincronización bidireccional cada 5s cuando hay internet",
                  "OfflineGuard middleware: bloquea operaciones prohibidas offline (controlados, nuevas fórmulas)",
                  "ConflictResolver service: aplica estrategia CRDT-lite según tipo de entidad",
                ],
              },
              {
                capa: "Capa de presentación (Svelte 5 + Inertia)",
                color: "#a78bfa",
                items: [
                  "connectivity.svelte.js: store reactivo del estado de conexión (online|offline|syncing)",
                  "Banner de modo local: visible y claro cuando opera sin internet",
                  "Indicador de sincronización pendiente: cuántas transacciones esperan subir",
                  "Bloqueo visual de operaciones prohibidas offline con explicación al usuario",
                  "Cola de facturas DIAN: contador de facturas en contingencia y tiempo límite",
                ],
              },
            ].map(layer => (
              <div key={layer.capa} className="card" style={{ marginBottom: 14, borderLeft: `3px solid ${layer.color}`, borderRadius: 8 }}>
                <h4 style={{ fontSize: 13, fontWeight: 700, color: layer.color, marginBottom: 12, fontFamily: "'IBM Plex Sans', sans-serif" }}>{layer.capa}</h4>
                {layer.items.map((item, i) => (
                  <div key={i} style={{ fontSize: 12, color: "#64748b", padding: "4px 0", paddingLeft: 12, borderLeft: `2px solid ${layer.color}30`, marginBottom: 4 }}>
                    → {item}
                  </div>
                ))}
              </div>
            ))}
          </div>
        )}

        {/* SINCRONIZACIÓN */}
        {active === "sincronizacion" && (
          <div>
            <h2 style={{ fontSize: 15, fontWeight: 700, color: "#93c5fd", marginBottom: 6, fontFamily: "'IBM Plex Sans', sans-serif" }}>🔄 Flujo de sincronización por estado de conexión</h2>
            <p style={{ fontSize: 12, color: "#475569", marginBottom: 20 }}>El sistema tiene 4 estados de conectividad, cada uno con comportamiento distinto.</p>
            {SYNC_FLOW.map(state => (
              <div key={state.estado} style={{ background: state.bg, border: `1px solid ${state.color}30`, borderLeft: `3px solid ${state.color}`, borderRadius: 8, padding: 20, marginBottom: 14 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 10 }}>
                  <span style={{ fontSize: 20 }}>{state.icon}</span>
                  <span style={{ fontSize: 14, fontWeight: 700, color: state.color, fontFamily: "'IBM Plex Sans', sans-serif" }}>{state.estado}</span>
                </div>
                <p style={{ fontSize: 13, color: "#94a3b8", lineHeight: 1.7 }}>{state.desc}</p>
              </div>
            ))}
            <div className="card" style={{ marginTop: 20, borderColor: "#334155" }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: "#e2e8f0", marginBottom: 12, fontFamily: "'IBM Plex Sans', sans-serif" }}>📊 Tabla de decisiones por operación</h3>
              <table style={{ width: "100%", fontSize: 11, borderCollapse: "collapse" }}>
                <thead>
                  <tr>
                    {["Operación", "Online", "Offline", "Al reconectar"].map(h => (
                      <th key={h} style={{ padding: "8px 12px", textAlign: "left", color: "#64748b", borderBottom: "1px solid #1e3a5f", fontWeight: 600 }}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {[
                    ["Venta normal", "✅ Cloud + Local", "✅ Solo Local", "Auto-sync"],
                    ["Medicamento controlado", "✅ Cloud + Local", "🔴 Bloqueado", "N/A"],
                    ["Nueva fórmula médica", "✅ Cloud + Local", "🔴 Bloqueado", "N/A"],
                    ["Consultar stock", "✅ Tiempo real", "✅ Caché local", "Actualiza caché"],
                    ["Factura electrónica DIAN", "✅ Inmediata", "⏳ Cola 48h max", "Flush automático"],
                    ["Editar producto/precio", "✅ Cloud first", "⚠️ Marcado 'local'", "Cloud gana si conflicto"],
                    ["Recepción de mercancía", "✅ Normal", "✅ Local + cola", "Sync y valida"],
                    ["Reporte INVIMA", "✅ Genera y envía", "✅ Genera, no envía", "Envío auto"],
                  ].map(([op, ...cells], i) => (
                    <tr key={op} style={{ background: i % 2 === 0 ? "transparent" : "rgba(255,255,255,0.02)" }}>
                      <td style={{ padding: "8px 12px", color: "#e2e8f0", fontWeight: 500 }}>{op}</td>
                      {cells.map((c, j) => (
                        <td key={j} style={{ padding: "8px 12px", color: c.includes("🔴") ? "#f87171" : c.includes("⏳") || c.includes("⚠️") ? "#f59e0b" : "#4ade80", fontSize: 11 }}>{c}</td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* CONFLICTOS */}
        {active === "conflictos" && (
          <div>
            <h2 style={{ fontSize: 15, fontWeight: 700, color: "#93c5fd", marginBottom: 6, fontFamily: "'IBM Plex Sans', sans-serif" }}>⚔️ Estrategias de resolución de conflictos</h2>
            <p style={{ fontSize: 12, color: "#475569", marginBottom: 20 }}>
              No todos los conflictos se resuelven igual. La estrategia depende del tipo de dato y su riesgo legal.
            </p>
            {CONFLICT_STRATEGIES.map(cs => (
              <div key={cs.caso} className="card" style={{ marginBottom: 14, borderLeft: `3px solid ${cs.color}` }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 10 }}>
                  <div>
                    <span style={{ fontSize: 13, fontWeight: 700, color: "#e2e8f0", fontFamily: "'IBM Plex Sans', sans-serif" }}>{cs.caso}</span>
                    <span style={{ marginLeft: 12, fontSize: 11, color: cs.color, background: `${cs.color}15`, padding: "2px 8px", borderRadius: 4, border: `1px solid ${cs.color}30` }}>
                      {cs.estrategia}
                    </span>
                  </div>
                  <span className="badge" style={{ background: `${cs.color}15`, color: cs.color, border: `1px solid ${cs.color}30`, flexShrink: 0 }}>
                    Riesgo: {cs.nivel}
                  </span>
                </div>
                <p style={{ fontSize: 12, color: "#64748b", lineHeight: 1.7 }}>{cs.detalle}</p>
              </div>
            ))}
            <div className="card" style={{ marginTop: 8, borderColor: "#a78bfa40" }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: "#a78bfa", marginBottom: 12, fontFamily: "'IBM Plex Sans', sans-serif" }}>
                🔑 La regla de oro para este dominio
              </h3>
              <p style={{ fontSize: 13, color: "#94a3b8", lineHeight: 1.8 }}>
                En farmacia, <strong style={{ color: "#a78bfa" }}>el dinero importa menos que el medicamento</strong>.
                Un error de contabilidad se corrige. Un error en el registro de controlados o una sobredispensación
                puede tener consecuencias legales graves. Por eso la estrategia es:
                <strong style={{ color: "#4ade80" }}> offline = permisivo en ventas, restrictivo en cumplimiento normativo</strong>.
              </p>
            </div>
          </div>
        )}

        {/* SERVIDOR LOCAL */}
        {active === "servidor" && (
          <div>
            <h2 style={{ fontSize: 15, fontWeight: 700, color: "#93c5fd", marginBottom: 20, fontFamily: "'IBM Plex Sans', sans-serif" }}>🖥️ Configuración del servidor local Linux</h2>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))", gap: 14, marginBottom: 20 }}>
              {SERVER_SPECS.map(s => (
                <div key={s.componente} className="card" style={{ borderColor: "#1e3a5f" }}>
                  <div style={{ fontSize: 20, marginBottom: 8 }}>{s.icon}</div>
                  <div style={{ fontSize: 13, fontWeight: 600, color: "#e2e8f0", marginBottom: 6, fontFamily: "'IBM Plex Sans', sans-serif" }}>{s.componente}</div>
                  <div style={{ fontSize: 12, color: "#64748b", lineHeight: 1.7 }}>{s.detalle}</div>
                </div>
              ))}
            </div>
            <div className="card" style={{ borderColor: "#4ade8040" }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: "#4ade80", marginBottom: 12, fontFamily: "'IBM Plex Sans', sans-serif" }}>
                🚀 Stack del servidor local (docker-compose.yml simplificado)
              </h3>
              <div className="code-block">
                <pre>{`services:
  nginx:
    image: nginx:alpine
    ports: ["80:80"]   # acceso LAN desde cualquier PC de la farmacia
    volumes: ["./nginx.conf:/etc/nginx/conf.d/default.conf"]

  app:
    image: farmasystem:latest  # misma imagen que el cloud
    environment:
      APP_MODE: local
      SERVER_ID: local          # identifica este nodo en sync_log
      CLOUD_SYNC_URL: https://api.farmasystem.co
      DB_HOST: postgres
      REDIS_HOST: redis
    depends_on: [postgres, redis]

  postgres:
    image: postgres:16
    environment:
      POSTGRES_DB: farma_tenant_${TENANT_SLUG}
    volumes:
      - pgdata:/var/lib/postgresql/data
      # Configurado como réplica lógica del cloud

  redis:
    image: redis:7-alpine
    # Cache + Queue de Horizon

  horizon:
    image: farmasystem:latest
    command: php artisan horizon
    # Procesa: SyncToCloud, ResolveConflicts, FlushDIANQueue

  connectivity-monitor:
    image: alpine
    command: |
      sh -c "while true; do
        if ping -c1 8.8.8.8 &>/dev/null; then
          redis-cli set connectivity:status online;
        else
          redis-cli set connectivity:status offline;
        fi; sleep 3; done"

  watchtower:
    image: containrrr/watchtower
    # Auto-actualiza las imágenes cuando hay internet
    volumes: ["/var/run/docker.sock:/var/run/docker.sock"]`}</pre>
              </div>
            </div>
          </div>
        )}

        {/* IMPLEMENTACIÓN */}
        {active === "implementacion" && (
          <div>
            <h2 style={{ fontSize: 15, fontWeight: 700, color: "#93c5fd", marginBottom: 6, fontFamily: "'IBM Plex Sans', sans-serif" }}>🧩 Implementación en Laravel + Svelte 5</h2>
            <p style={{ fontSize: 12, color: "#475569", marginBottom: 20 }}>4 fases de implementación, en orden de prioridad técnica.</p>
            {IMPL_STEPS.map(step => (
              <div key={step.fase} className="card" style={{ marginBottom: 20, borderColor: "#1e3a5f" }}>
                <div style={{ display: "flex", gap: 12, alignItems: "flex-start", marginBottom: 12 }}>
                  <span className="badge" style={{ background: "rgba(59,130,246,0.15)", color: "#60a5fa", border: "1px solid rgba(59,130,246,0.3)", fontSize: 11, padding: "4px 10px" }}>{step.fase}</span>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 700, color: "#e2e8f0", fontFamily: "'IBM Plex Sans', sans-serif" }}>{step.titulo}</div>
                    <div style={{ fontSize: 12, color: "#64748b", marginTop: 4, lineHeight: 1.7 }}>{step.desc}</div>
                  </div>
                </div>
                <div className="code-block">
                  <pre>{step.code}</pre>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* RIESGOS */}
        {active === "riesgos" && (
          <div>
            <h2 style={{ fontSize: 15, fontWeight: 700, color: "#93c5fd", marginBottom: 6, fontFamily: "'IBM Plex Sans', sans-serif" }}>⚠️ Riesgos y mitigaciones</h2>
            <p style={{ fontSize: 12, color: "#475569", marginBottom: 20 }}>Un análisis honesto de lo que puede salir mal y cómo prevenirlo.</p>
            {RISKS.map(r => (
              <div key={r.riesgo} className="card" style={{ marginBottom: 14, borderLeft: `3px solid ${r.color}` }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 10 }}>
                  <span style={{ fontSize: 13, fontWeight: 700, color: "#e2e8f0", fontFamily: "'IBM Plex Sans', sans-serif" }}>{r.riesgo}</span>
                  <div style={{ display: "flex", gap: 6, flexShrink: 0 }}>
                    <span className="badge" style={{ background: `${r.color}15`, color: r.color, border: `1px solid ${r.color}30` }}>P: {r.prob}</span>
                    <span className="badge" style={{ background: `${r.color}15`, color: r.color, border: `1px solid ${r.color}30` }}>I: {r.impacto}</span>
                  </div>
                </div>
                <div style={{ fontSize: 12, color: "#4ade80", padding: "8px 12px", background: "rgba(74,222,128,0.06)", borderRadius: 6, borderLeft: "2px solid rgba(74,222,128,0.3)" }}>
                  🛡️ {r.mitigacion}
                </div>
              </div>
            ))}
            <div className="card" style={{ marginTop: 20, borderColor: "#f59e0b40" }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: "#f59e0b", marginBottom: 12, fontFamily: "'IBM Plex Sans', sans-serif" }}>
                💡 Recomendación final para la Costa Caribe
              </h3>
              <p style={{ fontSize: 13, color: "#94a3b8", lineHeight: 1.8 }}>
                Este esquema híbrido es <strong style={{ color: "#4ade80" }}>perfectamente viable y recomendable</strong> para tu contexto.
                La clave es empezar con el Event Sourcing desde el día 1 — esa decisión de diseño hace que todo lo demás sea manejable.
                El costo adicional (mini-server local + UPS) se justifica completamente frente a la alternativa de una farmacia que no puede vender.
                Las referencias de arquitecturas similares son sistemas POS de franquicias (McDonald's, Bancolombia cajeros) que operan exactamente con este modelo.
              </p>
            </div>
          </div>
        )}

      </div>
    </div>
  );
}
