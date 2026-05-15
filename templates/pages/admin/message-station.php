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
            $msgDate  = $fmtDate($m['created_at']);
            $isHelp   = $m['sender'] === 'group';
            if ($msgDate !== $lastDate):
                $lastDate = $msgDate;
        ?>
            <div class="adm_msg-date"><?= htmlspecialchars($msgDate) ?></div>
        <?php endif; ?>

        <?php if ($isHelp): ?>
        <div style="margin:6px 0;padding:10px 14px;background:#fef2f2;border:1.5px solid #fca5a5;
                    border-radius:10px;display:flex;align-items:flex-start;gap:10px;">
            <span style="font-size:18px;flex-shrink:0;">🆘</span>
            <div style="flex:1;min-width:0;">
                <div style="font-size:11.5px;font-weight:700;color:#991b1b;margin-bottom:3px;">
                    HILFEANFRAGE · <?= htmlspecialchars($m['group_name'] ?? 'Gruppe') ?>
                </div>
                <div style="font-size:13.5px;color:#7f1d1d;">
                    <?= nl2br(htmlspecialchars($m['body'], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>
                </div>
                <div style="font-size:11px;color:#b91c1c;margin-top:4px;font-family:monospace;">
                    <?= $fmtTime($m['created_at']) ?>
                </div>
            </div>
            <form method="POST" action="/admin/messages/<?= (int)$m['id'] ?>/delete"
                  onsubmit="return confirm('Nachricht löschen?')" style="flex-shrink:0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;
                        color:#b91c1c;font-size:16px;padding:2px 4px;" title="Löschen">×</button>
            </form>
        </div>
        <?php else: ?>
        <div class="adm_msg-row adm_msg-row--<?= htmlspecialchars($m['sender']) ?>">
            <div class="adm_msg-bubble adm_msg-bubble--<?= htmlspecialchars($m['sender']) ?>"
                 style="position:relative;">
                <?= nl2br(htmlspecialchars($m['body'], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>
            </div>
            <div class="adm_msg-meta" style="display:flex;align-items:center;gap:8px;">
                <?php if ($m['sender'] === 'judge'): ?>
                    <?= htmlspecialchars($m['judge_name'] ?? 'Schiedsrichter') ?> ·
                <?php else: ?>
                    Zentrale ·
                <?php endif; ?>
                <?= $fmtTime($m['created_at']) ?>
                <form method="POST" action="/admin/messages/<?= (int)$m['id'] ?>/delete"
                      onsubmit="return confirm('Nachricht löschen?')" style="display:inline;margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" style="background:none;border:none;cursor:pointer;
                            color:var(--wt-text-subtle);font-size:14px;padding:0 2px;line-height:1;"
                            title="Löschen">×</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
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
(function () {
    const thread    = document.getElementById('msgThread');
    const stationId = <?= (int)$station['id'] ?>;
    const CSRF      = <?= json_encode($csrf) ?>;
    let lastCount   = <?= count($messages) ?>;

    // Beim Laden ans Ende scrollen
    if (thread) thread.scrollTop = thread.scrollHeight;

    function fmtTime(iso) {
        if (!iso) return '';
        const d = new Date(iso.replace(' ', 'T'));
        return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }
    function fmtDate(iso) {
        if (!iso) return '';
        const d = new Date(iso.replace(' ', 'T'));
        return String(d.getDate()).padStart(2,'0') + '.' +
               String(d.getMonth()+1).padStart(2,'0') + '.' +
               d.getFullYear();
    }
    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                              .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function renderThread(messages) {
        if (!thread) return;
        if (!messages.length) {
            thread.innerHTML = '<div style="text-align:center;padding:32px;color:var(--wt-text-subtle);font-size:.9rem;">Noch keine Nachrichten an dieser Station.</div>';
            return;
        }
        let html = '';
        let lastDate = '';
        for (const m of messages) {
            const d = fmtDate(m.created_at);
            if (d !== lastDate) {
                lastDate = d;
                html += `<div class="adm_msg-date">${esc(d)}</div>`;
            }
            const escAttr = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        const escBody = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const delBtn  = id => `<form method="POST" action="/admin/messages/${id}/delete"
            onsubmit="return confirm('Nachricht löschen?')" style="display:inline;margin:0;">
            <input type="hidden" name="csrf_token" value="${escAttr(CSRF)}">
            <button type="submit" style="background:none;border:none;cursor:pointer;
                color:var(--wt-text-subtle);font-size:14px;padding:0 2px;line-height:1;"
                title="Löschen">×</button></form>`;

        if (m.sender === 'group') {
            html += `
            <div style="margin:6px 0;padding:10px 14px;background:#fef2f2;border:1.5px solid #fca5a5;
                        border-radius:10px;display:flex;align-items:flex-start;gap:10px;">
                <span style="font-size:18px;flex-shrink:0;">🆘</span>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:11.5px;font-weight:700;color:#991b1b;margin-bottom:3px;">
                        HILFEANFRAGE · ${escBody(m.group_name || 'Gruppe')}
                    </div>
                    <div style="font-size:13.5px;color:#7f1d1d;">${escBody(m.body).replace(/\n/g,'<br>')}</div>
                    <div style="font-size:11px;color:#b91c1c;margin-top:4px;font-family:monospace;">${fmtTime(m.created_at)}</div>
                </div>
                ${delBtn(m.id)}
            </div>`;
        } else {
        const sender = m.sender === 'judge'
                ? esc(m.judge_name || 'Schiedsrichter')
                : 'Zentrale';
        html += `
            <div class="adm_msg-row adm_msg-row--${escAttr(m.sender)}">
                <div class="adm_msg-bubble adm_msg-bubble--${escAttr(m.sender)}">
                    ${escBody(m.body).replace(/\n/g,'<br>')}
                </div>
                <div class="adm_msg-meta" style="display:flex;align-items:center;gap:8px;">
                    ${sender} · ${fmtTime(m.created_at)} ${delBtn(m.id)}
                </div>
            </div>`;
        }
        }
        const wasAtBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 60;
        thread.innerHTML  = html;
        if (wasAtBottom) thread.scrollTop = thread.scrollHeight;
    }

    // Live-Polling alle 10 Sekunden — nur Thread aktualisieren, kein Seitenreload
    setInterval(async () => {
        try {
            const res  = await fetch(`/api/admin/messages/${stationId}`, { credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            if (data.messages && data.messages.length !== lastCount) {
                lastCount = data.messages.length;
                renderThread(data.messages);
            }
        } catch { /* Offline */ }
    }, 10_000);
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
