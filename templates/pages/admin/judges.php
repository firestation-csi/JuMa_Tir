<?php ob_start();
$judges      = $judges      ?? [];
$competition = $competition ?? null;

$now    = new DateTime();
$fmtTs  = fn($ts) => $ts ? date('d.m. H:i', strtotime($ts)) : '–';
$fmtAge = function (?string $ts) use ($now): string {
    if (!$ts) return '–';
    $diff = $now->diff(new DateTime($ts));
    if ($diff->days > 0)   return 'vor ' . $diff->days . ' Tag(en)';
    if ($diff->h  > 0)     return 'vor ' . $diff->h . ' Std.';
    if ($diff->i  > 0)     return 'vor ' . $diff->i . ' Min.';
    return 'gerade eben';
};
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
<?php else: ?>

<div class="adm_toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <span style="font-size:13px;color:var(--wt-text-muted);">
        <?= htmlspecialchars($competition['name']) ?> · <?= count($judges) ?> Schiedsrichter
    </span>
    <button class="adm_btn adm_btn--ghost adm_btn--sm" onclick="location.reload()">↺ Aktualisieren</button>
</div>

<?php
// Nach Station gruppieren
$byStation = [];
foreach ($judges as $j) {
    $byStation[$j['station_code']][] = $j;
}
ksort($byStation);
?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
<?php foreach ($byStation as $code => $stationJudges): ?>
<?php $s = $stationJudges[0]; ?>
<div class="adm_card" style="padding:0;overflow:hidden;">

    <!-- Station-Header -->
    <div style="padding:12px 16px;background:var(--wt-surface-alt);border-bottom:1px solid var(--wt-border);
                display:flex;align-items:center;gap:10px;">
        <span class="adm_mono" style="font-size:15px;font-weight:800;"><?= htmlspecialchars($code) ?></span>
        <span style="font-size:13px;font-weight:600;flex:1;"><?= htmlspecialchars($s['station_name']) ?></span>
        <span style="font-size:11px;color:var(--wt-text-subtle);"><?= count($stationJudges) ?> SR</span>
    </div>

    <!-- Schiedsrichter-Zeilen -->
    <?php foreach ($stationJudges as $j):
        $lastActive = max(array_filter([$j['last_score_at'], $j['last_message_at']])) ?: null;
        $isActive   = $lastActive && (time() - strtotime($lastActive)) < 1800; // aktiv = < 30 min
    ?>
    <div style="padding:12px 16px;border-bottom:1px solid var(--wt-border);display:flex;align-items:center;gap:12px;">

        <!-- Status-Dot -->
        <span style="width:9px;height:9px;border-radius:50%;flex-shrink:0;
                     background:<?= $isActive ? 'var(--wt-ok)' : 'var(--wt-border-strong)' ?>;"
              title="<?= $isActive ? 'Aktiv (< 30 min)' : 'Inaktiv' ?>"></span>

        <!-- Name + Anmeldung -->
        <div style="flex:1;min-width:0;">
            <div style="font-size:14px;font-weight:700;"><?= htmlspecialchars($j['judge_name']) ?></div>
            <div style="font-size:11.5px;color:var(--wt-text-subtle);">
                Angemeldet: <?= $fmtTs($j['logged_in_at']) ?>
            </div>
        </div>

        <!-- Stats -->
        <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:18px;font-weight:800;font-family:monospace;
                        color:<?= $j['score_count'] > 0 ? 'var(--wt-text)' : 'var(--wt-text-subtle)' ?>;">
                <?= (int)$j['score_count'] ?>
            </div>
            <div style="font-size:10px;color:var(--wt-text-subtle);">Bewertungen</div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Station-Summe -->
    <div style="padding:10px 16px;background:var(--wt-surface-alt);
                display:flex;justify-content:space-between;font-size:12px;color:var(--wt-text-muted);">
        <span>Letzte Aktivität:
            <?= $fmtAge(max(array_filter(array_map(
                fn($j) => max(array_filter([$j['last_score_at'], $j['last_message_at']])) ?: null,
                $stationJudges
            )))) ?>
        </span>
        <span class="adm_mono" style="font-weight:700;">
            <?= array_sum(array_column($stationJudges, 'score_count')) ?> total
        </span>
    </div>

</div>
<?php endforeach; ?>
</div>

<script>
// Auto-Reload alle 60 Sekunden
setTimeout(() => location.reload(), 60_000);
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
