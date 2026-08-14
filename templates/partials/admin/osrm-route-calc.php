<?php
/**
 * OSRM-Routenberechnung — fügt JS zu Stations-Formular hinzu.
 *
 * Erwartet im Scope:
 *   $stations     array  — Stationen mit id, code, name, lat, lng
 *   $fromFieldId  string — DOM-ID des "Von"-Selects  (default: 'from_station_id')
 *   $toFieldId    string — DOM-ID des "Zu"-Selects    (default: 'to_station_id')
 *   $distFieldId  string — DOM-ID des Distanz-Inputs  (default: 'distance_m')
 *   $timeFieldId  string — DOM-ID des Zeit-Inputs     (default: 'est_time_min')
 */
$fromFieldId = $fromFieldId ?? 'from_station_id';
$toFieldId   = $toFieldId   ?? 'to_station_id';
$distFieldId = $distFieldId ?? 'distance_m';
$timeFieldId = $timeFieldId ?? 'est_time_min';

// Koordinaten-Map als JSON
$coordMap = [];
foreach (($stations ?? []) as $s) {
    if ($s['lat'] && $s['lng']) {
        $coordMap[(int)$s['id']] = [
            'lat'  => (float)$s['lat'],
            'lng'  => (float)$s['lng'],
            'code' => $s['code'],
        ];
    }
}
$coordJson = json_encode($coordMap, JSON_UNESCAPED_UNICODE);
$hasCoords = !empty($coordMap);
?>

<?php if (!$hasCoords): ?>
<div class="adm_alert" style="font-size:12px;padding:8px 12px;margin-top:10px;">
    💡 Für automatische OSM-Routenberechnung bitte Koordinaten der Stationen unter
    <a href="/admin/stations">Stationen → Bearbeiten</a> eintragen.
</div>
<?php else: ?>
<div id="osrm-status" style="display:none;align-items:center;gap:8px;font-size:12px;padding:8px 12px;border-radius:8px;margin-top:10px;"></div>
<?php endif; ?>

<script>
(function () {
    const coords    = <?= $coordJson ?>;
    const fromSel   = document.getElementById(<?= json_encode($fromFieldId) ?>);
    const toSel     = document.getElementById(<?= json_encode($toFieldId) ?>);
    const distInput = document.getElementById(<?= json_encode($distFieldId) ?>);
    const timeInput = document.getElementById(<?= json_encode($timeFieldId) ?>);
    const status    = document.getElementById('osrm-status');

    if (!fromSel || !toSel || !distInput || !timeInput) return;

    function setStatus(type, msg) {
        if (!status) return;
        const styles = {
            loading: 'background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;',
            ok:      'background:var(--wt-ok-soft);color:var(--wt-ok);border:1px solid var(--wt-ok);',
            warn:    'background:#FFFBEB;color:#B45309;border:1px solid #FDE68A;',
            error:   'background:#FEF2F2;color:var(--wt-red);border:1px solid #FECACA;',
        };
        status.style.cssText = 'display:flex;align-items:center;gap:8px;font-size:12px;padding:8px 12px;border-radius:8px;margin-top:10px;' + (styles[type] || '');
        status.innerHTML = msg;
    }
    function hideStatus() { if (status) status.style.display = 'none'; }

    async function calcRoute() {
        const fromId = parseInt(fromSel.value);
        const toId   = parseInt(toSel.value);
        if (!fromId || !toId || fromId === toId) { hideStatus(); return; }

        const from = coords[fromId];
        const to   = coords[toId];

        if (!from || !to) {
            setStatus('warn', '⚠ Für eine oder beide Stationen fehlen Koordinaten – bitte manuell eintragen.');
            return;
        }

        setStatus('loading', '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="animation:spin 1s linear infinite;flex-shrink:0;"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.4" stroke-dasharray="20 15"/></svg> OSM-Route wird berechnet…');

        try {
            const { distanceM, durationMin } = await wtCalcRoute(
                [[from.lng, from.lat], [to.lng, to.lat]]
            );

            // Nur befüllen wenn noch leer oder vom letzten Auto-Fill
            distInput.value = distanceM;
            timeInput.value = durationMin;
            distInput.dataset.osrm = distanceM;
            timeInput.dataset.osrm = durationMin;

            const distStr = distanceM >= 1000
                ? (distanceM / 1000).toFixed(1) + ' km'
                : distanceM + ' m';
            setStatus('ok',
                `✓ OSM-Route: <strong>${distStr}</strong> · ca. <strong>${durationMin} min</strong> zu Fuß ` +
                `<span style="opacity:.6;font-size:11px;">(${from.code} → ${to.code})</span>`
            );
        } catch (err) {
            if (err.name === 'TimeoutError') {
                setStatus('error', '✗ Routing-Timeout – bitte manuell eintragen.');
            } else if (err.message === 'Keine Route gefunden') {
                setStatus('warn', '⚠ Keine Route gefunden – bitte manuell eintragen.');
            } else {
                setStatus('error', '✗ Routing-Dienst nicht erreichbar – bitte manuell eintragen.');
            }
        }
    }

    fromSel.addEventListener('change', calcRoute);
    toSel.addEventListener('change', calcRoute);

    // Beim Laden sofort berechnen wenn beide bereits ausgewählt
    if (fromSel.value && toSel.value) calcRoute();
})();
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
