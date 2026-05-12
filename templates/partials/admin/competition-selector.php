<?php
/**
 * Wettbewerb-Auswahlfeld
 * @var array       $competitions   Alle Wettbewerbe
 * @var array|null  $competition    Aktuell gewählter Wettbewerb
 * @var string      $csrf           CSRF-Token
 * @var string      $redirectUrl    Seite nach Auswahl (z.B. /admin oder /admin/results)
 */
$competitions = $competitions ?? [];
$selectedId   = isset($competition['id']) ? (int)$competition['id'] : 0;
$redirectUrl  = $redirectUrl ?? '/admin';

$statusLabel  = ['active' => 'Aktiv', 'finished' => 'Abgeschlossen', 'archived' => 'Archiviert'];
?>
<form method="POST" action="/admin/competition/select" class="comp_selector" id="compSelectorForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
    <input type="hidden" name="redirect"   value="<?= htmlspecialchars($redirectUrl) ?>">
    <label class="comp_selector__label" for="compSelect">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
            <path d="M8 1.5l1.5 3 3.5.5-2.5 2.5.5 3.5L8 9.5l-3 1.5.5-3.5L3 5l3.5-.5L8 1.5z"
                  stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
        </svg>
        Wettbewerb
    </label>
    <select class="adm_input comp_selector__select" id="compSelect" name="competition_id"
            onchange="document.getElementById('compSelectorForm').submit()">
        <?php foreach ($competitions as $c):
            $label = htmlspecialchars($c['name']);
            if ($c['date']) $label .= ' · ' . date('d.m.Y', strtotime($c['date']));
            $label .= ' [' . ($statusLabel[$c['status']] ?? $c['status']) . ']';
        ?>
        <option value="<?= (int)$c['id'] ?>" <?= $selectedId === (int)$c['id'] ? 'selected' : '' ?>>
            <?= $label ?>
        </option>
        <?php endforeach; ?>
    </select>
</form>
