<?php ob_start();
$judges      = $judges      ?? [];
$competition = $competition ?? null;

// PHP-8-sicheres Hilfsmittel: max() nur wenn Array nicht leer
$safeMax = fn(array $vals): ?string =>
    ($f = array_filter($vals)) ? max($f) : null;

$lastActivity = fn(array $j): ?string =>
    $safeMax([$j['last_score_at'] ?? null, $j['last_message_at'] ?? null]);

$fmtTs = fn(?string $ts): string =>
    $ts ? date('d.m. H:i', strtotime($ts)) : '–';

// Einheitlich mit Unix-Timestamps — vermeidet Timezone-Probleme mit DateInterval
$fmtAge = function (?string $ts): string {
    if (!$ts) return '–';
    $sec = max(0, time() - strtotime($ts));
    if ($sec >= 86400) return 'vor ' . intdiv($sec, 86400) . ' Tag(en)';
    if ($sec >= 3600)  return 'vor ' . intdiv($sec, 3600)  . ' Std.';
    if ($sec >= 60)    return 'vor ' . intdiv($sec, 60)    . ' Min.';
    return 'gerade eben';
};

$isActive = fn(?string $ts): bool =>
    $ts !== null && (time() - strtotime($ts)) < 1800;
?>

<?php if (!$competition): ?>
<div class="adm_empty">
    <div class="adm_empty__icon">⚖️</div>
    <p>Kein aktiver Wettbewerb gefunden.</p>
</div>

<?php elseif (empty($judges)): ?>
<div class="adm_empty">
    <div class="adm_empty__icon">⚖️</div>
    <p>Noch keine Schiedsrichter angemeldet.</p>
</div>

<?php else:
    // Aktivitäts-Zusammenfassung
    $staffed      = array_filter($judges, fn($j) => $j['judge_id'] !== null);
    $unstaffed    = array_filter($judges, fn($j) => $j['judge_id'] === null);
    $totalActive  = count(array_filter($staffed, fn($j) => $isActive($lastActivity($j))));
    $totalScores  = array_sum(array_column(array_values($staffed), 'score_count'));
?>

<div class="adm_toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <span style="font-size:13px;color:var(--wt-text-muted);">
            <?= htmlspecialchars($competition['name']) ?>
        </span>
        <span class="adm_badge adm_badge--<?= $totalActive > 0 ? 'active' : 'inactive' ?>">
            <?= $totalActive ?>/<?= count($staffed) ?> aktiv
        </span>
        <?php if (count($unstaffed) > 0): ?>
        <span class="adm_badge" style="background:var(--wt-red-soft);color:var(--wt-red);">
            <?= count($unstaffed) ?> unbesetzt
        </span>
        <?php endif; ?>
        <span style="font-size:13px;color:var(--wt-text-muted);">
            <?= $totalScores ?> Bewertungen gesamt
        </span>
    </div>
    <button class="adm_btn adm_btn--ghost adm_btn--sm" onclick="location.reload()">↺ Aktualisieren</button>
</div>

<div class="adm_card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
    <table class="adm_table">
        <thead>
            <tr>
                <th>Station</th>
                <th>Schiedsrichter</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:right;white-space:nowrap;">Bewertungen</th>
                <th style="white-space:nowrap;">Letzte Aktivität</th>
                <th style="white-space:nowrap;">Angemeldet</th>
            </tr>
        </thead>
        <tbody>
        <?php
        // Nach Station sortiert ausgeben
        $prevStation = null;
        foreach ($judges as $j):
            $noJudge     = $j['judge_id'] === null;
            $la          = $noJudge ? null : $lastActivity($j);
            $active      = $isActive($la);
            $sameStation = $j['station_code'] === $prevStation;
            $prevStation = $j['station_code'];
        ?>
        <tr <?= $noJudge ? 'style="background:var(--wt-red-soft);"' : '' ?>>
            <td>
                <?php if (!$sameStation): ?>
                    <span class="adm_mono" style="font-weight:700;"><?= htmlspecialchars($j['station_code']) ?></span><br>
                    <span class="adm_table__muted" style="font-size:.78rem;"><?= htmlspecialchars($j['station_name']) ?></span>
                <?php else: ?>
                    <span class="adm_table__muted" style="font-size:.78rem;padding-left:4px;">↳</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($noJudge): ?>
                    <span style="font-size:12px;font-weight:700;color:var(--wt-red);">— unbesetzt —</span>
                <?php else: ?>
                    <span class="adm_table__name"><?= htmlspecialchars($j['judge_name']) ?></span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;">
                <?php if (!$noJudge): ?>
                <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;
                             color:<?= $active ? 'var(--wt-ok)' : 'var(--wt-text-subtle)' ?>;">
                    <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;
                                 background:<?= $active ? 'var(--wt-ok)' : 'var(--wt-border-strong)' ?>;"></span>
                    <?= $active ? 'Aktiv' : 'Inaktiv' ?>
                </span>
                <?php endif; ?>
            </td>
            <td style="text-align:right;">
                <?php if (!$noJudge): ?>
                <span class="adm_mono" style="font-size:16px;font-weight:700;
                             color:<?= $j['score_count'] > 0 ? 'var(--wt-text)' : 'var(--wt-text-subtle)' ?>;">
                    <?= (int)$j['score_count'] ?>
                </span>
                <?php endif; ?>
            </td>
            <td class="adm_table__muted" style="font-size:.82rem;">
                <?= $fmtAge($la) ?>
                <?php if ($la): ?>
                    <br><span style="font-size:.75rem;"><?= $fmtTs($la) ?></span>
                <?php endif; ?>
            </td>
            <td class="adm_mono adm_table__muted" style="font-size:.78rem;">
                <?= $noJudge ? '–' : $fmtTs($j['logged_in_at']) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<script>setTimeout(() => location.reload(), 60_000);</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
