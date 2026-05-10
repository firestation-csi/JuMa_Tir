<?php
ob_start();
?>
<div class="adm_toolbar">
    <a href="/admin/competitions/new" class="adm_btn adm_btn--primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Neuer Wettbewerb
    </a>
</div>

<?php if (empty($competitions)): ?>
    <div class="adm_empty">
        <div class="adm_empty__icon">🏆</div>
        <p>Noch kein Wettbewerb angelegt.</p>
        <a href="/admin/competitions/new" class="adm_btn adm_btn--primary">Jetzt anlegen</a>
    </div>
<?php else: ?>
    <div class="adm_card">
        <table class="adm_table">
            <thead>
                <tr>
                    <th>Beschreibung</th>
                    <th>Ort</th>
                    <th>Datum</th>
                    <th>Status</th>
                    <th>Hash</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($competitions as $c): ?>
                    <tr>
                        <td>
                            <span class="adm_table__name"><?= htmlspecialchars($c['name']) ?></span>
                        </td>
                        <td class="adm_table__muted"><?= htmlspecialchars($c['location'] ?? '–') ?></td>
                        <td class="adm_mono"><?= htmlspecialchars($c['date']) ?></td>
                        <td>
                            <span class="adm_badge adm_badge--<?= $c['status'] === 'active' ? 'active' : 'inactive' ?>">
                                <?= $c['status'] === 'active' ? 'Aktiv' : 'Inaktiv' ?>
                            </span>
                        </td>
                        <td>
                            <code class="adm_hash" title="<?= htmlspecialchars($c['hash'] ?? '') ?>">
                                <?= htmlspecialchars(substr($c['hash'] ?? '–', 0, 10)) ?>…
                            </code>
                        </td>
                        <td class="adm_table__actions">
                            <a href="/admin/competitions/<?= (int)$c['id'] ?>/edit" class="adm_btn adm_btn--sm adm_btn--ghost">
                                Bearbeiten
                            </a>
                            <form method="POST" action="/admin/competitions/<?= (int)$c['id'] ?>/delete"
                                  onsubmit="return confirm('Wettbewerb «<?= htmlspecialchars(addslashes($c['name'])) ?>» wirklich löschen?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <button type="submit" class="adm_btn adm_btn--sm adm_btn--danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
