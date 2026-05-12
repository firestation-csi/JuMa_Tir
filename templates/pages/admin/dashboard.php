<?php
$competition       = $competition       ?? null;
$competitions      = $competitions      ?? [];
$stationStats      = $stationStats      ?? [];
$stationDurations  = $stationDurations  ?? [];
$kbiDistribution   = $kbiDistribution   ?? [];
$kbmDistribution   = $kbmDistribution   ?? [];
$ranking           = $ranking           ?? [];
$scoresByStation   = $scoresByStation   ?? [];
$totalGroups       = $totalGroups       ?? 0;
$totalStations     = $totalStations     ?? 0;
$totalScores       = $totalScores       ?? 0;
$completedGroups   = $completedGroups   ?? 0;
$uniqueFeuerwehren = $uniqueFeuerwehren ?? 0;
$csrf              = $csrf              ?? '';

// Hilfsfunktion: Sekunden → "M:SS min"
$fmtDur = function (?int $sek): string {
    if ($sek === null) return '–';
    return sprintf('%d:%02d min', intdiv($sek, 60), $sek % 60);
};
ob_start();

$extraHead = '
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
';
$extraScripts = '
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
';

// Selector immer anzeigen wenn Wettbewerbe vorhanden
if (!empty($competitions)): ?>
<div style="margin-bottom:20px;">
    <?php $redirectUrl = '/admin'; include dirname(__DIR__, 2) . '/partials/admin/competition-selector.php'; ?>
</div>
<?php endif;

if (!$competition): ?>
<div class="adm_empty">
    <div class="adm_empty__icon">🏆</div>
    <p>Kein aktiver Wettbewerb gefunden.</p>
    <a href="/admin/competitions/new" class="adm_btn adm_btn--primary">Wettbewerb anlegen</a>
</div>
<?php else:
$statusLabels = ['active' => 'Aktiv', 'finished' => 'Abgeschlossen', 'archived' => 'Archiviert'];
$statusColors = ['active' => 'var(--wt-ok)', 'finished' => 'var(--wt-text-muted)', 'archived' => '#aaa'];
$overallPct   = $totalGroups > 0 && $totalStations > 0
    ? min(100, (int)round($totalScores / ($totalGroups * $totalStations) * 100))
    : 0;
?>

<!-- ── Stat-Cards ──────────────────────────────────────── -->
<div class="dash_stat-grid">
    <div class="dash_stat-card">
        <div class="dash_stat-card__value"><?= $totalGroups ?></div>
        <div class="dash_stat-card__label">Gruppen angemeldet</div>
    </div>
    <div class="dash_stat-card">
        <div class="dash_stat-card__value"><?= $uniqueFeuerwehren ?></div>
        <div class="dash_stat-card__label">Feuerwehren vertreten</div>
    </div>
    <div class="dash_stat-card">
        <div class="dash_stat-card__value"><?= $totalScores ?></div>
        <div class="dash_stat-card__label">Bewertungen gesamt</div>
    </div>
    <div class="dash_stat-card dash_stat-card--accent">
        <div class="dash_stat-card__value"><?= $completedGroups ?></div>
        <div class="dash_stat-card__label">Gruppen fertig</div>
        <div class="dash_stat-card__sub">von <?= $totalGroups ?> angemeldet</div>
    </div>
</div>

