<?php
ob_start();
$isEdit    = !empty($task);
$stationId = (int)$station['id'];
$action    = $isEdit
    ? '/admin/stations/' . $stationId . '/tasks/' . (int)$task['id'] . '/edit'
    : '/admin/stations/' . $stationId . '/tasks';
?>
<div class="adm_form-wrap">

    <div class="adm_card adm_card--meta" style="margin-bottom:0">
        <span class="adm_meta__label">Station</span>
        <span class="adm_meta__value adm_mono"><?= htmlspecialchars($station['code']) ?></span>
        <span class="adm_meta__sep">·</span>
        <span class="adm_meta__value"><?= htmlspecialchars($station['name']) ?></span>
    </div>

    <?php if (!empty($error)): ?>
        <div class="adm_alert adm_alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $action ?>" class="adm_form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Bezeichnung -->
        <div class="adm_field">
            <label class="adm_label" for="label">Bezeichnung *</label>
            <input
                class="adm_input"
                type="text"
                id="label"
                name="label"
                value="<?= htmlspecialchars($task['label'] ?? '') ?>"
                placeholder="z.B. Fehlen des Mitgliedsausweis"
                required
                autofocus
            >
        </div>

        <!-- Typ -->
        <div class="adm_field">
            <label class="adm_label">Bewertungstyp *</label>
            <div class="adm_toggle-group">
                <label class="adm_toggle">
                    <input
                        type="radio"
                        name="type"
                        value="count"
                        <?= ($task['type'] ?? '') === 'count' ? 'checked' : '' ?>
                    >
                    <span class="adm_toggle__btn adm_toggle__btn--count">
                        ＋ / － &nbsp; Zähler je Teilnehmer
                    </span>
                </label>
                <label class="adm_toggle">
                    <input
                        type="radio"
                        name="type"
                        value="boolean"
                        <?= ($task['type'] ?? 'boolean') === 'boolean' ? 'checked' : '' ?>
                    >
                    <span class="adm_toggle__btn adm_toggle__btn--bool">
                        Ja / Nein &nbsp; für die Gruppe
                    </span>
                </label>
            </div>
            <span class="adm_hint">
                <strong>Zähler:</strong> Schiedsrichter zählt betroffene Teilnehmer (Plus/Minus-Buttons).<br>
                <strong>Ja/Nein:</strong> Fehler betrifft die gesamte Gruppe (ein Schalter).
            </span>
        </div>

        <!-- Fehlerpunkte und Reihenfolge -->
        <div class="adm_field-row">
            <div class="adm_field">
                <label class="adm_label" for="points">Fehlerpunkte *</label>
                <input
                    class="adm_input adm_input--mono"
                    type="number"
                    id="points"
                    name="points"
                    value="<?= (int)($task['points'] ?? 1) ?>"
                    min="1"
                    max="999"
                    required
                >
                <span class="adm_hint">FP je Vorfall (oder je Teilnehmer bei Zähler)</span>
            </div>
            <div class="adm_field">
                <label class="adm_label" for="sort_order">Reihenfolge</label>
                <input
                    class="adm_input adm_input--mono"
                    type="number"
                    id="sort_order"
                    name="sort_order"
                    value="<?= (int)($task['sort_order'] ?? 0) ?>"
                    min="0"
                    max="255"
                >
                <span class="adm_hint">Niedrigere Zahl = weiter oben</span>
            </div>
        </div>

        <!-- Aktionen -->
        <div class="adm_form-actions">
            <a href="/admin/stations/<?= $stationId ?>/tasks" class="adm_btn adm_btn--ghost">Abbrechen</a>
            <button type="submit" class="adm_btn adm_btn--primary">
                <?= $isEdit ? 'Änderungen speichern' : 'Aufgabe anlegen' ?>
            </button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
