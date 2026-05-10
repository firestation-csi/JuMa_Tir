// screens.jsx — JuMA Bewertungs-App screens
// Loaded after React + Babel + ios-frame.jsx.

const { useState, useEffect, useRef, useMemo } = React;

// ───────────────────────────────────────────
// Icon set (small inline SVGs — original)
// ───────────────────────────────────────────
const Icon = {
  qr: (p) => (
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" {...p}>
      <rect x="2" y="2" width="7" height="7" rx="1.4" stroke="currentColor" strokeWidth="1.6"/>
      <rect x="13" y="2" width="7" height="7" rx="1.4" stroke="currentColor" strokeWidth="1.6"/>
      <rect x="2" y="13" width="7" height="7" rx="1.4" stroke="currentColor" strokeWidth="1.6"/>
      <rect x="4.5" y="4.5" width="2" height="2" fill="currentColor"/>
      <rect x="15.5" y="4.5" width="2" height="2" fill="currentColor"/>
      <rect x="4.5" y="15.5" width="2" height="2" fill="currentColor"/>
      <path d="M13 13h2v2h-2zM17 13h3M13 17v3M17 17h3v3h-3z" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/>
    </svg>
  ),
  list: (p) => (
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" {...p}>
      <path d="M4 6h14M4 11h14M4 16h10" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/>
    </svg>
  ),
  flame: (p) => (
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" {...p}>
      <path d="M11 2c0 4-5 5-5 10a5 5 0 1010 0c0-2-1.5-3.5-2.5-4.5C11 6 14 5 11 2z"
            stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round"/>
    </svg>
  ),
  user: (p) => (
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" {...p}>
      <circle cx="11" cy="8" r="3.5" stroke="currentColor" strokeWidth="1.6"/>
      <path d="M4 19c1.5-3.5 4-5 7-5s5.5 1.5 7 5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/>
    </svg>
  ),
  arrow: (p) => (
    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" {...p}>
      <path d="M4 9h10M9 4l5 5-5 5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
  ),
  check: (p) => (
    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" {...p}>
      <path d="M3.5 9.5l4 4 7-9" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
  ),
  back: (p) => (
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" {...p}>
      <path d="M12 4l-6 6 6 6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
  ),
  more: (p) => (
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" {...p}>
      <circle cx="5" cy="10" r="1.4" fill="currentColor"/>
      <circle cx="10" cy="10" r="1.4" fill="currentColor"/>
      <circle cx="15" cy="10" r="1.4" fill="currentColor"/>
    </svg>
  ),
  info: (p) => (
    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" {...p}>
      <circle cx="9" cy="9" r="7.2" stroke="currentColor" strokeWidth="1.6"/>
      <circle cx="9" cy="5.6" r="0.9" fill="currentColor"/>
      <path d="M9 8.2v5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/>
    </svg>
  ),
  flash: (p) => (
    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" {...p}>
      <path d="M10 1l-6 9h4l-1 7 6-9h-4l1-7z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round"/>
    </svg>
  ),
};

// ───────────────────────────────────────────
// Faux-QR pattern (deterministic, just for visual)
// ───────────────────────────────────────────
function FauxQR({ size = 120, seed = 7, color = '#0E0D0B', bg = '#fff' }) {
  const cells = 17;
  const rng = (i, j) => {
    let x = (i + 1) * 374761393 + (j + 1) * 668265263 + seed * 1442695040;
    x = (x ^ (x >>> 13)) >>> 0;
    return ((x * 1274126177) >>> 0) % 100;
  };
  const isFinder = (i, j) => (
    (i < 7 && j < 7) || (i < 7 && j > cells - 8) || (i > cells - 8 && j < 7)
  );
  const finder = (cx, cy) => (
    <g key={`f${cx}-${cy}`}>
      <rect x={cx} y={cy} width={7} height={7} fill={color} rx={1.2}/>
      <rect x={cx + 1} y={cy + 1} width={5} height={5} fill={bg} rx={0.8}/>
      <rect x={cx + 2} y={cy + 2} width={3} height={3} fill={color} rx={0.4}/>
    </g>
  );
  const dots = [];
  for (let i = 0; i < cells; i++) {
    for (let j = 0; j < cells; j++) {
      if (isFinder(i, j)) continue;
      if (rng(i, j) > 50) dots.push(<rect key={`${i}-${j}`} x={j} y={i} width={1} height={1} fill={color}/>);
    }
  }
  return (
    <svg width={size} height={size} viewBox={`0 0 ${cells} ${cells}`} shapeRendering="crispEdges">
      <rect width={cells} height={cells} fill={bg} rx={0.5}/>
      {dots}
      {finder(0, 0)}
      {finder(cells - 7, 0)}
      {finder(0, cells - 7)}
    </svg>
  );
}

