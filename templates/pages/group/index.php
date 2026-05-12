<?php ob_start(); ?>

<style>
/* Ergänzungen zum wt_ Design-System */
.gi_scanner-wrap { border-radius: var(--wt-r-lg); overflow: hidden; background: #000; }
#gi-qr-reader video { border-radius: 0 !important; }
.gi_map { height: 300px; }
.gi_dist-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.gi_dist-card { text-align: center; padding: 14px; }
.gi_dist-val  { font-family: 'JetBrains Mono', monospace; font-size: 22px; font-weight: 800; }
.gi_dist-lbl  { font-size: 11px; color: var(--wt-text-subtle); margin-top: 3px; }
.gi_bar-track { height: 8px; background: var(--wt-border-strong); border-radius: var(--wt-r-pill); overflow: hidden; margin-top: 10px; }
.gi_bar-fill  { height: 100%; border-radius: var(--wt-r-pill); background: var(--wt-ok); transition: width .5s; }
.gi_stn-dot   { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 800; }
.gi_arrival   { font-size: 13px; font-weight: 700; color: var(--wt-ok); }
.gi_notes-box { background: var(--wt-surface-alt); border-left: 3px solid var(--wt-warn); border-radius: 0 var(--wt-r-sm) var(--wt-r-sm) 0; padding: 10px 12px; font-size: 13px; color: var(--wt-text-muted); margin-top: 10px; }
.gi_gps-btn   { position: absolute; bottom: 12px; right: 12px; z-index: 999; background: var(--wt-surface); border: 1px solid var(--wt-border-strong); border-radius: var(--wt-r-md); padding: 8px 12px; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: var(--wt-shadow-md); }
</style>

<!-- Header — zeigt nach Scan den Gruppennamen -->
<div class="wt_top-header">
    <span style="font-size:20px;font-weight:900;letter-spacing:-1px;color:var(--wt-red);">JuMa</span>
    <span class="wt_eyebrow" id="gi-header-title">Gruppeninfo</span>
    <button class="wt_btn wt_btn--ghost wt_btn--sm" id="gi-reset-btn"
            style="display:none;" onclick="resetScanner()">↩ Neu</button>
</div>

<div class="wt_scroll wt_scroll--no-tabbar" style="padding:16px;display:flex;flex-direction:column;gap:14px;">

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

    <!-- Ergebnis -->
    <div id="gi-result" style="display:none;flex-direction:column;gap:14px;">

        <!-- Laufweg-Badge + Wettbewerb -->
        <div class="wt_card" id="gi-lw-card" style="display:none;">
            <div class="wt_row" style="gap:8px;flex-wrap:wrap;">
                <div id="gi-lw-badge" class="wt_badge"></div>
                <span class="wt_caption" id="gi-comp-name"></span>
            </div>
        </div>

        <!-- Streckenverlauf -->
        <div class="wt_card" id="gi-dist-section" style="display:none;">
            <div style="padding:12px 16px 0;" class="wt_eyebrow">
                Streckenverlauf
                <span id="gi-dist-loading" class="wt_caption" style="margin-left:6px;color:var(--wt-warn);">wird berechnet…</span>
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

        <!-- Karte + Navigation -->
        <div class="wt_card" id="gi-map-card" style="display:none;overflow:hidden;position:relative;">
            <div style="padding:12px 16px;" class="wt_eyebrow">Navigation zur nächsten Station</div>
            <div id="gi-map" class="gi_map"></div>
            <button class="gi_gps-btn" onclick="enableGps()" id="gi-gps-btn" title="GPS-Position anzeigen">📍 GPS</button>
            <div style="padding:10px 16px 14px;display:flex;flex-direction:column;gap:6px;">
                <div id="gi-nav-info" class="wt_caption"></div>
                <div id="gi-arrival-info" class="gi_arrival" style="display:none;"></div>
                <div id="gi-route-notes" class="gi_notes-box" style="display:none;"></div>
            </div>
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
                    Nutzt diesen Button nur bei echtem Bedarf — ein Betreuer wird dann zu euch kommen.
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
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
'use strict';

let scanner     = null;
let groupToken  = null;
let mapInstance = null;
let gpsWatchId  = null;
let gpsMarker   = null;
let currentData = null;

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
        onScanSuccess, () => {}
    ).catch(err => showScanError('Kamera: ' + err));
}

