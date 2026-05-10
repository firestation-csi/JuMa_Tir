<?php
ob_start();
$isEdit = !empty($competition);
$action = $isEdit
    ? '/admin/competitions/' . (int)$competition['id'] . '/edit'
    : '/admin/competitions';
?>
<div class="adm_form-wrap">

    <?php if (!empty($error)): ?>
        <div class="adm_alert adm_alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $action ?>" class="adm_form" id="competitionForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Beschreibung -->
        <div class="adm_field">
            <label class="adm_label" for="name">Beschreibung *</label>
            <input
                class="adm_input"
                type="text"
                id="name"
                name="name"
                value="<?= htmlspecialchars($competition['name'] ?? '') ?>"
                placeholder="z.B. JuMa 2026"
                required
                autofocus
            >
            <span class="adm_hint">Vollständiger Name des Wettbewerbs</span>
        </div>

        <!-- Ort -->
        <div class="adm_field">
            <label class="adm_label" for="location">Austragungsort</label>
            <input
                class="adm_input"
                type="text"
                id="location"
                name="location"
                value="<?= htmlspecialchars($competition['location'] ?? '') ?>"
                placeholder="z.B. Musterstadt"
            >
        </div>

        <!-- Datum -->
        <div class="adm_field">
            <label class="adm_label" for="date">Datum *</label>
            <input
                class="adm_input adm_input--date"
                type="date"
                id="date"
                name="date"
                value="<?= htmlspecialchars($competition['date'] ?? '') ?>"
                required
            >
        </div>

        <!-- Aktiv -->
        <div class="adm_field">
            <label class="adm_label">Status</label>
            <div class="adm_toggle-group">
                <label class="adm_toggle">
                    <input
                        type="radio"
                        name="active"
                        value="1"
                        <?= ($competition['status'] ?? 'active') === 'active' ? 'checked' : '' ?>
                    >
                    <span class="adm_toggle__btn adm_toggle__btn--active">Aktiv</span>
                </label>
                <label class="adm_toggle">
                    <input
                        type="radio"
                        name="active"
                        value="0"
                        <?= ($competition['status'] ?? '') === 'finished' ? 'checked' : '' ?>
                    >
                    <span class="adm_toggle__btn">Inaktiv</span>
                </label>
            </div>
        </div>

        <!-- Hash -->
        <div class="adm_field">
            <label class="adm_label" for="hashDisplay">Hash (QR-Code-Inhalt)</label>
            <div class="adm_input-row">
                <input
                    class="adm_input adm_input--mono"
                    type="text"
                    id="hashDisplay"
                    value="<?= htmlspecialchars($competition['hash'] ?? '(wird beim Speichern generiert)') ?>"
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
            <span class="adm_hint">Wird automatisch generiert. Schiedsrichter-QR-Codes enthalten diesen Hash.</span>
        </div>

        <!-- Koordinaten -->
        <div class="adm_field-row">
            <div class="adm_field">
                <label class="adm_label" for="lat">Breitengrad (Lat)</label>
                <input
                    class="adm_input adm_input--mono"
                    type="number"
                    id="lat"
                    name="lat"
                    value="<?= htmlspecialchars((string)($competition['lat'] ?? '')) ?>"
                    placeholder="z.B. 48.137154"
                    step="0.0000001"
                    min="-90"
                    max="90"
                >
            </div>
            <div class="adm_field">
                <label class="adm_label" for="lng">Längengrad (Lng)</label>
                <input
                    class="adm_input adm_input--mono"
                    type="number"
                    id="lng"
                    name="lng"
                    value="<?= htmlspecialchars((string)($competition['lng'] ?? '')) ?>"
                    placeholder="z.B. 11.575382"
                    step="0.0000001"
                    min="-180"
                    max="180"
                >
            </div>
        </div>

        <!-- Aktionen -->
        <div class="adm_form-actions">
            <a href="/admin/competitions" class="adm_btn adm_btn--ghost">Abbrechen</a>
            <button type="submit" class="adm_btn adm_btn--primary">
                <?= $isEdit ? 'Änderungen speichern' : 'Wettbewerb anlegen' ?>
            </button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
