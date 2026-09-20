<?php
$report = $report ?? null;
ob_start();
?>

<?php if (!$report): ?>
<div class="adm_empty">
    <div class="adm_empty__icon">📋</div>
    <p>Gruppe nicht gefunden.</p>
</div>
<?php else: ?>

<div class="adm_toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <a href="/admin/groups" class="adm_btn adm_btn--ghost adm_btn--sm">← Zurück zu Gruppen</a>
    <button class="adm_btn adm_btn--primary adm_btn--sm" onclick="window.print()">⎙ Als PDF drucken</button>
</div>

<div class="adm_card">
    <?php $r = $report; include dirname(__DIR__, 2) . '/partials/admin/group-report-body.php'; ?>
</div>

<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