// ───────────────────────────────────────────
// Top header — visible on most screens
// ───────────────────────────────────────────
function TopHeader({ station, sync, onBack }) {
  return (
    <div className="top-header">
      {onBack ? (
        <button className="btn" style={{
          height: 32, padding: '0 10px 0 6px',
          background: 'var(--surface)', color: 'var(--text)',
          border: '1px solid var(--border)', borderRadius: 999,
          fontSize: 12, fontWeight: 600, gap: 4,
        }} onClick={onBack}>
          <Icon.back/> Zurück
        </button>
      ) : (
        station ? (
          <span className="station-chip">
            <span className="dot"/>
            {station.code} · {station.name}
          </span>
        ) : <span/>
      )}
      <span className="sync-pill">
        <span className={`pulse ${sync === 'offline' ? 'offline' : 'online'}`}/>
        {sync === 'offline' ? 'OFFLINE · 2 in Warteschlange' : 'WERTUNGSZENTRALE · LIVE'}
      </span>
    </div>
  );
}

// ───────────────────────────────────────────
// Tab bar
// ───────────────────────────────────────────
function TabBar({ active, onChange }) {
  const tab = (id, label, icon) => (
    <button
      key={id}
      className={active === id ? 'active' : ''}
      onClick={() => onChange(id)}
    >
      {icon}
      <span>{label}</span>
    </button>
  );
  return (
    <div className="tabbar">
      {tab('aktiv', 'Aktiv', <Icon.flame/>)}
      {tab('verlauf', 'Verlauf', <Icon.list/>)}
      {tab('profil', 'Profil', <Icon.user/>)}
    </div>
  );
}

// ───────────────────────────────────────────
// Login screen — scan station QR
// ───────────────────────────────────────────
function LoginScreen({ onScanned, dark }) {
  const [scanning, setScanning] = useState(true);

  // simulate scan after 2.6s
  useEffect(() => {
    if (!scanning) return;
    const t = setTimeout(() => {
      setScanning(false);
      setTimeout(() => onScanned(), 350);
    }, 2600);
    return () => clearTimeout(t);
  }, [scanning, onScanned]);

  return (
    <div className="app-scroll fade-in" style={{ paddingTop: 70 }}>
      <div style={{ padding: '28px 24px 16px' }}>
        <div className="eyebrow" style={{ marginBottom: 8 }}>JuMA · Bewerter</div>
        <h1 className="h1">Station<br/>scannen.</h1>
        <p className="body" style={{ marginTop: 10 }}>
          Halte die Kamera auf den QR-Code an deiner Station, um dich anzumelden.
        </p>
      </div>

      <div style={{ padding: '8px 24px 24px' }}>
        <div className={`viewfinder ${dark ? 'dark' : ''}`}>
          <span className="corner tl"/>
          <span className="corner tr"/>
          <span className="corner bl"/>
          <span className="corner br"/>
          <span className="scanline"/>
          <div className="qr-fake">
            <FauxQR size="100%" seed={11} color="rgba(255,255,255,0.85)" bg="transparent"/>
          </div>
        </div>
      </div>

      <div style={{ padding: '0 24px', display: 'flex', flexDirection: 'column', gap: 10 }}>
        <button className="btn btn-ghost btn-block">
          <Icon.flash/> Taschenlampe
        </button>
        <button className="btn btn-text" style={{ alignSelf: 'center' }}>
          Code manuell eingeben
        </button>
      </div>
    </div>
  );
}

// ───────────────────────────────────────────
// Station-confirm — recognised station, who am I?
// ───────────────────────────────────────────
function StationConfirmScreen({ station, judge, onConfirm }) {
  return (
    <div className="app-scroll fade-in" style={{ paddingTop: 70 }}>
      <div style={{ padding: '12px 24px 0' }}>
        <div className="eyebrow" style={{ color: 'var(--ok)', marginBottom: 12 }}>
          ✓ Station erkannt
        </div>
        <h1 className="h1" style={{ marginBottom: 4 }}>
          {station.name}.
        </h1>
        <div className="mono" style={{ fontSize: 13, color: 'var(--text-muted)', fontWeight: 600 }}>
          STATION {station.code} · {station.location}
        </div>
      </div>

      <div style={{ padding: '24px 24px 16px' }}>
        <div className="card">
          <div style={{ padding: '16px 16px 8px' }}>
            <div className="eyebrow" style={{ marginBottom: 10 }}>Du bist eingeloggt als</div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <div style={{
                width: 44, height: 44, borderRadius: 12,
                background: 'var(--red-tint)',
                color: 'var(--red)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontWeight: 700, fontSize: 16,
              }}>{judge.initials}</div>
              <div>
                <div style={{ fontSize: 15, fontWeight: 700 }}>{judge.name}</div>
                <div className="caption mono">SR-{judge.id} · {judge.role}</div>
              </div>
            </div>
          </div>
          <div className="divider"/>
          <div className="row-line">
            <div>
              <div className="row-label">Wertungskriterien</div>
              <div className="row-sub">{station.criteria.length} Knoten · {station.penalties.length} Fehlerarten</div>
            </div>
            <div className="caption mono">v{station.version}</div>
          </div>
          <div className="row-line">
            <div>
              <div className="row-label">Wertungszentrale</div>
              <div className="row-sub">Verbunden · letzte Sync vor 4&nbsp;s</div>
            </div>
            <span className="sync-pill">
              <span className="pulse online"/>LIVE
            </span>
          </div>
        </div>
      </div>

      <div style={{ padding: '8px 24px 24px', display: 'flex', flexDirection: 'column', gap: 10 }}>
        <button className="btn btn-primary btn-block btn-lg" onClick={onConfirm}>
          Station übernehmen <Icon.arrow/>
        </button>
        <button className="btn btn-text" style={{ alignSelf: 'center' }}>
          Falsche Station? Neu scannen
        </button>
      </div>
    </div>
  );
}