async function onScanSuccess(raw) {
    try { await scanner.stop(); } catch {}
    try { scanner.clear(); }     catch {}
    scanner = null;

    let token = raw;
    try { const u = new URL(raw); token = u.searchParams.get('token') || raw; } catch {}
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
        const json = await res.json();
        if (!json.success) { showScanError(json.error || 'Fehler'); restartScanner(); return; }
        currentData = json.data;
        renderResult(json.data);
    } catch(e) {
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
    if (scanner) { try { scanner.stop(); } catch {} try { scanner.clear(); } catch {} scanner = null; }
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }
    if (gpsWatchId !== null) { navigator.geolocation.clearWatch(gpsWatchId); gpsWatchId = null; }
    gpsMarker = null;
    document.getElementById('gi-result').style.display = 'none';
    document.getElementById('gi-scanner-card').style.display = 'block';
    document.getElementById('gi-scan-error').style.display   = 'none';
    document.getElementById('gi-help-result').style.display  = 'none';
    document.getElementById('gi-help-btn').disabled = false;
    document.getElementById('gi-help-btn').textContent = '🆘 Hilfe anfordern';
    document.getElementById('gi-header-title').textContent = 'Gruppeninfo';
    document.getElementById('gi-reset-btn').style.display = 'none';
    groupToken = null; currentData = null;
    startScanner();
}

// ── Ergebnis rendern ──────────────────────────────────
function renderResult(data) {
    document.getElementById('gi-scanner-card').style.display = 'none';
    const result = document.getElementById('gi-result');
    result.style.display = 'flex';

    // Header aktualisieren
    const groupLabel = (data.group.num ? '#'+data.group.num+' ' : '') + data.group.name;
    document.getElementById('gi-header-title').textContent = groupLabel;
    document.getElementById('gi-reset-btn').style.display  = 'block';

    // Laufweg + Wettbewerb
    if (data.laufweg || data.group.competition_name) {
        document.getElementById('gi-lw-card').style.display = 'block';
        const badge = document.getElementById('gi-lw-badge');
        if (data.laufweg) {
            badge.style.cssText = `background:${data.laufweg.color}22;color:${data.laufweg.color};border:1px solid ${data.laufweg.color}66;`;
            badge.innerHTML = `<span style="width:8px;height:8px;border-radius:50%;background:${data.laufweg.color};display:inline-block;"></span> ${data.laufweg.name}`;
        }
        if (data.group.competition_name) {
            document.getElementById('gi-comp-name').textContent = data.group.competition_name;
        }
    }

    // Streckenverlauf — DB-Werte sofort zeigen, OSRM nachladen
    if (data.total_m > 0 || data.all_segments?.length) {
        document.getElementById('gi-dist-section').style.display = 'block';
        updateDistDisplay(data.covered_m, data.remaining_m, data.total_m);
        if (data.all_segments?.length) calcOsrmDistances(data);
    }

    // Stationen
    renderStations(data);

    // Teilnehmer
    if (data.members?.length) {
        document.getElementById('gi-members-card').style.display = 'block';
        const list = document.getElementById('gi-members-list');
        list.innerHTML = '';
        data.members.forEach(m => {
            const row = document.createElement('div');
            row.className = 'wt_row';
            row.innerHTML = `
                <div style="flex:1;">
                    <div class="wt_row__label">${m.vorname} ${m.name}</div>
                    ${m.funktion ? `<div class="wt_row__sub">${m.funktion}</div>` : ''}
                </div>`;
            list.appendChild(row);
        });
    }

    // Karte
    if (data.next_station?.lat) {
        document.getElementById('gi-map-card').style.display = 'block';
        setTimeout(() => renderMap(data), 120);
    }
}

function updateDistDisplay(covered, remaining, total) {
    document.getElementById('gi-covered').textContent   = fmtDist(covered);
    document.getElementById('gi-remaining').textContent = fmtDist(remaining);
    const pct = total > 0 ? Math.round(covered / total * 100) : 0;
    document.getElementById('gi-progress-bar').style.width = pct + '%';
    document.getElementById('gi-progress-lbl').textContent  = pct + '% von ' + fmtDist(total);
}

// ── OSRM-Distanz für alle Segmente berechnen ──────────
async function calcOsrmDist(seg) {
    if (!seg.from_lat || !seg.to_lat) return null;
    const coords = [
        [seg.from_lng, seg.from_lat],
        ...(seg.waypoints || []).map(wp => [wp[1], wp[0]]),
        [seg.to_lng, seg.to_lat],
    ];
    const url = `https://router.project-osrm.org/route/v1/foot/${coords.map(c=>c.join(',')).join(';')}?overview=false`;
    try {
        const res  = await fetch(url, { signal: AbortSignal.timeout(7000) });
        const json = await res.json();
        if (json.code === 'Ok' && json.routes?.[0]) {
            const dist    = Math.round(json.routes[0].distance);
            const osrmMin = json.routes[0].duration / 60;
            const time    = Math.max(1, Math.ceil(osrmMin < dist/75*0.5 ? dist/75 : osrmMin));
            return { dist, time };
        }
    } catch {}
    return null;
}

