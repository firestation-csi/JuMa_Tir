<?php
$competition   = $competition   ?? null;
$competitions  = $competitions  ?? [];
$ranking       = $ranking       ?? [];
$stationScores = $stationScores ?? [];
$recentScores  = $recentScores  ?? [];
$matrix        = $matrix        ?? [];
$stations      = $stations      ?? [];
$totalStations = $totalStations ?? 0;
$csrf          = $csrf          ?? '';

ob_start();

$impLabel = ['sehr_gut' => 'Sehr gut', 'gut' => 'Gut', 'befriedigend' => 'Befriedigend'];
$impScore = ['sehr_gut' => 0, 'gut' => 1, 'befriedigend' => 2];
$impColor = ['sehr_gut' => 'var(--wt-ok)', 'gut' => 'var(--wt-text-muted)', 'befriedigend' => 'var(--wt-warn)'];

$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
.res_ticker__del {
    background: none; border: none; cursor: pointer;
    font-size: 16px; line-height: 1; padding: 0 2px;
    color: var(--wt-text-subtle); border-radius: 4px;
    transition: color .15s, background .15s;
}
.res_ticker__del:hover { color: var(--wt-red); background: var(--wt-red-soft, #fef2f2); }
</style>';
?>

<?php if (!empty($competitions)): ?>
<div style="margin-bottom:20px;">
    <?php $redirectUrl = '/admin/results'; include dirname(__DIR__, 2) . '/partials/admin/competition-selector.php'; ?>
</div>
<?php endif; ?>

<?php if (!$competition): ?>
<div class="adm_empty">
    <div class="adm_empty__icon">🏆</div>
    <p>Kein aktiver Wettbewerb gefunden.</p>
</div>
<?php else: ?>

<!-- ── Toolbar ─────────────────────────────────────────── -->
<div class="adm_toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span style="font-size:13px;color:var(--wt-text-muted);">
            <?= htmlspecialchars($competition['name']) ?> ·
            <?= $competition['date'] ? date('d.m.Y', strtotime($competition['date'])) : '' ?>
        </span>
        <span id="res-update-badge" style="font-size:11px;font-family:monospace;color:var(--wt-ok);background:var(--wt-ok-soft);padding:2px 8px;border-radius:6px;">
            ● LIVE
        </span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="adm_btn adm_btn--ghost adm_btn--sm" id="exportCsvBtn">↓ CSV Export</button>
        <button class="adm_btn adm_btn--ghost adm_btn--sm" onclick="window.print()">⎙ Drucken</button>
        <a href="/admin/results/present" target="_blank" class="adm_btn adm_btn--primary adm_btn--sm">▶ Präsentation</a>
    </div>
</div>

<!-- ── Layout: Main + Sidebar ──────────────────────────── -->
<div class="res_layout">

  <!-- ═══ MAIN ════════════════════════════════════════════ -->
  <div class="res_main">

    <!-- ── Podium Top 3 ──────────────────────────────── -->
    <?php
    $top3      = array_slice(array_filter($ranking, fn($r) => $r['is_complete']), 0, 3);
    $podiumOrder = [1 => null, 0 => null, 2 => null]; // 2nd, 1st, 3rd
    foreach ($top3 as $i => $r) { $podiumOrder[$i] = $r; }
    ?>
    <?php if (count($top3) >= 1): ?>
    <div class="adm_card res_podium">
        <div class="adm_eyebrow" style="margin-bottom:20px;">Podium</div>
        <div class="res_podium__stage">
            <?php foreach ([1, 0, 2] as $idx): ?>
            <?php $r = $podiumOrder[$idx] ?? null; ?>
            <div class="res_podium__slot res_podium__slot--<?= $idx + 1 ?> <?= !$r ? 'res_podium__slot--empty' : '' ?>">
                <?php if ($r): ?>
                <div class="res_podium__medal"><?= ['🥇','🥈','🥉'][$idx] ?></div>
                <div class="res_podium__name">#<?= htmlspecialchars($r['group_num']) ?><br><?= htmlspecialchars($r['group_name']) ?></div>
                <?php if ($r['feuerwehr_name']): ?>
                    <div class="res_podium__fw"><?= htmlspecialchars($r['feuerwehr_name']) ?></div>
                <?php endif; ?>
                <div class="res_podium__score"><?= number_format((float)$r['combined_score'], 1, ',', '') ?> Pkt</div>
                <div class="res_podium__bar" style="height:<?= [80, 120, 60][$idx] ?>px;"></div>
                <?php else: ?>
                <div class="res_podium__bar res_podium__bar--empty" style="height:<?= [80, 120, 60][$idx] ?>px;"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Gesamt-Rangliste ───────────────────────────── -->
    <div class="adm_card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <span class="adm_eyebrow">Gesamtrangliste</span>
            <span style="font-size:11px;color:var(--wt-text-subtle);">
                FP-Summe + Ø-Eindruck (Sehr gut=0 · Gut=1 · Befriedigend=2)
            </span>
        </div>
        <?php if (empty($ranking)): ?>
            <div class="adm_table__muted">Noch keine Ergebnisse.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="adm_table res_ranking-table" id="rankingTable">
            <thead>
                <tr>
                    <th style="width:2.5rem;text-align:center;">#</th>
                    <th>Gruppe</th>
                    <th>Feuerwehr · Bereich</th>
                    <th style="text-align:center;white-space:nowrap;">Stat.</th>
                    <th style="text-align:right;white-space:nowrap;">FP</th>
                    <th style="text-align:right;white-space:nowrap;">Eindruck</th>
                    <th style="text-align:right;white-space:nowrap;">Punkte</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ranking as $r):
                $rankClass = match((int)$r['rank']) { 1=>'res_row--gold', 2=>'res_row--silver', 3=>'res_row--bronze', default=>'' };
                $complete  = $r['is_complete'];
            ?>
            <tr class="<?= $rankClass ?>" data-group-id="<?= (int)$r['group_id'] ?>">
                <td style="text-align:center;font-weight:700;color:<?= (int)$r['rank'] <= 3 ? 'var(--wt-text)' : 'var(--wt-text-muted)' ?>;">
                    <?= $r['rank'] ?>
                </td>
                <td>
                    <span style="font-weight:600;">#<?= htmlspecialchars($r['group_num']) ?> <?= htmlspecialchars($r['group_name']) ?></span>
                    <?php if (!$complete): ?>
                        <span style="font-size:11px;color:var(--wt-text-subtle);margin-left:4px;">unvollständig</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--wt-text-muted);">
                    <?= $r['feuerwehr_name'] ? htmlspecialchars($r['feuerwehr_name']) : '–' ?>
                    <?php if ($r['bereich']): ?><br><span style="font-size:11px;"><?= htmlspecialchars($r['bereich']) ?></span><?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <span class="adm_mono" style="font-size:12px;color:<?= $complete ? 'var(--wt-ok)' : 'var(--wt-text-muted)' ?>;">
                        <?= (int)$r['stations_completed'] ?>/<?= $totalStations ?>
                    </span>
                </td>
                <td style="text-align:right;" class="adm_mono res_rank__fp"><?= (int)$r['total_fp'] ?></td>
                <td style="text-align:right;font-size:12px;color:var(--wt-text-muted);" class="res_rank__impression">
                    <?= $r['avg_impression'] !== null ? number_format((float)$r['avg_impression'], 2, ',', '') : '–' ?>
                </td>
                <td style="text-align:right;">
                    <span class="adm_mono res_rank__score" style="font-weight:700;font-size:15px;">
                        <?= number_format((float)$r['combined_score'], 1, ',', '') ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Station-Accordions ────────────────────────── -->
    <?php if (!empty($stationScores)): ?>
    <div class="adm_card" style="padding:0;overflow:hidden;">
        <div style="padding:16px 20px 14px;border-bottom:1px solid var(--wt-border);">
            <span class="adm_eyebrow">Stationsranglisten</span>
        </div>
        <?php foreach ($stationScores as $si => $sg): ?>
        <div class="res_accordion">
            <button class="res_accordion__head" data-acc="acc-<?= $sg['station_id'] ?>">
                <span style="font-weight:700;">
                    Station <?= htmlspecialchars($sg['station_code']) ?> · <?= htmlspecialchars($sg['station_name']) ?>
                </span>
                <span style="font-size:12px;color:var(--wt-text-muted);margin-left:8px;">
                    <?= count($sg['scores']) ?> Bewertungen
                </span>
                <svg class="res_accordion__arrow" width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <div class="res_accordion__body" id="acc-<?= $sg['station_id'] ?>">
                <table class="adm_table" style="font-size:13px;margin:0;">
                    <thead>
                        <tr>
                            <th style="width:2rem;text-align:center;">#</th>
                            <th>Gruppe</th>
                            <th>Feuerwehr</th>
                            <th style="text-align:center;">Eindruck</th>
                            <th style="text-align:right;">FP</th>
                            <th style="text-align:right;font-size:11px;color:var(--wt-text-subtle);">Zeit</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sg['scores'] as $pos => $sc): ?>
                    <tr <?= $pos === 0 ? 'style="background:var(--wt-ok-soft);"' : '' ?>>
                        <td style="text-align:center;font-weight:700;color:var(--wt-text-muted);"><?= $pos + 1 ?></td>
                        <td style="font-weight:600;">#<?= htmlspecialchars($sc['group_num']) ?> <?= htmlspecialchars($sc['group_name']) ?></td>
                        <td style="font-size:12px;color:var(--wt-text-muted);"><?= $sc['feuerwehr_name'] ? htmlspecialchars($sc['feuerwehr_name']) : '–' ?></td>
                        <td style="text-align:center;">
                            <span style="font-size:11px;font-weight:600;color:<?= $impColor[$sc['impression']] ?? 'var(--wt-text-muted)' ?>;">
                                <?= $impLabel[$sc['impression']] ?? '–' ?>
                            </span>
                        </td>
                        <td style="text-align:right;" class="adm_mono"><?= (int)$sc['total_fp'] ?></td>
                        <td style="text-align:right;font-size:11px;color:var(--wt-text-subtle);">
                            <?= date('H:i', strtotime($sc['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Abschluss-Matrix ───────────────────────────── -->
    <?php if (!empty($matrix) && !empty($stations)): ?>
    <div class="adm_card" style="overflow-x:auto;">
        <div class="adm_eyebrow" style="margin-bottom:14px;">Abschluss-Matrix</div>
        <table class="adm_table res_matrix" style="font-size:12px;">
            <thead>
                <tr>
                    <th>Gruppe</th>
                    <?php foreach ($stations as $st): if (!$st['active']) continue; ?>
                        <th style="text-align:center;white-space:nowrap;">
                            <?= htmlspecialchars($st['code']) ?><br>
                            <span style="font-size:10px;font-weight:400;color:var(--wt-text-muted);"><?= htmlspecialchars($st['name']) ?></span>
                        </th>
                    <?php endforeach; ?>
                    <th style="text-align:right;">Gesamt FP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matrix as $row):
                $totalFp = array_sum($row['stations']);
            ?>
            <tr>
                <td style="font-weight:600;white-space:nowrap;">#<?= htmlspecialchars($row['group_num']) ?> <?= htmlspecialchars($row['group_name']) ?></td>
                <?php foreach ($stations as $st): if (!$st['active']) continue;
                    $sid = (int)$st['id'];
                    $fp  = $row['stations'][$sid] ?? null;
                ?>
                <td style="text-align:center;">
                    <?php if ($fp !== null): ?>
                        <span class="res_matrix__done" title="<?= $fp ?> FP">
                            <?= $fp === 0 ? '✓' : $fp ?>
                        </span>
                    <?php else: ?>
                        <span class="res_matrix__missing">–</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td style="text-align:right;" class="adm_mono" style="font-weight:700;"><?= $totalFp ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

  </div><!-- /res_main -->

  <!-- ═══ SIDEBAR ═════════════════════════════════════════ -->
  <div class="res_sidebar">

    <!-- ── Live-Ticker ────────────────────────────────── -->
    <div class="adm_card res_ticker-card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 16px 12px;border-bottom:1px solid var(--wt-border);display:flex;align-items:center;justify-content:space-between;">
            <span class="adm_eyebrow">Live-Ticker</span>
            <span id="ticker-time" style="font-size:11px;font-family:monospace;color:var(--wt-text-subtle);"></span>
        </div>
        <div id="ticker-list" style="max-height:420px;overflow-y:auto;">
            <?php foreach ($recentScores as $sc):
                $impC = $impColor[$sc['impression']] ?? 'var(--wt-text-muted)';
            ?>
            <div class="res_ticker__item" data-score-id="<?= (int)$sc['id'] ?>">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px;">
                    <span style="font-weight:700;font-size:13px;">#<?= htmlspecialchars($sc['group_num']) ?> <?= htmlspecialchars($sc['group_name']) ?></span>
                    <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                        <span class="adm_mono" style="font-size:14px;font-weight:800;color:<?= (int)$sc['total_fp'] > 10 ? 'var(--wt-red)' : 'var(--wt-ok)' ?>;"><?= (int)$sc['total_fp'] ?> FP</span>
                        <button class="res_ticker__del" data-id="<?= (int)$sc['id'] ?>" title="Bewertung löschen">×</button>
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;margin-top:3px;">
                    <span style="font-size:11px;color:var(--wt-text-muted);">
                        Stn <?= htmlspecialchars($sc['station_code']) ?> · <?= htmlspecialchars($sc['judge_name']) ?>
                    </span>
                    <span style="font-size:11px;color:<?= $impC ?>;font-weight:600;"><?= $impLabel[$sc['impression']] ?? '' ?></span>
                </div>
                <div style="font-size:10px;color:var(--wt-text-subtle);margin-top:2px;font-family:monospace;">
                    <?= date('d.m. H:i', strtotime($sc['created_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentScores)): ?>
                <div style="padding:24px;text-align:center;color:var(--wt-text-subtle);font-size:13px;">Noch keine Bewertungen</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── KBI-Bereich Ranking ───────────────────────── -->
    <?php
    $kbiRanking = [];
    foreach ($ranking as $r) {
        $kbi = $r['kbi_bereich'] ?? '–';
        if (!isset($kbiRanking[$kbi])) {
            $kbiRanking[$kbi] = ['count' => 0, 'total_fp' => 0, 'best_combined' => PHP_INT_MAX, 'best_name' => ''];
        }
        $kbiRanking[$kbi]['count']++;
        $kbiRanking[$kbi]['total_fp'] += (int)$r['total_fp'];
        if ($r['is_complete'] && (float)$r['combined_score'] < $kbiRanking[$kbi]['best_combined']) {
            $kbiRanking[$kbi]['best_combined'] = (float)$r['combined_score'];
            $kbiRanking[$kbi]['best_name']     = $r['group_name'];
        }
    }
    ksort($kbiRanking);
    ?>
    <?php if (!empty($kbiRanking)): ?>
    <div class="adm_card">
        <div class="adm_eyebrow" style="margin-bottom:14px;">KBI-Bereich Ranking</div>
        <?php foreach ($kbiRanking as $kbi => $data): ?>
        <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--wt-border);">
            <div style="font-size:13px;font-weight:700;"><?= htmlspecialchars($kbi) ?></div>
            <div style="font-size:11px;color:var(--wt-text-muted);margin-top:2px;">
                <?= $data['count'] ?> Gruppen · Ø <?= $data['count'] > 0 ? round($data['total_fp'] / $data['count'], 1) : '–' ?> FP
            </div>
            <?php if ($data['best_name']): ?>
            <div style="font-size:11px;color:var(--wt-ok);margin-top:2px;font-weight:600;">
                Bestes: <?= htmlspecialchars($data['best_name']) ?>
                (<?= number_format($data['best_combined'], 1, ',', '') ?> Pkt)
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── FP-Verteilung Chart ───────────────────────── -->
    <?php if (!empty($ranking)): ?>
    <div class="adm_card">
        <div class="adm_eyebrow" style="margin-bottom:14px;">FP-Verteilung</div>
        <canvas id="fpChart" style="max-height:180px;"></canvas>
    </div>
    <?php endif; ?>

  </div><!-- /res_sidebar -->
</div><!-- /res_layout -->

<?php endif; ?>
<?php
$content = ob_get_clean();

// JSON für Charts und Live-Polling
$rankingJson = json_encode(array_map(fn($r) => [
    'num'      => $r['group_num'],
    'name'     => $r['group_name'],
    'fp'       => (int)$r['total_fp'],
    'combined' => (float)$r['combined_score'],
], array_slice($ranking ?? [], 0, 15)), JSON_UNESCAPED_UNICODE);

$impColorJson = json_encode(['sehr_gut' => '#27AE60', 'gut' => '#95A5A6', 'befriedigend' => '#E67E22']);
$csrfJson     = json_encode($csrf ?? '');

// Accordion läuft unabhängig – kein Chart.js erforderlich
$extraScripts .= <<<ACCORDION
<script>
(function () {
    document.querySelectorAll('.res_accordion__head').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.res_accordion').classList.toggle('res_accordion--open');
        });
    });
})();
</script>
ACCORDION;

