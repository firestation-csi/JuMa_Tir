<?php
ob_start();
?>

<!-- ===== Schritt 1: Station scannen ===== -->
<div id="stepScan" class="wt_scroll wt_scroll--no-tabbar wt_fade-in">
    <div style="padding: 36px 24px 20px;">
        <div class="wt_eyebrow" style="margin-bottom: 8px;">JuMA · Bewerter</div>
        <h1 class="wt_h1" style="margin-bottom: 10px;">Station<br>scannen.</h1>
        <p class="wt_body-text">
            Halte die Kamera auf den QR-Code an deiner Station, um dich anzumelden.
        </p>
    </div>

    <div id="scanErrorWrap" style="padding: 0 24px; display:none;">
        <div class="wt_alert wt_alert--error" id="scanError"></div>
    </div>

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
            <span class="wt_caption">oder Hash eingeben</span>
            <div class="wt_divider" style="flex: 1;"></div>
        </div>

        <form id="hashForm" style="display: flex; flex-direction: column; gap: 10px;">
            <input
                class="wt_input"
                type="text"
                id="hashInput"
                name="hash"
                placeholder="Station-Hash..."
                autocomplete="off"
                autocorrect="off"
                spellcheck="false"
                style="height: 50px; font-family: var(--wt-font-mono); font-size: 13px;"
            >
            <button type="submit" class="wt_btn wt_btn--ghost wt_btn--block">
                Weiter
            </button>
        </form>
    </div>
</div>

<!-- ===== Schritt 2: Name eingeben ===== -->
<div id="stepName" class="wt_scroll wt_scroll--no-tabbar wt_fade-in" hidden>
    <div style="padding: 36px 24px 20px;">
        <div class="wt_eyebrow" style="margin-bottom: 8px;">JuMA · Bewerter</div>
        <h1 class="wt_h1" style="margin-bottom: 10px;">Dein Name?</h1>
        <p class="wt_body-text">
            Gib deinen Namen ein, um dich an der Station anzumelden.
        </p>
    </div>

    <!-- Station-Bestätigung -->
    <div style="padding: 0 24px 20px;">
        <div class="wt_card" style="display: flex; align-items: center; gap: 16px; padding: 14px 18px;">
            <span style="width:36px; height:36px; border-radius:50%; background:var(--wt-ok-soft,#e6f4ed); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                    <path d="M4 10l5 5 7-7" stroke="var(--wt-ok,#1F7A4D)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <div>
                <div class="wt_caption" style="margin-bottom:2px;">Station gefunden</div>
                <div style="display:flex; align-items:baseline; gap:8px;">
                    <strong class="wt_mono" id="stationCode" style="font-size:1.1rem;">–</strong>
                    <span id="stationName" style="font-size:0.95rem; color:var(--wt-text);">–</span>
                </div>
            </div>
        </div>
    </div>

    <div id="loginErrorWrap" style="padding: 0 24px 12px; display:none;">
        <div class="wt_alert wt_alert--error" id="loginError"></div>
    </div>

    <div style="padding: 0 24px; display: flex; flex-direction: column; gap: 10px;">
        <input
            class="wt_input"
            type="text"
            id="judgeNameInput"
            placeholder="Vor- und Nachname..."
            autocomplete="name"
            style="height: 56px; font-size: 16px;"
        >
        <button id="loginBtn" class="wt_btn wt_btn--primary wt_btn--block" style="height: 56px; font-size: 16px;">
            Anmelden
        </button>
        <button id="backBtn" class="wt_btn wt_btn--ghost wt_btn--block" style="margin-top: 4px;">
            ← Andere Station scannen
        </button>
    </div>
</div>

<script src="/assets/js/qr.js" type="module"></script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/judge.php';
