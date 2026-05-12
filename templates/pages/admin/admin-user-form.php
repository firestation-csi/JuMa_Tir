<?php
/** @var array|null $user @var string $csrf @var string|null $error */
ob_start();
$isEdit = !empty($user);
$action = $isEdit
    ? '/admin/users/' . (int)$user['id'] . '/edit'
    : '/admin/users';
?>
<div class="adm_form-wrap">

    <?php if (!empty($error)): ?>
        <div class="adm_alert adm_alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $action ?>" class="adm_form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <div class="adm_field-row">
            <div class="adm_field">
                <label class="adm_label" for="username">Benutzername *</label>
                <input
                    class="adm_input adm_input--mono"
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($user['username'] ?? '') ?>"
                    autocomplete="off"
                    required
                    autofocus
                >
            </div>
            <div class="adm_field">
                <label class="adm_label" for="display_name">Anzeigename</label>
                <input
                    class="adm_input"
                    type="text"
                    id="display_name"
                    name="display_name"
                    value="<?= htmlspecialchars($user['display_name'] ?? '') ?>"
                    placeholder="z.B. Max Mustermann"
                >
            </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="adm_field">
            <label class="adm_label">Status</label>
            <div class="adm_toggle-group">
                <label class="adm_toggle">
                    <input type="radio" name="active" value="1"
                        <?= $user['active'] ? 'checked' : '' ?>>
                    <span class="adm_toggle__btn adm_toggle__btn--active">Aktiv</span>
                </label>
                <label class="adm_toggle">
                    <input type="radio" name="active" value="0"
                        <?= !$user['active'] ? 'checked' : '' ?>>
                    <span class="adm_toggle__btn">Inaktiv</span>
                </label>
            </div>
        </div>
        <?php endif; ?>

        <div style="border-top:1px solid var(--wt-border,#e5e3df);padding-top:20px;margin-top:4px;">
            <div class="adm_label" style="font-size:.7rem;margin-bottom:14px;letter-spacing:.08em;">
                <?= $isEdit ? 'PASSWORT ÄNDERN (leer lassen = unverändert)' : 'PASSWORT' ?>
            </div>
            <div class="adm_field-row">
                <div class="adm_field">
                    <label class="adm_label" for="password"><?= $isEdit ? 'Neues Passwort' : 'Passwort *' ?></label>
                    <input
                        class="adm_input"
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        placeholder="mind. 8 Zeichen"
                        <?= $isEdit ? '' : 'required' ?>
                    >
                </div>
                <div class="adm_field">
                    <label class="adm_label" for="password2">Passwort wiederholen <?= $isEdit ? '' : '*' ?></label>
                    <input
                        class="adm_input"
                        type="password"
                        id="password2"
                        name="password2"
                        autocomplete="new-password"
                        placeholder="Wiederholung"
                        <?= $isEdit ? '' : 'required' ?>
                    >
                </div>
            </div>
        </div>

        <div class="adm_form-actions">
            <a href="/admin/users" class="adm_btn adm_btn--ghost">Abbrechen</a>
            <button type="submit" class="adm_btn adm_btn--primary">
                <?= $isEdit ? 'Änderungen speichern' : 'Benutzer anlegen' ?>
            </button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
