<?php ob_start(); ?>
<div class="adm_login-page">
    <div class="adm_login-card">

        <div class="adm_login-logo">
            <img src="/assets/img/kfv_pdf_head.png" alt="Kreisfeuerwehrverband Tirschenreuth">
        </div>

        <div class="adm_login-divider"></div>

        <div class="adm_login-title">
            <span class="adm_login-title__main">Wertungsbüro</span>
            <span class="adm_login-title__sub">JuMa · Jugendfeuerwehr-Mannschafts-App</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="adm_alert adm_alert--error" style="margin-bottom:0;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/login" class="adm_login-form">

            <div class="adm_field">
                <label class="adm_label" for="username">Benutzername</label>
                <div class="adm_input-icon-wrap">
                    <svg class="adm_input-icon" width="16" height="16" viewBox="0 0 18 18" fill="none">
                        <circle cx="9" cy="6.5" r="3" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M2.5 16c1.2-3 3.8-4.5 6.5-4.5s5.3 1.5 6.5 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                    <input
                        class="adm_input adm_input--icon"
                        type="text"
                        id="username"
                        name="username"
                        autocomplete="username"
                        placeholder="Benutzername"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="adm_field">
                <label class="adm_label" for="password">Passwort</label>
                <div class="adm_input-icon-wrap">
                    <svg class="adm_input-icon" width="16" height="16" viewBox="0 0 18 18" fill="none">
                        <rect x="3" y="8" width="12" height="8" rx="2" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M6 8V5.5a3 3 0 0 1 6 0V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        <circle cx="9" cy="12" r="1.2" fill="currentColor"/>
                    </svg>
                    <input
                        class="adm_input adm_input--icon"
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Passwort"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="adm_btn adm_btn--primary adm_btn--block" style="height:46px;font-size:15px;margin-top:4px;">
                Anmelden
            </button>

        </form>

    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/base.php';
