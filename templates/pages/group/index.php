<?php
ob_start();
?>
<div class="gi_main" id="gi-app">

    <!-- ── QR-Scanner-Bereich ─────────────────────────── -->
    <div class="gi_card" id="gi-scanner-card">
        <div class="gi_card__head">QR-Code scannen</div>
        <div class="gi_card__body">
            <p class="gi_hint" style="margin-bottom:12px;">Scannt euren Gruppen-QR-Code ein.</p>
            <div class="gi_scanner-wrap">
                <div id="gi-qr-reader"></div>
            </div>
            <div id="gi-scan-error" class="gi_error" style="display:none;margin-top:10px;"></div>
        </div>
    </div>

    <!-- ── Ergebnis-Bereich (initial versteckt) ──────── -->
    <div id="gi-result" style="display:none;">

        <!-- Gruppe -->
        <div class="gi_card">
            <div class="gi_card__body" style="display:flex;align-items:center;gap:12px;">
                <div style="flex:1;">
                    <div class="gi_group-name" id="gi-group-name"></div>
                    <div class="gi_group-num"  id="gi-group-num"></div>
                    <div id="gi-lw-badge" class="gi_lw-badge" style="display:none;"></div>
                </div>
                <button class="gi_btn gi_btn--ghost" style="width:auto;padding:8px 14px;font-size:13px;"
                        onclick="resetScanner()">↩ Neu</button>
            </div>
        </div>

        <!-- Distanz-Karten -->
        <div id="gi-dist-section" class="gi_card" style="display:none;">
            <div class="gi_card__head">Streckenverlauf</div>
            <div class="gi_card__body">
                <div class="gi_dist-row">
                    <div class="gi_dist-card">
                        <div class="gi_dist-card__val" id="gi-covered" style="color:var(--c-ok);">–</div>
                        <div class="gi_dist-card__lbl">Zurückgelegt</div>
                    </div>
                    <div class="gi_dist-card">
                        <div class="gi_dist-card__val" id="gi-remaining" style="color:var(--c-warn);">–</div>
                        <div class="gi_dist-card__lbl">Noch offen</div>
                    </div>
                </div>
                <div class="gi_progress" style="margin-top:12px;">
                    <div class="gi_progress__bar" id="gi-progress-bar"
                         style="background:var(--c-ok);width:0%;"></div>
                </div>
                <div id="gi-progress-lbl" style="font-size:11px;color:var(--c-muted);text-align:right;margin-top:4px;"></div>
            </div>
        </div>

        <!-- Karte mit Navigation -->
        <div class="gi_card" id="gi-map-card" style="display:none;">
            <div class="gi_card__head">Navigation zur nächsten Station</div>
            <div id="gi-map"></div>
            <div id="gi-nav-info" style="padding:10px 16px;font-size:13px;color:var(--c-muted);border-top:1px solid var(--c-border);"></div>
        </div>

        <!-- Stationsliste -->
        <div class="gi_card">
            <div class="gi_card__head">Stationen</div>
            <div class="gi_card__body" id="gi-stations-list">
                <div class="gi_hint">Keine Daten</div>
            </div>
        </div>

        <!-- Hilfe anfordern -->
        <div class="gi_card">
            <div class="gi_card__head">Hilfe anfordern</div>
            <div class="gi_card__body">
                <textarea class="gi_help-input" id="gi-help-msg"
                          placeholder="Optionale Nachricht (z.B. Standort, Art des Problems)…"></textarea>
                <button class="gi_btn gi_btn--help" id="gi-help-btn" onclick="sendHelp()">
                    🆘 Hilfe anfordern
                </button>
                <div id="gi-help-result" style="display:none;" class="gi_sent">
                    ✓ Hilfe wurde angefordert. Ein Betreuer wird sich kümmern.
                </div>
            </div>
        </div>

    </div><!-- /gi-result -->
</div>

<!-- html5-qrcode -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
'use strict';

let scanner    = null;
let groupToken = null;
let mapInstance = null;

// ── Hilfsfunktionen ───────────────────────────────────
function fmtDist(m) {
    if (!m) return '–';
    return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : m + ' m';
}
function fmtTime(t) {
    if (!t) return '';
    return 'ca. ' + t + ' min';
}
function fmtTs(ts) {
    if (!ts) return '';
    const d = new Date(ts.replace(' ', 'T'));
    return d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
}

// ── Scanner starten ───────────────────────────────────
function startScanner() {
    scanner = new Html5Qrcode('gi-qr-reader');
    scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 240, height: 240 } },
        onScanSuccess,
        () => {}
    ).catch(err => {
        document.getElementById('gi-scan-error').textContent = 'Kamera nicht verfügbar: ' + err;
        document.getElementById('gi-scan-error').style.display = 'block';
    });
}

