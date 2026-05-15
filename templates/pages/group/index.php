<?php ob_start(); ?>

<style>
/* Sticky Header — kein Fixed-Positioning-Konflikt mit html5-qrcode */
.gi_header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: var(--wt-bg);
    border-bottom: 1px solid var(--wt-border);
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
}
.gi_header__logo  { font-size: 20px; font-weight: 900; letter-spacing: -1px; color: var(--wt-red); flex-shrink: 0; }
.gi_header__title { font-size: 12px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--wt-text-subtle); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.gi_main { padding: 16px; display: flex; flex-direction: column; gap: 14px; }

/* Scanner */
.gi_scanner-wrap { border-radius: var(--wt-r-lg); overflow: hidden; background: #000; }
#gi-qr-reader video { border-radius: 0 !important; }

/* Karte */
.gi_map { height: 300px; }

/* Distanz */
.gi_dist-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.gi_dist-card { text-align: center; padding: 14px; }
.gi_dist-val  { font-family: 'JetBrains Mono', monospace; font-size: 22px; font-weight: 800; }
.gi_dist-lbl  { font-size: 11px; color: var(--wt-text-subtle); margin-top: 3px; }
.gi_bar-track { height: 8px; background: var(--wt-border-strong); border-radius: var(--wt-r-pill); overflow: hidden; margin-top: 10px; }
.gi_bar-fill  { height: 100%; border-radius: var(--wt-r-pill); background: var(--wt-ok); transition: width .5s; }

/* Stationskreis */
.gi_stn-dot { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 800; }

/* Notizen */
.gi_notes-box { background: var(--wt-warn-soft); border-left: 3px solid var(--wt-warn); border-radius: 0 var(--wt-r-sm) var(--wt-r-sm) 0; padding: 10px 12px; font-size: 13px; color: var(--wt-text-muted); }

/* Tracking-Button */
.gi_tracking-btn { transition: background .2s; }
.gi_tracking-btn--active { background: var(--wt-ok) !important; color: #fff !important; }

/* Ansagen-Panel */
.gi_announce-panel { background: var(--wt-warn-soft); border-left: 3px solid var(--wt-warn); border-radius: 0 var(--wt-r-sm) var(--wt-r-sm) 0; }
.gi_announce-item  { padding: 10px 14px; border-bottom: 1px solid rgba(0,0,0,.06); font-size: 13px; color: var(--wt-text); }
.gi_announce-item:last-child { border-bottom: 0; }
.gi_announce-ts    { font-size: 11px; color: var(--wt-text-subtle); margin-top: 2px; font-family: monospace; }

/* QR-Modal */
.gi_qr-btn { padding: 0 10px; height: 32px; border-radius: var(--wt-r-sm); }

/* Toast-Benachrichtigung */
.gi_toast {
    position: fixed;
    bottom: 24px; left: 50%; transform: translateX(-50%) translateY(80px);
    z-index: 9999;
    background: var(--wt-ok);
    color: #fff;
    font-size: 14px; font-weight: 700;
    padding: 12px 22px;
    border-radius: var(--wt-r-pill);
    box-shadow: var(--wt-shadow-lg);
    white-space: nowrap;
    opacity: 0;
    transition: transform .35s cubic-bezier(.34,1.56,.64,1), opacity .25s;
    pointer-events: none;
}
.gi_toast--show { transform: translateX(-50%) translateY(0); opacity: 1; }

/* Karte bei Update kurz aufleuchten */
@keyframes gi-flash { 0%,100%{opacity:1} 50%{opacity:.4} }
.gi_map--flash { animation: gi-flash .6s ease; }
</style>

<!-- Sticky Header -->
<header class="gi_header">
    <span class="gi_header__logo">KFV Tirschenreuth</span>
    <span class="gi_header__title" id="gi-header-title">Gruppeninfo</span>
    <button class="wt_btn wt_btn--ghost wt_btn--sm" id="gi-reset-btn"
            style="display:none;flex-shrink:0;" onclick="resetScanner()">↩ Neu</button>
</header>

<main class="gi_main">

    <!-- QR-Scanner -->
    <div class="wt_card" id="gi-scanner-card">
        <div style="padding:12px 16px 0;" class="wt_eyebrow">QR-Code scannen</div>
        <div style="padding:12px 16px 16px;display:flex;flex-direction:column;gap:12px;">
            <p class="wt_caption" style="text-align:center;">Scannt euren Gruppen-QR-Code ein.</p>
            <div class="gi_scanner-wrap"><div id="gi-qr-reader"></div></div>
            <div id="gi-scan-error" class="wt_alert wt_alert--error" style="display:none;"></div>
        </div>
    </div>

    <!-- Ergebnis -->
    <div id="gi-result" style="display:none;flex-direction:column;gap:14px;">

        <!-- Laufweg + Wettbewerb + GPS/Tracking-Buttons -->
        <div class="wt_card">
            <div class="wt_row" style="flex-wrap:wrap;gap:8px;">
                <div id="gi-lw-badge" class="wt_badge" style="display:none;"></div>
                <span class="wt_caption" id="gi-comp-name" style="color:var(--wt-text-muted);"></span>
                <div style="margin-left:auto;display:flex;gap:8px;">
                    <button class="wt_btn wt_btn--ghost wt_btn--sm gi_qr-btn"
                            id="gi-qr-show-btn" onclick="showQrModal()" title="QR-Code anzeigen">
                        <svg width="16" height="16" viewBox="0 0 22 22" fill="none"><rect x="2" y="2" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/><rect x="13" y="2" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/><rect x="2" y="13" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/><rect x="4.5" y="4.5" width="2" height="2" fill="currentColor"/><rect x="15.5" y="4.5" width="2" height="2" fill="currentColor"/><rect x="4.5" y="15.5" width="2" height="2" fill="currentColor"/></svg>
                    </button>
                    <button class="wt_btn wt_btn--ghost wt_btn--sm gi_tracking-btn"
                            id="gi-gps-btn" onclick="toggleGps()">📍 GPS</button>
                    <button class="wt_btn wt_btn--ghost wt_btn--sm gi_tracking-btn"
                            id="gi-tracking-btn" onclick="toggleTracking()" style="display:none;">
                        📡 Tracking
                    </button>
                </div>
            </div>
            <div id="gi-gps-status" class="wt_caption" style="display:none;padding:0 16px 10px;color:var(--wt-text-subtle);"></div>
        </div>

        <!-- Streckenverlauf -->
        <div class="wt_card" id="gi-dist-section" style="display:none;">
            <div style="padding:12px 16px 0;display:flex;align-items:center;gap:8px;">
                <span class="wt_eyebrow">Streckenverlauf</span>
                <span id="gi-dist-loading" class="wt_caption" style="color:var(--wt-warn);">wird berechnet…</span>
            </div>
            <div style="padding:12px 16px 16px;">
                <div class="gi_dist-grid">
                    <div class="wt_card gi_dist-card">
                        <div class="gi_dist-val" id="gi-covered" style="color:var(--wt-ok);">–</div>
                        <div class="gi_dist-lbl">Zurückgelegt</div>
                    </div>
                    <div class="wt_card gi_dist-card">
                        <div class="gi_dist-val" id="gi-remaining" style="color:var(--wt-warn);">–</div>
                        <div class="gi_dist-lbl">Noch offen</div>
                    </div>
                </div>
                <div class="gi_bar-track">
                    <div class="gi_bar-fill" id="gi-progress-bar" style="width:0%;"></div>
                </div>
                <div class="wt_caption" id="gi-progress-lbl" style="text-align:right;margin-top:4px;"></div>
            </div>
        </div>

        <!-- Karte -->
        <div class="wt_card" id="gi-map-card" style="display:none;overflow:hidden;">
            <div style="padding:12px 16px;" class="wt_eyebrow">Navigation zur nächsten Station</div>
            <div id="gi-map" class="gi_map"></div>
            <div style="padding:10px 16px 14px;display:flex;flex-direction:column;gap:6px;">
                <div id="gi-nav-info" class="wt_caption"></div>
                <div id="gi-arrival-info" style="display:none;font-size:13px;font-weight:700;color:var(--wt-ok);"></div>
                <div id="gi-route-notes" class="gi_notes-box" style="display:none;"></div>
            </div>
        </div>

        <!-- Ansagen vom Wertungsbüro -->
        <div class="wt_card gi_announce-panel" id="gi-announce-card" style="display:none;padding:0;overflow:hidden;">
            <div style="padding:10px 14px 8px;display:flex;align-items:center;gap:8px;">
                <span style="font-size:16px;">📢</span>
                <span class="wt_eyebrow" style="color:var(--wt-warn);">Meldungen vom Wertungsbüro</span>
            </div>
            <div id="gi-announce-list"></div>
        </div>

        <!-- Stationen -->
        <div class="wt_card">
            <div style="padding:12px 16px 0;" class="wt_eyebrow">Stationen</div>
            <div id="gi-stations-list"></div>
        </div>

        <!-- Teilnehmer -->
        <div class="wt_card" id="gi-members-card" style="display:none;">
            <div style="padding:12px 16px 0;" class="wt_eyebrow">Teilnehmer</div>
            <div id="gi-members-list"></div>
        </div>

        <!-- Hilfe -->
        <div class="wt_card">
            <div style="padding:12px 16px 0;" class="wt_eyebrow">Hilfe anfordern</div>
            <div style="padding:12px 16px 16px;display:flex;flex-direction:column;gap:10px;">
                <div class="wt_alert wt_alert--info" style="font-size:13px;">
                    Nur bei echtem Bedarf verwenden — ein Betreuer kommt dann zu euch.
                </div>
                <textarea class="wt_textarea" id="gi-help-msg" rows="2"
                          placeholder="Optionale Nachricht (Standort, Art des Problems…)"></textarea>
                <button class="wt_btn wt_btn--block" id="gi-help-btn" onclick="sendHelp()"
                        style="background:var(--wt-warn);color:#fff;">🆘 Hilfe anfordern</button>
                <div id="gi-help-result" class="wt_alert wt_alert--success" style="display:none;">
                    ✓ Hilfe wurde angefordert. Ein Betreuer kommt zu euch.
                </div>
            </div>
        </div>

    </div>
</main>

<div class="gi_toast" id="gi-toast"></div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
'use strict';

let scanner          = null;
let groupToken       = null;
let rawQrValue       = null;
let mapInstance      = null;
let gpsWatchId       = null;
let gpsMarker        = null;
let trackingTimer    = null;
let lastGpsPos       = null;
let trackingOn       = false;
let pollTimer        = null;
let announceTimer     = null;
let seenAnnounceIds   = new Set();
let firstAnnouncePoll = true;
let lastCheckedOut   = null;
let lastStationId    = null;

// ── Wake Lock ─────────────────────────────────────────
let wakeLock = null;

async function acquireWakeLock() {
    if (!('wakeLock' in navigator)) return;
    try {
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLock.addEventListener('release', () => { wakeLock = null; });
    } catch {}
}

function releaseWakeLock() {
    wakeLock?.release().catch(() => {});
    wakeLock = null;
}

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && groupToken) acquireWakeLock();
});

