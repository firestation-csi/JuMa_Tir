<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live-Tracking · JuMa</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; display: flex; flex-direction: column; height: 100dvh; background: #f1f5f9; }

        #track-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 16px; background: #fff; border-bottom: 1px solid #e2e8f0;
            flex-shrink: 0; gap: 12px; flex-wrap: wrap;
        }
        #track-header h1 { font-size: 15px; font-weight: 700; color: #0f172a; }
        #track-meta { font-size: 12px; color: #64748b; font-family: monospace; }
        #track-badge {
            font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 6px;
            background: #dcfce7; color: #15803d;
        }
        #track-badge.offline { background: #fee2e2; color: #b91c1c; }

        #track-legend {
            display: flex; gap: 14px; align-items: center; font-size: 11.5px; color: #475569;
        }
        .leg-dot {
            width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 4px;
        }

        #map { flex: 1; }

        /* Leaflet popup Styling */
        .trk-popup { min-width: 160px; font-family: system-ui, sans-serif; }
        .trk-popup__num { font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 2px; }
        .trk-popup__name { font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        .trk-popup__row { font-size: 11.5px; color: #64748b; margin-bottom: 2px; }
        .trk-popup__row strong { color: #0f172a; }
        .trk-popup__age { font-size: 11px; font-weight: 600; margin-top: 6px; }
        .trk-popup__age--fresh { color: #15803d; }
        .trk-popup__age--stale { color: #d97706; }
        .trk-popup__age--old   { color: #94a3b8; }
    </style>
</head>
<body>

<div id="track-header">
    <div style="display:flex;align-items:center;gap:12px;">
        <h1>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px;margin-right:4px;">
                <path d="M6 2L2 4v9l4-2 4 2 4-2V2l-4 2-4-2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                <path d="M6 2v9M10 4v9" stroke="currentColor" stroke-width="1.4"/>
            </svg>
            Live-Tracking
        </h1>
        <span id="track-badge">● LIVE</span>
    </div>
    <div id="track-legend">
        <span><span class="leg-dot" style="background:#22c55e;"></span>&lt; 5 min</span>
        <span><span class="leg-dot" style="background:#f59e0b;"></span>5–15 min</span>
        <span><span class="leg-dot" style="background:#94a3b8;"></span>&gt; 15 min</span>
    </div>
    <div id="track-meta">Laden…</div>
</div>

<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const map = L.map('map', { zoomControl: true }).setView([47.27, 11.40], 13);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    const markers = {};
    const badge   = document.getElementById('track-badge');
    const meta    = document.getElementById('track-meta');

    function ageMinutes(recorded_at) {
        return (Date.now() - new Date(recorded_at.replace(' ', 'T')).getTime()) / 60000;
    }

    function markerColor(age) {
        if (age < 5)  return { bg: '#22c55e', border: '#15803d' };
        if (age < 15) return { bg: '#f59e0b', border: '#d97706' };
        return             { bg: '#94a3b8', border: '#64748b' };
    }

    function ageLabel(age) {
        if (age < 1)  return { text: 'gerade eben',   cls: 'fresh' };
        if (age < 5)  return { text: `vor ${Math.round(age)} min`, cls: 'fresh' };
        if (age < 15) return { text: `vor ${Math.round(age)} min`, cls: 'stale' };
        return               { text: `vor ${Math.round(age)} min`, cls: 'old'   };
    }

    function buildIcon(num, age) {
        const { bg, border } = markerColor(age);
        return L.divIcon({
            className: '',
            html: `<div style="
                background:${bg};color:#fff;border-radius:50%;
                width:36px;height:36px;display:flex;align-items:center;justify-content:center;
                font-family:monospace;font-size:11px;font-weight:800;
                box-shadow:0 2px 8px rgba(0,0,0,.25);border:2.5px solid ${border};
                white-space:nowrap;">${num ?? '?'}</div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -22],
        });
    }

    function buildPopup(loc, age) {
        const al   = ageLabel(age);
        const acc  = loc.accuracy ? Math.round(loc.accuracy) + ' m' : '–';
        return `<div class="trk-popup">
            <div class="trk-popup__num">#${loc.group_num ?? '?'}</div>
            <div class="trk-popup__name">${loc.group_name}</div>
            ${loc.kreis ? `<div class="trk-popup__row">${loc.kreis}</div>` : ''}
            <div class="trk-popup__row">Genauigkeit: <strong>${acc}</strong></div>
            <div class="trk-popup__age trk-popup__age--${al.cls}">${al.text}</div>
        </div>`;
    }

    async function refresh() {
        try {
            const res  = await fetch('/api/admin/groups/locations', { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            const locs = data.locations || [];

            const seen = new Set();

            locs.forEach(loc => {
                if (!loc.lat || !loc.lng) return;
                const id  = loc.group_id;
                const age = ageMinutes(loc.recorded_at);
                seen.add(id);

                const icon    = buildIcon(loc.group_num, age);
                const popup   = buildPopup(loc, age);
                const latLng  = [parseFloat(loc.lat), parseFloat(loc.lng)];

                if (markers[id]) {
                    markers[id].setLatLng(latLng).setIcon(icon).setPopupContent(popup);
                } else {
                    markers[id] = L.marker(latLng, { icon })
                        .bindPopup(popup)
                        .addTo(map);
                }
            });

            // Marker entfernen wenn Gruppe keine Position mehr liefert
            Object.keys(markers).forEach(id => {
                if (!seen.has(parseInt(id))) {
                    map.removeLayer(markers[id]);
                    delete markers[id];
                }
            });

            // Karte auf alle Marker einzoomen (nur beim ersten Laden)
            if (firstLoad && seen.size > 0) {
                firstLoad = false;
                const all = Object.values(markers).map(m => m.getLatLng());
                map.fitBounds(L.latLngBounds(all), { padding: [48, 48], maxZoom: 16 });
            }

            badge.textContent = '● LIVE';
            badge.className   = '';
            meta.textContent  = `${locs.length} Gruppen · ${data.ts}`;
        } catch {
            badge.textContent = '○ OFFLINE';
            badge.className   = 'offline';
        }
    }

    let firstLoad = true;
    refresh();
    setInterval(refresh, 30_000);
})();
</script>
</body>
</html>
