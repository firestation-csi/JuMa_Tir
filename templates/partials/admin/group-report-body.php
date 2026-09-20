<?php
/**
 * Partial: Auswertung einer Gruppe (Stationen + Eindrücke).
 * Erwartet $r = ['group','scores','total_fp','avg_impression','stations_completed','stations_total'].
 */
$group  = $r['group'];
$scores = $r['scores'];

$impLabel = ['sehr_gut' => 'Sehr gut', 'gut' => 'Gut', 'befriedigend' => 'Befriedigend'];
$impColor = ['sehr_gut' => 'var(--wt-ok)', 'gut' => 'var(--wt-text-muted)', 'befriedigend' => 'var(--wt-warn)'];

$fmtTime = function (?int $ms): string {
    if ($ms === null) return '–';
    $sek = intdiv($ms, 1000);
    return sprintf('%d:%02d', intdiv($sek, 60), $sek % 60);
};
?>
<div class="grp_report">
    <div class="grp_report__head">
        <div>
            <div class="grp_report__title">
                #<?= htmlspecialchars((string)($group['num'] ?? '–')) ?> <?= htmlspecialchars($group['name']) ?>
            </div>
            <div class="grp_report__sub">
                <?php if ($group['feuerwehr_name'] ?? null): ?>
                    <?= htmlspecialchars($group['feuerwehr_name']) ?><?php if ($group['feuerwehr_bereich'] ?? null): ?> · <?= htmlspecialchars($group['feuerwehr_bereich']) ?><?php endif; ?>
                <?php elseif ($group['kreis'] ?? null): ?>
                    <?= htmlspecialchars($group['kreis']) ?>
                <?php endif; ?>
                <?php if ($group['altersgruppe'] ?? null): ?> · <?= htmlspecialchars($group['altersgruppe']) ?><?php endif; ?>
            </div>
        </div>
        <div class="grp_report__comp">
            <?= htmlspecialchars($group['competition_name'] ?? '–') ?>
            <?php if ($group['competition_date'] ?? null): ?><br><?= date('d.m.Y', strtotime($group['competition_date'])) ?><?php endif; ?>
        </div>
    </div>

    <div class="grp_report__summary">
        <div class="grp_report__stat">
            <span class="grp_report__stat-label">Stationen</span>
            <span class="grp_report__stat-value"><?= (int)$r['stations_completed'] ?>/<?= (int)$r['stations_total'] ?></span>
        </div>
        <div class="grp_report__stat">
            <span class="grp_report__stat-label">Gesamt-FP</span>
            <span class="grp_report__stat-value"><?= (int)$r['total_fp'] ?></span>
        </div>
        <div class="grp_report__stat">
            <span class="grp_report__stat-label">Ø Eindruck</span>
            <span class="grp_report__stat-value">
                <?= $r['avg_impression'] !== null ? number_format((float)$r['avg_impression'], 2, ',', '') : '–' ?>
            </span>
        </div>
    </div>

    <?php if (empty($scores)): ?>
        <div class="adm_table__muted" style="padding:16px 0;">Noch keine Bewertungen vorhanden.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="adm_table grp_report__table">
        <thead>
            <tr>
                <th style="width:3rem;">Stat.</th>
                <th>Aufgabe</th>
                <th style="text-align:center;">Eindruck</th>
                <th style="text-align:right;">FP</th>
                <th class="adm_col--hide-sm" style="text-align:right;">Messzeit</th>
                <th class="adm_col--hide-sm">Schiedsrichter</th>
                <th>Bemerkung</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($scores as $sc): ?>
            <tr>
                <td class="adm_mono" style="font-weight:700;"><?= htmlspecialchars($sc['station_code']) ?></td>
                <td><?= htmlspecialchars($sc['station_name']) ?></td>
                <td style="text-align:center;">
                    <span style="font-size:11px;font-weight:600;color:<?= $impColor[$sc['impression']] ?? 'var(--wt-text-muted)' ?>;">
                        <?= $impLabel[$sc['impression']] ?? '–' ?>
                    </span>
                </td>
                <td style="text-align:right;" class="adm_mono"><?= (int)$sc['total_fp'] ?></td>
                <td class="adm_col--hide-sm adm_mono" style="text-align:right;">
                    <?= $fmtTime($sc['time_ms'] !== null ? (int)$sc['time_ms'] : null) ?>
                </td>
                <td class="adm_col--hide-sm adm_table__muted"><?= htmlspecialchars($sc['judge_name'] ?? '–') ?></td>
                <td style="font-size:12px;color:var(--wt-text-muted);">
                    <?= $sc['notes'] ? nl2br(htmlspecialchars($sc['notes'])) : '–' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