// ── Formatierung ──────────────────────────────────────
const fmtDist = m => !m ? '–' : m >= 1000 ? (m/1000).toFixed(1)+' km' : m+' m';
const fmtTime = t => t ? 'ca. '+t+' min' : '';
const fmtTs   = ts => ts ? new Date(ts.replace(' ','T')).toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'}) : '';

// ── Scanner ───────────────────────────────────────────
function startScanner() {
    scanner = new Html5Qrcode('gi-qr-reader');
    scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 240, height: 240 } },
        onScanSuccess,
        () => {}
    ).catch(err => showScanError('Kamera nicht verfügbar: ' + err));
}

async function onScanSuccess(raw) {
    try { await scanner.stop(); } catch {}
    try { scanner.clear(); }     catch {}
    scanner = null;

    rawQrValue = raw;
    let token = raw;
    try { const u = new URL(raw); token = u.searchParams.get('token') || raw; } catch {}
    loadGroupInfo(token.trim());
}

async function loadGroupInfo(token) {
    groupToken = token;
    document.getElementById('gi-scan-error').style.display = 'none';
    try {
        const res  = await fetch('/api/group/info', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ token }),
        });
        const json = await res.json();
        if (!json.success) { showScanError(json.error || 'Fehler'); restartScanner(); return; }
        renderResult(json.data);
    } catch (e) {
        showScanError('Verbindungsfehler: ' + e.message);
        restartScanner();
    }
}

