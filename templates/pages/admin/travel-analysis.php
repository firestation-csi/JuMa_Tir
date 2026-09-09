<?php
$competition  = $competition  ?? null;
$competitions = $competitions ?? [];
$laufwege     = $laufwege     ?? [];
$analysis     = $analysis     ?? [];

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
    'pending'     => '→',
    'scoring'     => '…',
    'not_started' => '○',
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
$scIcon  = fn(string $s) => $statusIcon[$s]  ?? '–';
$scColor = fn(string $s) => $statusColor[$s] ?? 'var(--wt-text-subtle)';
$scLabel = fn(string $s) => $statusLabel[$s] ?? $s;

$lwMap = array_column($laufwege, null, 'id');

// Analyse nach Laufweg gruppieren
$analysisByLw = [];
foreach ($analysis as $seg) {
    $lid = $seg['laufweg_id'] ? (int)$seg['laufweg_id'] : 0;
    $analysisByLw[$lid][] = $seg;
}
?>

<div class="adm_toolbar" style="justify-content:space-between;">
    <a href="/admin/groups" class="adm_btn adm_btn--ghost">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Gruppen
    </a>
    <a href="/admin/stations/routes" class="adm_btn adm_btn--ghost">Laufrouten verwalten</a>
</div>

<?php if (!empty($competitions)): ?>
<div style="margin-bottom:20px;">
    <?php $redirectUrl = '/admin/stations/travel-analysis'; include dirname(__DIR__, 2) . '/partials/admin/competition-selector.php'; ?>
</div>
<?php endif; ?>

<?php if (!$competition): ?>
<div class="adm_empty"><div class="adm_empty__icon">⏱</div><p>Kein Wettbewerb ausgewählt.</p></div>
<?php else: ?>

<div class="adm_card" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <div>
            <div class="adm_eyebrow" style="margin-bottom:2px;">Reisezeiten-Analyse</div>
            <div style="font-size:12px;color:var(--wt-text-subtle);">Ist- vs. Schätzzeit je Gruppe</div>
        </div>
        <div style="display:flex;gap:14px;flex-wrap:wrap;">
            <?php foreach ([
                'ok'          => 'Planmäßig',
                'warn'        => 'Verzögert',
                'lost'        => 'Verlaufen?',
                'pending'     => 'Unterwegs',
                'scoring'     => 'Bewertet',
                'not_started' => 'Ausstehend',
            ] as $s => $lbl): ?>
            <span style="display:flex;align-items:center;gap:4px;font-size:12px;">
                <b style="color:<?= $scColor($s) ?>;"><?= $scIcon($s) ?></b>
                <span style="color:var(--wt-text-muted);"><?= $lbl ?></span>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <div style="margin-top:12px;padding:10px 14px;background:var(--wt-surface-alt);border-radius:10px;font-size:12px;color:var(--wt-text-muted);line-height:1.5;">
        <strong>Reisezeit</strong> = Bewertung gespeichert (Abgang) → QR-Scan nächste Station (Ankunft)
    </div>
</div>

<?php if (empty($analysis)): ?>
<div class="adm_empty"><div class="adm_empty__icon">🗺</div><p>Noch keine Routen oder Protokolldaten.</p></div>
<?php else: ?>

<div class="adm_grid-2">
    <?php foreach (array_keys($analysisByLw) as $lid):
        $lwInfo  = $lid > 0 ? ($lwMap[$lid] ?? null) : null;
        $lwSegs  = $analysisByLw[$lid];
        $lwColor = $lwInfo ? $lwInfo['color'] : '#aaa';
        $lwName  = $lwInfo ? $lwInfo['name'] : 'Nicht zugeordnet';
    ?>
    <div class="adm_card" style="padding:0;overflow:hidden;">
        <div style="padding:10px 16px;background:<?= htmlspecialchars($lwColor) ?>11;display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;border-bottom:1px solid var(--wt-border);">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($lwColor) ?>;flex-shrink:0;"></span>
            <?= htmlspecialchars($lwName) ?>
        </div>
        <?php foreach ($lwSegs as $seg): ?>
        <div class="rte_seg">
            <div class="rte_seg__head">
                <span class="rte_seg__route">
                    <?= htmlspecialchars($seg['from_name']) ?>
                    <svg width="12" height="12" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?= htmlspecialchars($seg['to_name']) ?>
                </span>
                <span style="font-size:10px;color:var(--wt-text-muted);white-space:nowrap;flex-shrink:0;">
                    <?= $seg['est_time_min'] ? '~' . $seg['est_time_min'] . ' min' : '' ?>
                </span>
            </div>
            <?php foreach ($seg['groups'] as $g):
                $sc      = $g['status'];
                $hasTime = $g['actual_sek'] !== null && $g['actual_sek'] >= 0;
            ?>
            <div class="rte_seg__row">
                <span style="font-size:12px;font-weight:600;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">#<?= htmlspecialchars($g['group_num']) ?> <?= htmlspecialchars($g['group_name']) ?></span>

                <?php if ($hasTime): ?>
                    <span class="adm_mono" style="font-size:12px;font-weight:700;color:<?= $scColor($sc) ?>;flex-shrink:0;"><?= $fmtDur($g['actual_sek']) ?></span>
                <?php elseif ($sc === 'pending'): ?>
                    <span style="font-size:11px;color:<?= $scColor($sc) ?>;flex-shrink:0;">Abgegangen <?= $g['departed'] ? date('H:i', strtotime($g['departed'])) : '' ?></span>
                <?php elseif ($sc === 'scoring'): ?>
                    <span style="font-size:11px;color:<?= $scColor($sc) ?>;flex-shrink:0;">An Stn. <?= htmlspecialchars($seg['from_name'] ?? '') ?></span>
                <?php else: ?>
                    <span style="font-size:11px;color:<?= $scColor($sc) ?>;flex-shrink:0;">–</span>
                <?php endif; ?>

                <span style="font-size:12px;color:<?= $scColor($sc) ?>;width:16px;text-align:center;flex-shrink:0;" title="<?= htmlspecialchars($scLabel($sc)) ?>"><?= $scIcon($sc) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
