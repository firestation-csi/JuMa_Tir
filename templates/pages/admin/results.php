<?php
ob_start();
?>
<div class="wt_results-header">
    <?php if (!empty($competition)): ?>
        <p class="wt_results__competition"><?= htmlspecialchars($competition['name']) ?>
            – <?= $competition['date'] ? date('d.m.Y', strtotime($competition['date'])) : '' ?></p>
    <?php endif; ?>
    <button class="wt_btn wt_btn--secondary" id="exportCsvBtn" type="button">CSV exportieren</button>
    <button class="wt_btn wt_btn--secondary" onclick="window.print()">Drucken</button>
</div>

<?php if (empty($ranking)): ?>
    <div class="wt_alert wt_alert--info">Noch keine Ergebnisse vorhanden.</div>
<?php else: ?>
    <div class="wt_card">
        <h2 class="wt_card__title">Gesamtrangliste</h2>
        <table class="wt_table wt_table--ranking" id="rankingTable">
            <thead>
                <tr>
                    <th>Rang</th>
                    <th>Gruppe</th>
                    <th>Punkte gesamt</th>
                    <th>Stationen</th>
                    <th>Abschluss</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranking as $entry): ?>
                    <tr class="<?= $entry['rank'] === 1 ? 'wt_row--gold' : ($entry['rank'] === 2 ? 'wt_row--silver' : ($entry['rank'] === 3 ? 'wt_row--bronze' : '')) ?>">
                        <td class="wt_rank"><?= (int)$entry['rank'] ?>.</td>
                        <td><?= htmlspecialchars($entry['group_name']) ?></td>
                        <td class="wt_score"><?= number_format((float)($entry['total_score'] ?? 0), 1, ',', '.') ?></td>
                        <td><?= (int)$entry['stations_completed'] ?> / <?= (int)$entry['stations_total'] ?></td>
                        <td>
                            <div class="wt_progress">
                                <div class="wt_progress__bar" style="width:<?= (int)$entry['completion_pct'] ?>%"></div>
                                <span><?= (int)$entry['completion_pct'] ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($stations)): ?>
        <h2 class="wt_section-title">Stationsdetails</h2>
        <?php foreach ($stations as $station): ?>
            <div class="wt_card wt_card--station-detail">
                <h3 class="wt_card__title"><?= htmlspecialchars($station['name']) ?></h3>
                <p class="wt_station__task"><?= htmlspecialchars($station['task'] ?? '') ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
