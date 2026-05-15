<?php
ob_start();
$isEdit     = !empty($group);
$action     = $isEdit
    ? '/admin/groups/' . (int)$group['id'] . '/edit'
    : '/admin/groups';
$activeComp = $activeComp ?? null;
?>

<?php if (!$isEdit && $activeComp && ($activeComp['hash'] ?? null)): ?>
<div class="adm_card" style="margin-bottom:20px;background:var(--wt-ok-soft);border-color:var(--wt-ok);">
    <div class="adm_eyebrow" style="color:var(--wt-ok);margin-bottom:8px;">🔗 Registrierungslink für Gruppen</div>
    <p style="font-size:13px;color:var(--wt-text-muted);margin-bottom:10px;">
        Diesen Link an Feuerwehren senden — Gruppen können sich damit ohne Login selbst anmelden.
        Selbst-angemeldete Gruppen erscheinen in der Gruppenliste und müssen aktiviert werden.
    </p>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="text" readonly id="regLink"
               value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/anmeldung/' . $activeComp['hash']) ?>"
               style="flex:1;min-width:200px;padding:8px 10px;border:1px solid var(--wt-border);
                      border-radius:var(--wt-r-sm);font-family:monospace;font-size:12px;
                      background:var(--wt-surface);color:var(--wt-text);">
        <button type="button" class="adm_btn adm_btn--ghost adm_btn--sm" onclick="
            navigator.clipboard.writeText(document.getElementById('regLink').value)
                .then(() => { this.textContent='✓ Kopiert!'; setTimeout(()=>{ this.textContent='Kopieren'; },2000); });
        ">Kopieren</button>
    </div>
</div>
<?php endif; ?>

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

        <!-- Feuerwehr -->
        <?php
        // Feuerwehren nach KBI-Bereich gruppieren
        $fwByKbi = [];
        foreach ($feuerwehren as $fw) {
            $fwByKbi[$fw['kbi_bereich']][] = $fw;
        }
        ksort($fwByKbi);
        $selectedFwId = (int)($group['feuerwehr_id'] ?? 0);
        ?>
        <div class="adm_field">
            <label class="adm_label" for="feuerwehr_id">Feuerwehr</label>
            <select class="adm_input" id="feuerwehr_id" name="feuerwehr_id">
                <option value="">– Feuerwehr wählen –</option>
                <?php foreach ($fwByKbi as $kbi => $wehren): ?>
                    <optgroup label="<?= htmlspecialchars($kbi) ?>">
                        <?php foreach ($wehren as $fw): ?>
                            <option value="<?= (int)$fw['id'] ?>"
                                    data-bereich="<?= htmlspecialchars($fw['bereich']) ?>"
                                    <?= $selectedFwId === (int)$fw['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fw['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Bereich (wird aus Feuerwehr-Auswahl befüllt) -->
        <div class="adm_field">
            <label class="adm_label" for="kbm_area_display">Bereich</label>
            <input
                class="adm_input adm_input--mono"
                type="text"
                id="kbm_area_display"
                value="<?= htmlspecialchars($group['kbm_area'] ?? '') ?>"
                readonly
                placeholder="wird aus Feuerwehr-Auswahl übernommen"
            >
        </div>

        <script>
        (function () {
            const sel     = document.getElementById('feuerwehr_id');
            const display = document.getElementById('kbm_area_display');
            function update() {
                const opt = sel.options[sel.selectedIndex];
                display.value = opt ? (opt.dataset.bereich || '') : '';
            }
            sel.addEventListener('change', update);
            update();
        })();
        </script>

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
