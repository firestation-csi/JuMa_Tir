<?php
ob_start();
?>
<div class="wt_scroll wt_scroll--no-tabbar wt_fade-in">
    <div style="padding: 36px 24px 20px;">
        <div class="wt_eyebrow" style="margin-bottom: 8px;">JuMA · Bewerter</div>
        <h1 class="wt_h1" style="margin-bottom: 10px;">Station<br>scannen.</h1>
        <p class="wt_body-text">
            Halte die Kamera auf den QR-Code an deiner Station, um dich anzumelden.
        </p>
    </div>

    <?php if (!empty($error)): ?>
        <div style="padding: 0 24px;">
            <div class="wt_alert wt_alert--error"><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <div style="padding: 8px 24px 20px;">
        <div class="wt_viewfinder" id="qrReaderContainer">
            <span class="wt_viewfinder__corner wt_viewfinder__corner--tl"></span>
            <span class="wt_viewfinder__corner wt_viewfinder__corner--tr"></span>
            <span class="wt_viewfinder__corner wt_viewfinder__corner--bl"></span>
            <span class="wt_viewfinder__corner wt_viewfinder__corner--br"></span>
            <span class="wt_viewfinder__scanline" id="scanline"></span>
        </div>
    </div>

    <div style="padding: 0 24px; display: flex; flex-direction: column; gap: 10px;">
        <button class="wt_btn wt_btn--primary wt_btn--block" id="startScanBtn" style="height: 56px; font-size: 16px;">
            <svg width="20" height="20" viewBox="0 0 22 22" fill="none" style="flex-shrink:0">
                <rect x="2" y="2" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/>
                <rect x="13" y="2" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/>
                <rect x="2" y="13" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/>
                <rect x="4.5" y="4.5" width="2" height="2" fill="currentColor"/>
                <rect x="15.5" y="4.5" width="2" height="2" fill="currentColor"/>
                <rect x="4.5" y="15.5" width="2" height="2" fill="currentColor"/>
            </svg>
            QR-Code scannen
        </button>

        <div style="display: flex; align-items: center; gap: 12px; padding: 4px 0;">
            <div class="wt_divider" style="flex: 1;"></div>
            <span class="wt_caption">oder</span>
            <div class="wt_divider" style="flex: 1;"></div>
        </div>

        <form id="tokenForm" style="display: flex; flex-direction: column; gap: 10px;">
            <input
                class="wt_input"
                type="text"
                id="tokenInput"
                name="token"
                placeholder="Token manuell eingeben..."
                autocomplete="off"
                style="height: 50px;"
            >
            <button type="submit" class="wt_btn wt_btn--ghost wt_btn--block">
                Anmelden
            </button>
        </form>
    </div>
</div>

<script src="/assets/js/qr.js" type="module"></script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/judge.php';
