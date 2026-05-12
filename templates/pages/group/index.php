<?php ob_start(); ?>

<style>
/* Gruppeninfo-spezifische Ergänzungen — nur was wt_ nicht abdeckt */
.gi_scanner-wrap { border-radius: var(--wt-r-lg); overflow: hidden; background: #000; }
#gi-qr-reader video { border-radius: 0 !important; }

.gi_map { height: 260px; }

.gi_dist-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.gi_dist-card { text-align: center; padding: 14px; }
.gi_dist-val  { font-family: 'JetBrains Mono', monospace; font-size: 22px; font-weight: 800; }
.gi_dist-lbl  { font-size: 11px; color: var(--wt-text-subtle); margin-top: 3px; }

.gi_bar-track { height: 8px; background: var(--wt-border-strong); border-radius: var(--wt-r-pill); overflow: hidden; margin-top: 10px; }
.gi_bar-fill  { height: 100%; border-radius: var(--wt-r-pill); background: var(--wt-ok); transition: width .4s; }

.gi_stn-dot {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 800;
}
</style>

<!-- Header -->
<div class="wt_top-header">
    <span style="font-size:20px;font-weight:900;letter-spacing:-1px;color:var(--wt-red);">JuMa</span>
    <span class="wt_eyebrow">Gruppeninfo</span>
    <div style="width:60px;"></div>
</div>

<div class="wt_scroll wt_scroll--no-tabbar" style="padding:16px;display:flex;flex-direction:column;gap:14px;" id="gi-app">

    <!-- QR-Scanner -->
    <div class="wt_card" id="gi-scanner-card">
        <div style="padding:12px 16px 0;" class="wt_eyebrow">QR-Code scannen</div>
        <div style="padding:12px 16px 16px;">
            <p class="wt_caption" style="margin-bottom:12px;text-align:center;">
                Scannt euren Gruppen-QR-Code ein.
            </p>
            <div class="gi_scanner-wrap">
                <div id="gi-qr-reader"></div>
            </div>
            <div id="gi-scan-error" class="wt_alert wt_alert--error" style="display:none;margin-top:12px;"></div>
        </div>
    </div>

    <!-- Ergebnis (initial versteckt) -->
    <div id="gi-result" style="display:none;display:flex;flex-direction:column;gap:14px;">

        <!-- Gruppe -->
        <div class="wt_card">
            <div class="wt_row">
                <div style="flex:1;">
                    <div style="font-size:20px;font-weight:800;letter-spacing:-0.02em;" id="gi-group-name"></div>
                    <div class="wt_caption" id="gi-group-num" style="margin-top:2px;"></div>
                    <div id="gi-lw-badge" class="wt_badge" style="display:none;margin-top:8px;"></div>
                </div>
                <button class="wt_btn wt_btn--ghost wt_btn--sm" onclick="resetScanner()">↩ Neu</button>
            </div>
        </div>

        <!-- Strecke -->
        <div class="wt_card" id="gi-dist-section" style="display:none;">
            <div style="padding:12px 16px 0;" class="wt_eyebrow">Streckenverlauf</div>
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

        <!-- Karte / Navigation -->
        <div class="wt_card" id="gi-map-card" style="display:none;overflow:hidden;">
            <div style="padding:12px 16px;" class="wt_eyebrow">Navigation zur nächsten Station</div>
            <div id="gi-map" class="gi_map"></div>
            <div id="gi-nav-info" class="wt_caption" style="padding:10px 16px;border-top:1px solid var(--wt-border);"></div>
        </div>

        <!-- Stationen -->
        <div class="wt_card">
            <div style="padding:12px 16px 0;" class="wt_eyebrow">Stationen</div>
            <div id="gi-stations-list"></div>
        </div>

        <!-- Hilfe anfordern -->
        <div class="wt_card">
            <div style="padding:12px 16px 0;" class="wt_eyebrow">Hilfe anfordern</div>
            <div style="padding:12px 16px 16px;display:flex;flex-direction:column;gap:10px;">
                <textarea class="wt_textarea" id="gi-help-msg" rows="2"
                          placeholder="Optionale Nachricht (Standort, Art des Problems…)"></textarea>
                <button class="wt_btn wt_btn--block" id="gi-help-btn"
                        onclick="sendHelp()"
                        style="background:var(--wt-warn);color:#fff;">
                    🆘 Hilfe anfordern
                </button>
                <div id="gi-help-result" class="wt_alert wt_alert--success" style="display:none;">
                    ✓ Hilfe wurde angefordert. Ein Betreuer kommt zu euch.
                </div>
            </div>
        </div>

    </div><!-- /gi-result -->
</div>

<!-- html5-qrcode -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
'use strict';

let scanner     = null;
let groupToken  = null;
let mapInstance = null;

// ── Hilfsfunktionen ───────────────────────────────────
function fmtDist(m) {
    if (!m) return '–';
    return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : m + ' m';
}
function fmtTime(t) { return t ? 'ca. ' + t + ' min' : ''; }
function fmtTs(ts) {
    if (!ts) return '';
    return new Date(ts.replace(' ', 'T')).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
}

// ── Scanner ───────────────────────────────────────────
function startScanner() {
    scanner = new Html5Qrcode('gi-qr-reader');
    scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 240, height: 240 } },
        onScanSuccess,
        () => {}
    ).catch(err => {
        showScanError('Kamera nicht verfügbar: ' + err);
    });
}

