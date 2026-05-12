<?php
$competition  = $competition  ?? null;
$competitions = $competitions ?? [];
$stations     = $stations     ?? [];
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

$statusIcon  = ['ok' => '✓', 'warn' => '⚠', 'lost' => '✗', 'no_data' => '–'];
$statusColor = [
    'ok'      => 'var(--wt-ok)',
    'warn'    => 'var(--wt-warn)',
    'lost'    => 'var(--wt-red)',
    'no_data' => 'var(--wt-text-subtle)',
];
$statusLabel = ['ok' => 'Planmäßig', 'warn' => 'Verzögert', 'lost' => 'Verlaufen?', 'no_data' => 'Keine Daten'];
?>

<!-- Toolbar -->
<div class="adm_toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <a href="/admin/stations" class="adm_btn adm_btn--ghost">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Stationen
    </a>
</div>

<!-- Wettbewerb-Selector -->
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
  <!-- ═══ LINKE SPALTE: Verwaltung ══════════════════════ -->
  <div class="rte_main">

    <!-- ── Visuelle Routenkette ─────────────────────────── -->
    <?php if (!empty($routes)): ?>
    <div class="adm_card">
        <div class="adm_eyebrow" style="margin-bottom:16px;">Geplante Laufroute</div>
        <div class="rte_chain">
            <?php foreach ($routes as $i => $r): ?>
            <?php if ($i === 0): ?>
            <div class="rte_chain__node rte_chain__node--start">
                <span class="rte_chain__code"><?= htmlspecialchars($r['from_code']) ?></span>
                <span class="rte_chain__name"><?= htmlspecialchars($r['from_name']) ?></span>
            </div>
            <?php endif; ?>
            <div class="rte_chain__edge">
                <div class="rte_chain__line"></div>
                <div class="rte_chain__info">
                    <?php if ($r['distance_m']): ?>
                        <span><?= number_format((int)$r['distance_m']) ?> m</span>
                    <?php endif; ?>
                    <?php if ($r['est_time_min']): ?>
                        <span>~<?= (int)$r['est_time_min'] ?> min</span>
                    <?php endif; ?>
                    <?php if ($r['notes']): ?>
                        <span style="font-style:italic;color:var(--wt-text-subtle);"><?= htmlspecialchars($r['notes']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="rte_chain__node">
                <span class="rte_chain__code"><?= htmlspecialchars($r['to_code']) ?></span>
                <span class="rte_chain__name"><?= htmlspecialchars($r['to_name']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Abschnitte verwalten ─────────────────────────── -->
    <div class="adm_card">
        <div class="adm_eyebrow" style="margin-bottom:16px;">Abschnitte</div>
        <?php if (empty($routes)): ?>
            <div class="adm_table__muted" style="margin-bottom:16px;font-size:13px;">
                Noch keine Abschnitte definiert. Füge unten den ersten hinzu.
            </div>
        <?php else: ?>
        <table class="adm_table" style="margin-bottom:16px;">
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
            <?php foreach ($routes as $r): ?>
            <tr>
                <td class="adm_mono adm_table__muted"><?= (int)$r['sort_order'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($r['from_code']) ?> · <?= htmlspecialchars($r['from_name']) ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($r['to_code']) ?> · <?= htmlspecialchars($r['to_name']) ?></td>
                <td style="text-align:right;" class="adm_mono">
                    <?= $r['distance_m'] ? number_format((int)$r['distance_m']) . ' m' : '–' ?>
                </td>
                <td style="text-align:right;" class="adm_mono">
                    <?= $r['est_time_min'] ? (int)$r['est_time_min'] . ' min' : '–' ?>
                </td>
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
        <?php endif; ?>

        <!-- Neuen Abschnitt hinzufügen -->
        <div style="border-top:1px solid var(--wt-border);padding-top:16px;">
            <div class="adm_eyebrow" style="margin-bottom:12px;">Abschnitt hinzufügen</div>
            <form method="POST" action="/admin/stations/routes" class="adm_form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="adm_field-row">
                    <div class="adm_field">
                        <label class="adm_label" for="from_station_id">Von Station *</label>
                        <select class="adm_input" id="from_station_id" name="from_station_id" required>
                            <option value="">– wählen –</option>
                            <?php foreach ($stations as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['code']) ?> · <?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="adm_field">
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
                        <label class="adm_label" for="distance_m">Distanz (Meter)</label>
                        <input class="adm_input adm_input--mono" type="number" id="distance_m" name="distance_m" min="0" max="99999" placeholder="z.B. 200">
                    </div>
                    <div class="adm_field">
                        <label class="adm_label" for="est_time_min">Schätzzeit (Minuten)</label>
                        <input class="adm_input adm_input--mono" type="number" id="est_time_min" name="est_time_min" min="1" max="240" placeholder="z.B. 5">
                    </div>
                    <div class="adm_field">
                        <label class="adm_label" for="sort_order">Reihenfolge</label>
                        <input class="adm_input adm_input--mono" type="number" id="sort_order" name="sort_order" value="<?= count($routes) * 10 ?>" min="0" max="255">
                    </div>
                </div>
                <div class="adm_field">
                    <label class="adm_label" for="notes">Notiz / Wegbeschreibung</label>
                    <input class="adm_input" type="text" id="notes" name="notes" placeholder="z.B. Linker Feldweg, dann über Brücke">
                </div>
                <div class="adm_form-actions" style="justify-content:flex-start;">
                    <button type="submit" class="adm_btn adm_btn--primary">Abschnitt speichern</button>
                </div>
            </form>
            <?php include dirname(__DIR__, 2) . '/partials/admin/osrm-route-calc.php'; ?>
        </div>
    </div>

  </div><!-- /rte_main -->

  <!-- ═══ ANALYSE ══════════════════════════════════════ -->
  <div class="rte_analysis">
    <div class="adm_card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 18px 12px;border-bottom:1px solid var(--wt-border);">
            <span style="font-weight:700;font-size:14px;">Reisezeiten-Analyse</span>
            <div style="font-size:11px;color:var(--wt-text-subtle);margin-top:2px;">
                Tatsächliche Laufzeit zwischen Stationen vs. Schätzung
            </div>
        </div>

        <!-- Legende -->
        <div style="padding:10px 16px;border-bottom:1px solid var(--wt-border);display:flex;gap:14px;flex-wrap:wrap;">
            <?php foreach (['ok' => 'Planmäßig (≤ 1,5×)', 'warn' => 'Verzögert (≤ 3×)', 'lost' => 'Verlaufen? (> 3×)'] as $s => $label): ?>
            <span style="display:flex;align-items:center;gap:5px;font-size:11px;">
                <span style="color:<?= $statusColor[$s] ?>;font-weight:700;"><?= $statusIcon[$s] ?></span>
                <span style="color:var(--wt-text-muted);"><?= $label ?></span>
            </span>
            <?php endforeach; ?>
        </div>

        <?php if (empty($analysis)): ?>
        <div style="padding:32px 18px;text-align:center;color:var(--wt-text-subtle);font-size:13px;">
            Noch keine Routen definiert oder keine Laufprotokoll-Daten vorhanden.
        </div>
        <?php else: ?>
        <?php foreach ($analysis as $seg): ?>
        <div class="rte_seg">
            <div class="rte_seg__head">
                <span class="rte_seg__route">
                    <?= htmlspecialchars($seg['from_code']) ?>
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;vertical-align:middle;"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?= htmlspecialchars($seg['to_code']) ?>
                </span>
                <span style="font-size:11px;color:var(--wt-text-muted);">
                    <?php if ($seg['distance_m']): ?>
                        <?= number_format($seg['distance_m']) ?> m ·
                    <?php endif; ?>
                    <?php if ($seg['est_time_min']): ?>
                        ~<?= $seg['est_time_min'] ?> min
                    <?php endif; ?>
                </span>
            </div>
            <?php
            $hasAny = array_filter($seg['groups'], fn($g) => $g['actual_sek'] !== null && $g['actual_sek'] > 0);
            if (empty($hasAny)): ?>
            <div style="padding:8px 16px;font-size:12px;color:var(--wt-text-subtle);">Noch keine Laufprotokoll-Daten</div>
            <?php else: ?>
            <?php foreach ($seg['groups'] as $g):
                if ($g['actual_sek'] === null || $g['actual_sek'] <= 0) continue;
            ?>
            <div class="rte_seg__row">
                <span style="font-size:12px;font-weight:600;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    #<?= htmlspecialchars($g['group_num']) ?> <?= htmlspecialchars($g['group_name']) ?>
                </span>
                <span class="adm_mono" style="font-size:12px;font-weight:700;color:<?= $statusColor[$g['status']] ?>;flex-shrink:0;">
                    <?= $fmtDur($g['actual_sek']) ?>
                </span>
                <span style="font-size:13px;color:<?= $statusColor[$g['status']] ?>;flex-shrink:0;width:16px;text-align:center;" title="<?= $statusLabel[$g['status']] ?>">
                    <?= $statusIcon[$g['status']] ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
  </div><!-- /rte_analysis -->

</div><!-- /rte_layout -->
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