function showScanError(msg) {
    const el = document.getElementById('gi-scan-error');
    el.textContent = msg; el.style.display = 'block';
}

function restartScanner() {
    document.getElementById('gi-scanner-card').style.display = 'block';
    startScanner();
}

function resetScanner() {
    if (scanner)   { try { scanner.stop(); } catch {} try { scanner.clear(); } catch {} scanner = null; }
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }
    stopGps();
    stopTracking();
    stopPolling();
    stopAnnouncePolling();
    releaseWakeLock();
    seenAnnounceIds   = new Set();
    firstAnnouncePoll = true;
    rawQrValue        = null;
    document.getElementById('gi-result').style.display      = 'none';
    document.getElementById('gi-scanner-card').style.display = 'block';
    document.getElementById('gi-scan-error').style.display   = 'none';
    document.getElementById('gi-help-result').style.display  = 'none';
    document.getElementById('gi-help-btn').disabled          = false;
    document.getElementById('gi-help-btn').textContent       = '🆘 Hilfe anfordern';
    document.getElementById('gi-header-title').textContent   = 'Gruppeninfo';
    document.getElementById('gi-reset-btn').style.display    = 'none';
    groupToken = null;
    startScanner();
}

// ── Ergebnis rendern ──────────────────────────────────
function renderResult(data) {
    document.getElementById('gi-scanner-card').style.display = 'none';
    const result = document.getElementById('gi-result');
    result.style.display = 'flex';

    // Header
    const label = (data.group.num ? '#'+data.group.num+' ' : '') + data.group.name;
    document.getElementById('gi-header-title').textContent = label;
    document.getElementById('gi-reset-btn').style.display  = 'block';

    // Laufweg-Badge
    const badge = document.getElementById('gi-lw-badge');
    if (data.laufweg) {
        badge.style.cssText = `display:inline-flex;background:${data.laufweg.color}22;color:${data.laufweg.color};border:1px solid ${data.laufweg.color}66;`;
        badge.innerHTML = `<span style="width:8px;height:8px;border-radius:50%;background:${data.laufweg.color};display:inline-block;margin-right:4px;"></span>${data.laufweg.name}`;
    }
    if (data.group.competition_name) {
        document.getElementById('gi-comp-name').textContent = data.group.competition_name;
    }

    // Tracking-Button freischalten
    document.getElementById('gi-tracking-btn').style.display = 'inline-flex';

    // Streckenverlauf
    if (data.total_m > 0 || data.all_segments?.length) {
        document.getElementById('gi-dist-section').style.display = 'block';
        updateDistDisplay(data.covered_m, data.remaining_m, data.total_m);
        if (data.all_segments?.length) calcOsrmDistances(data);
    }

    renderStations(data);
    renderMembers(data);

    if (data.next_station?.lat) {
        document.getElementById('gi-map-card').style.display = 'block';
        setTimeout(() => renderMap(data), 150);
    }

    // Polling-Startzustand merken + Intervall starten
    lastCheckedOut = data.last_station?.checked_out ?? null;
    lastStationId  = data.last_station?.id          ?? null;
    startPolling();
    startAnnouncePolling();
    acquireWakeLock();
}