async function onScanSuccess(raw) {
    // Kamera sofort stoppen
    try { await scanner.stop(); } catch {}
    try { scanner.clear(); } catch {}
    scanner = null;

    // Token aus URL oder rohem Wert extrahieren
    let token = raw;
    try {
        const u = new URL(raw);
        token = u.searchParams.get('token') || raw;
    } catch {}

    loadGroupInfo(token.trim());
}

async function loadGroupInfo(token) {
    groupToken = token;
    document.getElementById('gi-scan-error').style.display = 'none';
    try {
        const res  = await fetch('/api/group/info', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token }),
        });
        const data = await res.json();
        if (!data.success) { showScanError(data.error || 'Fehler'); restartScanner(); return; }
        renderResult(data);
    } catch { showScanError('Verbindungsfehler'); restartScanner(); }
}

function showScanError(msg) {
    const el = document.getElementById('gi-scan-error');
    el.textContent = msg;
    el.style.display = 'block';
}

function restartScanner() {
    document.getElementById('gi-scanner-card').style.display = 'block';
    startScanner();
}

function resetScanner() {
    if (scanner) { try { scanner.stop(); } catch {} try { scanner.clear(); } catch {} scanner = null; }
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }
    document.getElementById('gi-result').style.display    = 'none';
    document.getElementById('gi-scanner-card').style.display = 'block';
    document.getElementById('gi-scan-error').style.display  = 'none';
    document.getElementById('gi-help-result').style.display = 'none';
    document.getElementById('gi-help-btn').disabled         = false;
    document.getElementById('gi-help-btn').textContent      = '🆘 Hilfe anfordern';
    groupToken = null;
    startScanner();
}

// ── Ergebnis rendern ──────────────────────────────────
function renderResult(data) {
    document.getElementById('gi-scanner-card').style.display = 'none';
    const result = document.getElementById('gi-result');
    result.style.display = 'flex';

    // Gruppe
    document.getElementById('gi-group-name').textContent = data.group.name;
    document.getElementById('gi-group-num').textContent  = data.group.num ? 'Gruppe #' + data.group.num : '';

    // Laufweg-Badge
    const badge = document.getElementById('gi-lw-badge');
    if (data.laufweg) {
        badge.style.cssText = `display:inline-flex;background:${data.laufweg.color}22;color:${data.laufweg.color};border:1px solid ${data.laufweg.color}66;`;
        badge.innerHTML = `<span style="width:8px;height:8px;border-radius:50%;background:${data.laufweg.color};"></span> ${data.laufweg.name}`;
    }

    // Distanz
    if (data.total_m > 0) {
        document.getElementById('gi-dist-section').style.display = 'block';
        document.getElementById('gi-covered').textContent   = fmtDist(data.covered_m);
        document.getElementById('gi-remaining').textContent = fmtDist(data.remaining_m);
        const pct = Math.round(data.covered_m / data.total_m * 100);
        document.getElementById('gi-progress-bar').style.width = pct + '%';
        document.getElementById('gi-progress-lbl').textContent = pct + '% von ' + fmtDist(data.total_m);
    }

    // Stationen
    renderStations(data);

    // Karte
    if (data.next_station?.lat) {
        document.getElementById('gi-map-card').style.display = 'block';
        setTimeout(() => renderMap(data), 120);
    }
}

