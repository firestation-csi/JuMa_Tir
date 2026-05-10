<?php
ob_start();
?>
<div class="adm_toolbar">
    <a href="/admin/stations/new" class="adm_btn adm_btn--primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Neue Station
    </a>
</div>

<?php if (empty($stations)): ?>
    <div class="adm_empty">
        <div class="adm_empty__icon">📍</div>
        <p>Noch keine Station angelegt.</p>
        <a href="/admin/stations/new" class="adm_btn adm_btn--primary">Jetzt anlegen</a>
    </div>
<?php else: ?>
    <div class="adm_card">
        <table class="adm_table">
            <thead>
                <tr>
                    <th>Nr.</th>
                    <th>Beschreibung</th>
                    <th>Wettbewerb</th>
                    <th>Status</th>
                    <th>Hash</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stations as $s): ?>
                    <tr>
                        <td class="adm_mono"><?= htmlspecialchars($s['code']) ?></td>
                        <td>
                            <span class="adm_table__name"><?= htmlspecialchars($s['name']) ?></span>
                        </td>
                        <td class="adm_table__muted"><?= htmlspecialchars($s['competition_name'] ?? '–') ?></td>
                        <td>
                            <span class="adm_badge adm_badge--<?= $s['active'] ? 'active' : 'inactive' ?>">
                                <?= $s['active'] ? 'Aktiv' : 'Inaktiv' ?>
                            </span>
                        </td>
                        <td>
                            <code class="adm_hash" title="<?= htmlspecialchars($s['hash'] ?? '') ?>">
                                <?= htmlspecialchars(substr($s['hash'] ?? '–', 0, 10)) ?>…
                            </code>
                        </td>
                        <td class="adm_table__actions">
                            <a href="/admin/stations/<?= (int)$s['id'] ?>/tasks" class="adm_btn adm_btn--sm adm_btn--ghost">
                                Aufgaben
                            </a>
                            <a href="/admin/stations/<?= (int)$s['id'] ?>/edit" class="adm_btn adm_btn--sm adm_btn--ghost">
                                Bearbeiten
                            </a>
                            <form method="POST" action="/admin/stations/<?= (int)$s['id'] ?>/delete"
                                  onsubmit="return confirm('Station «<?= htmlspecialchars(addslashes($s['name'])) ?>» wirklich löschen?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
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