function updateDistDisplay(covered, remaining, total) {
    document.getElementById('gi-covered').textContent   = fmtDist(covered);
    document.getElementById('gi-remaining').textContent = fmtDist(remaining);
    const pct = total > 0 ? Math.round(covered / total * 100) : 0;
    document.getElementById('gi-progress-bar').style.width = pct + '%';
    document.getElementById('gi-progress-lbl').textContent = pct + '% von ' + fmtDist(total);
}

// ── OSRM Gesamtstrecke ────────────────────────────────
async function calcOsrmSeg(seg) {
    if (!seg.from_lat || !seg.to_lat) return null;
    const coords = [
        [seg.from_lng, seg.from_lat],
        ...(seg.waypoints || []).map(wp => [wp[1], wp[0]]),
        [seg.to_lng, seg.to_lat],
    ];
    const url = `https://router.project-osrm.org/route/v1/foot/${coords.map(c=>c.join(',')).join(';')}?overview=false`;
    try {
        const r = await fetch(url, { signal: AbortSignal.timeout(7000) });
        const j = await r.json();
        if (j.code === 'Ok' && j.routes?.[0]) return Math.round(j.routes[0].distance);
    } catch {}
    return null;
}

async function calcOsrmDistances(data) {
    const results = await Promise.all(data.all_segments.map(calcOsrmSeg));
    let totalM = 0, coveredM = 0;
    data.all_segments.forEach((seg, i) => {
        const d = results[i] ?? seg.distance_m;
        totalM += d;
        if (seg.done) coveredM += d;
    });
    document.getElementById('gi-dist-loading').style.display = 'none';
    updateDistDisplay(coveredM, totalM - coveredM, totalM);
}

