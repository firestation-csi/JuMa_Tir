<?php
ob_start();
?>
<div class="adm_toolbar">
    <a href="/admin/groups/new" class="adm_btn adm_btn--primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Neue Gruppe
    </a>
</div>

<?php if (empty($groups)): ?>
    <div class="adm_empty">
        <div class="adm_empty__icon">👥</div>
        <p>Noch keine Gruppe angelegt.</p>
        <a href="/admin/groups/new" class="adm_btn adm_btn--primary">Jetzt anlegen</a>
    </div>
<?php else: ?>
    <div class="adm_card">
        <table class="adm_table">
            <thead>
                <tr>
                    <th>Gruppenname</th>
                    <th>Wettbewerb</th>
                    <th>Anmeldedatum</th>
                    <th>Status</th>
                    <th>Letzte Station</th>
                    <th>Hash</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g): ?>
                    <tr>
                        <td>
                            <span class="adm_table__name"><?= htmlspecialchars($g['name']) ?></span>
                        </td>
                        <td class="adm_table__muted"><?= htmlspecialchars($g['competition_name'] ?? '–') ?></td>
                        <td class="adm_mono"><?= !empty($g['registration_date']) ? date('d.m.Y', strtotime($g['registration_date'])) : '–' ?></td>
                        <td>
                            <span class="adm_badge adm_badge--<?= $g['active'] ? 'active' : 'inactive' ?>">
                                <?= $g['active'] ? 'Aktiv' : 'Inaktiv' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($g['last_station_id']): ?>
                                <span class="adm_mono" style="font-size:.8rem"><?= htmlspecialchars($g['last_station_code'] ?? '') ?></span>
                                <span class="adm_table__muted"> <?= htmlspecialchars($g['last_station_name'] ?? '') ?></span>
                            <?php else: ?>
                                <span class="adm_table__muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code class="adm_hash" title="<?= htmlspecialchars($g['qr_token'] ?? '') ?>">
                                <?= htmlspecialchars(substr($g['qr_token'] ?? '–', 0, 10)) ?>…
                            </code>
                        </td>
                        <td class="adm_table__actions">
                            <a href="/admin/groups/<?= (int)$g['id'] ?>/members"
                               class="adm_btn adm_btn--sm adm_btn--ghost">Mitglieder</a>
                            <a href="/admin/groups/<?= (int)$g['id'] ?>/edit"
                               class="adm_btn adm_btn--sm adm_btn--ghost">Bearbeiten</a>
                            <form method="POST" action="/admin/groups/<?= (int)$g['id'] ?>/delete"
                                  onsubmit="return confirm('Gruppe «<?= htmlspecialchars(addslashes($g['name'])) ?>» wirklich löschen?')">
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
