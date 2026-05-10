<?php
ob_start();
?>
<?php if (!$competition): ?>
    <div class="wt_alert wt_alert--info">Kein aktiver Wettbewerb gefunden.</div>
<?php else: ?>
    <div class="wt_dashboard-grid">
        <div class="wt_card">
            <h2 class="wt_card__title">Wettbewerb</h2>
            <p><strong><?= htmlspecialchars($competition['name']) ?></strong></p>
            <p>Datum: <?= htmlspecialchars($competition['date']) ?></p>
            <p>Status: <span class="wt_badge wt_badge--<?= htmlspecialchars($competition['status']) ?>">
                <?= htmlspecialchars($competition['status']) ?>
            </span></p>
        </div>

        <div class="wt_card">
            <h2 class="wt_card__title">Übersicht</h2>
            <ul class="wt_stat-list">
                <li><span class="wt_stat__label">Stationen</span>
                    <span class="wt_stat__value"><?= count($stations) ?></span></li>
                <li><span class="wt_stat__label">Gruppen</span>
                    <span class="wt_stat__value"><?= count($groups) ?></span></li>
            </ul>
        </div>
    </div>

    <div class="wt_card">
        <h2 class="wt_card__title">Stationen</h2>
        <table class="wt_table">
            <thead>
                <tr>
                    <th>Station</th>
                    <th>Aufgabe</th>
                    <th>Max. Punkte</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stations as $station): ?>
                    <tr>
                        <td><?= htmlspecialchars($station['name']) ?></td>
                        <td><?= htmlspecialchars($station['task'] ?? '–') ?></td>
                        <td><?= (int)($station['max_score'] ?? 10) ?></td>
                        <td>
                            <a href="/admin/results" class="wt_btn wt_btn--sm">Ergebnisse</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="wt_card">
        <h2 class="wt_card__title">Gruppen</h2>
        <table class="wt_table">
            <thead>
                <tr>
                    <th>Gruppe</th>
                    <th>QR-Code</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td><?= htmlspecialchars($group['name']) ?></td>
                        <td><a href="/admin/qrcodes" class="wt_btn wt_btn--sm">QR anzeigen</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