// ── Stationsliste ─────────────────────────────────────
function renderStations(data) {
    const list = document.getElementById('gi-stations-list');
    list.innerHTML = '';
    data.visited.forEach(v => {
        const isCurrent = v.id === data.last_station?.id && !v.done;
        const dotBg  = v.done ? 'var(--wt-ok)' : isCurrent ? 'var(--wt-warn)' : 'var(--wt-border-strong)';
        const dotClr = (v.done || isCurrent) ? '#fff' : 'var(--wt-text-subtle)';
        const time   = v.done ? fmtTs(v.checked_in)+' – '+fmtTs(v.checked_out) : 'Seit '+fmtTs(v.checked_in);
        const row = document.createElement('div');
        row.className = 'wt_row';
        row.innerHTML = `
            <div class="gi_stn-dot" style="background:${dotBg};color:${dotClr};">${v.code}</div>
            <div style="flex:1;"><div class="wt_row__label">${v.name}</div><div class="wt_row__sub">${time}</div></div>
            ${v.done ? '<span style="color:var(--wt-ok);font-size:18px;">✓</span>' : ''}`;
        list.appendChild(row);
    });
    if (data.next_station) {
        const ns  = data.next_station;
        const sub = [fmtDist(ns.distance_m), fmtTime(ns.est_time_min)].filter(Boolean).join(' · ');
        const row = document.createElement('div');
        row.className = 'wt_row';
        row.innerHTML = `
            <div class="gi_stn-dot" style="background:var(--wt-surface);color:var(--wt-red);border:2.5px solid var(--wt-red);">${ns.code}</div>
            <div style="flex:1;"><div class="wt_row__label">${ns.name}</div><div class="wt_row__sub" style="color:var(--wt-red);">${sub}</div></div>
            <span style="font-size:18px;">→</span>`;
        list.appendChild(row);
    }
}

function renderMembers(data) {
    if (!data.members?.length) return;
    document.getElementById('gi-members-card').style.display = 'block';
    const list = document.getElementById('gi-members-list');
    list.innerHTML = '';
    data.members.forEach(m => {
        const row = document.createElement('div');
        row.className = 'wt_row';
        row.innerHTML = `<div style="flex:1;"><div class="wt_row__label">${m.vorname} ${m.name}</div>${m.funktion ? `<div class="wt_row__sub">${m.funktion}</div>` : ''}</div>`;
        list.appendChild(row);
    });
}

// ── Karte ─────────────────────────────────────────────
function renderMap(data) {
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }
    const ns    = data.next_station;
    const color = data.laufweg?.color ?? '#D4263A';

    mapInstance = L.map('gi-map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 19,
    }).addTo(mapInstance);

    const pts = [];
    if (ns.from_lat) {
        pts.push([ns.from_lat, ns.from_lng]);
        L.marker([ns.from_lat, ns.from_lng], { icon: stnIcon(data.last_station?.code ?? '?', color, true) })
            .addTo(mapInstance)
            .bindPopup(`<strong>${data.last_station?.name ?? 'Letzte Station'}</strong><br>Ihr wart hier`);
    }
    pts.push([ns.lat, ns.lng]);
    L.marker([ns.lat, ns.lng], { icon: stnIcon(ns.code, color, false) })
        .addTo(mapInstance).bindPopup(`<strong>Ziel: ${ns.name}</strong>`);

    if (ns.from_lat) drawRoute(data, color);

    if (pts.length > 1) mapInstance.fitBounds(L.latLngBounds(pts), { padding:[40,40] });
    else mapInstance.setView(pts[0], 15);
}

