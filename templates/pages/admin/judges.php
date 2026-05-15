<?php ob_start();
$judges      = $judges      ?? [];
$competition = $competition ?? null;

$now = new DateTime();

// PHP-8-sicheres Hilfsmittel: max() nur wenn Array nicht leer
$safeMax = fn(array $vals): ?string =>
    ($f = array_filter($vals)) ? max($f) : null;

$lastActivity = fn(array $j): ?string =>
    $safeMax([$j['last_score_at'] ?? null, $j['last_message_at'] ?? null]);

$fmtTs = fn(?string $ts): string =>
    $ts ? date('d.m. H:i', strtotime($ts)) : '–';

$fmtAge = function (?string $ts) use ($now): string {
    if (!$ts) return '–';
    $diff = $now->diff(new DateTime($ts));
    if ($diff->days > 0) return 'vor ' . $diff->days . ' Tag(en)';
    if ($diff->h  > 0)   return 'vor ' . $diff->h  . ' Std.';
    if ($diff->i  > 0)   return 'vor ' . $diff->i  . ' Min.';
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
    $totalActive  = count(array_filter($judges, fn($j) => $isActive($lastActivity($j))));
    $totalScores  = array_sum(array_column($judges, 'score_count'));
?>

<div class="adm_toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
        <span style="font-size:13px;color:var(--wt-text-muted);">
            <?= htmlspecialchars($competition['name']) ?>
        </span>
        <span class="adm_badge adm_badge--<?= $totalActive > 0 ? 'active' : 'inactive' ?>">
            <?= $totalActive ?>/<?= count($judges) ?> aktiv
        </span>
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
            $la       = $lastActivity($j);
            $active   = $isActive($la);
            $sameStation = $j['station_code'] === $prevStation;
            $prevStation = $j['station_code'];
        ?>
        <tr>
            <td>
                <?php if (!$sameStation): ?>
                    <span class="adm_mono" style="font-weight:700;"><?= htmlspecialchars($j['station_code']) ?></span><br>
                    <span class="adm_table__muted" style="font-size:.78rem;"><?= htmlspecialchars($j['station_name']) ?></span>
                <?php else: ?>
                    <span class="adm_table__muted" style="font-size:.78rem;padding-left:4px;">↳</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="adm_table__name"><?= htmlspecialchars($j['judge_name']) ?></span>
            </td>
            <td style="text-align:center;">
                <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;
                             color:<?= $active ? 'var(--wt-ok)' : 'var(--wt-text-subtle)' ?>;">
                    <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;
                                 background:<?= $active ? 'var(--wt-ok)' : 'var(--wt-border-strong)' ?>;"></span>
                    <?= $active ? 'Aktiv' : 'Inaktiv' ?>
                </span>
            </td>
            <td style="text-align:right;">
                <span class="adm_mono" style="font-size:16px;font-weight:700;
                             color:<?= $j['score_count'] > 0 ? 'var(--wt-text)' : 'var(--wt-text-subtle)' ?>;">
                    <?= (int)$j['score_count'] ?>
                </span>
            </td>
            <td class="adm_table__muted" style="font-size:.82rem;">
                <?= $fmtAge($la) ?>
                <?php if ($la): ?>
                    <br><span style="font-size:.75rem;"><?= $fmtTs($la) ?></span>
                <?php endif; ?>
            </td>
            <td class="adm_mono adm_table__muted" style="font-size:.78rem;">
                <?= $fmtTs($j['logged_in_at']) ?>
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
