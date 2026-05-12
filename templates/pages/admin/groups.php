<?php ob_start(); ?>
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
    <div class="adm_card" style="padding:0;overflow:hidden;">
        <div style="overflow-x:auto;">
        <table class="adm_table" style="min-width:760px;">
            <thead>
                <tr>
                    <th style="white-space:nowrap;">#</th>
                    <th>Gruppenname</th>
                    <th class="adm_col--hide-sm">Wettbewerb</th>
                    <th class="adm_col--hide-sm" style="white-space:nowrap;">Anmeldung</th>
                    <th style="white-space:nowrap;">Status</th>
                    <th style="white-space:nowrap;">Letzte Station</th>
                    <th class="adm_col--hide-sm">Hash</th>
                    <th style="width:1px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g): ?>
                    <tr>
                        <td class="adm_mono adm_table__muted" style="font-size:.8rem;"><?= htmlspecialchars($g['num'] ?? '–') ?></td>
                        <td>
                            <span class="adm_table__name"><?= htmlspecialchars($g['name']) ?></span>
                            <?php if ($g['kreis'] ?? null): ?>
                                <span class="adm_table__muted" style="font-size:.75rem;display:block;"><?= htmlspecialchars($g['kreis']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="adm_table__muted adm_col--hide-sm"><?= htmlspecialchars($g['competition_name'] ?? '–') ?></td>
                        <td class="adm_mono adm_col--hide-sm"><?= !empty($g['registration_date']) ? date('d.m.Y', strtotime($g['registration_date'])) : '–' ?></td>
                        <td>
                            <span class="adm_badge adm_badge--<?= $g['active'] ? 'active' : 'inactive' ?>">
                                <?= $g['active'] ? 'Aktiv' : 'Inaktiv' ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($g['last_station_code'] ?? null): ?>
                                <span class="adm_mono" style="font-weight:700;"><?= htmlspecialchars($g['last_station_code']) ?></span>
                                <span class="adm_table__muted" style="font-size:.8rem;"> <?= htmlspecialchars($g['last_station_name'] ?? '') ?></span>
                                <div style="font-size:.72rem;color:var(--wt-text-subtle);margin-top:1px;">
                                    <?php
                                    $checkedIn  = $g['last_checked_in']  ?? null;
                                    $checkedOut = $g['last_checked_out'] ?? null;
                                    if ($checkedIn): ?>
                                        <?= date('d.m. H:i', strtotime($checkedIn)) ?>
                                        <?php if ($checkedOut): ?>
                                            → <?= date('H:i', strtotime($checkedOut)) ?>
                                        <?php else: ?>
                                            <span style="color:var(--wt-ok);font-weight:600;">● aktiv</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="adm_table__muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td class="adm_col--hide-sm">
                            <code class="adm_hash" title="<?= htmlspecialchars($g['qr_token'] ?? '') ?>">
                                <?= htmlspecialchars(substr($g['qr_token'] ?? '–', 0, 8)) ?>…
                            </code>
                        </td>
                        <td class="adm_table__actions" style="white-space:nowrap;">
                            <a href="/admin/groups/<?= (int)$g['id'] ?>/members"
                               class="adm_btn adm_btn--sm adm_btn--ghost">Mitglieder</a>
                            <a href="/admin/print/qr/group/<?= (int)$g['id'] ?>"
                               class="adm_btn adm_btn--sm adm_btn--ghost"
                               onclick="window.open(this.href,'_blank','width=680,height=540,resizable=yes'); return false;"
                               title="QR-Code drucken">
                                <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><rect x="2" y="5" width="12" height="7" rx="1.2" stroke="currentColor" stroke-width="1.4"/><path d="M4 5V3h8v2M4 12H2v-5h12v5h-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><rect x="5" y="8.5" width="6" height="1.2" rx=".6" fill="currentColor"/></svg>
                                Drucken
                            </a>
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
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
