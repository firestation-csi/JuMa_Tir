<?php
$old         = $old         ?? [];
$error       = $error       ?? null;
$feuerwehren = $feuerwehren ?? [];
$competition = $competition ?? [];

$fwByKbi = [];
foreach ($feuerwehren as $fw) {
    $fwByKbi[$fw['kbi_bereich']][] = $fw;
}
ksort($fwByKbi);

$oldFwId  = (int)($old['feuerwehr_id'] ?? 0);
$oldName  = htmlspecialchars($old['name'] ?? '');
$oldGesch = $old['geschlecht'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gruppenanmeldung · <?= htmlspecialchars($competition['name'] ?? '') ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        body { background: var(--wt-bg); color: var(--wt-text); font-family: system-ui, sans-serif;
               min-height: 100dvh; }

        .reg_header { background: var(--wt-red); color: #fff; padding: 20px 20px 16px;
                      text-align: center; }
        .reg_header__logo  { font-size: 13px; font-weight: 700; opacity: .8; margin-bottom: 4px; }
        .reg_header__title { font-size: 22px; font-weight: 900; letter-spacing: -.03em; }
        .reg_header__comp  { font-size: 13px; opacity: .75; margin-top: 3px; }

        .reg_body  { max-width: 560px; margin: 0 auto; padding: 20px 16px 60px; }

        .reg_card  { background: var(--wt-surface); border: 1px solid var(--wt-border);
                     border-radius: var(--wt-r-lg); padding: 20px; margin-bottom: 16px; }
        .reg_section-title { font-size: 13px; font-weight: 800; text-transform: uppercase;
                              letter-spacing: .07em; color: var(--wt-text-muted); margin-bottom: 14px; }

        .reg_field { margin-bottom: 14px; }
        .reg_label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px;
                     color: var(--wt-text-muted); }
        .reg_input { width: 100%; padding: 10px 12px; border: 1px solid var(--wt-border);
                     border-radius: var(--wt-r-sm); font-family: inherit; font-size: 16px;
                     background: var(--wt-bg); color: var(--wt-text); box-sizing: border-box;
                     appearance: none; -webkit-appearance: none; }
        .reg_input:focus { outline: none; border-color: var(--wt-red); }

        .reg_radio-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .reg_radio { flex: 1; min-width: 90px; }
        .reg_radio input { display: none; }
        .reg_radio__btn { display: block; text-align: center; padding: 9px 10px;
                          border: 1.5px solid var(--wt-border); border-radius: var(--wt-r-sm);
                          font-size: 14px; font-weight: 600; cursor: pointer;
                          transition: border-color .15s, background .15s; color: var(--wt-text); }
        .reg_radio input:checked + .reg_radio__btn {
            border-color: var(--wt-red); background: rgba(212,38,58,.08); color: var(--wt-red); }

        .reg_member { background: var(--wt-surface-alt); border: 1px solid var(--wt-border);
                      border-radius: var(--wt-r-sm); padding: 14px; margin-bottom: 10px;
                      position: relative; }
        .reg_member__head { display: flex; align-items: center; justify-content: space-between;
                            margin-bottom: 12px; }
        .reg_member__num  { font-size: 12px; font-weight: 700; color: var(--wt-text-muted); }
        .reg_member__del  { background: none; border: none; cursor: pointer; font-size: 18px;
                            color: var(--wt-text-subtle); padding: 0 4px; line-height: 1; }
        .reg_member__del:hover { color: var(--wt-red); }
        .reg_member__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .reg_add-btn { width: 100%; padding: 11px; border: 1.5px dashed var(--wt-border);
                       border-radius: var(--wt-r-sm); background: none; font-family: inherit;
                       font-size: 14px; font-weight: 600; color: var(--wt-text-muted);
                       cursor: pointer; display: flex; align-items: center; justify-content: center;
                       gap: 6px; margin-top: 4px; }
        .reg_add-btn:hover { border-color: var(--wt-red); color: var(--wt-red); }

        .reg_error { background: rgba(212,38,58,.08); border: 1.5px solid var(--wt-red);
                     border-radius: var(--wt-r-sm); padding: 12px 14px; font-size: 14px;
                     color: var(--wt-red); margin-bottom: 16px; font-weight: 600; }

        .reg_submit { width: 100%; padding: 15px; background: var(--wt-red); color: #fff;
                      border: none; border-radius: var(--wt-r-sm); font-family: inherit;
                      font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 8px; }
        .reg_submit:active { opacity: .85; }

        .reg_hint { font-size: 12px; color: var(--wt-text-subtle); margin-top: 5px; }
    </style>
</head>
<body>

<div class="reg_header">
    <div class="reg_header__logo">KFV Tirschenreuth</div>
    <div class="reg_header__title">Gruppenanmeldung</div>
    <?php if ($competition['name'] ?? null): ?>
    <div class="reg_header__comp"><?= htmlspecialchars($competition['name']) ?>
        <?= ($competition['date'] ?? null) ? ' · ' . date('d.m.Y', strtotime($competition['date'])) : '' ?>
    </div>
    <?php endif; ?>
