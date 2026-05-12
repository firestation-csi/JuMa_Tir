<?php
/** @var array $users @var string $csrf */
ob_start();
?>
<div class="adm_toolbar">
    <a href="/admin/users/new" class="adm_btn adm_btn--primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Benutzer anlegen
    </a>
</div>

<div class="adm_card adm_card--meta">
    <span class="adm_meta__label">Hinweis</span>
    <span class="adm_meta__value">Der initiale Admin aus der <code>.env</code> ist immer aktiv und wird hier nicht angezeigt.</span>
</div>

<?php if (empty($users)): ?>
    <div class="adm_empty">
        <div class="adm_empty__icon">👤</div>
        <p>Noch keine Datenbankbenutzer angelegt.</p>
        <a href="/admin/users/new" class="adm_btn adm_btn--primary">Benutzer anlegen</a>
    </div>
<?php else: ?>
    <div class="adm_card">
        <table class="adm_table">
            <thead>
                <tr>
                    <th>Benutzername</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Angelegt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="adm_mono"><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['display_name']) ?></td>
                        <td>
                            <?php if ($u['active']): ?>
                                <span class="adm_badge adm_badge--active">Aktiv</span>
                            <?php else: ?>
                                <span class="adm_badge adm_badge--inactive">Inaktiv</span>
                            <?php endif; ?>
                        </td>
                        <td class="adm_table__muted"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                        <td class="adm_table__actions">
                            <a href="/admin/users/<?= (int)$u['id'] ?>/edit"
                               class="adm_btn adm_btn--sm adm_btn--ghost">Bearbeiten</a>
                            <form method="POST"
                                  action="/admin/users/<?= (int)$u['id'] ?>/delete"
                                  onsubmit="return confirm('Benutzer «<?= htmlspecialchars(addslashes($u['username'])) ?>» wirklich löschen?')">
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