function stnIcon(code, color, filled) {
    return L.divIcon({
        className: '',
        html: `<div style="background:${filled?color:'#fff'};color:${filled?'#fff':color};border:3px solid ${color};border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-family:monospace;font-size:12px;font-weight:800;box-shadow:0 2px 8px rgba(0,0,0,.25);">${code}</div>`,
        iconSize:[36,36], iconAnchor:[18,18],
    });
}

async function drawRoute(data, color) {
    const ns   = data.next_station;
    const wps  = ns.waypoints ?? [];
    const coords = [
        [ns.from_lng, ns.from_lat],
        ...wps.map(wp => [wp[1], wp[0]]),
        [ns.lng, ns.lat],
    ];
    const url = `https://router.project-osrm.org/route/v1/foot/${coords.map(c=>c.join(',')).join(';')}?overview=full&geometries=geojson`;
    try {
        const res  = await fetch(url, { signal: AbortSignal.timeout(8000) });
        const json = await res.json();
        if (json.code === 'Ok' && json.routes?.[0]) {
            const latlngs = json.routes[0].geometry.coordinates.map(c => [c[1],c[0]]);
            L.polyline(latlngs, { color, weight:5, opacity:.85, dashArray:'8,4' }).addTo(mapInstance);
            const dist    = Math.round(json.routes[0].distance);
            const osrmMin = json.routes[0].duration / 60;
            const time    = Math.max(1, Math.ceil(osrmMin < dist/75*0.5 ? dist/75 : osrmMin));
            document.getElementById('gi-nav-info').innerHTML =
                `<b style="color:var(--wt-red);">→ ${ns.name}</b> &nbsp;·&nbsp; ${fmtDist(dist)} &nbsp;·&nbsp; ca. ${time} min zu Fuß`;
            const arrival = new Date(Date.now() + time * 60000);
            const arrEl   = document.getElementById('gi-arrival-info');
            arrEl.style.display = 'block';
            arrEl.textContent   = '⏱ Erwartete Ankunft: ' + arrival.toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'}) + ' Uhr';
            if (ns.notes) {
                document.getElementById('gi-route-notes').style.display = 'block';
                document.getElementById('gi-route-notes').textContent   = '📋 ' + ns.notes;
            }
            return;
        }
    } catch {}
    // Fallback Luftlinie
    L.polyline([[ns.from_lat,ns.from_lng],...wps,[ns.lat,ns.lng]], { color, weight:4, opacity:.7, dashArray:'6,4' }).addTo(mapInstance);
}

