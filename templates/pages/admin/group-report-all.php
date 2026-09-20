<?php
$reports    = $reports    ?? [];
$activeComp = $activeComp ?? null;
ob_start();
?>

<div class="adm_toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <a href="/admin/groups" class="adm_btn adm_btn--ghost adm_btn--sm">← Zurück zu Gruppen</a>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <?php if ($activeComp): ?>
            <span style="font-size:13px;color:var(--wt-text-muted);"><?= htmlspecialchars($activeComp['name']) ?></span>
        <?php endif; ?>
        <button class="adm_btn adm_btn--primary adm_btn--sm" onclick="window.print()">⎙ Alle Gruppen als PDF</button>
    </div>
</div>

<?php if (empty($reports)): ?>
    <div class="adm_empty">
        <div class="adm_empty__icon">👥</div>
        <p>Keine aktiven Gruppen im aktuellen Wettbewerb gefunden.</p>
    </div>
<?php else: ?>
    <?php foreach ($reports as $r): ?>
    <div class="adm_card grp_report-page">
        <?php include dirname(__DIR__, 2) . '/partials/admin/group-report-body.php'; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
