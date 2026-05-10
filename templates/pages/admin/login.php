<?php
ob_start();
?>
<div class="wt_login-wrapper">
    <div class="wt_card wt_card--login">
        <h1 class="wt_card__title">Wertungsbüro</h1>
        <p class="wt_card__subtitle">JuMa Tirol – Admin-Zugang</p>

        <?php if (!empty($error)): ?>
            <div class="wt_alert wt_alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="wt_form" method="POST" action="/admin/login">
            <div class="wt_form__group">
                <label class="wt_label" for="password">Passwort</label>
                <input
                    class="wt_input"
                    type="password"
                    id="password"
                    name="password"
                    required
                    autofocus
                >
            </div>
            <button type="submit" class="wt_btn wt_btn--primary">Anmelden</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/base.php';