// ── GPS-Position ──────────────────────────────────────
function toggleGps() {
    if (gpsWatchId !== null) { stopGps(); return; }
    if (!navigator.geolocation) { alert('GPS nicht verfügbar'); return; }

    const btn = document.getElementById('gi-gps-btn');
    const status = document.getElementById('gi-gps-status');
    btn.textContent = '📍 …';
    status.style.display = 'block';
    status.textContent   = 'GPS wird abgefragt…';

    gpsWatchId = navigator.geolocation.watchPosition(
        pos => {
            lastGpsPos = pos;
            btn.textContent = '📍 GPS aktiv';
            btn.classList.add('gi_tracking-btn--active');
            status.textContent = `GPS aktiv · Genauigkeit ±${Math.round(pos.coords.accuracy)} m`;

            const ll = [pos.coords.latitude, pos.coords.longitude];
            if (mapInstance) {
                if (!gpsMarker) {
                    const icon = L.divIcon({
                        className: '',
                        html: '<div style="width:16px;height:16px;border-radius:50%;background:#2980B9;border:3px solid #fff;box-shadow:0 0 0 4px rgba(41,128,185,.35);"></div>',
                        iconSize:[16,16], iconAnchor:[8,8],
                    });
                    gpsMarker = L.marker(ll, { icon, zIndexOffset:2000 })
                        .addTo(mapInstance).bindTooltip('Dein Standort');
                } else {
                    gpsMarker.setLatLng(ll);
                }
            }
        },
        err => {
            stopGps();
            status.style.display = 'block';
            status.textContent   = 'GPS-Fehler: ' + err.message;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
    );
}

function stopGps() {
    if (gpsWatchId !== null) { navigator.geolocation.clearWatch(gpsWatchId); gpsWatchId = null; }
    if (gpsMarker && mapInstance) { gpsMarker.remove(); gpsMarker = null; }
    const btn = document.getElementById('gi-gps-btn');
    btn.textContent = '📍 GPS';
    btn.classList.remove('gi_tracking-btn--active');
    document.getElementById('gi-gps-status').style.display = 'none';
    lastGpsPos = null;
}

// ── Tracking (alle 60 s Position senden) ─────────────
function toggleTracking() {
    if (trackingOn) { stopTracking(); return; }

    // GPS muss aktiv sein
    if (gpsWatchId === null) {
        toggleGps();
        // kurz warten bis GPS initialisiert
        setTimeout(() => { if (gpsWatchId !== null) startTracking(); }, 2000);
    } else {
        startTracking();
    }
}

function startTracking() {
    trackingOn = true;
    const btn  = document.getElementById('gi-tracking-btn');
    btn.textContent = '📡 Tracking aktiv';
    btn.classList.add('gi_tracking-btn--active');

    sendLocation(); // sofort einmal senden
    trackingTimer = setInterval(sendLocation, 60000);
}

function stopTracking() {
    if (trackingTimer) { clearInterval(trackingTimer); trackingTimer = null; }
    trackingOn = false;
    const btn  = document.getElementById('gi-tracking-btn');
    btn.textContent = '📡 Tracking';
    btn.classList.remove('gi_tracking-btn--active');
}

async function sendLocation() {
    if (!groupToken || !lastGpsPos) return;
    try {
        await fetch('/api/group/location', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                token:    groupToken,
                lat:      lastGpsPos.coords.latitude,
                lng:      lastGpsPos.coords.longitude,
                accuracy: lastGpsPos.coords.accuracy,
            }),
        });
    } catch {}
}

// ── Hilfe senden ──────────────────────────────────────
async function sendHelp() {
    const btn = document.getElementById('gi-help-btn');
    const msg = document.getElementById('gi-help-msg').value.trim();
    btn.disabled = true; btn.textContent = 'Wird gesendet…';
    try {
        const res  = await fetch('/api/group/help', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ token: groupToken, message: msg }),
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('gi-help-result').style.display = 'block';
            btn.style.display = 'none';
        } else {
            btn.disabled = false; btn.textContent = '🆘 Hilfe anfordern';
            alert(data.error || 'Fehler');
        }
    } catch {
        btn.disabled = false; btn.textContent = '🆘 Hilfe anfordern';
        alert('Verbindungsfehler');
    }
}

// ── QR-Code Modal ─────────────────────────────────────
function showQrModal() {
    if (!groupToken) return;
    const qrData = encodeURIComponent(rawQrValue || groupToken);
    const d = document.createElement('dialog');
    d.style.cssText = 'border:0;border-radius:20px;padding:0;background:var(--wt-surface);color:var(--wt-text);width:300px;max-width:90vw;box-shadow:0 16px 48px rgba(0,0,0,.22);';
    d.innerHTML = `
        <div style="padding:16px 16px 0;text-align:center;">
            <div style="font-weight:800;font-size:15px;margin-bottom:14px;">QR-Code der Gruppe</div>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=${qrData}"
                 width="220" height="220" style="display:block;margin:0 auto;border-radius:10px;"
                 alt="QR-Code">
            <div style="font-size:11px;color:var(--wt-text-subtle);margin-top:10px;font-family:monospace;
                        word-break:break-all;padding:0 8px;">${groupToken}</div>
        </div>
        <div style="padding:14px 16px;">
            <button style="width:100%;height:44px;border:1px solid var(--wt-border);
                           background:var(--wt-surface-alt);border-radius:12px;
                           font-family:inherit;font-weight:600;font-size:14px;cursor:pointer;
                           color:var(--wt-text);"
                    onclick="this.closest('dialog').close()">Schließen</button>
        </div>`;
    document.body.appendChild(d);
    d.showModal();
    d.addEventListener('close', () => d.remove());
}

// ── Ansagen vom Wertungsbüro ──────────────────────────
function startAnnouncePolling() {
    stopAnnouncePolling();
    pollAnnouncements();
    announceTimer = setInterval(pollAnnouncements, 30_000);
}