async function onScanSuccess(raw) {
    // QR enthält Token oder URL mit ?token=
    let token = raw;
    try {
        const url = new URL(raw);
        token = url.searchParams.get('token') || raw;
    } catch {}

    await scanner.stop();
    loadGroupInfo(token);
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

        if (!data.success) {
            showScanError(data.error || 'Unbekannter Fehler');
            startScanner();
            return;
        }

        renderResult(data);
    } catch (e) {
        showScanError('Verbindungsfehler');
        startScanner();
    }
}

function showScanError(msg) {
    document.getElementById('gi-scan-error').textContent = msg;
    document.getElementById('gi-scan-error').style.display = 'block';
}

function resetScanner() {
    document.getElementById('gi-result').style.display = 'none';
    document.getElementById('gi-scanner-card').style.display = 'block';
    document.getElementById('gi-help-result').style.display = 'none';
    document.getElementById('gi-help-btn').disabled = false;
    groupToken = null;
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }
    startScanner();
}

// ── Ergebnis rendern ──────────────────────────────────
function renderResult(data) {
    document.getElementById('gi-scanner-card').style.display = 'none';
    document.getElementById('gi-result').style.display = 'block';

    // Gruppe
    document.getElementById('gi-group-name').textContent = data.group.name;
    document.getElementById('gi-group-num').textContent  = data.group.num ? 'Gruppe #' + data.group.num : '';

    // Laufweg-Badge
    const badge = document.getElementById('gi-lw-badge');
    if (data.laufweg) {
        badge.style.display = 'inline-flex';
        badge.style.background = data.laufweg.color + '33';
        badge.style.color       = data.laufweg.color;
        badge.style.border      = '1px solid ' + data.laufweg.color + '88';
        badge.innerHTML = `<span style="width:8px;height:8px;border-radius:50%;background:${data.laufweg.color};"></span>${data.laufweg.name}`;
    } else {
        badge.style.display = 'none';
    }

    // Distanzen
    if (data.total_m > 0) {
        document.getElementById('gi-dist-section').style.display = 'block';
        document.getElementById('gi-covered').textContent   = fmtDist(data.covered_m);
        document.getElementById('gi-remaining').textContent = fmtDist(data.remaining_m);
        const pct = data.total_m > 0 ? Math.round(data.covered_m / data.total_m * 100) : 0;
        document.getElementById('gi-progress-bar').style.width = pct + '%';
        document.getElementById('gi-progress-lbl').textContent = pct + '% der Gesamtstrecke (' + fmtDist(data.total_m) + ')';
    }

    // Stationsliste
    renderStations(data);

    // Karte + Navigation
    if (data.next_station && data.next_station.lat) {
        document.getElementById('gi-map-card').style.display = 'block';
        setTimeout(() => renderMap(data), 100);
    }
}

function renderStations(data) {
    const list = document.getElementById('gi-stations-list');
    list.innerHTML = '';

    const lastId = data.last_station ? data.last_station.id : null;
    const nextId = data.next_station ? data.next_station.id : null;
    const visitedIds = data.visited.map(v => v.id);

    // Besuchte Stationen
    data.visited.forEach(v => {
        const isCurrent = v.id === lastId && !v.done;
        const row = document.createElement('div');
        row.className = 'gi_station-row';

        const dotCls = v.done ? 'gi_station-dot--done' :
                       isCurrent ? 'gi_station-dot--current' : 'gi_station-dot--pending';
        const timeStr = v.done
            ? fmtTs(v.checked_in) + ' – ' + fmtTs(v.checked_out)
            : 'Seit ' + fmtTs(v.checked_in);

        row.innerHTML = `
            <div class="gi_station-dot ${dotCls}">${v.code}</div>
            <div class="gi_station-info">
                <div class="gi_station-info__name">${v.name}</div>
                <div class="gi_station-info__time">${timeStr}</div>
            </div>
            ${v.done ? '<span style="font-size:18px;">✓</span>' : ''}`;
        list.appendChild(row);
    });

    // Nächste Station (noch nicht besucht)
    if (data.next_station) {
        const ns = data.next_station;
        const row = document.createElement('div');
        row.className = 'gi_station-row';
        const distStr = [fmtDist(ns.distance_m), fmtTime(ns.est_time_min)].filter(Boolean).join(' · ');
        row.innerHTML = `
            <div class="gi_station-dot gi_station-dot--next">${ns.code}</div>
            <div class="gi_station-info">
                <div class="gi_station-info__name">${ns.name}</div>
                <div class="gi_station-info__time" style="color:var(--c-blue);">${distStr}</div>
            </div>
            <span style="font-size:18px;">→</span>`;
        list.appendChild(row);
    }
}

