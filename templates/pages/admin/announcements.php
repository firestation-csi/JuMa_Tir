<?php ob_start();
$competitions  = $competitions  ?? [];
$announcements = $announcements ?? [];
$competitionId = $competitionId ?? 0;
$csrf          = $csrf          ?? '';

$fmtTs = fn($ts) => $ts ? date('d.m.Y H:i', strtotime($ts)) : '';
?>

<div class="adm_toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <span style="font-size:13px;color:var(--wt-text-muted);">
        Nachrichten werden allen Gruppen des Wettbewerbs angezeigt.
    </span>
</div>

<!-- Neue Ansage -->
<div class="adm_card" style="margin-bottom:20px;">
    <div class="adm_eyebrow" style="margin-bottom:14px;">Neue Ansage senden</div>
    <form method="POST" action="/admin/announcements" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <?php if (count($competitions) > 1): ?>
        <div>
            <label class="adm_label">Wettbewerb</label>
            <select name="competition_id" class="adm_input">
                <?php foreach ($competitions as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $competitionId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
            <input type="hidden" name="competition_id" value="<?= $competitionId ?>">
        <?php endif; ?>

        <div>
            <label class="adm_label">Nachricht</label>
            <textarea name="body" class="adm_textarea" rows="3" required
                      placeholder="z.B. Station 3 hat 15 Minuten Verzögerung…"></textarea>
        </div>

        <div>
            <button type="submit" class="adm_btn adm_btn--primary">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M14 8L2 2l3 6-3 6 12-6z" fill="currentColor"/></svg>
                Ansage senden
            </button>
        </div>
    </form>
</div>

<!-- Bestehende Ansagen -->
<?php if (empty($announcements)): ?>
    <div class="adm_empty">
        <div class="adm_empty__icon">📢</div>
        <p>Noch keine Ansagen vorhanden.</p>
    </div>
<?php else: ?>
    <div class="adm_card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 20px 12px;border-bottom:1px solid var(--wt-border);">
            <span class="adm_eyebrow">Gesendete Ansagen (<?= count($announcements) ?>)</span>
        </div>
        <?php foreach ($announcements as $a): ?>
        <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 20px;border-bottom:1px solid var(--wt-border);">
            <div style="flex:1;min-width:0;">
                <div style="font-size:14px;font-weight:600;color:var(--wt-text);margin-bottom:4px;">
                    <?= htmlspecialchars($a['body']) ?>
                </div>
                <div style="font-size:11.5px;color:var(--wt-text-muted);font-family:monospace;">
                    <?= $fmtTs($a['created_at']) ?>
                    <?php if (!empty($a['competition_name'])): ?>
                        · <?= htmlspecialchars($a['competition_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST" action="/admin/announcements/<?= (int)$a['id'] ?>/delete"
                  onsubmit="return confirm('Ansage löschen?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="adm_btn adm_btn--sm adm_btn--danger">Löschen</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
