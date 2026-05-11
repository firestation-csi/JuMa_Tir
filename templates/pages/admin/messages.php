<?php
/** @var array|null $competition @var array $stations @var array $overview @var string $csrf */
ob_start();

$totalUnread = array_sum(array_column(array_column($overview, 'unread_judge'), null));
$totalUnread = 0;
foreach ($overview as $o) $totalUnread += $o['unread_judge'];

$fmtTime = function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('d.m.Y H:i', $ts) : '';
};
?>

<div class="adm_toolbar">
    <?php if (!empty($competition)): ?>
        <span class="adm_meta__value"><?= htmlspecialchars($competition['name']) ?></span>
    <?php endif; ?>
    <?php if ($totalUnread > 0): ?>
        <span class="adm_badge adm_badge--active" style="margin-left:auto;">
            <?= $totalUnread ?> neue Nachricht<?= $totalUnread > 1 ? 'en' : '' ?>
        </span>
    <?php endif; ?>
</div>

<?php if (empty($stations)): ?>
    <div class="adm_empty">
        <div class="adm_empty__icon">💬</div>
        <p>Kein aktiver Wettbewerb oder keine Stationen vorhanden.</p>
    </div>
<?php else: ?>
    <div class="adm_card">
        <table class="adm_table">
            <thead>
                <tr>
                    <th style="width:5rem">Station</th>
                    <th>Letzte Nachricht</th>
                    <th style="width:7rem">Uhrzeit</th>
                    <th style="width:6rem">Ungelesen</th>
                    <th style="width:6rem"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stations as $s):
                    $sid  = $s['id'];
                    $stat = $overview[$sid] ?? ['last_message' => null, 'unread_judge' => 0];
                    $last = $stat['last_message'];
                    $unr  = (int)$stat['unread_judge'];
                ?>
                <tr>
                    <td>
                        <span class="adm_mono" style="font-weight:700;"><?= htmlspecialchars($s['code']) ?></span><br>
                        <span class="adm_table__muted" style="font-size:.78rem;"><?= htmlspecialchars($s['name']) ?></span>
                    </td>
                    <td style="max-width:320px;">
                        <?php if ($last): ?>
                            <span style="font-size:.8rem;color:var(--wt-text-subtle);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">
                                <?= $last['sender'] === 'judge' ? htmlspecialchars($last['judge_name'] ?? 'Schiedsrichter') : 'Zentrale' ?>
                            </span><br>
                            <span style="font-size:.9rem;" class="adm_table__name">
                                <?= htmlspecialchars(mb_substr($last['body'], 0, 80)) ?><?= mb_strlen($last['body']) > 80 ? '…' : '' ?>
                            </span>
                        <?php else: ?>
                            <span class="adm_table__muted">Noch keine Nachrichten</span>
                        <?php endif; ?>
                    </td>
                    <td class="adm_mono adm_table__muted" style="font-size:.78rem;">
                        <?= $last ? $fmtTime($last['created_at']) : '–' ?>
                    </td>
                    <td>
                        <?php if ($unr > 0): ?>
                            <span class="adm_badge adm_badge--active"><?= $unr ?> neu</span>
                        <?php else: ?>
                            <span class="adm_table__muted">–</span>
                        <?php endif; ?>
                    </td>
                    <td class="adm_table__actions">
                        <a href="/admin/messages/<?= (int)$sid ?>"
                           class="adm_btn adm_btn--sm <?= $unr > 0 ? 'adm_btn--primary' : 'adm_btn--ghost' ?>">
                            <?= $unr > 0 ? 'Antworten' : 'Öffnen' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
// Übersicht alle 15 Sekunden aktualisieren wenn ungelesene Nachrichten vorhanden
(function () {
    let hasUnread = <?= $totalUnread > 0 ? 'true' : 'false' ?>;
    setInterval(async () => {
        try {
            const res  = await fetch('/api/admin/message-count', { credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            if ((data.unread > 0) !== hasUnread) {
                location.reload();
            }
        } catch { /* Offline */ }
    }, 15_000);
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