// ── Leaflet-Karte ─────────────────────────────────────
function renderMap(data) {
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }

    const ns    = data.next_station;
    const last  = data.last_station;
    const color = data.laufweg ? data.laufweg.color : '#2980B9';

    mapInstance = L.map('gi-map', { zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(mapInstance);

    const pts = [];

    // Alle besuchten Stationen (grau/grün)
    data.visited.forEach((v, i) => {
        // Koordinaten sind nicht im visited-Array → nur wenn last/next
    });

    // Startpunkt (letzte Station) Marker
    if (ns.from_lat && ns.from_lng) {
        pts.push([ns.from_lat, ns.from_lng]);
        const fromIcon = L.divIcon({
            className: '',
            html: `<div style="background:${color};color:#fff;border-radius:50%;
                width:36px;height:36px;display:flex;align-items:center;justify-content:center;
                font-family:monospace;font-size:12px;font-weight:800;
                box-shadow:0 2px 8px rgba(0,0,0,.4);border:2px solid #fff;">${last ? last.code : '?'}</div>`,
            iconSize: [36, 36], iconAnchor: [18, 18],
        });
        L.marker([ns.from_lat, ns.from_lng], { icon: fromIcon })
            .addTo(mapInstance)
            .bindPopup(`<strong>${last ? last.name : 'Startstation'}</strong><br>Ihr seid hier`);
    }

    // Zielpunkt (nächste Station)
    pts.push([ns.lat, ns.lng]);
    const toIcon = L.divIcon({
        className: '',
        html: `<div style="background:#fff;color:${color};border:3px solid ${color};border-radius:50%;
            width:36px;height:36px;display:flex;align-items:center;justify-content:center;
            font-family:monospace;font-size:12px;font-weight:800;
            box-shadow:0 2px 8px rgba(0,0,0,.4);">${ns.code}</div>`,
        iconSize: [36, 36], iconAnchor: [18, 18],
    });
    L.marker([ns.lat, ns.lng], { icon: toIcon })
        .addTo(mapInstance)
        .bindPopup(`<strong>Ziel: ${ns.name}</strong>`);

    // Route zeichnen: gespeicherte Waypoints → OSRM
    if (ns.from_lat && ns.from_lng) {
        drawRoute(data, color);
    } else if (pts.length > 1) {
        L.polyline(pts, { color, weight: 5, opacity: .8 }).addTo(mapInstance);
    }

    if (pts.length > 1) {
        mapInstance.fitBounds(L.latLngBounds(pts), { padding: [40, 40] });
    } else {
        mapInstance.setView(pts[0], 16);
    }
}

async function drawRoute(data, color) {
    const ns  = data.next_station;
    const wps = ns.waypoints || [];

    const coords = [
        [ns.from_lng, ns.from_lat],
        ...wps.map(wp => [wp[1], wp[0]]),
        [ns.lng, ns.lat],
    ];
    const coordStr = coords.map(c => c[0] + ',' + c[1]).join(';');
    const url = `https://router.project-osrm.org/route/v1/foot/${coordStr}?overview=full&geometries=geojson`;

    try {
        const res  = await fetch(url, { signal: AbortSignal.timeout(8000) });
        const json = await res.json();

        if (json.code === 'Ok' && json.routes?.[0]) {
            const latlngs = json.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
            L.polyline(latlngs, { color, weight: 5, opacity: .85, dashArray: '8,4' }).addTo(mapInstance);

            const dist = Math.round(json.routes[0].distance);
            const osrmMin = json.routes[0].duration / 60;
            const walkMin = dist / 75;
            const time = Math.max(1, Math.ceil(osrmMin < walkMin * 0.5 ? walkMin : osrmMin));
            document.getElementById('gi-nav-info').innerHTML =
                `<span style="color:var(--c-blue);font-weight:700;">→ ${ns.name}</span>` +
                ` &nbsp;·&nbsp; ${fmtDist(dist)} &nbsp;·&nbsp; ca. ${time} min zu Fuß`;
        } else {
            fallbackLine(data, color);
        }
    } catch {
        fallbackLine(data, color);
    }
}

function fallbackLine(data, color) {
    const ns = data.next_station;
    if (!ns.from_lat) return;
    const pts = [[ns.from_lat, ns.from_lng], ...( ns.waypoints || []), [ns.lat, ns.lng]];
    L.polyline(pts, { color, weight: 4, opacity: .7, dashArray: '6,4' }).addTo(mapInstance);
}

// ── Hilfe senden ──────────────────────────────────────
async function sendHelp() {
    const btn = document.getElementById('gi-help-btn');
    const msg = document.getElementById('gi-help-msg').value.trim();
    btn.disabled = true;
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
            btn.disabled = false;
            btn.textContent = '🆘 Hilfe anfordern';
            alert(data.error || 'Fehler beim Senden');
        }
    } catch {
        btn.disabled = false;
        btn.textContent = '🆘 Hilfe anfordern';
        alert('Verbindungsfehler');
    }
}

// Scanner beim Laden starten
startScanner();
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/group.php';
?>
