<?php
/** @var array $station @var array $messages @var string $csrf */
ob_start();

$fmtTime = function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('H:i', $ts) : '';
};
$fmtDate = function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('d.m.Y', $ts) : '';
};
?>

<div class="adm_toolbar">
    <a href="/admin/messages" class="adm_btn adm_btn--ghost">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
            <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Alle Nachrichten
    </a>
</div>

<div class="adm_card adm_card--meta">
    <span class="adm_meta__label">Station</span>
    <span class="adm_meta__value adm_mono"><?= htmlspecialchars($station['code']) ?></span>
    <span class="adm_meta__sep">·</span>
    <span class="adm_meta__value"><?= htmlspecialchars($station['name']) ?></span>
</div>

<!-- Chat-Verlauf -->
<div class="adm_card adm_msg-thread" id="msgThread">
    <?php if (empty($messages)): ?>
        <div style="text-align:center;padding:32px;color:var(--wt-text-subtle);font-size:.9rem;">
            Noch keine Nachrichten an dieser Station.
        </div>
    <?php else:
        $lastDate = '';
        foreach ($messages as $m):
            $msgDate = $fmtDate($m['created_at']);
            if ($msgDate !== $lastDate):
                $lastDate = $msgDate;
        ?>
            <div class="adm_msg-date"><?= htmlspecialchars($msgDate) ?></div>
        <?php endif; ?>
        <div class="adm_msg-row adm_msg-row--<?= $m['sender'] ?>">
            <div class="adm_msg-bubble adm_msg-bubble--<?= $m['sender'] ?>">
                <?= nl2br(htmlspecialchars($m['body'])) ?>
            </div>
            <div class="adm_msg-meta">
                <?php if ($m['sender'] === 'judge'): ?>
                    <?= htmlspecialchars($m['judge_name'] ?? 'Schiedsrichter') ?> ·
                <?php else: ?>
                    Zentrale ·
                <?php endif; ?>
                <?= $fmtTime($m['created_at']) ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Nachricht senden -->
<div class="adm_card" style="margin-top:0;border-top:2px solid var(--wt-border);">
    <form method="POST" action="/admin/messages/<?= (int)$station['id'] ?>/send" class="adm_msg-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <textarea
            name="body"
            id="msgBody"
            class="adm_input adm_msg-textarea"
            rows="3"
            placeholder="Nachricht an Station <?= htmlspecialchars($station['code']) ?> …"
            required
            autofocus
        ></textarea>
        <div class="adm_msg-form-actions">
            <span class="adm_hint" style="margin:0;">
                Schiedsrichter erhält die Nachricht beim nächsten Abruf (max. 20 Sekunden).
            </span>
            <button type="submit" class="adm_btn adm_btn--primary">
                <svg width="16" height="16" viewBox="0 0 18 18" fill="none">
                    <path d="M15 9L3 3l3 6-3 6 12-6z" fill="currentColor"/>
                </svg>
                Senden
            </button>
        </div>
    </form>
</div>

<script>
// Nachrichten ans Ende scrollen
(function () {
    const thread = document.getElementById('msgThread');
    if (thread) thread.scrollTop = thread.scrollHeight;
})();

// Live-Polling: neue Nachrichten alle 10 Sekunden laden
(function () {
    const stationId = <?= (int)$station['id'] ?>;
    let lastCount   = <?= count($messages) ?>;

    setInterval(async () => {
        try {
            const res  = await fetch(`/api/admin/messages/${stationId}`, { credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            if (data.messages && data.messages.length !== lastCount) {
                lastCount = data.messages.length;
                // Seite neu laden um neue Nachrichten anzuzeigen
                location.reload();
            }
        } catch { /* Offline – ignorieren */ }
    }, 10_000);
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
