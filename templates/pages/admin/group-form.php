<?php
ob_start();
$isEdit = !empty($group);
$action = $isEdit
    ? '/admin/groups/' . (int)$group['id'] . '/edit'
    : '/admin/groups';
?>
<div class="adm_form-wrap">

    <?php if (!empty($error)): ?>
        <div class="adm_alert adm_alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $action ?>" class="adm_form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Wettbewerb -->
        <div class="adm_field">
            <label class="adm_label" for="competition_id">Wettbewerb *</label>
            <select class="adm_input" id="competition_id" name="competition_id" required>
                <option value="">– Wettbewerb wählen –</option>
                <?php foreach ($competitions as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"
                        <?= (int)($group['competition_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Gruppenname -->
        <div class="adm_field">
            <label class="adm_label" for="name">Gruppenname *</label>
            <input
                class="adm_input"
                type="text"
                id="name"
                name="name"
                value="<?= htmlspecialchars($group['name'] ?? '') ?>"
                placeholder="z.B. FF Musterdorf"
                required
                autofocus
            >
        </div>

        <!-- Anmeldedatum -->
        <div class="adm_field">
            <label class="adm_label" for="registration_date">Anmeldedatum</label>
            <input
                class="adm_input adm_input--date"
                type="date"
                id="registration_date"
                name="registration_date"
                value="<?= htmlspecialchars($group['registration_date'] ?? '') ?>"
            >
        </div>

        <!-- Aktiv -->
        <div class="adm_field">
            <label class="adm_label">Status</label>
            <div class="adm_toggle-group">
                <label class="adm_toggle">
                    <input type="radio" name="active" value="1"
                        <?= ($group['active'] ?? 1) ? 'checked' : '' ?>>
                    <span class="adm_toggle__btn adm_toggle__btn--active">Aktiv</span>
                </label>
                <label class="adm_toggle">
                    <input type="radio" name="active" value="0"
                        <?= isset($group['active']) && !$group['active'] ? 'checked' : '' ?>>
                    <span class="adm_toggle__btn">Inaktiv</span>
                </label>
            </div>
        </div>

        <!-- Hash / QR-Token -->
        <div class="adm_field">
            <label class="adm_label" for="hashDisplay">Hash (QR-Code-Inhalt)</label>
            <div class="adm_input-row">
                <input
                    class="adm_input adm_input--mono"
                    type="text"
                    id="hashDisplay"
                    value="<?= htmlspecialchars($group['qr_token'] ?? '(wird beim Speichern generiert)') ?>"
                    readonly
                >
                <?php if ($isEdit): ?>
                    <label class="adm_btn adm_btn--ghost adm_btn--sm" style="cursor:pointer; white-space:nowrap;">
                        <input type="checkbox" name="regenerate_hash" value="1" style="display:none"
                               onchange="this.closest('label').querySelector('span').textContent = this.checked ? '↺ Neu generieren ✓' : '↺ Neu generieren'">
                        <span>↺ Neu generieren</span>
                    </label>
                <?php endif; ?>
            </div>
            <span class="adm_hint">Wird automatisch generiert. Gruppen-QR-Codes enthalten diesen Hash.</span>
        </div>

        <!-- KBM-Bereich (Platzhalter für später) -->
        <div class="adm_field">
            <label class="adm_label" for="kbm_area">
                KBM-Bereich
                <span class="adm_badge adm_badge--inactive" style="margin-left:6px; vertical-align:middle;">demnächst</span>
            </label>
            <input
                class="adm_input"
                type="text"
                id="kbm_area"
                name="kbm_area"
                value="<?= htmlspecialchars($group['kbm_area'] ?? '') ?>"
                placeholder="wird später konfiguriert"
                disabled
            >
        </div>

        <!-- Aktionen -->
        <div class="adm_form-actions">
            <a href="/admin/groups" class="adm_btn adm_btn--ghost">Abbrechen</a>
            <button type="submit" class="adm_btn adm_btn--primary">
                <?= $isEdit ? 'Änderungen speichern' : 'Gruppe anlegen' ?>
            </button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
