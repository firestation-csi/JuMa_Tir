<?php
/** @var array $station @var array|null $task @var string $csrf @var string|null $error */
ob_start();
$isEdit    = !empty($task);
$stationId = (int)$station['id'];
$action    = $isEdit
    ? '/admin/stations/' . $stationId . '/tasks/' . (int)$task['id'] . '/edit'
    : '/admin/stations/' . $stationId . '/tasks';

$currentType = $task['type'] ?? 'boolean';
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

    <form method="POST" action="<?= $action ?>" class="adm_form" id="taskForm">
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
                placeholder="z.B. Schutzanzug komplett getragen"
                required
                autofocus
            >
        </div>

        <!-- Typ -->
        <div class="adm_field">
            <label class="adm_label">Bewertungstyp *</label>
            <div class="adm_toggle-group">
                <label class="adm_toggle">
                    <input type="radio" name="type" value="count"
                        <?= $currentType === 'count' ? 'checked' : '' ?>>
                    <span class="adm_toggle__btn adm_toggle__btn--count">
                        ＋ / － &nbsp; Zähler (Nummer)
                    </span>
                </label>
                <label class="adm_toggle">
                    <input type="radio" name="type" value="boolean"
                        <?= $currentType === 'boolean' ? 'checked' : '' ?>>
                    <span class="adm_toggle__btn adm_toggle__btn--bool">
                        Ja / Nein &nbsp; Gruppe
                    </span>
                </label>
                <label class="adm_toggle">
                    <input type="radio" name="type" value="time"
                        <?= $currentType === 'time' ? 'checked' : '' ?>>
                    <span class="adm_toggle__btn adm_toggle__btn--time">
                        ⏱ &nbsp; Zeitwertung
                    </span>
                </label>
            </div>
            <span class="adm_hint">
                <strong>Zähler:</strong> Anzahl betroffener Teilnehmer (0 bis Max-Feldwert) × Fehlerpunkte.<br>
                <strong>Ja/Nein:</strong> Einmal für die Gruppe — Nein ergibt Fehlerpunkte.<br>
                <strong>Zeitwertung:</strong> Stoppuhr — FP = ⌊(Ist − Soll) / Zeiteinheit⌋ × FP-Wert.
            </span>
        </div>

        <!-- Felder für count und boolean -->
        <div id="fields-count-bool">
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
                    >
                    <span class="adm_hint">FP je Vorfall (Ja/Nein) oder je Teilnehmer (Zähler)</span>
                </div>
                <!-- max_count: nur für count-Typ sichtbar -->
                <div class="adm_field" id="field-max-count" style="display:none;">
                    <label class="adm_label" for="max_count">Max-Feldwert *</label>
                    <input
                        class="adm_input adm_input--mono"
                        type="number"
                        id="max_count"
                        name="max_count"
                        value="<?= htmlspecialchars((string)($task['max_count'] ?? '')) ?>"
                        min="1"
                        max="999"
                        placeholder="z.B. 4"
                    >
                    <span class="adm_hint">Obergrenze Stepper (z.B. Anzahl Teilnehmer)</span>
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
        </div>

        <!-- Zeitwertung-Felder (für time-Typ Pflicht, sonst optional) -->
        <div id="fields-time" style="border-top: 1px solid var(--wt-border, #e5e3df); padding-top: 20px; margin-top: 4px;">
            <div class="adm_label" style="font-size:.7rem; margin-bottom:14px; letter-spacing:.08em;" id="time-section-label">
                ZEITWERTUNG
            </div>

            <div class="adm_field-row">
                <div class="adm_field">
                    <label class="adm_label" for="sollzeit_sek">Sollzeit (Sekunden) *</label>
                    <input
                        class="adm_input adm_input--mono"
                        type="number"
                        id="sollzeit_sek"
                        name="sollzeit_sek"
                        value="<?= htmlspecialchars((string)($task['sollzeit_sek'] ?? '')) ?>"
                        min="1"
                        placeholder="z.B. 180 (= 3:00)"
                    >
                </div>
                <div class="adm_field">
                    <label class="adm_label" for="hoechstzeit_sek">Höchstzeit (Sekunden)</label>
                    <input
                        class="adm_input adm_input--mono"
                        type="number"
                        id="hoechstzeit_sek"
                        name="hoechstzeit_sek"
                        value="<?= htmlspecialchars((string)($task['hoechstzeit_sek'] ?? '')) ?>"
                        min="1"
                        placeholder="z.B. 300 (= 5:00)"
                    >
                </div>
            </div>

            <div class="adm_field-row">
                <div class="adm_field">
                    <label class="adm_label" for="zeitstrafe_fp">FP je Zeiteinheit *</label>
                    <input
                        class="adm_input adm_input--mono"
                        type="number"
                        id="zeitstrafe_fp"
                        name="zeitstrafe_fp"
                        value="<?= htmlspecialchars((string)($task['zeitstrafe_fp'] ?? '')) ?>"
                        min="1"
                        placeholder="z.B. 1"
                    >
                </div>
                <div class="adm_field">
                    <label class="adm_label" for="zeiteinheit_sek">Zeiteinheit (Sekunden) *</label>
                    <input
                        class="adm_input adm_input--mono"
                        type="number"
                        id="zeiteinheit_sek"
                        name="zeiteinheit_sek"
                        value="<?= htmlspecialchars((string)($task['zeiteinheit_sek'] ?? '')) ?>"
                        min="1"
                        placeholder="z.B. 10"
                    >
                </div>
            </div>

            <!-- Reihenfolge für time-Typ -->
            <div class="adm_field">
                <label class="adm_label" for="zeit_felder">Zeit-Felder (Anzahl Teilnehmer) *</label>
                <input
                    class="adm_input adm_input--mono"
                    type="number"
                    id="zeit_felder"
                    name="zeit_felder"
                    value="<?= (int)($task['zeit_felder'] ?? 1) ?>"
                    min="1"
                    max="10"
                >
                <span class="adm_hint">
                    1 = ein Stoppuhr-Wert für die Gruppe.<br>
                    &gt;1 = je ein Stoppuhr-Wert pro Teilnehmer (z.B. 4 beim Zielwurf).
                </span>
            </div>

            <div class="adm_field" id="field-sort-time">
                <label class="adm_label" for="sort_order_time">Reihenfolge</label>
                <input
                    class="adm_input adm_input--mono"
                    type="number"
                    id="sort_order_time"
                    name="sort_order"
                    value="<?= (int)($task['sort_order'] ?? 0) ?>"
                    min="0"
                    max="255"
                >
                <span class="adm_hint">Niedrigere Zahl = weiter oben</span>
            </div>

            <?php
            $maxFp = \App\Model\StationTask::maxZeitstrafe(
                isset($task['sollzeit_sek'])    ? (int)$task['sollzeit_sek']    : null,
                isset($task['hoechstzeit_sek']) ? (int)$task['hoechstzeit_sek'] : null,
                isset($task['zeitstrafe_fp'])   ? (int)$task['zeitstrafe_fp']   : null,
                isset($task['zeiteinheit_sek']) ? (int)$task['zeiteinheit_sek'] : null,
            );
            ?>
            <span class="adm_hint" id="zeitHint">
                Formel: <code>FP = ⌊(Ist − Soll) / Zeiteinheit⌋ × FP-Wert</code>, gedeckelt durch Höchstzeit.
                <?php if ($maxFp !== null): ?>
                    Aktuell max. <strong><?= $maxFp ?> FP</strong> durch Zeitüberschreitung.
                <?php endif; ?>
            </span>
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