async function calcOsrmDistances(data) {
    // Parallel alle Segmente berechnen
    const results = await Promise.all(data.all_segments.map(calcOsrmDist));

    let totalM = 0, coveredM = 0;
    data.all_segments.forEach((seg, i) => {
        const r = results[i];
        const d = r ? r.dist : seg.distance_m;
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

// ── Leaflet-Karte ─────────────────────────────────────
function renderMap(data) {
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }

    const ns    = data.next_station;
    const color = data.laufweg?.color ?? '#D4263A';

    mapInstance = L.map('gi-map', { zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 19,
    }).addTo(mapInstance);

    const pts = [];

    if (ns.from_lat && ns.from_lng) {
        pts.push([ns.from_lat, ns.from_lng]);
        L.marker([ns.from_lat, ns.from_lng], { icon: stnIcon(data.last_station?.code ?? '?', color, true) })
            .addTo(mapInstance)
            .bindPopup(`<strong>${data.last_station?.name ?? 'Letzte Station'}</strong><br>Ihr wart hier`);
    }
    pts.push([ns.lat, ns.lng]);
    L.marker([ns.lat, ns.lng], { icon: stnIcon(ns.code, color, false) })
        .addTo(mapInstance)
        .bindPopup(`<strong>Ziel: ${ns.name}</strong>`);

    if (ns.from_lat && ns.from_lng) drawRoute(data, color);

    if (pts.length > 1) mapInstance.fitBounds(L.latLngBounds(pts), { padding: [40, 40] });
    else mapInstance.setView(pts[0], 15);
}

function stnIcon(code, color, filled) {
    return L.divIcon({
        className: '',
        html: `<div style="background:${filled?color:'#fff'};color:${filled?'#fff':color};
            border:3px solid ${color};border-radius:50%;width:36px;height:36px;
            display:flex;align-items:center;justify-content:center;
            font-family:monospace;font-size:12px;font-weight:800;
            box-shadow:0 2px 8px rgba(0,0,0,.25);">${code}</div>`,
        iconSize: [36,36], iconAnchor: [18,18],
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

            // Ankunftszeit schätzen
            const arrival = new Date(Date.now() + time * 60000);
            document.getElementById('gi-arrival-info').style.display = 'block';
            document.getElementById('gi-arrival-info').textContent =
                '⏱ Erwartete Ankunft: ' + arrival.toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'}) + ' Uhr';

            // Wegbeschreibung
            if (ns.notes) {
                document.getElementById('gi-route-notes').style.display = 'block';
                document.getElementById('gi-route-notes').textContent = '📋 ' + ns.notes;
            }
            return;
        }
    } catch {}
    // Fallback: Luftlinie
    const pts = [[ns.from_lat,ns.from_lng], ...wps, [ns.lat,ns.lng]];
    L.polyline(pts, { color, weight:4, opacity:.7, dashArray:'6,4' }).addTo(mapInstance);
}

// ── GPS ───────────────────────────────────────────────
function enableGps() {
    if (!navigator.geolocation) { alert('GPS nicht verfügbar'); return; }
    const btn = document.getElementById('gi-gps-btn');
    btn.textContent = '📍 …';
    btn.disabled    = true;

    gpsWatchId = navigator.geolocation.watchPosition(pos => {
        btn.textContent = '📍 GPS aktiv';
        const latlng = [pos.coords.latitude, pos.coords.longitude];
        if (!mapInstance) return;
        if (!gpsMarker) {
            const icon = L.divIcon({
                className: '',
                html: '<div style="width:16px;height:16px;border-radius:50%;background:#2980B9;border:3px solid #fff;box-shadow:0 0 0 3px rgba(41,128,185,.4);"></div>',
                iconSize: [16,16], iconAnchor: [8,8],
            });
            gpsMarker = L.marker(latlng, { icon }).addTo(mapInstance).bindTooltip('Dein Standort');
        } else {
            gpsMarker.setLatLng(latlng);
        }
    }, err => {
        btn.textContent = '📍 GPS';
        btn.disabled    = false;
        alert('GPS-Fehler: ' + err.message);
    }, { enableHighAccuracy: true });
}

// ── Hilfe senden ──────────────────────────────────────
async function sendHelp() {
    const btn = document.getElementById('gi-help-btn');
    const msg = document.getElementById('gi-help-msg').value.trim();
    btn.disabled = true; btn.textContent = 'Wird gesendet…';
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
            btn.disabled = false; btn.textContent = '🆘 Hilfe anfordern';
            alert(data.error || 'Fehler');
        }
    } catch {
        btn.disabled = false; btn.textContent = '🆘 Hilfe anfordern';
        alert('Verbindungsfehler');
    }
}

startScanner();
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/group.php';
?>