// ───────────────────────────────────────────
// Dashboard
// ───────────────────────────────────────────
function DashboardScreen({ station, sync, queue, history, onCheckIn }) {
  const last = history[0];
  return (
    <div className="app-scroll fade-in">
      <TopHeader station={station} sync={sync}/>

      <div style={{ padding: '8px 18px 14px' }}>
        <div className="eyebrow">An deiner Station</div>
        <h2 className="h1" style={{ marginTop: 4, fontSize: 26 }}>Bereit für<br/>nächste Gruppe.</h2>
      </div>

      <div style={{ padding: '0 18px 16px' }}>
        <button className="btn btn-primary btn-block btn-lg" onClick={onCheckIn}
                style={{ height: 64, fontSize: 16, borderRadius: 18,
                         boxShadow: '0 8px 24px rgba(212,38,58,0.25), inset 0 1px 0 rgba(255,255,255,0.18)' }}>
          <Icon.qr/> Gruppen-QR scannen
        </button>
        <div className="caption" style={{ textAlign: 'center', marginTop: 8 }}>
          Gruppe meldet sich mit Karte an deiner Station an
        </div>
      </div>

      <div style={{ padding: '4px 18px' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 4px 10px' }}>
          <span className="eyebrow">Warteliste</span>
          <span className="caption mono">{queue.length} OFFEN</span>
        </div>
        <div className="card" style={{ overflow: 'hidden' }}>
          {queue.map((g, i) => (
            <div className="list-row" key={g.id}>
              <div className="group-num" style={i > 0 ? { background: 'var(--surface-alt)', color: 'var(--text-muted)' } : {}}>
                #{g.num}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 14.5, fontWeight: 600, letterSpacing: '-0.005em' }}>{g.name}</div>
                <div className="group-meta">
                  <span>{g.kreis}</span><span className="sep"/>
                  <span>{g.altersgruppe}</span><span className="sep"/>
                  <span>{i === 0 ? 'als nächstes' : `in ~${i * 6} min`}</span>
                </div>
              </div>
              {i === 0 && (
                <span className="total-pill" style={{ background: 'var(--ok-soft)', color: 'var(--ok)' }}>NEXT</span>
              )}
            </div>
          ))}
        </div>
      </div>

      <div style={{ padding: '20px 18px 0' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 4px 10px' }}>
          <span className="eyebrow">Zuletzt bewertet</span>
          <span className="caption mono">HEUTE · {history.length}</span>
        </div>
        {last ? (
          <div className="card card-pad" style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <div className="group-num">#{last.num}</div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 14.5, fontWeight: 600 }}>{last.name}</div>
              <div className="group-meta">
                <span>{last.timestamp}</span><span className="sep"/>
                <span>{last.fehlerpunkte} Fp.</span><span className="sep"/>
                <span style={{ color: 'var(--ok)' }}>übermittelt</span>
              </div>
            </div>
            <Icon.more style={{ color: 'var(--text-subtle)' }}/>
          </div>
        ) : null}
      </div>
    </div>
  );
}

// ───────────────────────────────────────────
// Group check-in (group scans QR)
// ───────────────────────────────────────────
function GroupCheckinScreen({ station, sync, group, onScanned, onBack, dark }) {
  const [scanning, setScanning] = useState(true);
  useEffect(() => {
    if (!scanning) return;
    const t = setTimeout(() => {
      setScanning(false);
      setTimeout(() => onScanned(), 600);
    }, 2400);
    return () => clearTimeout(t);
  }, [scanning, onScanned]);

  return (
    <div className="app-scroll fade-in">
      <TopHeader station={station} sync={sync} onBack={onBack}/>
      <div style={{ padding: '12px 24px 6px' }}>
        <div className="eyebrow">Schritt 1/3 · Anmeldung</div>
        <h2 className="h1" style={{ marginTop: 4, fontSize: 26 }}>
          {scanning ? 'Karte der Gruppe scannen.' : `Gruppe #${group.num}.`}
        </h2>
      </div>

      <div style={{ padding: '12px 24px 18px' }}>
        <div className={`viewfinder ${dark ? 'dark' : ''}`}>
          <span className="corner tl"/>
          <span className="corner tr"/>
          <span className="corner bl"/>
          <span className="corner br"/>
          {scanning && <span className="scanline"/>}
          <div className="qr-fake">
            <FauxQR size="100%" seed={31} color={scanning ? 'rgba(255,255,255,0.65)' : '#fff'} bg="transparent"/>
          </div>
          {!scanning && (
            <div style={{
              position: 'absolute', inset: 0, background: 'rgba(31,122,77,0.25)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
              <div className="sync-check">
                <svg width="28" height="28" viewBox="0 0 18 18" fill="none">
                  <path d="M3.5 9.5l4 4 7-9" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
              </div>
            </div>
          )}
        </div>
      </div>

      <div style={{ padding: '0 24px', minHeight: 90 }}>
        {!scanning ? (
          <div className="card card-pad fade-in" style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <div className="group-num" style={{ background: 'var(--ok-soft)', color: 'var(--ok)' }}>#{group.num}</div>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 15, fontWeight: 700 }}>{group.name}</div>
              <div className="group-meta">
                <span>{group.kreis}</span><span className="sep"/>
                <span>{group.altersgruppe}</span><span className="sep"/>
                <span>{group.startnr}</span>
              </div>
            </div>
            <span className="total-pill" style={{ background: 'var(--ok-soft)', color: 'var(--ok)' }}>OK</span>
          </div>
        ) : (
          <div className="caption" style={{ textAlign: 'center' }}>
            Halte die Kamera ruhig auf die Gruppen-Karte
          </div>
        )}
      </div>

      {!scanning && group && group.members && group.members.length > 0 && (
        <div style={{ padding: '14px 24px 0' }}>
          <button className="btn btn-ghost btn-block" onClick={() => showGroupInfo(group)}>
            <Icon.info/> Gruppen-Info ({group.members.length} Teilnehmer)
          </button>
        </div>
      )}
    </div>
  );
}

// ───────────────────────────────────────────
// Stopwatch
// ───────────────────────────────────────────
function Stopwatch({ value, onChange }) {
  const [running, setRunning] = useState(false);
  const startRef = useRef(0);
  const baseRef = useRef(value || 0);
  const rafRef = useRef(0);

  useEffect(() => {
    if (!running) return;
    startRef.current = performance.now();
    const tick = () => {
      const elapsed = performance.now() - startRef.current;
      onChange(baseRef.current + elapsed);
      rafRef.current = requestAnimationFrame(tick);
    };
    rafRef.current = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(rafRef.current);
  }, [running, onChange]);

  const fmt = (ms) => {
    const total = Math.max(0, ms);
    const m = Math.floor(total / 60000);
    const s = Math.floor((total % 60000) / 1000);
    const cs = Math.floor((total % 1000) / 10);
    return [`${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`, String(cs).padStart(2,'0')];
  };
  const [sec, hund] = fmt(value);
  const handleStop = () => {
    baseRef.current = value;
    setRunning(false);
  };
  const handleStart = () => {
    baseRef.current = value;
    setRunning(true);
  };
  const handleReset = () => {
    baseRef.current = 0;
    setRunning(false);
    onChange(0);
  };

  return (
    <div className="stopwatch">
      <div className="face">
        {sec}<span className="ms">.{hund}</span>
      </div>
      <div className="controls">
        {!running ? (
          <button className="btn btn-primary btn-block" onClick={handleStart}>
            {value > 0 ? 'Weiter' : 'Start'}
          </button>
        ) : (
          <button className="btn btn-block" onClick={handleStop}
                  style={{ background: 'var(--text)', color: 'var(--bg)' }}>
            Stopp
          </button>
        )}
        <button className="btn btn-ghost" onClick={handleReset} style={{ flex: 0, padding: '0 18px' }}>
          ↺
        </button>
      </div>
    </div>
  );
}

// ───────────────────────────────────────────
// Input variants for Fehlerpunkte (driven by tweak)
// ───────────────────────────────────────────
function PenaltyInput({ style, value, onChange, max = 10, step = 1 }) {
  if (style === 'slider') {
    return (
      <div className="slider-wrap">
        <input
          type="range" min="0" max={max} step={step}
          value={value}
          onChange={(e) => onChange(parseInt(e.target.value, 10))}
        />
        <div className="slider-val">{value} Fp</div>
      </div>
    );
  }
  if (style === 'dropdown') {
    return (
      <select className="select" value={value} onChange={(e) => onChange(parseInt(e.target.value, 10))}>
        {Array.from({ length: max + 1 }, (_, i) => i * step).map(v => (
          <option key={v} value={v}>{v} Fp</option>
        ))}
      </select>
    );
  }
  // stepper
  return (
    <div className="stepper">
      <button onClick={() => onChange(Math.max(0, value - step))} disabled={value === 0}>−</button>
      <span className="val">{value}</span>
      <button onClick={() => onChange(Math.min(max, value + step))}>+</button>
    </div>
  );
}

function showGroupInfo(group) {
  const rows = (group.members || []).map((m, i) => `
    <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--border);">
      <div style="width:28px;height:28px;border-radius:8px;background:var(--surface-alt);display:flex;align-items:center;justify-content:center;font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:600;color:var(--text-muted);">${i + 1}</div>
      <div style="flex:1;min-width:0;text-align:left;">
        <div style="font-size:13.5px;font-weight:600;letter-spacing:-0.005em;color:var(--text);">${m.vorname} ${m.name}</div>
        <div style="font-size:11.5px;color:var(--text-subtle);">${m.fn}</div>
      </div>
      <div style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:600;color:var(--text);">${m.alter}<span style="font-size:10px;color:var(--text-subtle);font-weight:500;margin-left:2px;">J</span></div>
    </div>
  `).join('');
  const empty = `<div style="padding:24px 12px;text-align:center;color:var(--text-subtle);font-size:13px;">Keine Mitglieder hinterlegt.</div>`;

  window.JuMAlert.fire({
    title: `#${group.num} · ${group.name}`,
    html: `
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
        ${group.kreis} · ${group.altersgruppe} · ${group.startnr} · ${(group.members || []).length} Teilnehmer
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;max-height:340px;overflow-y:auto;">
        ${(group.members && group.members.length) ? rows : empty}
      </div>
    `,
    showConfirmButton: true,
    confirmButtonText: 'Schließen',
    width: 380,
  });
}

function ImpressionSelect({ value, onChange }) {
  const opts = [
    { id: 'sehr_gut',     label: 'Sehr gut',     short: '1' },
    { id: 'gut',          label: 'Gut',          short: '2' },
    { id: 'befriedigend', label: 'Befriedigend', short: '3' },
  ];
  return (
    <div style={{
      display: 'grid', gridTemplateColumns: '1fr 1fr 1fr',
      gap: 8, padding: 14,
    }}>
      {opts.map(o => {
        const active = value === o.id;
        return (
          <button
            key={o.id}
            onClick={() => onChange(o.id)}
            style={{
              appearance: 'none',
              border: active ? '1px solid var(--red)' : '1px solid var(--border-strong)',
              background: active ? 'var(--red)' : 'var(--surface)',
              color: active ? '#fff' : 'var(--text)',
              padding: '12px 6px 10px',
              borderRadius: 'var(--r-md)',
              cursor: 'pointer',
              fontFamily: 'inherit',
              display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 4,
              transition: 'background 120ms ease, border-color 120ms ease, transform 80ms ease',
              boxShadow: active ? '0 4px 12px rgba(212,38,58,0.20)' : 'none',
            }}
          >
            <span className="mono" style={{
              fontSize: 11, fontWeight: 600, letterSpacing: '0.04em',
              opacity: active ? 0.85 : 0.5,
            }}>0{o.short}</span>
            <span style={{ fontSize: 13.5, fontWeight: 700, letterSpacing: '-0.005em' }}>
              {o.label}
            </span>
          </button>
        );
      })}
    </div>
  );
}

function CheckToggle({ value, onChange }) {
  return (
    <div className="check-toggle">
      <button
        className={`ok ${value === 'ok' ? 'active' : ''}`}
        onClick={() => onChange('ok')}
      >Korrekt</button>
      <button
        className={`fail ${value === 'fail' ? 'active' : ''}`}
        onClick={() => onChange('fail')}
      >Fehler</button>
    </div>
  );
}

// ───────────────────────────────────────────
// Scoring screen (layout depends on flow tweak)
// ───────────────────────────────────────────
function ScoringScreen({ station, sync, group, scoring, setScoring, inputStyle, flowOrder, onSubmit, onBack }) {
  const totalKnotPenalty = useMemo(() => {
    return station.criteria.reduce((sum, c) => sum + (scoring.checks[c.id] === 'fail' ? c.weight : 0), 0);
  }, [scoring.checks, station.criteria]);

  const totalPenalty = useMemo(() => {
    const p = Object.entries(scoring.penalties).reduce((sum, [id, val]) => {
      const def = station.penalties.find(x => x.id === id);
      return sum + (def ? val * def.weight : 0);
    }, 0);
    return p + totalKnotPenalty;
  }, [scoring.penalties, station.penalties, totalKnotPenalty]);

  const setCheck = (id, v) => setScoring(s => ({ ...s, checks: { ...s.checks, [id]: v } }));
  const setPenalty = (id, v) => setScoring(s => ({ ...s, penalties: { ...s.penalties, [id]: v } }));
  const setComment = (v) => setScoring(s => ({ ...s, comment: v }));
  const setTime = (v) => setScoring(s => ({ ...s, time: v }));

  const allChecked = station.criteria.every(c => scoring.checks[c.id]) && scoring.impression;

  return (
    <div className="app-scroll fade-in">
      <TopHeader station={station} sync={sync} onBack={onBack}/>

      {/* Group banner */}
      <div style={{ padding: '8px 18px 12px' }}>
        <div className="card card-pad" style={{ display: 'flex', alignItems: 'center', gap: 12,
                                                 background: 'var(--text)', color: 'var(--bg)', border: 0 }}>
          <div className="group-num" style={{ background: 'rgba(255,255,255,0.12)', color: '#fff' }}>#{group.num}</div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 15, fontWeight: 700, letterSpacing: '-0.005em' }}>{group.name}</div>
            <div className="mono" style={{ fontSize: 11.5, color: 'rgba(255,255,255,0.6)', fontWeight: 500, marginTop: 2 }}>
              {group.kreis} · {group.altersgruppe} · {group.startnr}
            </div>
          </div>
          <button
            onClick={() => showGroupInfo(group)}
            title="Gruppen-Info"
            style={{
              appearance: 'none', border: 0, cursor: 'pointer',
              width: 36, height: 36, borderRadius: 12,
              background: 'rgba(255,255,255,0.10)', color: '#fff',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              transition: 'background 120ms ease',
            }}
            onMouseEnter={(e) => e.currentTarget.style.background = 'rgba(255,255,255,0.18)'}
            onMouseLeave={(e) => e.currentTarget.style.background = 'rgba(255,255,255,0.10)'}
          >
            <Icon.info/>
          </button>
          <div style={{ textAlign: 'right' }}>
            <div className="mono" style={{ fontSize: 22, fontWeight: 600, letterSpacing: '-0.02em' }}>
              {totalPenalty}
            </div>
            <div className="caption" style={{ color: 'rgba(255,255,255,0.55)' }}>FEHLER&shy;PUNKTE</div>
          </div>
        </div>

        <div className="progress-track" style={{ marginTop: 14 }}>
          <div className="progress-step done"/>
          <div className="progress-step active"/>
          <div className="progress-step"/>
        </div>
        <div className="caption" style={{ display: 'flex', justifyContent: 'space-between' }}>
          <span>Schritt 2/3 · Bewertung</span>
          <span className="mono">{station.code}</span>
        </div>
      </div>

      {/* Time card */}
      {station.hasTime && (
        <div style={{ padding: '0 18px 14px' }}>
          <div className="eyebrow" style={{ padding: '0 4px 8px' }}>Zeit</div>
          <div className="card">
            <Stopwatch value={scoring.time} onChange={setTime}/>
          </div>
        </div>
      )}

      {/* Criteria checks */}
      <div style={{ padding: '0 18px 14px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', padding: '0 4px 8px' }}>
          <span className="eyebrow">Kriterien · 4 Knoten</span>
          <span className="caption mono">je Fehler {station.criteria[0].weight} Fp</span>
        </div>
        <div className="card">
          {station.criteria.map(c => {
            const val = scoring.checks[c.id];
            return (
              <div className="row-line" key={c.id}>
                <div>
                  <div className="row-label">{c.label}</div>
                  <div className="row-sub">{c.hint}</div>
                </div>
                <CheckToggle value={val} onChange={(v) => setCheck(c.id, v)}/>
              </div>
            );
          })}
        </div>
      </div>

      {/* Penalties */}
      <div style={{ padding: '0 18px 14px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', padding: '0 4px 8px' }}>
          <span className="eyebrow">Allgemeine Fehlerpunkte</span>
          <span className="caption mono">{inputStyle.toUpperCase()}</span>
        </div>
        <div className="card">
          {station.penalties.map(p => (
            <div className="row-line" key={p.id}>
              <div>
                <div className="row-label">{p.label}</div>
                <div className="row-sub">{p.weight} Fp je Vorfall · max {p.max ?? 10}</div>
              </div>
              <PenaltyInput
                style={inputStyle}
                value={scoring.penalties[p.id] ?? 0}
                onChange={(v) => setPenalty(p.id, v)}
                max={p.max ?? 10}
              />
            </div>
          ))}
        </div>
      </div>

      {/* Impression */}
      <div style={{ padding: '0 18px 14px' }}>
        <div className="eyebrow" style={{ padding: '0 4px 8px' }}>Eindruck der Gruppe</div>
        <div className="card">
          <ImpressionSelect
            value={scoring.impression}
            onChange={(v) => setScoring(s => ({ ...s, impression: v }))}
          />
        </div>
      </div>

      {/* Comment */}
      <div style={{ padding: '0 18px 14px' }}>
        <div className="eyebrow" style={{ padding: '0 4px 8px' }}>Kommentar (optional)</div>
        <textarea
          value={scoring.comment}
          onChange={(e) => setComment(e.target.value)}
          placeholder="Auffälligkeiten an die Wertungszentrale weitergeben..."
          rows={3}
          style={{
            width: '100%', resize: 'none',
            padding: 14,
            background: 'var(--surface)',
            border: '1px solid var(--border)',
            borderRadius: 'var(--r-md)',
            fontFamily: 'inherit', fontSize: 14, lineHeight: 1.4,
            color: 'var(--text)',
            outline: 'none',
            boxShadow: 'var(--shadow-sm)',
          }}
        />
      </div>

      {/* Submit */}
      <div style={{ padding: '6px 18px 16px' }}>
        <button
          className="btn btn-primary btn-block btn-lg"
          onClick={onSubmit}
          disabled={!allChecked}
        >
          {flowOrder === 'confirm-first' ? 'Zur Bestätigung' : 'Bewertung abschließen'} <Icon.arrow/>
        </button>
        {!allChecked && (
          <div className="caption" style={{ textAlign: 'center', marginTop: 10 }}>
            Bitte alle 4 Knoten beurteilen und Eindruck wählen.
          </div>
        )}
      </div>
    </div>
  );
}

// ───────────────────────────────────────────
// Confirm + sync screen
// ───────────────────────────────────────────
function ConfirmScreen({ station, sync, group, scoring, onSubmit, onBack, syncing, done }) {
  const totalKnotPenalty = station.criteria.reduce((sum, c) =>
    sum + (scoring.checks[c.id] === 'fail' ? c.weight : 0), 0);
  const totalGenPenalty = Object.entries(scoring.penalties).reduce((sum, [id, val]) => {
    const def = station.penalties.find(x => x.id === id);
    return sum + (def ? val * def.weight : 0);
  }, 0);
  const total = totalKnotPenalty + totalGenPenalty;
  const fails = station.criteria.filter(c => scoring.checks[c.id] === 'fail');

  return (
    <div className="app-scroll fade-in">
      <TopHeader station={station} sync={sync} onBack={syncing || done ? null : onBack}/>

      <div style={{ padding: '12px 18px 6px' }}>
        <div className="progress-track">
          <div className="progress-step done"/>
          <div className="progress-step done"/>
          <div className="progress-step active"/>
        </div>
        <div className="caption" style={{ display: 'flex', justifyContent: 'space-between' }}>
          <span>Schritt 3/3 · Bestätigung</span>
          <span className="mono">{station.code}</span>
        </div>
        <h2 className="h1" style={{ marginTop: 14, fontSize: 26 }}>Übermitteln<br/>an Wertung.</h2>
      </div>

      <div style={{ padding: '14px 18px 12px' }}>
        <div className="card card-pad-lg">
          <div className="eyebrow" style={{ marginBottom: 10 }}>Zusammenfassung</div>
          <div style={{ display: 'flex', alignItems: 'flex-end', gap: 12 }}>
            <div className="mono" style={{ fontSize: 56, fontWeight: 600, letterSpacing: '-0.04em', lineHeight: 0.95 }}>
              {total}
            </div>
            <div style={{ paddingBottom: 6 }}>
              <div className="caption">Fehlerpunkte gesamt</div>
              <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--text)' }}>
                {totalKnotPenalty} aus Knoten · {totalGenPenalty} allgemein
              </div>
            </div>
          </div>

          <div className="divider" style={{ margin: '16px 0' }}/>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
              <span style={{ color: 'var(--text-muted)' }}>Gruppe</span>
              <span style={{ fontWeight: 600 }}>#{group.num} · {group.name}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
              <span style={{ color: 'var(--text-muted)' }}>Station</span>
              <span style={{ fontWeight: 600 }}>{station.code} · {station.name}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
              <span style={{ color: 'var(--text-muted)' }}>Schiedsrichter</span>
              <span style={{ fontWeight: 600 }}>SR-12 · N. Müller</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
              <span style={{ color: 'var(--text-muted)' }}>Zeitstempel</span>
              <span className="mono" style={{ fontWeight: 600 }}>14:23:08</span>
            </div>
          </div>

          {fails.length > 0 && (
            <>
              <div className="divider" style={{ margin: '16px 0' }}/>
              <div className="eyebrow" style={{ marginBottom: 10, color: 'var(--red)' }}>
                Fehler ({fails.length})
              </div>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                {fails.map(f => (
                  <span key={f.id} className="total-pill" style={{
                    background: 'var(--red-tint)', color: 'var(--red)',
                  }}>{f.label} +{f.weight}</span>
                ))}
              </div>
            </>
          )}

          {scoring.impression && (
            <>
              <div className="divider" style={{ margin: '16px 0' }}/>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div className="eyebrow">Eindruck der Gruppe</div>
                <span className="total-pill" style={{
                  background: 'var(--ok-soft)', color: 'var(--ok)',
                }}>{IMPRESSION_LABEL[scoring.impression]}</span>
              </div>
            </>
          )}

          {scoring.comment && (
            <>
              <div className="divider" style={{ margin: '16px 0' }}/>
              <div className="eyebrow" style={{ marginBottom: 8 }}>Kommentar</div>
              <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--text-muted)' }}>
                "{scoring.comment}"
              </div>
            </>
          )}
        </div>
      </div>

      <div style={{ padding: '6px 18px 16px', display: 'flex', flexDirection: 'column', gap: 10 }}>
        <button className="btn btn-primary btn-block btn-lg" onClick={onSubmit} disabled={syncing || done}>
          {syncing ? 'Übertrage…' : done ? 'Übermittelt' : (
            <>Senden an Wertungszentrale <Icon.arrow/></>
          )}
        </button>
        <button className="btn btn-text" style={{ alignSelf: 'center' }} onClick={onBack} disabled={syncing || done}>
          Korrigieren
        </button>
      </div>

      {(syncing || done) && (
        <div className="sync-overlay">
          {syncing ? (
            <>
              <div className="sync-spinner"/>
              <div style={{ textAlign: 'center' }}>
                <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 4 }}>Wird übertragen…</div>
                <div className="caption mono">→ Wertungszentrale · {station.code}</div>
              </div>
            </>
          ) : (
            <>
              <div className="sync-check">
                <svg width="32" height="32" viewBox="0 0 18 18" fill="none">
                  <path d="M3.5 9.5l4 4 7-9" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
              </div>
              <div style={{ textAlign: 'center' }}>
                <div style={{ fontSize: 18, fontWeight: 700, marginBottom: 4 }}>Übermittelt!</div>
                <div className="caption mono">{total} Fp · Quittung 8F2A-{group.num}</div>
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}

// ───────────────────────────────────────────
// History screen
// ───────────────────────────────────────────
function HistoryScreen({ station, sync, history }) {
  return (
    <div className="app-scroll fade-in">
      <TopHeader station={station} sync={sync}/>
      <div style={{ padding: '8px 18px 14px' }}>
        <div className="eyebrow">Verlauf</div>
        <h2 className="h1" style={{ marginTop: 4, fontSize: 26 }}>{history.length} bewertet<br/>heute.</h2>
      </div>

      <div style={{ padding: '0 18px' }}>
        <div className="card" style={{ overflow: 'hidden' }}>
          {history.map((h, i) => (
            <div className="list-row" key={h.id}>
              <div className="group-num" style={{
                background: h.flag === 'ok' ? 'var(--ok-soft)' : 'var(--red-tint)',
                color: h.flag === 'ok' ? 'var(--ok)' : 'var(--red)',
              }}>#{h.num}</div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 14.5, fontWeight: 600 }}>{h.name}</div>
                <div className="group-meta">
                  <span>{h.timestamp}</span><span className="sep"/>
                  <span>{h.kreis}</span><span className="sep"/>
                  <span style={{ color: h.synced ? 'var(--ok)' : 'var(--warn)' }}>
                    {h.synced ? 'übermittelt' : 'offline · ausstehend'}
                  </span>
                </div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div className="mono" style={{ fontSize: 16, fontWeight: 600 }}>{h.fehlerpunkte}</div>
                <div className="caption" style={{ fontSize: 10 }}>FP</div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

// ───────────────────────────────────────────
// Profile screen
// ───────────────────────────────────────────
function ProfileScreen({ station, sync, judge }) {
  return (
    <div className="app-scroll fade-in">
      <TopHeader station={station} sync={sync}/>
      <div style={{ padding: '8px 18px 16px' }}>
        <div className="eyebrow">Profil</div>
        <div style={{ display: 'flex', gap: 14, alignItems: 'center', marginTop: 14 }}>
          <div style={{
            width: 64, height: 64, borderRadius: 18,
            background: 'var(--red-tint)', color: 'var(--red)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontWeight: 700, fontSize: 22,
          }}>{judge.initials}</div>
          <div>
            <div style={{ fontSize: 20, fontWeight: 700, letterSpacing: '-0.01em' }}>{judge.name}</div>
            <div className="caption mono">SR-{judge.id} · {judge.role}</div>
          </div>
        </div>
      </div>

      <div style={{ padding: '0 18px' }}>
        <div className="card" style={{ overflow: 'hidden' }}>
          <div className="row-line">
            <div>
              <div className="row-label">Aktive Station</div>
              <div className="row-sub">Wechseln durch Neu-Scan</div>
            </div>
            <span className="station-chip">{station.code} · {station.name}</span>
          </div>
          <div className="row-line">
            <div>
              <div className="row-label">Heute bewertet</div>
              <div className="row-sub">Seit 09:12 Uhr</div>
            </div>
            <div className="mono" style={{ fontSize: 18, fontWeight: 600 }}>14</div>
          </div>
          <div className="row-line">
            <div>
              <div className="row-label">Sync-Warteschlange</div>
              <div className="row-sub">Werden bei Verbindung übertragen</div>
            </div>
            <div className="mono" style={{ fontSize: 18, fontWeight: 600,
                                            color: sync === 'offline' ? 'var(--warn)' : 'var(--text-subtle)' }}>
              {sync === 'offline' ? '2' : '0'}
            </div>
          </div>
          <div className="row-line">
            <div>
              <div className="row-label">Datenschutz</div>
              <div className="row-sub">Lokale Daten löschen</div>
            </div>
            <Icon.arrow style={{ color: 'var(--text-subtle)' }}/>
          </div>
        </div>

        <button className="btn btn-ghost btn-block" style={{ marginTop: 16 }} onClick={() => {
          window.JuMAlert.fire({
            title: 'Schiedsrichter wechseln?',
            html: 'Du wirst von der Station <b>' + station.code + '</b> abgemeldet. Offene Bewertungen bleiben in der Sync-Warteschlange.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Abmelden',
            cancelButtonText: 'Bleiben',
          }).then(r => {
            if (r.isConfirmed) {
              window.JuMAToast.fire({ icon: 'success', title: 'Abgemeldet' });
            }
          });
        }}>
          Schiedsrichter wechseln
        </button>
      </div>
    </div>
  );
}

// expose for app.jsx
const IMPRESSION_LABEL = {
  sehr_gut: 'Sehr gut',
  gut: 'Gut',
  befriedigend: 'Befriedigend',
};

Object.assign(window, {
  LoginScreen, StationConfirmScreen, DashboardScreen, GroupCheckinScreen,
  ScoringScreen, ConfirmScreen, HistoryScreen, ProfileScreen,
  TopHeader, TabBar, FauxQR, Icon, ImpressionSelect, IMPRESSION_LABEL,
});