</div>

<div class="reg_body">

    <?php if ($error): ?>
    <div class="reg_error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="regForm">

        <!-- Feuerwehr & Gruppe -->
        <div class="reg_card">
            <div class="reg_section-title">Gruppe</div>

            <div class="reg_field">
                <label class="reg_label" for="feuerwehr_id">Feuerwehr *</label>
                <select class="reg_input" id="feuerwehr_id" name="feuerwehr_id" required>
                    <option value="">– Feuerwehr wählen –</option>
                    <?php foreach ($fwByKbi as $kbi => $wehren): ?>
                    <optgroup label="<?= htmlspecialchars($kbi) ?>">
                        <?php foreach ($wehren as $fw): ?>
                        <option value="<?= (int)$fw['id'] ?>"
                            <?= $oldFwId === (int)$fw['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fw['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="reg_field">
                <label class="reg_label" for="name">Gruppenname *</label>
                <input class="reg_input" type="text" id="name" name="name"
                       value="<?= $oldName ?>" placeholder="z.B. Gruppe 1" required>
            </div>

        </div>

        <!-- Mitglieder -->
        <div class="reg_card">
            <div class="reg_section-title">Mitglieder</div>
            <div id="memberList"></div>
            <button type="button" class="reg_add-btn" onclick="addMember()">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Mitglied hinzufügen
            </button>
            <div class="reg_hint" style="margin-top:10px;">Mindestens 1 Mitglied erforderlich.</div>
        </div>

        <button type="submit" class="reg_submit">Jetzt anmelden →</button>
        <div class="reg_hint" style="text-align:center;margin-top:10px;">
            Nach der Anmeldung wird eure Gruppe vom Admin freigeschaltet.
        </div>

    </form>
</div>

<script>
'use strict';
let memberCount = 0;

function addMember(data) {
    memberCount++;
    const idx  = memberCount;
    const wrap = document.createElement('div');
    wrap.className = 'reg_member';
    wrap.id        = 'member-' + idx;
    wrap.innerHTML = `
        <div class="reg_member__head">
            <span class="reg_member__num">Mitglied ${idx}</span>
            <button type="button" class="reg_member__del" onclick="removeMember(${idx})" title="Entfernen">×</button>
        </div>
        <div class="reg_member__grid">
            <div class="reg_field" style="margin:0;">
                <label class="reg_label">Vorname *</label>
                <input class="reg_input" type="text" name="members[${idx}][vorname]"
                       value="${esc(data?.vorname ?? '')}" placeholder="Vorname" required>
            </div>
            <div class="reg_field" style="margin:0;">
                <label class="reg_label">Nachname *</label>
                <input class="reg_input" type="text" name="members[${idx}][name]"
                       value="${esc(data?.name ?? '')}" placeholder="Nachname" required>
            </div>
        </div>
        <div class="reg_member__grid" style="margin-top:10px;">
            <div class="reg_field" style="margin:0;">
                <label class="reg_label">Geburtsdatum</label>
                <input class="reg_input" type="date" name="members[${idx}][geburtsdatum]"
                       value="${esc(data?.geburtsdatum ?? '')}">
            </div>
            <div class="reg_field" style="margin:0;">
                <label class="reg_label">Geschlecht</label>
                <select class="reg_input" name="members[${idx}][geschlecht]">
                    <option value="">–</option>
                    <option value="m" ${data?.geschlecht === 'm' ? 'selected' : ''}>Männlich</option>
                    <option value="w" ${data?.geschlecht === 'w' ? 'selected' : ''}>Weiblich</option>
                    <option value="d" ${data?.geschlecht === 'd' ? 'selected' : ''}>Divers</option>
                </select>
            </div>
        </div>`;
    document.getElementById('memberList').appendChild(wrap);
}

function removeMember(idx) {
    const el = document.getElementById('member-' + idx);
    if (el) el.remove();
}

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                          .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Formular-Validation: mind. 1 Mitglied
document.getElementById('regForm').addEventListener('submit', e => {
    if (!document.getElementById('memberList').querySelector('input[type=text]')) {
        e.preventDefault();
        alert('Bitte mindestens ein Mitglied hinzufügen.');
    }
});

// Beim Laden: mind. 1 Mitglied vorbelegen
<?php if (!empty($old['members'])): ?>
<?php foreach ($old['members'] as $m): ?>
addMember(<?= json_encode(['vorname' => $m['vorname'] ?? '', 'name' => $m['name'] ?? '',
    'geburtsdatum' => $m['geburtsdatum'] ?? '', 'geschlecht' => $m['geschlecht'] ?? '']) ?>);
<?php endforeach; ?>
<?php else: ?>
addMember();
<?php endif; ?>
</script>
</body>
</html>
