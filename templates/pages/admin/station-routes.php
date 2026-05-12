<?php
$competition  = $competition  ?? null;
$competitions = $competitions ?? [];
$stations     = $stations     ?? [];
$laufwege     = $laufwege     ?? [];
$routes       = $routes       ?? [];
$analysis     = $analysis     ?? [];
$csrf         = $csrf         ?? '';
$error        = $_GET['error'] ?? null;

ob_start();

$fmtDur = function (?int $sek): string {
    if ($sek === null || $sek <= 0) return '–';
    if ($sek < 60) return $sek . 's';
    return sprintf('%d:%02d min', intdiv($sek, 60), $sek % 60);
};
$statusIcon  = [
    'ok'          => '✓',
    'warn'        => '⚠',
    'lost'        => '✗',
    'pending'     => '→',   // Bewertet an A, noch nicht bei B angekommen
    'scoring'     => '…',   // An A, noch nicht bewertet
    'not_started' => '○',   // Noch nicht an Startstation
    'no_data'     => '–',
];
$statusColor = [
    'ok'          => 'var(--wt-ok)',
    'warn'        => 'var(--wt-warn)',
    'lost'        => 'var(--wt-red)',
    'pending'     => '#2980B9',
    'scoring'     => 'var(--wt-text-muted)',
    'not_started' => 'var(--wt-text-subtle)',
    'no_data'     => 'var(--wt-text-subtle)',
];
$statusLabel = [
    'ok'          => 'Planmäßig',
    'warn'        => 'Verzögert',
    'lost'        => 'Verlaufen?',
    'pending'     => 'Unterwegs (bewertet, noch nicht angekommen)',
    'scoring'     => 'An Station, noch nicht bewertet',
    'not_started' => 'Noch nicht an Startstation',
    'no_data'     => 'Keine Daten',
];

// Abschnitte nach Laufweg gruppieren
$routesByLaufweg = [];   // laufweg_id (or 0=unzugeordnet) → [routes]
foreach ($routes as $r) {
    $lid = $r['laufweg_id'] ? (int)$r['laufweg_id'] : 0;
    $routesByLaufweg[$lid][] = $r;
}

// Analyse nach Laufweg gruppieren
$analysisByLaufweg = [];
foreach ($analysis as $seg) {
    $lid = $seg['laufweg_id'] ? (int)$seg['laufweg_id'] : 0;
    $analysisByLaufweg[$lid][] = $seg;
}

// Bekannte Farben für Laufweg-Picker
$presetColors = ['#C0392B','#2980B9','#27AE60','#E67E22','#8E44AD','#16A085','#2C3E50','#F39C12'];
?>

<div class="adm_toolbar" style="justify-content:space-between;">
    <a href="/admin/stations" class="adm_btn adm_btn--ghost">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Stationen
    </a>
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

