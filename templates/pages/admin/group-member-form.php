<?php
ob_start();
$isEdit  = !empty($member);
$groupId = (int)$group['id'];
$action  = $isEdit
    ? '/admin/groups/' . $groupId . '/members/' . (int)$member['id'] . '/edit'
    : '/admin/groups/' . $groupId . '/members';
?>
<div class="adm_form-wrap">

    <div class="adm_card adm_card--meta" style="margin-bottom:0">
        <span class="adm_meta__label">Gruppe</span>
        <span class="adm_meta__value"><?= htmlspecialchars($group['name']) ?></span>
    </div>

    <?php if (!empty($error)): ?>
        <div class="adm_alert adm_alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $action ?>" class="adm_form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Vorname + Nachname -->
        <div class="adm_field-row">
            <div class="adm_field">
                <label class="adm_label" for="vorname">Vorname *</label>
                <input
                    class="adm_input"
                    type="text"
                    id="vorname"
                    name="vorname"
                    value="<?= htmlspecialchars($member['vorname'] ?? '') ?>"
                    placeholder="Vorname"
                    required
                    autofocus
                >
            </div>
            <div class="adm_field">
                <label class="adm_label" for="name">Nachname *</label>
                <input
                    class="adm_input"
                    type="text"
                    id="name"
                    name="name"
                    value="<?= htmlspecialchars($member['name'] ?? '') ?>"
                    placeholder="Nachname"
                    required
                >
            </div>
        </div>

        <!-- Geburtsdatum + Geschlecht -->
        <div class="adm_field-row">
            <div class="adm_field">
                <label class="adm_label" for="geburtsdatum">Geburtsdatum</label>
                <input
                    class="adm_input adm_input--date"
                    type="date"
                    id="geburtsdatum"
                    name="geburtsdatum"
                    value="<?= htmlspecialchars($member['geburtsdatum'] ?? '') ?>"
                >
                <span class="adm_hint">Alter wird zum Wettbewerbstag berechnet</span>
            </div>
            <div class="adm_field">
                <label class="adm_label">Geschlecht</label>
                <div class="adm_toggle-group">
                    <label class="adm_toggle">
                        <input type="radio" name="geschlecht" value="m"
                            <?= ($member['geschlecht'] ?? '') === 'm' ? 'checked' : '' ?>>
                        <span class="adm_toggle__btn">männlich</span>
                    </label>
                    <label class="adm_toggle">
                        <input type="radio" name="geschlecht" value="w"
                            <?= ($member['geschlecht'] ?? '') === 'w' ? 'checked' : '' ?>>
                        <span class="adm_toggle__btn">weiblich</span>
                    </label>
                    <label class="adm_toggle">
                        <input type="radio" name="geschlecht" value="d"
                            <?= ($member['geschlecht'] ?? '') === 'd' ? 'checked' : '' ?>>
                        <span class="adm_toggle__btn">divers</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Funktion + Reihenfolge -->
        <div class="adm_field-row">
            <div class="adm_field">
                <label class="adm_label" for="funktion">Funktion</label>
                <input
                    class="adm_input"
                    type="text"
                    id="funktion"
                    name="funktion"
                    value="<?= htmlspecialchars($member['funktion'] ?? '') ?>"
                    placeholder="z.B. Gruppenführer"
                >
            </div>
            <div class="adm_field">
                <label class="adm_label" for="sort_order">Reihenfolge</label>
                <input
                    class="adm_input adm_input--mono"
                    type="number"
                    id="sort_order"
                    name="sort_order"
                    value="<?= (int)($member['sort_order'] ?? 0) ?>"
                    min="0"
                    max="255"
                >
                <span class="adm_hint">Niedrigere Zahl = weiter oben</span>
            </div>
        </div>

        <!-- Aktionen -->
        <div class="adm_form-actions">
            <a href="/admin/groups/<?= $groupId ?>/members" class="adm_btn adm_btn--ghost">Abbrechen</a>
            <button type="submit" class="adm_btn adm_btn--primary">
                <?= $isEdit ? 'Änderungen speichern' : 'Mitglied anlegen' ?>
            </button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