function renderStations(data) {
    const list   = document.getElementById('gi-stations-list');
    const lastId = data.last_station?.id;
    const nextId = data.next_station?.id;
    list.innerHTML = '';

    data.visited.forEach(v => {
        const isCurrent = v.id === lastId && !v.done;
        const dotBg  = v.done ? 'var(--wt-ok)' : isCurrent ? 'var(--wt-warn)' : 'var(--wt-border-strong)';
        const dotClr = (v.done || isCurrent) ? '#fff' : 'var(--wt-text-subtle)';
        const time   = v.done
            ? fmtTs(v.checked_in) + ' – ' + fmtTs(v.checked_out)
            : 'Seit ' + fmtTs(v.checked_in);
        const row = document.createElement('div');
        row.className = 'wt_row';
        row.innerHTML = `
            <div class="gi_stn-dot" style="background:${dotBg};color:${dotClr};">${v.code}</div>
            <div style="flex:1;">
                <div class="wt_row__label">${v.name}</div>
                <div class="wt_row__sub">${time}</div>
            </div>
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
            <div style="flex:1;">
                <div class="wt_row__label">${ns.name}</div>
                <div class="wt_row__sub" style="color:var(--wt-red);">${sub}</div>
            </div>
            <span style="font-size:18px;">→</span>`;
        list.appendChild(row);
    }
}

// ── Karte ─────────────────────────────────────────────
function renderMap(data) {
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }

    const ns    = data.next_station;
    const last  = data.last_station;
    const color = data.laufweg?.color ?? 'var(--wt-red)';

    mapInstance = L.map('gi-map', { zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 19,
    }).addTo(mapInstance);

    const pts = [];

    if (ns.from_lat && ns.from_lng) {
        pts.push([ns.from_lat, ns.from_lng]);
        L.marker([ns.from_lat, ns.from_lng], { icon: stationIcon(last?.code ?? '?', color, true) })
            .addTo(mapInstance)
            .bindPopup(`<strong>${last?.name ?? 'Startstation'}</strong><br>Ihr seid hier`);
    }

    pts.push([ns.lat, ns.lng]);
    L.marker([ns.lat, ns.lng], { icon: stationIcon(ns.code, color, false) })
        .addTo(mapInstance)
        .bindPopup(`<strong>Ziel: ${ns.name}</strong>`);

    if (ns.from_lat && ns.from_lng) {
        drawRoute(data, color);
    }

    if (pts.length > 1) mapInstance.fitBounds(L.latLngBounds(pts), { padding: [40, 40] });
    else mapInstance.setView(pts[0], 16);
}

function stationIcon(code, color, filled) {
    return L.divIcon({
        className: '',
        html: `<div style="background:${filled ? color : '#fff'};color:${filled ? '#fff' : color};
            border:3px solid ${color};border-radius:50%;width:36px;height:36px;
            display:flex;align-items:center;justify-content:center;
            font-family:monospace;font-size:12px;font-weight:800;
            box-shadow:0 2px 8px rgba(0,0,0,.25);">${code}</div>`,
        iconSize: [36, 36], iconAnchor: [18, 18],
    });
}

async function drawRoute(data, color) {
    const ns  = data.next_station;
    const wps = ns.waypoints ?? [];
    const coords = [
        [ns.from_lng, ns.from_lat],
        ...wps.map(wp => [wp[1], wp[0]]),
        [ns.lng, ns.lat],
    ];
    const url = `https://router.project-osrm.org/route/v1/foot/${coords.map(c => c.join(',')).join(';')}?overview=full&geometries=geojson`;
    try {
        const res  = await fetch(url, { signal: AbortSignal.timeout(8000) });
        const json = await res.json();
        if (json.code === 'Ok' && json.routes?.[0]) {
            const latlngs = json.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
            L.polyline(latlngs, { color, weight: 5, opacity: .85, dashArray: '8,4' }).addTo(mapInstance);
            const dist    = Math.round(json.routes[0].distance);
            const osrmMin = json.routes[0].duration / 60;
            const time    = Math.max(1, Math.ceil(osrmMin < dist / 75 * 0.5 ? dist / 75 : osrmMin));
            document.getElementById('gi-nav-info').innerHTML =
                `<b style="color:var(--wt-red);">→ ${ns.name}</b> &nbsp;·&nbsp; ${fmtDist(dist)} &nbsp;·&nbsp; ca. ${time} min zu Fuß`;
            return;
        }
    } catch {}
    // Fallback: Luftlinie mit Waypoints
    const pts = [[ns.from_lat, ns.from_lng], ...wps, [ns.lat, ns.lng]];
    L.polyline(pts, { color, weight: 4, opacity: .7, dashArray: '6,4' }).addTo(mapInstance);
}

// ── Hilfe senden ──────────────────────────────────────
async function sendHelp() {
    const btn = document.getElementById('gi-help-btn');
    const msg = document.getElementById('gi-help-msg').value.trim();
    btn.disabled    = true;
    btn.textContent = 'Wird gesendet…';
    try {
        const res  = await fetch('/api/group/help', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: groupToken, message: msg }),
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('gi-help-result').style.display = 'block';
            btn.style.display = 'none';
        } else {
            btn.disabled    = false;
            btn.textContent = '🆘 Hilfe anfordern';
            alert(data.error || 'Fehler');
        }
    } catch {
        btn.disabled    = false;
        btn.textContent = '🆘 Hilfe anfordern';
        alert('Verbindungsfehler');
    }
}

startScanner();
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/group.php';
?>