$extraScripts .= <<<JS
<script>
(function () {
    const rankingData = {$rankingJson};
    const impColors   = {$impColorJson};

    // ── FP-Verteilung Bar-Chart ─────────────────────────
    const fpCtx = document.getElementById('fpChart');
    if (fpCtx && rankingData.length && typeof Chart !== 'undefined') {
        new Chart(fpCtx, {
            type: 'bar',
            data: {
                labels:   rankingData.map(r => '#' + r.num),
                datasets: [{
                    label:           'Gesamtpunkte',
                    data:            rankingData.map(r => r.combined),
                    backgroundColor: rankingData.map(r => r.combined < 5 ? '#27AE6055' : r.combined < 15 ? '#E67E2255' : '#C0392B55'),
                    borderColor:     rankingData.map(r => r.combined < 5 ? '#27AE60'   : r.combined < 15 ? '#E67E22'   : '#C0392B'),
                    borderWidth: 1.5,
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { font: { family: 'monospace', size: 10 } } },
                    x: { ticks: { font: { family: 'monospace', size: 10 } } },
                },
            },
        });
    }

    // ── Score löschen ──────────────────────────────────
    const CSRF = {$csrfJson};

    async function deleteScore(id, itemEl) {
        if (!confirm('Bewertung wirklich löschen?')) return;
        try {
            const resp = await fetch('/admin/scores/' + id + '/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(CSRF),
                credentials: 'same-origin',
            });
            const data = await resp.json();
            if (data.success) {
                itemEl.style.transition = 'opacity .2s';
                itemEl.style.opacity = '0';
                setTimeout(() => itemEl.remove(), 200);
            } else {
                alert('Fehler: ' + (data.error ?? 'Unbekannt'));
            }
        } catch (e) {
            alert('Verbindungsfehler: ' + e.message);
        }
    }

    document.getElementById('ticker-list')?.addEventListener('click', e => {
        const btn = e.target.closest('.res_ticker__del');
        if (!btn) return;
        deleteScore(btn.dataset.id, btn.closest('.res_ticker__item'));
    });

    // ── Live-Ticker Polling alle 20s ───────────────────
    const tickerList = document.getElementById('ticker-list');
    const tickerTime = document.getElementById('ticker-time');
    const badge      = document.getElementById('res-update-badge');

    function fmtTime(iso) {
        if (!iso) return '';
        const d = new Date(iso.replace(' ', 'T'));
        return `\${String(d.getDate()).padStart(2,'0')}.\${String(d.getMonth()+1).padStart(2,'0')}. \${String(d.getHours()).padStart(2,'0')}:\${String(d.getMinutes()).padStart(2,'0')}`;
    }

    async function pollResults() {
        try {
            const res  = await fetch(location.href, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const json = await res.json();
            const data = (json && json.data) ? json.data : json;

            // Ticker aktualisieren
            if (tickerList && data.recentScores) {
                const colors = {$impColorJson};
                const labels = { sehr_gut: 'Sehr gut', gut: 'Gut', befriedigend: 'Befriedigend' };
                tickerList.innerHTML = data.recentScores.map(sc => `
                    <div class="res_ticker__item" data-score-id="\${sc.id}">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px;">
                            <span style="font-weight:700;font-size:13px;">#\${sc.group_num} \${sc.group_name}</span>
                            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                                <span style="font-family:monospace;font-size:14px;font-weight:800;color:\${sc.total_fp > 10 ? '#C0392B' : '#27AE60'};">\${sc.total_fp} FP</span>
                                <button class="res_ticker__del" data-id="\${sc.id}" title="Bewertung löschen">×</button>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-top:3px;">
                            <span style="font-size:11px;color:var(--wt-text-muted);">Stn \${sc.station_code} · \${sc.judge_name}</span>
                            <span style="font-size:11px;color:\${colors[sc.impression] ?? '#888'};font-weight:600;">\${labels[sc.impression] ?? ''}</span>
                        </div>
                        <div style="font-size:10px;color:var(--wt-text-subtle);margin-top:2px;font-family:monospace;">\${fmtTime(sc.created_at)}</div>
                    </div>`).join('') || '<div style="padding:24px;text-align:center;color:var(--wt-text-subtle);font-size:13px;">Noch keine Bewertungen</div>';
            }
            // Rangliste aktualisieren
            if (data.ranking) {
                const fmt1 = n => Number(n).toFixed(1).replace('.', ',');
                const fmt2 = n => n != null ? Number(n).toFixed(2).replace('.', ',') : '–';
                data.ranking.forEach(r => {
                    const row = document.querySelector(`#rankingTable tbody tr[data-group-id="\${r.group_id}"]`);
                    if (!row) return;
                    const fpCell  = row.querySelector('.res_rank__fp');
                    const impCell = row.querySelector('.res_rank__impression');
                    const scCell  = row.querySelector('.res_rank__score');
                    if (fpCell)  fpCell.textContent  = r.total_fp;
                    if (impCell) impCell.textContent = fmt2(r.avg_impression);
                    if (scCell)  scCell.textContent  = fmt1(r.combined_score);
                });
            }

            if (tickerTime) tickerTime.textContent = 'aktualisiert ' + (data.ts || '');
            if (badge) { badge.textContent = '● LIVE'; badge.style.color = 'var(--wt-ok)'; }
        } catch {
            if (badge) { badge.textContent = '○ OFFLINE'; badge.style.color = 'var(--wt-red)'; }
        }
    }
    setInterval(pollResults, 20_000);
    const now = new Date();
    if (tickerTime) tickerTime.textContent = 'aktualisiert ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0');

    // ── CSV Export ─────────────────────────────────────
    document.getElementById('exportCsvBtn')?.addEventListener('click', () => {
        const table = document.getElementById('rankingTable');
        if (!table) return;
        const rows  = [...table.querySelectorAll('tr')].map(tr =>
            [...tr.querySelectorAll('th,td')].map(td => '"' + td.innerText.replace(/"/g,'""') + '"').join(';')
        );
        const blob  = new Blob(['﻿' + rows.join('\\r\\n')], { type: 'text/csv;charset=utf-8' });
        const a     = document.createElement('a');
        a.href      = URL.createObjectURL(blob);
        a.download  = 'rangliste.csv';
        a.click();
    });
})();
</script>
JS;

require dirname(__DIR__, 2) . '/layout/admin.php';
