<?php
$route       = $route       ?? null;
$stations    = $stations    ?? [];
$csrf        = $csrf        ?? '';
ob_start();
?>
<div class="adm_form-wrap">
    <form method="POST" action="/admin/stations/routes/<?= (int)$route['id'] ?>/edit" class="adm_form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <div class="adm_field-row">
            <div class="adm_field">
                <label class="adm_label" for="from_station_id">Von Station *</label>
                <select class="adm_input" id="from_station_id" name="from_station_id" required>
                    <option value="">– wählen –</option>
                    <?php foreach ($stations as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= (int)$route['from_station_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['code']) ?> · <?= htmlspecialchars($s['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="adm_field">
                <label class="adm_label" for="to_station_id">Zu Station *</label>
                <select class="adm_input" id="to_station_id" name="to_station_id" required>
                    <option value="">– wählen –</option>
                    <?php foreach ($stations as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= (int)$route['to_station_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['code']) ?> · <?= htmlspecialchars($s['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="adm_field-row">
            <div class="adm_field">
                <label class="adm_label" for="distance_m">Distanz (Meter)</label>
                <input class="adm_input adm_input--mono" type="number" id="distance_m" name="distance_m"
                       value="<?= htmlspecialchars((string)($route['distance_m'] ?? '')) ?>" min="0" max="99999" placeholder="z.B. 200">
            </div>
            <div class="adm_field">
                <label class="adm_label" for="est_time_min">Schätzzeit (Minuten)</label>
                <input class="adm_input adm_input--mono" type="number" id="est_time_min" name="est_time_min"
                       value="<?= htmlspecialchars((string)($route['est_time_min'] ?? '')) ?>" min="1" max="240" placeholder="z.B. 5">
            </div>
            <div class="adm_field">
                <label class="adm_label" for="sort_order">Reihenfolge</label>
                <input class="adm_input adm_input--mono" type="number" id="sort_order" name="sort_order"
                       value="<?= (int)($route['sort_order'] ?? 0) ?>" min="0" max="255">
            </div>
        </div>

        <div class="adm_field">
            <label class="adm_label" for="notes">Notiz / Wegbeschreibung</label>
            <input class="adm_input" type="text" id="notes" name="notes"
                   value="<?= htmlspecialchars($route['notes'] ?? '') ?>"
                   placeholder="z.B. Linker Feldweg, dann über Brücke">
        </div>

        <div class="adm_form-actions">
            <a href="/admin/stations/routes" class="adm_btn adm_btn--ghost">Abbrechen</a>
            <button type="submit" class="adm_btn adm_btn--primary">Änderungen speichern</button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/admin.php';
