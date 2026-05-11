<?php
/** @var array $station @var array $tasks @var string $csrf */
ob_start();
$stationId = (int)$station['id'];
?>
<div class="adm_toolbar">
    <a href="/admin/stations" class="adm_btn adm_btn--ghost">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Stationen
    </a>
    <a href="/admin/stations/<?= $stationId ?>/tasks/new" class="adm_btn adm_btn--primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Aufgabe hinzufügen
    </a>
</div>

<div class="adm_card adm_card--meta">
    <span class="adm_meta__label">Station</span>
    <span class="adm_meta__value adm_mono"><?= htmlspecialchars($station['code']) ?></span>
    <span class="adm_meta__sep">·</span>
    <span class="adm_meta__value"><?= htmlspecialchars($station['name']) ?></span>
</div>

<?php if (empty($tasks)): ?>
    <div class="adm_empty">
        <div class="adm_empty__icon">📋</div>
        <p>Noch keine Aufgaben für diese Station definiert.</p>
        <a href="/admin/stations/<?= $stationId ?>/tasks/new" class="adm_btn adm_btn--primary">Aufgabe hinzufügen</a>
    </div>
<?php else: ?>
    <div class="adm_card">
        <table class="adm_table">
            <thead>
                <tr>
                    <th style="width:3rem">#</th>
                    <th>Bezeichnung</th>
                    <th>Typ</th>
                    <th>Fehlerpunkte</th>
                    <th>Zeitwertung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                    <tr>
                        <td class="adm_mono adm_table__muted"><?= (int)$t['sort_order'] ?></td>
                        <td>
                            <span class="adm_table__name"><?= htmlspecialchars($t['label']) ?></span>
                        </td>
                        <td>
                            <?php if ($t['type'] === 'count'): ?>
                                <span class="adm_badge adm_badge--count">
                                    <svg width="11" height="11" viewBox="0 0 16 16" fill="none" style="vertical-align:-1px"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                                    Zähler
                                </span>
                                <?php if ($t['max_count'] !== null): ?>
                                    <span class="adm_table__muted" style="font-size:.75rem;margin-left:4px;">max <?= (int)$t['max_count'] ?></span>
                                <?php endif; ?>
                            <?php elseif ($t['type'] === 'time'): ?>
                                <span class="adm_badge adm_badge--time">
                                    ⏱ Zeitwertung
                                </span>
                            <?php else: ?>
                                <span class="adm_badge adm_badge--bool">
                                    <svg width="11" height="11" viewBox="0 0 16 16" fill="none" style="vertical-align:-1px"><path d="M3 8l4 4 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Ja / Nein
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="adm_mono">
                            <?php if ($t['type'] === 'time'): ?>
                                <?= (int)($t['zeitstrafe_fp'] ?? 0) ?> FP / <?= (int)($t['zeiteinheit_sek'] ?? 0) ?>s
                            <?php else: ?>
                                <?= (int)$t['points'] ?> FP
                                <?php if ($t['type'] === 'count' && $t['max_count'] !== null): ?>
                                    <span class="adm_table__muted" style="font-size:.8rem;">
                                        · max <?= (int)$t['max_count'] * (int)$t['points'] ?> FP
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['sollzeit_sek'] !== null): ?>
                                <?php
                                $fmt = fn(int $s) => sprintf('%d:%02d', intdiv($s, 60), $s % 60);
                                $maxFp = \App\Model\StationTask::maxZeitstrafe(
                                    (int)$t['sollzeit_sek'],
                                    $t['hoechstzeit_sek'] !== null ? (int)$t['hoechstzeit_sek'] : null,
                                    $t['zeitstrafe_fp']   !== null ? (int)$t['zeitstrafe_fp']   : null,
                                    $t['zeiteinheit_sek'] !== null ? (int)$t['zeiteinheit_sek'] : null,
                                );
                                ?>
                                <span class="adm_mono" style="font-size:.8rem; white-space:nowrap;">
                                    Soll <?= $fmt((int)$t['sollzeit_sek']) ?>
                                    <?php if ($t['hoechstzeit_sek']): ?> / Max <?= $fmt((int)$t['hoechstzeit_sek']) ?><?php endif; ?>
                                </span><br>
                                <span class="adm_table__muted" style="font-size:.75rem;">
                                    <?php if ($maxFp !== null): ?>max <?= $maxFp ?> FP<?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="adm_table__muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td class="adm_table__actions">
                            <a href="/admin/stations/<?= $stationId ?>/tasks/<?= (int)$t['id'] ?>/edit"
                               class="adm_btn adm_btn--sm adm_btn--ghost">Bearbeiten</a>
                            <form method="POST"
                                  action="/admin/stations/<?= $stationId ?>/tasks/<?= (int)$t['id'] ?>/delete"
                                  onsubmit="return confirm('Aufgabe «<?= htmlspecialchars(addslashes($t['label'])) ?>» wirklich löschen?')">
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