<!-- ── Wettbewerb-Status + Fortschritt ───────────────── -->
<div class="dash_section-row">
    <div class="adm_card dash_comp-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;">
            <div>
                <div class="adm_eyebrow" style="margin-bottom:4px;">Aktiver Wettbewerb</div>
                <div style="font-size:20px;font-weight:800;letter-spacing:-.02em;"><?= htmlspecialchars($competition['name']) ?></div>
                <div style="font-size:13px;color:var(--wt-text-muted);margin-top:3px;">
                    <?= $competition['date'] ? date('d.m.Y', strtotime($competition['date'])) : '–' ?>
                    <?php if ($competition['location']): ?> · <?= htmlspecialchars($competition['location']) ?><?php endif; ?>
                </div>
            </div>
            <span class="adm_badge" style="background:<?= $statusColors[$competition['status']] ?? 'var(--wt-text-muted)' ?>22;color:<?= $statusColors[$competition['status']] ?? 'var(--wt-text-muted)' ?>;flex-shrink:0;">
                <?= $statusLabels[$competition['status']] ?? $competition['status'] ?>
            </span>
        </div>
        <div class="adm_eyebrow" style="margin-bottom:8px;">Gesamtfortschritt</div>
        <div class="dash_progress-bar">
            <div class="dash_progress-bar__fill" style="width:<?= $overallPct ?>%;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--wt-text-muted);margin-top:6px;">
            <span><?= $totalScores ?> von <?= $totalGroups * $totalStations ?> Bewertungen</span>
            <span class="adm_mono" style="font-weight:700;color:var(--wt-text);"><?= $overallPct ?>%</span>
        </div>
    </div>

    <!-- ── Station-Fortschritt + Dauer ──────────────────── -->
    <div class="adm_card dash_station-progress">
        <div class="adm_eyebrow" style="margin-bottom:14px;">Stationsfortschritt &amp; Aufenthaltsdauer</div>
        <?php foreach ($stationStats as $st):
            $dur = $stationDurations[(int)$st['id']] ?? null;
        ?>
        <div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--wt-border);">
            <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:600;margin-bottom:5px;">
                <span><?= htmlspecialchars($st['code']) ?> · <?= htmlspecialchars($st['name']) ?></span>
                <span class="adm_mono" style="color:var(--wt-text-muted);font-size:12px;"><?= (int)$st['scored_count'] ?>/<?= $st['total_groups'] ?></span>
            </div>
            <div class="dash_progress-bar dash_progress-bar--sm" style="margin-bottom:7px;">
                <div class="dash_progress-bar__fill <?= $st['pct'] === 100 ? 'dash_progress-bar__fill--done' : '' ?>" style="width:<?= $st['pct'] ?>%;"></div>
            </div>
            <?php if ($dur && $dur['visits'] > 0): ?>
            <div class="dash_duration-row">
                <span class="dash_duration-chip" title="Durchschnitt">
                    <svg width="11" height="11" viewBox="0 0 14 14" fill="none" style="flex-shrink:0;"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.4"/><path d="M7 4.5V7l1.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    Ø <?= $fmtDur($dur['avg_sek']) ?>
                </span>
                <span class="dash_duration-chip dash_duration-chip--muted" title="Minimum">
                    ↓ <?= $fmtDur($dur['min_sek']) ?>
                </span>
                <span class="dash_duration-chip dash_duration-chip--muted" title="Maximum">
                    ↑ <?= $fmtDur($dur['max_sek']) ?>
                </span>
                <span style="font-size:11px;color:var(--wt-text-subtle);margin-left:auto;"><?= $dur['visits'] ?> Besuche</span>
            </div>
            <?php else: ?>
            <div style="font-size:11px;color:var(--wt-text-subtle);">Noch keine Aufenthaltsdaten</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($stationStats)): ?>
            <div class="adm_table__muted" style="font-size:13px;">Keine Stationen konfiguriert.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ── OSM Karte ──────────────────────────────────────── -->
<div class="adm_card" style="padding:0;overflow:hidden;">
    <div style="padding:16px 20px 12px;border-bottom:1px solid var(--wt-border);">
        <span style="font-weight:700;font-size:15px;">Stationskarte</span>
        <?php $hasCoords = array_filter($stationStats, fn($s) => $s['lat'] && $s['lng']); ?>
        <?php if (empty($hasCoords)): ?>
            <span style="font-size:12px;color:var(--wt-text-muted);margin-left:10px;">
                Koordinaten unter
                <a href="/admin/stations" style="color:var(--wt-text-muted);">Stationen → Bearbeiten</a> eintragen
            </span>
        <?php endif; ?>
    </div>
    <div id="dash-map" style="height:380px;"></div>
