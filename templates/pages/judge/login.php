<?php
ob_start();
?>
<div class="wt_card wt_card--centered">
    <h1 class="wt_card__title">Schiedsrichter-Login</h1>
    <p class="wt_card__subtitle">QR-Code scannen oder Token eingeben</p>

    <?php if (!empty($error)): ?>
        <div class="wt_alert wt_alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="wt_qr-scanner" id="qrScanner">
        <div id="qrReaderContainer"></div>
        <button class="wt_btn wt_btn--primary wt_btn--large" id="startScanBtn">
            QR-Code scannen
        </button>
    </div>

    <div class="wt_divider">oder</div>

    <form class="wt_form" id="tokenForm">
        <div class="wt_form__group">
            <label class="wt_label" for="tokenInput">Token manuell eingeben</label>
            <input
                class="wt_input"
                type="text"
                id="tokenInput"
                name="token"
                placeholder="Token eingeben..."
                autocomplete="off"
            >
        </div>
        <button type="submit" class="wt_btn wt_btn--secondary wt_btn--large">
            Anmelden
        </button>
    </form>
</div>
<script src="/assets/js/qr.js" type="module"></script>
<?php
$content = ob_get_clean();
$extraCss = 'judge';
require dirname(__DIR__, 2) . '/layout/judge.php';
