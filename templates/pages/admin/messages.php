<?php
/** @var array|null $competition @var array $stations @var array $overview @var string $csrf
 *  @var array $announcements @var array $groups */
ob_start();
$announcements ??= [];
$groups        ??= [];

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

<!-- ── Gruppenansagen ───────────────────────────────── -->
<?php if ($competition): ?>
<div class="adm_card" style="margin-top:24px;">
    <div class="adm_eyebrow" style="margin-bottom:16px;">📢 Gruppenansagen</div>

    <!-- Formular -->
    <form method="POST" action="/admin/announcements" id="announce-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="competition_id" value="<?= (int)$competition['id'] ?>">

        <div style="margin-bottom:12px;">
            <textarea name="body" rows="2" required
                      placeholder="Nachricht an Gruppen (z.B. Station 3 hat 15 min Verzögerung)…"
                      style="width:100%;padding:9px 12px;border:1px solid var(--wt-border);border-radius:var(--wt-r-sm);
                             font-family:inherit;font-size:14px;resize:vertical;background:var(--wt-surface);
                             color:var(--wt-text);box-sizing:border-box;"></textarea>
        </div>

        <!-- Zielgruppen -->
        <div style="margin-bottom:14px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;
                          cursor:pointer;margin-bottom:8px;">
                <input type="checkbox" id="announce-all" checked
                       style="width:16px;height:16px;cursor:pointer;accent-color:var(--wt-red);">
                Alle Gruppen
            </label>
            <?php if (!empty($groups)): ?>
            <div id="announce-groups" style="display:none;padding:10px 12px;background:var(--wt-surface-alt);
                 border-radius:var(--wt-r-sm);border:1px solid var(--wt-border);
                 display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px;">
                <?php foreach ($groups as $g): ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                    <input type="checkbox" name="group_ids[]" value="<?= (int)$g['id'] ?>"
                           style="width:15px;height:15px;cursor:pointer;accent-color:var(--wt-red);">
                    <span>
                        <?php if ($g['num'] ?? null): ?><span class="adm_mono" style="font-size:11px;color:var(--wt-text-muted);">#<?= htmlspecialchars($g['num']) ?></span><?php endif; ?>
                        <?= htmlspecialchars($g['name']) ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <button type="submit" class="adm_btn adm_btn--primary" style="gap:6px;">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M14 8L2 2l3 6-3 6 12-6z" fill="currentColor"/></svg>
            Ansage senden
        </button>
    </form>

    <!-- Liste gesendeter Ansagen -->
    <?php if (!empty($announcements)): ?>
    <div style="margin-top:20px;border-top:1px solid var(--wt-border);padding-top:16px;">
        <div style="font-size:12px;font-weight:700;color:var(--wt-text-muted);text-transform:uppercase;
                    letter-spacing:.06em;margin-bottom:10px;">Gesendete Ansagen</div>
        <div style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($announcements as $a):
            $targets = $a['target_group_ids'] ? json_decode($a['target_group_ids'], true) : null;
            $targetLabel = (!$targets || count($targets) === 0)
                ? '<span style="color:var(--wt-ok);font-size:11px;font-weight:600;">Alle Gruppen</span>'
                : '<span style="color:var(--wt-text-muted);font-size:11px;">' . count($targets) . ' Gruppe(n)</span>';
        ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;
                    background:var(--wt-surface-alt);border-radius:var(--wt-r-sm);
                    border:1px solid var(--wt-border);">
            <div style="flex:1;min-width:0;">
                <div style="font-size:13.5px;font-weight:600;margin-bottom:3px;">
                    <?= htmlspecialchars($a['body']) ?>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <?= $targetLabel ?>
                    <span style="font-size:11px;color:var(--wt-text-subtle);font-family:monospace;">
                        <?= $fmtTime($a['created_at']) ?>
                    </span>
                </div>
            </div>
            <form method="POST" action="/admin/announcements/<?= (int)$a['id'] ?>/delete"
                  onsubmit="return confirm('Ansage löschen?')" style="flex-shrink:0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="adm_btn adm_btn--sm adm_btn--danger">Löschen</button>
            </form>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const allCb     = document.getElementById('announce-all');
    const groupsDiv = document.getElementById('announce-groups');
    if (!allCb || !groupsDiv) return;
    allCb.addEventListener('change', () => {
        groupsDiv.style.display = allCb.checked ? 'none' : 'grid';
        groupsDiv.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.disabled = allCb.checked;
        });
    });
    // Initial state
    groupsDiv.style.display = 'none';
})();
</script>
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
