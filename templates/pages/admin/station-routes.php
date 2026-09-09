<?php
$competition      = $competition      ?? null;
$competitions     = $competitions     ?? [];
$stations         = $stations         ?? [];
$laufwege         = $laufwege         ?? [];
$routes           = $routes           ?? [];
$stationDurations = $stationDurations ?? [];
$csrf             = $csrf             ?? '';
$error            = $_GET['error']    ?? null;

$extraHead = '
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
';
$extraScripts = '
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
';

ob_start();

// Abschnitte nach Laufweg gruppieren
$routesByLaufweg = [];   // laufweg_id (or 0=unzugeordnet) → [routes]
foreach ($routes as $r) {
    $lid = $r['laufweg_id'] ? (int)$r['laufweg_id'] : 0;
    $routesByLaufweg[$lid][] = $r;
}

// Bekannte Farben für Laufweg-Picker
$presetColors = ['#C0392B','#2980B9','#27AE60','#E67E22','#8E44AD','#16A085','#2C3E50','#F39C12'];
?>

<div class="adm_toolbar" style="justify-content:space-between;">
    <a href="/admin/stations" class="adm_btn adm_btn--ghost">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Stationen
    </a>
    <a href="/admin/stations/travel-analysis" class="adm_btn adm_btn--ghost">Reisezeiten-Analyse</a>
</div>

<?php if (!empty($competitions)): ?>
<div style="margin-bottom:20px;">
    <?php $redirectUrl = '/admin/stations/routes'; include dirname(__DIR__, 2) . '/partials/admin/competition-selector.php'; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="adm_alert adm_alert--error" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$competition): ?>
<div class="adm_empty"><div class="adm_empty__icon">🗺</div><p>Kein Wettbewerb ausgewählt.</p></div>
<?php else: ?>