<div class="rte_layout">
  <!-- ═══ LINKE SPALTE ═══════════════════════════════════ -->
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
    $lwMap = array_column($laufwege, null, 'id');
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
            <span style="font-size:12px;color:var(--wt-text-muted);margin-left:auto;"><?= count($grpRoutes) ?> Abschnitte</span>
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

  <!-- ═══ ANALYSE ══════════════════════════════════════ -->
  <div class="rte_analysis">
    <div class="adm_card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 18px 12px;border-bottom:1px solid var(--wt-border);">
            <div style="font-weight:700;font-size:14px;">Reisezeiten-Analyse</div>
            <div style="font-size:11px;color:var(--wt-text-subtle);margin-top:2px;">Ist- vs. Schätzzeit je Gruppe</div>
        </div>
        <div style="padding:8px 14px;border-bottom:1px solid var(--wt-border);display:flex;gap:10px;flex-wrap:wrap;">
            <?php foreach ([
                'ok'          => 'Planmäßig',
                'warn'        => 'Verzögert',
                'lost'        => 'Verlaufen?',
                'pending'     => 'Unterwegs',
                'scoring'     => 'Bewertet',
                'not_started' => 'Ausstehend',
            ] as $s => $lbl): ?>
            <span style="display:flex;align-items:center;gap:3px;font-size:11px;">
                <b style="color:<?= $statusColor[$s] ?>;"><?= $statusIcon[$s] ?></b>
                <span style="color:var(--wt-text-muted);"><?= $lbl ?></span>
            </span>
            <?php endforeach; ?>
        </div>

        <!-- Erklärung wann Daten erscheinen -->
        <div style="padding:8px 14px;border-bottom:1px solid var(--wt-border);background:var(--wt-surface-alt);font-size:11px;color:var(--wt-text-muted);line-height:1.5;">
            <strong>Reisezeit</strong> = Bewertung gespeichert (Abgang) → QR-Scan nächste Station (Ankunft)
        </div>

        <?php if (empty($analysis)): ?>
        <div style="padding:28px;text-align:center;color:var(--wt-text-subtle);font-size:13px;">
            Noch keine Routen oder Protokolldaten.
        </div>
        <?php else: ?>

        <?php
        // Analyse nach Laufweg gruppiert anzeigen
        $analysisByLw = [];
        foreach ($analysis as $seg) {
            $lid = $seg['laufweg_id'] ? (int)$seg['laufweg_id'] : 0;
            $analysisByLw[$lid][] = $seg;
        }
        foreach (array_keys($analysisByLw) as $lid):
            $lwInfo   = $lid > 0 ? ($lwMap[$lid] ?? null) : null;
            $lwSegs   = $analysisByLw[$lid];
            $lwColor  = $lwInfo ? $lwInfo['color'] : '#aaa';
            $lwName   = $lwInfo ? $lwInfo['name'] : 'Nicht zugeordnet';
        ?>
        <div style="border-bottom:1px solid var(--wt-border);">
            <div style="padding:8px 14px;background:<?= htmlspecialchars($lwColor) ?>11;display:flex;align-items:center;gap:7px;font-size:12px;font-weight:700;">
                <span style="width:9px;height:9px;border-radius:50%;background:<?= htmlspecialchars($lwColor) ?>;flex-shrink:0;"></span>
                <?= htmlspecialchars($lwName) ?>
            </div>
            <?php foreach ($lwSegs as $seg):
                $hasData = array_filter($seg['groups'], fn($g) => ($g['actual_sek'] ?? 0) > 0);
            ?>
            <div class="rte_seg">
                <div class="rte_seg__head">
                    <span class="rte_seg__route">
                        <?= htmlspecialchars($seg['from_code']) ?>
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?= htmlspecialchars($seg['to_code']) ?>
                    </span>
                    <span style="font-size:10px;color:var(--wt-text-muted);">
                        <?= $seg['est_time_min'] ? '~' . $seg['est_time_min'] . ' min' : '' ?>
                    </span>
                </div>
                <?php foreach ($seg['groups'] as $g):
                    $sc = $g['status'];
                    $hasTime = $g['actual_sek'] !== null && $g['actual_sek'] >= 0;
                ?>
                <div class="rte_seg__row">
                    <span style="font-size:12px;font-weight:600;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">#<?= htmlspecialchars($g['group_num']) ?> <?= htmlspecialchars($g['group_name']) ?></span>

                    <?php if ($hasTime): ?>
                        <span class="adm_mono" style="font-size:12px;font-weight:700;color:<?= $statusColor[$sc] ?>;flex-shrink:0;"><?= $fmtDur($g['actual_sek']) ?></span>
                    <?php elseif ($sc === 'pending'): ?>
                        <span style="font-size:11px;color:<?= $statusColor[$sc] ?>;flex-shrink:0;">Abgegangen <?= $g['departed'] ? date('H:i', strtotime($g['departed'])) : '' ?></span>
                    <?php elseif ($sc === 'scoring'): ?>
                        <span style="font-size:11px;color:<?= $statusColor[$sc] ?>;flex-shrink:0;">An Stn. <?= htmlspecialchars($seg['from_code']) ?></span>
                    <?php else: ?>
                        <span style="font-size:11px;color:<?= $statusColor[$sc] ?>;flex-shrink:0;">–</span>
                    <?php endif; ?>

                    <span style="font-size:12px;color:<?= $statusColor[$sc] ?>;width:16px;text-align:center;flex-shrink:0;" title="<?= htmlspecialchars($statusLabel[$sc]) ?>"><?= $statusIcon[$sc] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
  </div>

</div><!-- /rte_layout -->

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
</script>

<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