function stopAnnouncePolling() {
    if (announceTimer) { clearInterval(announceTimer); announceTimer = null; }
}

async function pollAnnouncements() {
    if (!groupToken) return;
    try {
        const res  = await fetch('/api/group/announcements', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ token: groupToken }),
        });
        const json = await res.json();
        if (!json.success) return;
        const items = (json.data?.announcements ?? []);

        const card = document.getElementById('gi-announce-card');
        const list = document.getElementById('gi-announce-list');
        if (!card || !list) return;

        if (items.length === 0) { card.style.display = 'none'; return; }

        card.style.display = 'block';
        list.innerHTML = items.map(a => {
            const ts = a.created_at
                ? new Date(a.created_at.replace(' ', 'T'))
                    .toLocaleString('de-DE', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' })
                : '';
            return `<div class="gi_announce-item">
                <div>${a.body}</div>
                <div class="gi_announce-ts">${ts}</div>
            </div>`;
        }).join('');

        // Beim ersten Poll alle Ansagen still als gesehen markieren
        // Danach: Toast für wirklich neue Einträge
        let hasNew = false;
        items.forEach(a => {
            if (!seenAnnounceIds.has(a.id)) {
                seenAnnounceIds.add(a.id);
                if (!firstAnnouncePoll) hasNew = true;
            }
        });
        firstAnnouncePoll = false;
        if (hasNew) showToast('📢 Neue Meldung vom Wertungsbüro');
    } catch {}
}

// ── Polling: Station-Status überwachen ───────────────
function startPolling() {
    stopPolling();
    pollTimer = setInterval(pollStatus, 15000);
}

function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

async function pollStatus() {
    if (!groupToken) return;
    try {
        const res  = await fetch('/api/group/info', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ token: groupToken }),
        });
        const json = await res.json();
        if (!json.success) return;
        const data = json.data;

        const newCheckout   = data.last_station?.checked_out ?? null;
        const newStationId  = data.last_station?.id          ?? null;

        // Fall 1: Bewertung abgeschlossen (checked_out gesetzt) → Navigation aktualisieren
        if (newCheckout && !lastCheckedOut && newStationId === lastStationId) {
            lastCheckedOut = newCheckout;
            onStationCompleted(data);
            return;
        }

        // Fall 2: Gruppe ist bereits an nächster Station angekommen (neue station_id)
        if (newStationId !== lastStationId && newStationId !== null) {
            lastStationId  = newStationId;
            lastCheckedOut = newCheckout;
            onStationChanged(data);
        }
    } catch {}
}

function onStationCompleted(data) {
    showToast('✓ Station abgeschlossen — Navigation wird aktualisiert');
    updateNavigation(data);
}

function onStationChanged(data) {
    showToast('📍 Neue Station erkannt');
    updateNavigation(data);
}

function updateNavigation(data) {
    // Streckenverlauf aktualisieren
    updateDistDisplay(data.covered_m, data.remaining_m, data.total_m);
    if (data.all_segments?.length) calcOsrmDistances(data);

    // Stationsliste neu rendern
    renderStations(data);

    // Karte: aufleuchten + neu zeichnen wenn neue Next-Station vorhanden
    const mapCard = document.getElementById('gi-map-card');
    if (data.next_station?.lat) {
        mapCard.style.display = 'block';
        const mapEl = document.getElementById('gi-map');
        mapEl.classList.remove('gi_map--flash');
        // Reflow erzwingen damit Animation neu startet
        void mapEl.offsetWidth;
        mapEl.classList.add('gi_map--flash');
        setTimeout(() => renderMap(data), 300);
    } else if (!data.next_station) {
        // Alle Stationen absolviert
        mapCard.style.display = 'none';
        showToast('🏁 Alle Stationen abgeschlossen!');
        stopPolling();
    }
}

// ── Toast ─────────────────────────────────────────────
function showToast(msg) {
    const el = document.getElementById('gi-toast');
    el.textContent = msg;
    el.classList.add('gi_toast--show');
    setTimeout(() => el.classList.remove('gi_toast--show'), 3500);
}

startScanner();
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/group.php';
?>
