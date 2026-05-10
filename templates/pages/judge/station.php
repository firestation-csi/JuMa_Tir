<?php
ob_start();
$appData = json_encode([
    'station' => $station,
    'tasks'   => $tasks,
    'judge'   => $judge,
    'history' => $history,
    'csrf'    => $csrf,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>
<script type="application/json" id="__JUMA__"><?= $appData ?></script>
<div id="juma-app" style="position:relative; min-height:100vh;"></div>
<script src="/assets/js/station.js" type="module"></script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/judge.php';