<div class="rte_main">

    <!-- ── Laufwege verwalten ─────────────────────────── -->
    <div class="adm_card">
        <div class="adm_eyebrow" style="margin-bottom:14px;">Parcours / Laufwege</div>

        <?php if (!empty($laufwege)): ?>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
            <?php foreach ($laufwege as $lw): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--wt-surface-alt);border-radius:10px;border:1px solid var(--wt-border);">
                <span style="width:16px;height:16px;border-radius:50%;background:<?= htmlspecialchars($lw['color']) ?>;flex-shrink:0;border:2px solid rgba(0,0,0,.1);"></span>
                <span style="font-weight:700;font-size:14px;flex:1;"><?= htmlspecialchars($lw['name']) ?></span>
                <?php if ($lw['notes']): ?>
                    <span style="font-size:12px;color:var(--wt-text-muted);"><?= htmlspecialchars($lw['notes']) ?></span>
                <?php endif; ?>
                <?php $cnt = count($routesByLaufweg[(int)$lw['id']] ?? []); ?>
                <span class="adm_badge" style="background:<?= htmlspecialchars($lw['color']) ?>22;color:<?= htmlspecialchars($lw['color']) ?>;">
                    <?= $cnt ?> Abschnitt<?= $cnt !== 1 ? 'e' : '' ?>
                </span>
                <form method="POST" action="/admin/stations/laufwege/<?= (int)$lw['id'] ?>/delete"
                      onsubmit="return confirm('Parcours «<?= htmlspecialchars(addslashes($lw['name'])) ?>» löschen? Abschnitte werden nicht gelöscht.')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="adm_btn adm_btn--sm adm_btn--danger">×</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Neuen Parcours anlegen -->
        <form method="POST" action="/admin/stations/laufwege" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="adm_field" style="flex:2;min-width:140px;margin:0;">
                <label class="adm_label" for="lw_name">Name *</label>
                <input class="adm_input" type="text" id="lw_name" name="lw_name" placeholder="z.B. Roter Parcours" required>
            </div>
            <div class="adm_field" style="margin:0;">
                <label class="adm_label">Farbe</label>
                <div style="display:flex;gap:5px;align-items:center;">
                    <?php foreach ($presetColors as $i => $col): ?>
                    <label style="cursor:pointer;position:relative;">
                        <input type="radio" name="lw_color" value="<?= $col ?>" <?= $i === 0 ? 'checked' : '' ?>
                               style="position:absolute;opacity:0;width:0;height:0;">
                        <span class="lw-color-dot" style="background:<?= $col ?>;" data-color="<?= $col ?>"></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="adm_field" style="flex:2;min-width:120px;margin:0;">
                <label class="adm_label" for="lw_notes">Notiz</label>
                <input class="adm_input" type="text" id="lw_notes" name="lw_notes" placeholder="optional">
            </div>
            <input type="hidden" name="lw_sort" value="<?= count($laufwege) * 10 ?>">
            <button type="submit" class="adm_btn adm_btn--primary" style="margin-bottom:1px;">Parcours anlegen</button>
        </form>
    </div>

    <!-- ── Abschnitte nach Laufweg gruppiert ─────────── -->
    <?php
    // Alle Laufwege anzeigen (bekannte + unzugeordnet)
    $displayGroups = [];
    foreach ($laufwege as $lw) {
        $lid = (int)$lw['id'];
        if (isset($routesByLaufweg[$lid])) {
            $displayGroups[] = ['laufweg' => $lw, 'routes' => $routesByLaufweg[$lid]];
        }
    }
    if (!empty($routesByLaufweg[0])) {
        $displayGroups[] = ['laufweg' => ['id' => 0, 'name' => 'Nicht zugeordnet', 'color' => '#aaa'], 'routes' => $routesByLaufweg[0]];
    }
    ?>

    <?php foreach ($displayGroups as $dg):
        $lw = $dg['laufweg'];
        $grpRoutes = $dg['routes'];
    ?>
    <div class="adm_card" style="padding:0;overflow:hidden;">
        <!-- Parcours-Header -->
        <div style="padding:12px 18px;border-bottom:1px solid var(--wt-border);display:flex;align-items:center;gap:10px;background:<?= htmlspecialchars($lw['color']) ?>11;">
            <span style="width:12px;height:12px;border-radius:50%;background:<?= htmlspecialchars($lw['color']) ?>;flex-shrink:0;"></span>
            <span style="font-weight:800;font-size:15px;"><?= htmlspecialchars($lw['name']) ?></span>
            <span style="font-size:12px;color:var(--wt-text-muted);"><?= count($grpRoutes) ?> Abschnitte</span>
            <button class="adm_btn adm_btn--sm adm_btn--ghost" style="margin-left:auto;"
                    onclick="openParcoursMap(<?= (int)$lw['id'] ?>)">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                    <path d="M1 3l5 2 4-2 5 2v10l-5-2-4 2-5-2V3z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                    <path d="M6 5v10M10 3v10" stroke="currentColor" stroke-width="1.4"/>
                </svg>
                Karte
            </button>
        </div>

        <!-- Visuelle Kette -->
        <div style="padding:16px 18px;border-bottom:1px solid var(--wt-border);">
            <div class="rte_chain">
                <?php foreach ($grpRoutes as $i => $r): ?>
                <?php if ($i === 0): ?>
                <div class="rte_chain__node rte_chain__node--start" style="--chain-color:<?= htmlspecialchars($lw['color']) ?>;">
                    <span class="rte_chain__code"><?= htmlspecialchars($r['from_code']) ?></span>
                    <span class="rte_chain__name"><?= htmlspecialchars($r['from_name']) ?></span>
                </div>
                <?php endif; ?>
                <div class="rte_chain__edge">
                    <div class="rte_chain__line" style="background:<?= htmlspecialchars($lw['color']) ?>66;"></div>
                    <div class="rte_chain__info">
                        <?php if ($r['distance_m']): ?><span><?= number_format((int)$r['distance_m']) ?> m</span><?php endif; ?>
                        <?php if ($r['est_time_min']): ?><span>~<?= (int)$r['est_time_min'] ?> min</span><?php endif; ?>
                    </div>
                </div>
                <div class="rte_chain__node">
                    <span class="rte_chain__code" style="border-color:<?= htmlspecialchars($lw['color']) ?>;color:<?= htmlspecialchars($lw['color']) ?>;"><?= htmlspecialchars($r['to_code']) ?></span>
                    <span class="rte_chain__name"><?= htmlspecialchars($r['to_name']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tabelle der Abschnitte -->
        <table class="adm_table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="width:2rem;">#</th>
                    <th>Von</th>
                    <th>Nach</th>
                    <th style="text-align:right;">Distanz</th>
                    <th style="text-align:right;">Schätzzeit</th>
                    <th>Notiz</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($grpRoutes as $r): ?>
            <tr>
                <td class="adm_mono adm_table__muted"><?= (int)$r['sort_order'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($r['from_code']) ?> · <?= htmlspecialchars($r['from_name']) ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($r['to_code']) ?> · <?= htmlspecialchars($r['to_name']) ?></td>
                <td class="adm_mono" style="text-align:right;"><?= $r['distance_m'] ? number_format((int)$r['distance_m']) . ' m' : '–' ?></td>
                <td class="adm_mono" style="text-align:right;"><?= $r['est_time_min'] ? (int)$r['est_time_min'] . ' min' : '–' ?></td>
                <td style="font-size:12px;color:var(--wt-text-muted);"><?= htmlspecialchars($r['notes'] ?? '') ?></td>
                <td class="adm_table__actions">
                    <a href="/admin/stations/routes/<?= (int)$r['id'] ?>/edit" class="adm_btn adm_btn--sm adm_btn--ghost">Bearbeiten</a>
                    <form method="POST" action="/admin/stations/routes/<?= (int)$r['id'] ?>/delete"
                          onsubmit="return confirm('Abschnitt «<?= htmlspecialchars($r['from_code']) ?> → <?= htmlspecialchars($r['to_code']) ?>» wirklich löschen?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="adm_btn adm_btn--sm adm_btn--danger">Löschen</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <!-- ── Neuen Abschnitt hinzufügen ─────────────────── -->
    <div class="adm_card">
        <div class="adm_eyebrow" style="margin-bottom:14px;">Abschnitt hinzufügen</div>
        <form method="POST" action="/admin/stations/routes" class="adm_form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="adm_field-row">
                <div class="adm_field" style="flex:2;">
                    <label class="adm_label" for="laufweg_id">Parcours</label>
                    <select class="adm_input" id="laufweg_id" name="laufweg_id">
                        <option value="">– kein Parcours –</option>
                        <?php foreach ($laufwege as $lw): ?>
                        <option value="<?= (int)$lw['id'] ?>"><?= htmlspecialchars($lw['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="adm_field" style="flex:2;">
                    <label class="adm_label" for="from_station_id">Von Station *</label>
                    <select class="adm_input" id="from_station_id" name="from_station_id" required>
                        <option value="">– wählen –</option>
                        <?php foreach ($stations as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['code']) ?> · <?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="adm_field" style="flex:2;">
                    <label class="adm_label" for="to_station_id">Zu Station *</label>
                    <select class="adm_input" id="to_station_id" name="to_station_id" required>
                        <option value="">– wählen –</option>
                        <?php foreach ($stations as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['code']) ?> · <?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="adm_field-row">
                <div class="adm_field">
                    <label class="adm_label" for="distance_m">Distanz (m)</label>
                    <input class="adm_input adm_input--mono" type="number" id="distance_m" name="distance_m" min="0" max="99999" placeholder="z.B. 200">
                </div>
                <div class="adm_field">
                    <label class="adm_label" for="est_time_min">Schätzzeit (min)</label>
                    <input class="adm_input adm_input--mono" type="number" id="est_time_min" name="est_time_min" min="1" max="240" placeholder="z.B. 5">
                </div>
                <div class="adm_field">
                    <label class="adm_label" for="sort_order">Reihenfolge</label>
                    <input class="adm_input adm_input--mono" type="number" id="sort_order" name="sort_order" value="<?= count($routes) * 10 ?>" min="0" max="255">
                </div>
            </div>
            <div class="adm_field">
                <label class="adm_label" for="notes">Wegbeschreibung</label>
                <input class="adm_input" type="text" id="notes" name="notes" placeholder="z.B. Linker Feldweg, dann über Brücke">
            </div>
            <?php include dirname(__DIR__, 2) . '/partials/admin/osrm-route-calc.php'; ?>
            <div class="adm_form-actions" style="justify-content:flex-start;margin-top:12px;">
                <button type="submit" class="adm_btn adm_btn--primary">Abschnitt speichern</button>
            </div>
        </form>
    </div>

  </div><!-- /rte_main -->

<!-- ── Karten-Modal ─────────────────────────────────── -->
<style>
#parcoursMapModal { border:0;padding:0;border-radius:16px;width:min(920px,96vw);height:min(680px,90vh);overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.28); }
#parcoursMapModal[open] { display:flex;flex-direction:column; }
</style>
<dialog id="parcoursMapModal">

    <div id="mapModalHeader" style="
        display:flex;align-items:center;gap:10px;
        padding:12px 16px;border-bottom:1px solid var(--wt-border);
        background:var(--wt-surface);flex-shrink:0;">
        <span id="mapModalColorDot" style="width:14px;height:14px;border-radius:50%;flex-shrink:0;"></span>
        <span id="mapModalTitle" style="font-weight:800;font-size:15px;flex:1;"></span>
        <span id="mapModalHint" style="font-size:12px;color:var(--wt-text-muted);">
            Klick auf Karte = Wegpunkt · Ziehen = verschieben · Rechtsklick = entfernen
        </span>
        <button id="btnRecalcRoute" class="adm_btn adm_btn--ghost adm_btn--sm">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 8a6 6 0 1 0 .5-2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M2 4v4h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Route neu
        </button>
        <button id="btnSaveWaypoints" class="adm_btn adm_btn--primary adm_btn--sm">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 2h9l3 3v9H2V2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M5 2v4h6V2M5 9h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            Speichern
        </button>
        <button id="btnCloseMapModal" class="adm_btn adm_btn--ghost adm_btn--sm" style="padding:0 10px;">✕</button>
    </div>

    <div id="parcoursMap" style="flex:1;min-height:0;"></div>

    <div id="mapModalStatus" style="
        padding:8px 16px;font-size:12px;color:var(--wt-text-muted);
        border-top:1px solid var(--wt-border);background:var(--wt-surface-alt);
        display:flex;align-items:center;gap:12px;flex-shrink:0;">
        <span id="mapStatusText">Bereit</span>
        <span id="mapDistInfo" style="margin-left:auto;font-family:monospace;"></span>
    </div>
</dialog>

<script>
// Farb-Picker: ausgewählten Punkt hervorheben
document.querySelectorAll('.lw-color-dot').forEach(dot => {
    const inp = dot.previousElementSibling;
    if (inp?.checked) dot.classList.add('lw-color-dot--active');
    inp?.addEventListener('change', () => {
        document.querySelectorAll('.lw-color-dot').forEach(d => d.classList.remove('lw-color-dot--active'));
        dot.classList.add('lw-color-dot--active');
    });
});

// ── Parcours-Daten aus PHP ─────────────────────────────
<?php
// Parcours-Daten für JavaScript aufbereiten
$parcoursData = [];
foreach ($laufwege as $lw) {
    $lwId    = (int)$lw['id'];
    $segs    = [];
    foreach ($routes as $r) {
        if ((int)$r['laufweg_id'] !== $lwId) continue;
        $fromDur = $stationDurations[(int)$r['from_station_id']] ?? null;
        $toDur   = $stationDurations[(int)$r['to_station_id']]   ?? null;
        $segs[] = [
            'route_id'        => (int)$r['id'],
            'from_station_id' => (int)$r['from_station_id'],
            'from_code'       => $r['from_code'],
            'from_name'       => $r['from_name'],
            'from_lat'        => $r['from_lat']  ? (float)$r['from_lat']  : null,
            'from_lng'        => $r['from_lng']  ? (float)$r['from_lng']  : null,
            'from_avg_sek'    => $fromDur ? $fromDur['avg_sek'] : null,
            'from_visits'     => $fromDur ? $fromDur['visits']  : 0,
            'to_station_id'   => (int)$r['to_station_id'],
            'to_code'         => $r['to_code'],
            'to_name'         => $r['to_name'],
            'to_lat'          => $r['to_lat']    ? (float)$r['to_lat']    : null,
            'to_lng'          => $r['to_lng']    ? (float)$r['to_lng']    : null,
            'to_avg_sek'      => $toDur ? $toDur['avg_sek'] : null,
            'to_visits'       => $toDur ? $toDur['visits']  : 0,
            'waypoints'       => $r['waypoints'] ? json_decode($r['waypoints'], true) : [],
            'distance_m'      => $r['distance_m']  ? (int)$r['distance_m']  : null,
            'est_time_min'    => $r['est_time_min'] ? (int)$r['est_time_min'] : null,
        ];
    }
    if (empty($segs)) continue;
    $parcoursData[$lwId] = [
        'id'    => $lwId,
        'name'  => $lw['name'],
        'color' => $lw['color'],
        'segments' => $segs,
    ];
}
?>
const PARCOURS = <?= json_encode($parcoursData, JSON_UNESCAPED_UNICODE) ?>;
const CSRF     = <?= json_encode($csrf) ?>;

// ── Karten-Modul ──────────────────────────────────────
let mapInstance   = null;
let currentLwId   = null;
let waypointData  = {};  // route_id → [[lat,lng], ...]
let waypointMarkers = {}; // route_id → [L.Marker, ...]
let routePolylines  = {}; // route_id → L.Polyline
let stationMarkers  = []; // L.Marker[]
let osrmResults     = {}; // route_id → {distance_m, est_time_min}
let recalcTokens    = {}; // route_id → Zähler der letzten angestoßenen Neuberechnung

async function openParcoursMap(lwId) {
    const p = PARCOURS[lwId];
    if (!p) return;

    currentLwId = lwId;

    // Modal befüllen
    document.getElementById('mapModalColorDot').style.background = p.color;
    document.getElementById('mapModalTitle').textContent = p.name;
    const modal = document.getElementById('parcoursMapModal');
    modal.showModal();

    // Kurz warten bis der Dialog gerendert ist
    await new Promise(r => setTimeout(r, 60));

    // Leaflet-Karte initialisieren oder recyceln
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }
    mapInstance = L.map('parcoursMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(mapInstance);

    // State für diesen Parcours initialisieren
    waypointData    = {};
    waypointMarkers = {};
    routePolylines  = {};
    stationMarkers  = [];
    osrmResults     = {};
    recalcTokens    = {};

    p.segments.forEach(s => {
        waypointData[s.route_id] = (s.waypoints || []).map(wp => [...wp]);
    });

    // Stationen + Waypoints zeichnen
    drawStations(p);
    drawAllWaypointMarkers(p);

    // Bestehende Routen laden (OSRM)
    await recalcAllRoutes(p);

    // Karte auf Parcours-Ausdehnung einpassen
    fitMap(p);

    // Klick auf Karte = neuen Wegpunkt hinzufügen (zum nächstgelegenen Segment)
    mapInstance.on('click', e => {
        const seg = findNearestSegment(p, e.latlng);
        if (!seg) return;
        addWaypoint(p, seg.route_id, [e.latlng.lat, e.latlng.lng]);
    });
}

// Findet das Segment, dessen Streckenverlauf (Start → Wegpunkte → Ziel) dem Klickpunkt am nächsten liegt
function findNearestSegment(p, latlng) {
    const segs = p.segments.filter(s => s.from_lat && s.to_lat);
    if (!segs.length) return null;
    const clickPx = mapInstance.latLngToLayerPoint(latlng);
    let best = null, bestDist = Infinity;
    segs.forEach(s => {
        const pts = [
            [s.from_lat, s.from_lng],
            ...(waypointData[s.route_id] || []),
            [s.to_lat, s.to_lng],
        ].map(pt => mapInstance.latLngToLayerPoint(pt));
        for (let i = 0; i < pts.length - 1; i++) {
            const d = L.LineUtil.pointToSegmentDistance(clickPx, pts[i], pts[i + 1]);
            if (d < bestDist) { bestDist = d; best = s; }
        }
    });
    return best;
}

function fitMap(p) {
    const pts = [];
    p.segments.forEach(s => {
        if (s.from_lat) pts.push([s.from_lat, s.from_lng]);
        if (s.to_lat)   pts.push([s.to_lat, s.to_lng]);
        (waypointData[s.route_id] || []).forEach(wp => pts.push(wp));
    });
    if (pts.length > 1) mapInstance.fitBounds(L.latLngBounds(pts), { padding: [40, 40] });
    else if (pts.length === 1) mapInstance.setView(pts[0], 14);
    else mapInstance.setView([49.877, 12.330], 12); // Fallback TIR
}

function fmtSek(sek) {
    if (sek === null || sek === undefined) return null;
    if (sek < 60) return sek + ' s';
    return Math.floor(sek / 60) + ':' + String(sek % 60).padStart(2, '0') + ' min';
}

function drawStations(p) {
    stationMarkers.forEach(m => m.remove());
    stationMarkers = [];
    const seen = new Set(); // code → true
    // collect avg_sek + visits per station code
    const durMap = {};
    p.segments.forEach(s => {
        if (!(s.from_code in durMap)) durMap[s.from_code] = { avg_sek: s.from_avg_sek, visits: s.from_visits };
        if (!(s.to_code   in durMap)) durMap[s.to_code]   = { avg_sek: s.to_avg_sek,   visits: s.to_visits   };
    });

    p.segments.forEach((s, i) => {
        [
            { code: s.from_code, name: s.from_name, lat: s.from_lat, lng: s.from_lng, isStart: i === 0 },
            { code: s.to_code,   name: s.to_name,   lat: s.to_lat,   lng: s.to_lng,   isStart: false },
        ].forEach(st => {
            if (!st.lat || seen.has(st.code)) return;
            seen.add(st.code);
            const icon = L.divIcon({
                className: '',
                html: `<div style="
                    background:${st.isStart ? p.color : '#fff'};
                    color:${st.isStart ? '#fff' : p.color};
                    border:3px solid ${p.color};
                    border-radius:50%;width:36px;height:36px;
                    display:flex;align-items:center;justify-content:center;
                    font-family:monospace;font-size:12px;font-weight:800;
                    box-shadow:0 2px 8px rgba(0,0,0,.25);">${st.code}</div>`,
                iconSize: [36, 36], iconAnchor: [18, 18],
            });
            const dur  = durMap[st.code] || {};
            const fmt  = fmtSek(dur.avg_sek ?? null);
            const chip = fmt
                ? `<span style="display:inline-block;margin-top:6px;padding:3px 8px;border-radius:20px;
                       font-size:11px;font-weight:700;background:${p.color}22;color:${p.color};
                       border:1px solid ${p.color}55;">⏱ Ø ${fmt}${dur.visits ? ' · ' + dur.visits + ' Gr.' : ''}</span>`
                : `<span style="display:inline-block;margin-top:6px;font-size:11px;color:#aaa;">Noch keine Daten</span>`;
            const popup = `<strong>Station ${st.code}</strong><br>${st.name}<br>${chip}`;
            const m = L.marker([st.lat, st.lng], { icon, zIndexOffset: 1000 })
                .addTo(mapInstance)
                .bindPopup(popup, { maxWidth: 220 });
            stationMarkers.push(m);
        });
    });
}

function drawAllWaypointMarkers(p) {
    Object.values(waypointMarkers).flat().forEach(m => m.remove());
    waypointMarkers = {};
    p.segments.forEach(s => {
        waypointMarkers[s.route_id] = [];
        (waypointData[s.route_id] || []).forEach((wp, idx) => {
            addWaypointMarker(p, s.route_id, wp, idx);
        });
    });
}

function addWaypointMarker(p, routeId, latlng, idx) {
    const icon = L.divIcon({
        className: '',
        html: `<div style="
            background:#fff;border:2.5px solid ${p.color};
            border-radius:50%;width:14px;height:14px;
            box-shadow:0 1px 4px rgba(0,0,0,.3);cursor:move;"></div>`,
        iconSize: [14, 14], iconAnchor: [7, 7],
    });
    const marker = L.marker(latlng, { icon, draggable: true, zIndexOffset: 500 })
        .addTo(mapInstance)
        .bindTooltip('Ziehen zum Verschieben · Rechtsklick zum Entfernen', { direction: 'top' });

    marker.on('dragend', async () => {
        const pos = marker.getLatLng();
        waypointData[routeId][idx] = [pos.lat, pos.lng];
        const seg = PARCOURS[currentLwId].segments.find(s => s.route_id === routeId);
        if (seg) await recalcSegment(PARCOURS[currentLwId], seg);
    });

    marker.on('contextmenu', async () => {
        waypointData[routeId].splice(idx, 1);
        drawAllWaypointMarkers(PARCOURS[currentLwId]);
        const seg = PARCOURS[currentLwId].segments.find(s => s.route_id === routeId);
        if (seg) await recalcSegment(PARCOURS[currentLwId], seg);
    });

    if (!waypointMarkers[routeId]) waypointMarkers[routeId] = [];
    waypointMarkers[routeId][idx] = marker;
    return marker;
}

function addWaypoint(p, routeId, latlng) {
    if (!waypointData[routeId]) waypointData[routeId] = [];
    const idx = waypointData[routeId].length;
    waypointData[routeId].push(latlng);
    addWaypointMarker(p, routeId, latlng, idx);
    const seg = p.segments.find(s => s.route_id === routeId);
    if (seg) recalcSegment(p, seg);
}

async function recalcAllRoutes(p) {
    setStatus('Berechne Routen…');
    Object.values(routePolylines).forEach(pl => pl.remove());
    routePolylines = {};

    let totalDist = 0;
    for (const seg of p.segments) {
        const dist = await recalcSegment(p, seg);
        if (dist) totalDist += dist;
    }
    if (totalDist > 0) {
        document.getElementById('mapDistInfo').textContent =
            'Gesamt: ' + (totalDist >= 1000 ? (totalDist / 1000).toFixed(1) + ' km' : totalDist + ' m');
    }
    setStatus('Route geladen · Klick auf Karte = Wegpunkt hinzufügen');
}

async function recalcSegment(p, seg) {
    if (!seg.from_lat || !seg.to_lat) return null;

    // Token-Guard: bei mehreren schnell aufeinanderfolgenden Wegpunkt-Klicks können
    // die Valhalla-Antworten in anderer Reihenfolge eintreffen, als die Anfragen
    // gestartet wurden. Eine veraltete Antwort darf die neuere nicht überschreiben.
    const myToken = (recalcTokens[seg.route_id] = (recalcTokens[seg.route_id] || 0) + 1);

    const wps  = waypointData[seg.route_id] || [];
    const coords = [
        [seg.from_lng, seg.from_lat],
        ...wps.map(wp => [wp[1], wp[0]]),
        [seg.to_lng, seg.to_lat],
    ];

    try {
        const { distanceM, durationMin, latlngs } = await wtCalcRoute(coords, { geometry: true });

        if (recalcTokens[seg.route_id] !== myToken) return null; // veraltete Antwort verwerfen

        if (routePolylines[seg.route_id]) routePolylines[seg.route_id].remove();

        routePolylines[seg.route_id] = L.polyline(latlngs, {
            color:   p.color,
            weight:  5,
            opacity: 0.85,
        }).addTo(mapInstance);

        // Popup auf Linienmitte mit Segment-Info
        routePolylines[seg.route_id].bindPopup(
            `<strong>${seg.from_code} → ${seg.to_code}</strong><br>${distanceM >= 1000 ? (distanceM/1000).toFixed(1) + ' km' : distanceM + ' m'} · ca. ${durationMin} min`
        );

        // Ergebnisse merken für späteres Speichern
        osrmResults[seg.route_id] = { distance_m: distanceM, est_time_min: durationMin };

        return distanceM;
    } catch { return null; }
}

function setStatus(msg) {
    document.getElementById('mapStatusText').textContent = msg;
}

// ── Buttons ───────────────────────────────────────────
document.getElementById('btnRecalcRoute').addEventListener('click', () => {
    if (currentLwId) recalcAllRoutes(PARCOURS[currentLwId]);
});

document.getElementById('btnSaveWaypoints').addEventListener('click', async () => {
    const p    = PARCOURS[currentLwId];
    const btn  = document.getElementById('btnSaveWaypoints');
    btn.disabled = true;
    btn.textContent = 'Speichert…';
    let ok = true;

    for (const seg of p.segments) {
        const wps    = waypointData[seg.route_id] || [];
        const osrm   = osrmResults[seg.route_id]  || {};
        try {
            const res = await fetch(`/admin/stations/routes/${seg.route_id}/waypoints`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    waypoints:    wps,
                    distance_m:   osrm.distance_m   ?? null,
                    est_time_min: osrm.est_time_min  ?? null,
                    csrf_token:   CSRF,
                }),
            });
            if (!res.ok) ok = false;
        } catch { ok = false; }
    }

    btn.disabled = false;
    if (ok) {
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 8l5 5 7-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Gespeichert';
        setTimeout(() => window.location.reload(), 800);
    } else {
        btn.innerHTML = '⚠ Fehler';
        setTimeout(() => {
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 2h9l3 3v9H2V2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M5 2v4h6V2M5 9h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg> Speichern';
        }, 2000);
    }
});

document.getElementById('btnCloseMapModal').addEventListener('click', () => {
    document.getElementById('parcoursMapModal').close();
});

document.getElementById('parcoursMapModal').addEventListener('close', () => {
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }
    currentLwId = null;
});
</script>

<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
