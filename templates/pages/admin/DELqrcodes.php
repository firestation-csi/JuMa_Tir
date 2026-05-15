<?php
ob_start();
?>
<div class="wt_qrcodes-actions">
    <button class="wt_btn wt_btn--secondary" onclick="window.print()">Alle drucken</button>
</div>

<?php if (!$competition): ?>
    <div class="wt_alert wt_alert--info">Kein aktiver Wettbewerb.</div>
<?php else: ?>

    <?php if (!empty($judges)): ?>
        <h2 class="wt_section-title">Schiedsrichter-QR-Codes</h2>
        <div class="wt_qr-grid">
            <?php foreach ($judges as $judge): ?>
                <div class="wt_qr-card">
                    <img
                        src="<?= htmlspecialchars($judge['qr_data_url']) ?>"
                        alt="QR-Code <?= htmlspecialchars($judge['name']) ?>"
                        class="wt_qr-img"
                    >
                    <p class="wt_qr-label"><strong><?= htmlspecialchars($judge['name']) ?></strong></p>
                    <p class="wt_qr-sublabel"><?= htmlspecialchars($judge['station_name']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($groups)): ?>
        <h2 class="wt_section-title">Gruppen-QR-Codes</h2>
        <div class="wt_qr-grid">
            <?php foreach ($groups as $group): ?>
                <div class="wt_qr-card">
                    <img
                        src="<?= htmlspecialchars($group['qr_data_url']) ?>"
                        alt="QR-Code <?= htmlspecialchars($group['name']) ?>"
                        class="wt_qr-img"
                    >
                    <p class="wt_qr-label"><strong><?= htmlspecialchars($group['name']) ?></strong></p>
                    <p class="wt_qr-sublabel">Teilnehmergruppe</p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