<script>
(function () {
    const radios      = document.querySelectorAll('input[name="type"]');
    const fieldsCountBool = document.getElementById('fields-count-bool');
    const fieldsTime  = document.getElementById('fields-time');
    const fieldMaxCount = document.getElementById('field-max-count');
    const timeSectionLabel = document.getElementById('time-section-label');
    const fieldSortTime = document.getElementById('field-sort-time');
    // Das sort_order-Feld im count/bool-Block
    const sortOrderMain = document.querySelector('#fields-count-bool [name="sort_order"]');

    function applyType(type) {
        if (type === 'time') {
            fieldsCountBool.style.display = 'none';
            fieldsTime.style.display      = 'block';
            fieldsTime.style.borderTop    = 'none';
            fieldsTime.style.paddingTop   = '0';
            timeSectionLabel.style.display = 'none';
            fieldSortTime.style.display   = 'block';
            // sort_order im time-Block aktivieren, im anderen deaktivieren
            if (sortOrderMain) sortOrderMain.removeAttribute('name');
            document.getElementById('sort_order_time').setAttribute('name', 'sort_order');
        } else {
            fieldsCountBool.style.display = 'block';
            // Zeitfelder als optionalen Block anzeigen
            fieldsTime.style.display      = 'block';
            fieldsTime.style.borderTop    = '1px solid var(--wt-border, #e5e3df)';
            fieldsTime.style.paddingTop   = '20px';
            timeSectionLabel.style.display = 'block';
            fieldSortTime.style.display   = 'none';
            // sort_order im Haupt-Block
            if (sortOrderMain) sortOrderMain.setAttribute('name', 'sort_order');
            document.getElementById('sort_order_time').removeAttribute('name');

            fieldMaxCount.style.display = type === 'count' ? 'block' : 'none';
        }
    }

    radios.forEach(r => r.addEventListener('change', () => applyType(r.value)));

    // Initialzustand
    const currentType = '<?= htmlspecialchars($currentType) ?>';
    applyType(currentType);
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
