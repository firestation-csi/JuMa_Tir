<?php
ob_start();
$isEdit = !empty($station);
$action = $isEdit
    ? '/admin/stations/' . (int)$station['id'] . '/edit'
    : '/admin/stations';
?>
<div class="adm_form-wrap">

    <?php if (!empty($error)): ?>
        <div class="adm_alert adm_alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $action ?>" class="adm_form" id="stationForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Wettbewerb -->
        <div class="adm_field">
            <label class="adm_label" for="competition_id">Wettbewerb *</label>
            <select
                class="adm_input"
                id="competition_id"
                name="competition_id"
                required
                <?= $isEdit ? 'autofocus' : '' ?>
            >
                <option value="">– Wettbewerb wählen –</option>
                <?php foreach ($competitions as $c): ?>
                    <option
                        value="<?= (int)$c['id'] ?>"
                        <?= (int)($station['competition_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Nummer und Beschreibung in einer Zeile -->
        <div class="adm_field-row">
            <div class="adm_field">
                <label class="adm_label" for="code">Nummer *</label>
                <input
                    class="adm_input adm_input--mono"
                    type="text"
                    id="code"
                    name="code"
                    value="<?= htmlspecialchars($station['code'] ?? '') ?>"
                    placeholder="z.B. A06"
                    maxlength="10"
                    required
                    <?= !$isEdit ? 'autofocus' : '' ?>
                >
                <span class="adm_hint">Kürzel der Station (max. 10 Zeichen)</span>
            </div>
            <div class="adm_field">
                <label class="adm_label" for="name">Beschreibung *</label>
                <input
                    class="adm_input"
                    type="text"
                    id="name"
                    name="name"
                    value="<?= htmlspecialchars($station['name'] ?? '') ?>"
                    placeholder="z.B. Knotenkunde"
                    required
                >
            </div>
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
                        <?= ($station['active'] ?? 1) ? 'checked' : '' ?>
                    >
                    <span class="adm_toggle__btn adm_toggle__btn--active">Aktiv</span>
                </label>
                <label class="adm_toggle">
                    <input
                        type="radio"
                        name="active"
                        value="0"
                        <?= isset($station['active']) && !$station['active'] ? 'checked' : '' ?>
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
                    value="<?= htmlspecialchars($station['hash'] ?? '(wird beim Speichern generiert)') ?>"
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
                    value="<?= htmlspecialchars((string)($station['lat'] ?? '')) ?>"
                    placeholder="z.B. 47.259659"
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
                    value="<?= htmlspecialchars((string)($station['lng'] ?? '')) ?>"
                    placeholder="z.B. 11.400375"
                    step="0.0000001"
                    min="-180"
                    max="180"
                >
            </div>
        </div>

        <!-- Aktionen -->
        <div class="adm_form-actions">
            <a href="/admin/stations" class="adm_btn adm_btn--ghost">Abbrechen</a>
            <button type="submit" class="adm_btn adm_btn--primary">
                <?= $isEdit ? 'Änderungen speichern' : 'Station anlegen' ?>
            </button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