</div>

<!-- ── Leaderboard + KBM-Verteilung ──────────────────── -->
<div class="dash_section-row">
    <!-- Leaderboard -->
    <div class="adm_card" style="flex:3;min-width:0;">
        <div class="adm_eyebrow" style="margin-bottom:14px;">Aktuelles Ranking</div>
        <?php if (empty($ranking)): ?>
            <div class="adm_table__muted" style="font-size:13px;">Noch keine Bewertungen vorhanden.</div>
        <?php else: ?>
        <table class="adm_table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="width:2.5rem;text-align:center;">#</th>
                    <th>Gruppe</th>
                    <th>Feuerwehr · Bereich</th>
                    <th style="text-align:center;">Stat.</th>
                    <th style="text-align:right;">FP gesamt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($ranking, 0, 15) as $rank => $r):
                    $fpColor = (int)$r['total_fp'] === 0 ? 'var(--wt-ok)' : ((int)$r['total_fp'] > 20 ? 'var(--wt-red)' : 'var(--wt-text)');
                    $isComplete = (int)$r['stations_completed'] >= $totalStations && $totalStations > 0;
                ?>
                <tr <?= $rank === 0 ? 'style="background:var(--wt-ok-soft);"' : '' ?>>
                    <td style="text-align:center;font-weight:700;color:<?= $rank < 3 ? 'var(--wt-ok)' : 'var(--wt-text-muted)' ?>;">
                        <?= $rank + 1 ?>
                    </td>
                    <td>
                        <span style="font-weight:600;">#<?= htmlspecialchars($r['group_num']) ?> <?= htmlspecialchars($r['group_name']) ?></span>
                    </td>
                    <td style="color:var(--wt-text-muted);">
                        <?php if ($r['feuerwehr_name']): ?>
                            <?= htmlspecialchars($r['feuerwehr_name']) ?>
                            <?php if ($r['bereich']): ?><span style="font-size:11px;"> · <?= htmlspecialchars($r['bereich']) ?></span><?php endif; ?>
                        <?php else: ?>–<?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="adm_mono" style="font-size:12px;color:<?= $isComplete ? 'var(--wt-ok)' : 'var(--wt-text-muted)' ?>;">
                            <?= (int)$r['stations_completed'] ?>/<?= $totalStations ?>
                            <?= $isComplete ? '✓' : '' ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <span class="adm_mono" style="font-size:15px;font-weight:700;color:<?= $fpColor ?>;">
                            <?= (int)$r['total_fp'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($ranking) > 15): ?>
            <div style="text-align:center;padding:10px 0 2px;font-size:12px;color:var(--wt-text-muted);">
                + <?= count($ranking) - 15 ?> weitere →
                <a href="/admin/results" style="color:var(--wt-text-muted);">Vollständige Ergebnisse</a>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Charts: KBI + KBM -->
    <div style="flex:2;min-width:0;display:flex;flex-direction:column;gap:16px;">

        <!-- KBI-Bereich Donut -->
        <div class="adm_card">
            <div class="adm_eyebrow" style="margin-bottom:12px;">KBI-Bereich Verteilung</div>
            <?php if (empty($kbiDistribution)): ?>
                <div class="adm_table__muted" style="font-size:13px;">Keine Daten vorhanden.</div>
            <?php else:
            $chartColors = ['#8B1A1A','#C0392B','#E74C3C','#F39C12','#E67E22','#2C3E50','#7F8C8D','#BDC3C7']; ?>
            <div style="position:relative;height:180px;">
                <canvas id="kbiChart"></canvas>
            </div>
            <div style="margin-top:12px;display:flex;flex-direction:column;gap:6px;">
                <?php foreach ($kbiDistribution as $i => $row): ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:12px;">
                    <span style="width:11px;height:11px;border-radius:3px;background:<?= $chartColors[$i % count($chartColors)] ?>;flex-shrink:0;"></span>
                    <span style="flex:1;"><?= htmlspecialchars($row['kbi_bereich']) ?></span>
                    <span class="adm_mono" style="font-weight:700;"><?= (int)$row['group_count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- KBM-Bereich Horizontale Balken -->
        <div class="adm_card">
            <div class="adm_eyebrow" style="margin-bottom:12px;">KBM-Bereich Verteilung</div>
            <?php if (empty($kbmDistribution)): ?>
                <div class="adm_table__muted" style="font-size:13px;">Keine Daten vorhanden.</div>
            <?php else:
                $kbmMax = max(array_column($kbmDistribution, 'group_count')) ?: 1;
                // Farben je KBI-Bereich vergeben
                $kbiColorMap = [];
                foreach ($kbmDistribution as $i => $row) {
                    $kbi = $row['kbi_bereich'];
                    if (!isset($kbiColorMap[$kbi])) {
                        $kbiColorMap[$kbi] = $chartColors[count($kbiColorMap) % count($chartColors)];
                    }
                }
            ?>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach ($kbmDistribution as $row):
                    $pct   = (int)round((int)$row['group_count'] / $kbmMax * 100);
                    $color = $kbiColorMap[$row['kbi_bereich']] ?? '#aaa';
                ?>
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:11.5px;margin-bottom:3px;">
                        <span style="font-weight:600;"><?= htmlspecialchars($row['bereich']) ?></span>
                        <span class="adm_mono" style="color:var(--wt-text-muted);"><?= (int)$row['group_count'] ?></span>
                    </div>
                    <div style="background:var(--wt-surface-alt);border-radius:4px;height:7px;overflow:hidden;">
                        <div style="background:<?= $color ?>;width:<?= $pct ?>%;height:100%;border-radius:4px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /Charts -->
</div>

<?php endif; ?>
<?php
$content = ob_get_clean();

// JSON für Karte + Chart
$stationJson = json_encode(array_map(function($s) use ($scoresByStation, $stationDurations) {
    $dur = $stationDurations[(int)$s['id']] ?? null;
    return [
        'id'      => (int)$s['id'],
        'code'    => $s['code'],
        'name'    => $s['name'],
        'lat'     => $s['lat'] ? (float)$s['lat'] : null,
        'lng'     => $s['lng'] ? (float)$s['lng'] : null,
        'scored'  => (int)$s['scored_count'],
        'total'   => (int)$s['total_groups'],
        'pct'     => (int)$s['pct'],
        'best_fp' => $s['best_fp'] !== null ? (int)$s['best_fp'] : null,
        'avg_sek' => $dur ? $dur['avg_sek'] : null,
        'visits'  => $dur ? (int)$dur['visits'] : 0,
        'scores'  => array_map(fn($sc) => [
            'num'   => $sc['group_num'],
            'name'  => $sc['group_name'],
            'fp'    => (int)$sc['total_fp'],
        ], $scoresByStation[(int)$s['id']] ?? []),
    ];
}, $stationStats ?? []), JSON_UNESCAPED_UNICODE);

$kbiJson = json_encode(array_values($kbiDistribution ?? []), JSON_UNESCAPED_UNICODE);
$chartColors = ['#8B1A1A','#C0392B','#E74C3C','#F39C12','#E67E22','#2C3E50','#7F8C8D','#BDC3C7'];

$extraScripts .= '
<script>
(function () {
    // ── Leaflet Karte ─────────────────────────────────
    const mapEl = document.getElementById("dash-map");
    if (!mapEl) return;

    const stations = ' . $stationJson . ';
    const stationsWithCoords = stations.filter(s => s.lat && s.lng);

    const defaultCenter = [49.8772, 12.3305];
    const defaultZoom   = stationsWithCoords.length ? 12 : 11;

    const map = L.map("dash-map").setView(defaultCenter, defaultZoom);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a>",
        maxZoom: 19,
    }).addTo(map);

    stationsWithCoords.forEach(s => {
        const pct   = s.pct;
        const color = pct === 100 ? "#27AE60" : pct >= 50 ? "#E67E22" : "#C0392B";
        const icon  = L.divIcon({
            className: "",
            html: `<div style="
                background:${color};color:#fff;border-radius:50%;
                width:38px;height:38px;display:flex;align-items:center;justify-content:center;
                font-family:monospace;font-size:13px;font-weight:800;
                box-shadow:0 2px 8px rgba(0,0,0,.3);border:2px solid #fff;">
                ${s.code}
            </div>`,
            iconSize: [38, 38], iconAnchor: [19, 19],
        });

        const scoreRows = s.scores.length
            ? s.scores.slice(0, 5).map(sc =>
                `<tr><td style="padding:2px 8px 2px 0;font-size:12px;">#${sc.num} ${sc.name}</td><td style="font-family:monospace;font-weight:700;font-size:12px;">${sc.fp} FP</td></tr>`
              ).join("") + (s.scores.length > 5 ? `<tr><td colspan="2" style="font-size:11px;color:#888;">+ ${s.scores.length - 5} weitere</td></tr>` : "")
            : `<tr><td colspan="2" style="color:#888;font-size:12px;">Noch keine Bewertungen</td></tr>`;

        const fmtSek = sek => {
            if (!sek) return null;
            if (sek < 60) return sek + ' s';
            return Math.floor(sek / 60) + ':' + String(sek % 60).padStart(2, '0') + ' min';
        };
        const durFmt  = fmtSek(s.avg_sek);
        const durChip = durFmt
            ? `<span style="display:inline-block;margin-top:6px;padding:3px 8px;border-radius:20px;
                   font-size:11px;font-weight:700;background:${color}22;color:${color};
                   border:1px solid ${color}55;">⏱ Ø ${durFmt}${s.visits ? ' · ' + s.visits + ' Gr.' : ''}</span>`
            : '';

        const popup = `
            <div style="min-width:180px;">
                <div style="font-size:14px;font-weight:800;margin-bottom:6px;">Station ${s.code} · ${s.name}</div>
                <div style="margin-bottom:8px;">
                    <div style="background:#eee;border-radius:4px;height:8px;">
                        <div style="background:${color};width:${pct}%;height:8px;border-radius:4px;"></div>
                    </div>
                    <div style="font-size:11px;color:#666;margin-top:3px;">${s.scored}/${s.total} Gruppen (${pct}%)</div>
                </div>
                <table style="border-collapse:collapse;width:100%;">${scoreRows}</table>
                ${s.best_fp !== null ? `<div style="font-size:11px;color:#27AE60;margin-top:6px;font-weight:600;">Bestes Ergebnis: ${s.best_fp} FP</div>` : ""}
                ${durChip}
            </div>`;
        L.marker([s.lat, s.lng], { icon }).addTo(map).bindPopup(popup);
    });

    if (stationsWithCoords.length > 1) {
        const bounds = L.latLngBounds(stationsWithCoords.map(s => [s.lat, s.lng]));
        map.fitBounds(bounds, { padding: [40, 40] });
    }

    // ── KBI Donut-Chart ───────────────────────────────
    const kbiData  = ' . $kbiJson . ';
    const kbiCtx   = document.getElementById("kbiChart");
    if (!kbiCtx || !kbiData.length) return;
    const colors   = ' . json_encode($chartColors) . ';

    new Chart(kbiCtx, {
        type: "doughnut",
        data: {
            labels:   kbiData.map(d => d.kbi_bereich),
            datasets: [{
                data:            kbiData.map(d => d.group_count),
                backgroundColor: colors.slice(0, kbiData.length),
                borderWidth:     2,
                borderColor:     "#fff",
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: "65%",
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed} Gruppen`,
                    },
                },
            },
        },
    });
})();
</script>
';

require dirname(__DIR__, 2) . '/layout/admin.php';
