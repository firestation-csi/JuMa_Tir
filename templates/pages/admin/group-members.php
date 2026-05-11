<?php
ob_start();
$groupId = (int)$group['id'];
?>
<div class="adm_toolbar">
    <a href="/admin/groups" class="adm_btn adm_btn--ghost">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Gruppen
    </a>
    <a href="/admin/groups/<?= $groupId ?>/members/new" class="adm_btn adm_btn--primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Mitglied hinzufügen
    </a>
</div>

<!-- Gruppen-Info -->
<div class="adm_card adm_card--meta">
    <span class="adm_meta__label">Gruppe</span>
    <span class="adm_meta__value"><?= htmlspecialchars($group['name']) ?></span>
    <?php if (!empty($group['registration_date'])): ?>
        <span class="adm_meta__sep">·</span>
        <span class="adm_meta__value adm_mono"><?= date('d.m.Y', strtotime($group['registration_date'])) ?></span>
    <?php endif; ?>
    <?php if ($group['last_station_id']): ?>
        <span class="adm_meta__sep">·</span>
        <span class="adm_meta__label">Letzte Station:</span>
        <span class="adm_meta__value adm_mono"><?= htmlspecialchars($group['last_station_code'] ?? '') ?></span>
    <?php endif; ?>
</div>

<!-- Mitgliederliste -->
<?php if (empty($members)): ?>
    <div class="adm_empty">
        <div class="adm_empty__icon">🧑‍🚒</div>
        <p>Noch keine Mitglieder eingetragen.</p>
        <a href="/admin/groups/<?= $groupId ?>/members/new" class="adm_btn adm_btn--primary">Mitglied hinzufügen</a>
    </div>
<?php else: ?>
    <div class="adm_card">
        <table class="adm_table">
            <thead>
                <tr>
                    <th style="width:2.5rem">#</th>
                    <th>Name</th>
                    <th>Geschlecht</th>
                    <th>Geburtsdatum</th>
                    <th>Alter</th>
                    <th>Funktion</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m):
                    $alter = ($m['geburtsdatum'] && $competition_date)
                        ? \App\Model\GroupMember::calcAge($m['geburtsdatum'], $competition_date)
                        : null;
                    $geschlechtLabel = match($m['geschlecht'] ?? '') {
                        'm' => 'männlich', 'w' => 'weiblich', 'd' => 'divers', default => '–'
                    };
                ?>
                    <tr>
                        <td class="adm_mono adm_table__muted"><?= (int)$m['sort_order'] ?></td>
                        <td>
                            <span class="adm_table__name">
                                <?= htmlspecialchars($m['vorname'] . ' ' . $m['name']) ?>
                            </span>
                        </td>
                        <td class="adm_table__muted"><?= htmlspecialchars($geschlechtLabel) ?></td>
                        <td class="adm_mono"><?= htmlspecialchars($m['geburtsdatum'] ?? '–') ?></td>
                        <td class="adm_mono"><?= $alter !== null ? $alter . ' J.' : '–' ?></td>
                        <td class="adm_table__muted"><?= htmlspecialchars($m['funktion'] ?? '–') ?></td>
                        <td class="adm_table__actions">
                            <a href="/admin/groups/<?= $groupId ?>/members/<?= (int)$m['id'] ?>/edit"
                               class="adm_btn adm_btn--sm adm_btn--ghost">Bearbeiten</a>
                            <form method="POST"
                                  action="/admin/groups/<?= $groupId ?>/members/<?= (int)$m['id'] ?>/delete"
                                  onsubmit="return confirm('Mitglied «<?= htmlspecialchars(addslashes($m['vorname'] . ' ' . $m['name'])) ?>» wirklich löschen?')">
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

<!-- Anmeldeverlauf -->
<?php if (!empty($log)): ?>
    <h2 class="adm_section-title" style="margin-top: 28px;">Anmeldeverlauf</h2>
    <div class="adm_card">
        <table class="adm_table">
            <thead>
                <tr>
                    <th>Station</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log as $l): ?>
                    <tr>
                        <td>
                            <span class="adm_mono" style="font-size:.85rem"><?= htmlspecialchars($l['station_code']) ?></span>
                            <span class="adm_table__muted"> <?= htmlspecialchars($l['station_name']) ?></span>
                        </td>
                        <td class="adm_mono"><?= htmlspecialchars($l['checked_in']) ?></td>
                        <td class="adm_mono"><?= htmlspecialchars($l['checked_out'] ?? '–') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
