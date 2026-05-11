<?php
ob_start();
$appData = json_encode([
    'station'     => $station,
    'tasks'       => $tasks,
    'judge'       => $judge,
    'history'     => $history,
    'unreadCount' => $unreadCount,
    'csrf'        => $csrf,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>
<script type="application/json" id="__JUMA__"><?= $appData ?></script>
<div id="juma-app" style="position:fixed;inset:0;display:flex;flex-direction:column;background:var(--wt-bg);overflow:hidden;">
    <div id="juma-loading" style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:16px;color:var(--wt-text-subtle);">
        <div class="wt_sync-spinner"></div>
        <span style="font-size:13px;font-weight:600;">Lade…</span>
    </div>
</div>
<script src="/assets/js/station.js" type="module"></script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/judge.php';
