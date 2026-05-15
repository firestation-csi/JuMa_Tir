<?php
$type      = $type      ?? 'station';
$label     = $label     ?? '';
$sublabel  = $sublabel  ?? '';
$qrDataUrl = $qrDataUrl ?? '';
$qrContent = $qrContent ?? '';

// Etiketten-Größen (Breite × Höhe in mm, Landscape)
$labelSizes = [
    '89x36' => ['w' => 89, 'h' => 36, 'name' => '89 × 36 mm  (Standard Adressetikett)'],
    '89x51' => ['w' => 89, 'h' => 51, 'name' => '89 × 51 mm  (Großes Adressetikett)'],
    '89x28' => ['w' => 89, 'h' => 28, 'name' => '89 × 28 mm  (Schmales Etikett)'],
    '54x25' => ['w' => 54, 'h' => 25, 'name' => '54 × 25 mm  (Namensschildchen)'],
    '57x32' => ['w' => 57, 'h' => 32, 'name' => '57 × 32 mm  (11354 Multi Purpose)'],
];
$defaultSize = '89x36';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'QR-Code drucken') ?></title>
    <style>
        /* ── Basis ── */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: #f0f0f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 16px 40px;
            color: #1a1a1a;
        }

        /* ── Toolbar ── */
        .prt_toolbar {
            width: 100%;
            max-width: 600px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .prt_toolbar h1 {
            font-size: 16px;
            font-weight: 700;
            flex: 1;
        }
        .prt_btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #ddd;
            background: #fff;
            color: #1a1a1a;
            text-decoration: none;
            transition: background .15s;
        }
        .prt_btn:hover { background: #f5f5f5; }
        .prt_btn--primary { background: #8B1A1A; color: #fff; border-color: #8B1A1A; }
        .prt_btn--primary:hover { background: #6e1515; }
        .prt_btn--dymo { background: #005B99; color: #fff; border-color: #005B99; }
        .prt_btn--dymo:hover { background: #004a7c; }
        .prt_btn:disabled { opacity: .45; cursor: not-allowed; }

        /* ── Einstellungen ── */
        .prt_settings {
            width: 100%;
            max-width: 600px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        .prt_settings label { font-size: 12px; font-weight: 600; color: #888; white-space: nowrap; }
        .prt_settings select, .prt_settings input[type=number] {
            font-family: inherit;
            font-size: 13px;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fafafa;
        }
        .prt_settings select { min-width: 200px; }

        /* ── Label-Vorschau ── */
        .prt_preview-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        .prt_preview-label {
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,.15), 0 0 0 1px rgba(0,0,0,.08);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 10px;
            transition: width .2s, height .2s;
            overflow: hidden;
        }
        .prt_qr img {
            display: block;
            image-rendering: pixelated;
        }
        .prt_text { flex: 1; min-width: 0; }
        .prt_text__main {
            font-weight: 800;
            line-height: 1.1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .prt_text__sub {
            color: #666;
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .prt_text__token {
            font-family: 'Courier New', monospace;
            color: #999;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── Dymo Status ── */
        .prt_dymo-status {
            width: 100%;
            max-width: 600px;
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            background: #fafafa;
            color: #888;
            margin-top: 8px;
            display: none;
        }
        .prt_dymo-status--ok    { background: #f0fdf4; color: #166534; border-color: #bbf7d0; display: block; }
        .prt_dymo-status--error { background: #fef2f2; color: #991b1b; border-color: #fecaca; display: block; }

        /* ── Drucker-Auswahl ── */
        #dymoSection { display: none; }
        #dymoSection.visible { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        /* ── Print Media ── */
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .prt_toolbar,
            .prt_settings,
            .prt_dymo-status,
            #dymoSection,
            .prt_preview-caption { display: none !important; }
            .prt_preview-wrap {
                position: fixed;
                top: 0; left: 0;
                width: 100%; height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                padding: 0;
            }
            .prt_preview-label {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>

<!-- ── Toolbar ──────────────────────────────────────────── -->
<div class="prt_toolbar">
    <button class="prt_btn" onclick="window.close()">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Schließen
    </button>
    <h1><?= htmlspecialchars($title ?? 'QR-Code') ?></h1>
    <button class="prt_btn prt_btn--primary" id="btnBrowserPrint" onclick="window.print()">
        <svg width="15" height="15" viewBox="0 0 18 18" fill="none"><rect x="3" y="6" width="12" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M5 6V3h8v3M5 14H3v-6h12v6h-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="6" y="10" width="6" height="1.5" rx=".75" fill="currentColor"/></svg>
        Im Browser drucken
    </button>
    <button class="prt_btn prt_btn--dymo" id="btnDymoPrint" disabled>
        <svg width="15" height="15" viewBox="0 0 18 18" fill="none"><rect x="1" y="5" width="16" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M5 5V3h8v2M5 14h8v1H5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        Auf Dymo drucken
    </button>
</div>

<!-- ── Einstellungen ────────────────────────────────────── -->
<div class="prt_settings">
    <label>Etikettengröße</label>
    <select id="sizeSelect">
        <?php foreach ($labelSizes as $key => $size): ?>
        <option value="<?= $key ?>" <?= $key === $defaultSize ? 'selected' : '' ?>>
            <?= htmlspecialchars($size['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <label>Kopien</label>
    <input type="number" id="copies" value="1" min="1" max="50" style="width:56px;">

    <div id="dymoSection">
        <label>Drucker</label>
        <select id="dymoSelect" style="min-width:180px;"></select>
    </div>
</div>

<!-- ── Label-Vorschau ───────────────────────────────────── -->
<div class="prt_preview-wrap">
    <div class="prt_preview-label" id="labelPreview">
        <div class="prt_qr" id="qrWrap">
            <img src="<?= htmlspecialchars($qrDataUrl) ?>" id="qrImg" alt="QR-Code">
        </div>
        <div class="prt_text" id="labelText">
            <div class="prt_text__main" id="labelMain"><?= htmlspecialchars($label) ?></div>
            <?php if ($sublabel): ?>
            <div class="prt_text__sub" id="labelSub"><?= htmlspecialchars($sublabel) ?></div>
            <?php endif; ?>
            <div class="prt_text__token" id="labelToken"><?= htmlspecialchars(substr($qrContent, 0, 40)) ?><?= strlen($qrContent) > 40 ? '…' : '' ?></div>
        </div>
    </div>
    <div class="prt_preview-caption" id="previewCaption" style="font-size:12px;color:#888;"></div>
</div>

<div class="prt_dymo-status" id="dymoStatus"></div>

<script>
(function () {
    // ── Größen-Daten aus PHP ──────────────────────────────
    const sizes = <?= json_encode($labelSizes) ?>;
    const SCALE = 3.78; // mm → px bei 96dpi (ungefähre Vorschau)

    const label    = document.getElementById('labelPreview');
    const qrImg    = document.getElementById('qrImg');
    const caption  = document.getElementById('previewCaption');
    const mainEl   = document.getElementById('labelMain');
    const subEl    = document.getElementById('labelSub');
    const tokenEl  = document.getElementById('labelToken');
    const sizeSelect = document.getElementById('sizeSelect');

    function applySize(key) {
        const s = sizes[key];
        if (!s) return;
        const w = Math.round(s.w * SCALE);
        const h = Math.round(s.h * SCALE);
        label.style.width  = w + 'px';
        label.style.height = h + 'px';
        label.style.padding = Math.round(h * 0.07) + 'px ' + Math.round(h * 0.12) + 'px';

        const qrSize = Math.round(h * 0.82);
        qrImg.style.width  = qrSize + 'px';
        qrImg.style.height = qrSize + 'px';

        const fs = Math.round(h * 0.22);
        mainEl.style.fontSize  = fs + 'px';
        if (subEl)   subEl.style.fontSize   = Math.round(fs * 0.7) + 'px';
        if (tokenEl) tokenEl.style.fontSize = Math.round(fs * 0.45) + 'px';

        caption.textContent = s.w + ' × ' + s.h + ' mm · ' + s.name.split('(')[1]?.replace(')','').trim();

        // CSS @page-Größe für Browser-Druck dynamisch setzen
        let style = document.getElementById('printPageStyle');
        if (!style) { style = document.createElement('style'); style.id = 'printPageStyle'; document.head.appendChild(style); }
        style.textContent = `@media print { @page { size: ${s.w}mm ${s.h}mm; margin: 1.5mm; } .prt_preview-label { width: ${s.w - 3}mm; height: ${s.h - 3}mm; } }`;
    }

    sizeSelect.addEventListener('change', () => applySize(sizeSelect.value));
    applySize(sizeSelect.value);

    // ── Dymo Connect Framework ────────────────────────────
    // https://github.com/dymosoftware/dymo-connect-framework
    const dymoStatus  = document.getElementById('dymoStatus');
    const dymoSection = document.getElementById('dymoSection');
    const dymoSelect  = document.getElementById('dymoSelect');
    const btnDymo     = document.getElementById('btnDymoPrint');

    function setDymoStatus(type, msg) {
        dymoStatus.className = 'prt_dymo-status prt_dymo-status--' + type;
        dymoStatus.innerHTML = msg;
    }

    function escXml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /** Label-XML für den aktuell gewählten Etikettentyp aufbauen (ObjectInfo-Format für DCF) */
    function buildLabelXml(qrBase64, mainText, subText, sizeKey) {
        const s = sizes[sizeKey] ?? sizes['89x36'];
        const paperNames = {
            '89x36': '30252 Address',
            '89x51': '30321 Large Address',
            '89x28': '30336 Small Address',
            '54x25': '30334 Return Address',
            '57x32': '11354 Multi Purpose',
        };
        const paperName = paperNames[sizeKey] ?? '30252 Address';

        // 1 mm = 1440 / 25.4 ≈ 56.693 Twips
        const T     = 56.693;
        const twipW = Math.round(s.w * T);
        const twipH = Math.round(s.h * T);
        const pad   = 57;
        const qrSz  = Math.round(twipH * 0.85);
        const qrY   = Math.round((twipH - qrSz) / 2);
        const txtX  = pad + qrSz + 80;
        const txtW  = twipW - txtX - pad;
        const mid   = Math.round(twipH / 2);

        return '<' + '?xml version="1.0" encoding="utf-8"?>\n' +
`<DYMOLabel Version="8">
  <Description/>
  <PaperOrientation>Landscape</PaperOrientation>
  <Id>Address</Id>
  <IsOutlined>false</IsOutlined>
  <PaperName>${paperName}</PaperName>
  <DrawCommands/>
  <ObjectInfo>
    <ImageObject>
      <Name>QRCode</Name>
      <ForeColor Alpha="255" Red="0" Green="0" Blue="0"/>
      <BackColor Alpha="0" Red="255" Green="255" Blue="255"/>
      <LinkedObjectName/>
      <Rotation>Rotation0</Rotation>
      <IsMirrored>False</IsMirrored>
      <IsVariable>False</IsVariable>
      <ImageType>PNG</ImageType>
      <Data>${qrBase64}</Data>
      <ScaleMode>Uniform</ScaleMode>
      <HorizontalAlignment>Center</HorizontalAlignment>
      <VerticalAlignment>Center</VerticalAlignment>
      <IsBackground>False</IsBackground>
    </ImageObject>
    <Bounds X="${pad}" Y="${qrY}" Width="${qrSz}" Height="${qrSz}"/>
  </ObjectInfo>
  <ObjectInfo>
    <TextObject>
      <Name>LabelMain</Name>
      <ForeColor Alpha="255" Red="0" Green="0" Blue="0"/>
      <BackColor Alpha="0" Red="255" Green="255" Blue="255"/>
      <LinkedObjectName/>
      <Rotation>Rotation0</Rotation>
      <IsMirrored>False</IsMirrored>
      <IsVariable>False</IsVariable>
      <HorizontalAlignment>Left</HorizontalAlignment>
      <VerticalAlignment>Middle</VerticalAlignment>
      <TextFitMode>ShrinkToFit</TextFitMode>
      <UseFullFontHeight>True</UseFullFontHeight>
      <Verticalized>False</Verticalized>
      <StyledText>
        <Element>
          <String>${escXml(mainText)}</String>
          <Attributes>
            <Font Family="Helvetica" Size="14" Bold="True" Italic="False" Underline="False" StrikeOut="False"/>
            <ForeColor Alpha="255" Red="0" Green="0" Blue="0"/>
          </Attributes>
        </Element>
      </StyledText>
    </TextObject>
    <Bounds X="${txtX}" Y="${pad}" Width="${txtW}" Height="${mid - pad}"/>
  </ObjectInfo>
  <ObjectInfo>
    <TextObject>
      <Name>LabelSub</Name>
      <ForeColor Alpha="255" Red="0" Green="0" Blue="0"/>
      <BackColor Alpha="0" Red="255" Green="255" Blue="255"/>
      <LinkedObjectName/>
      <Rotation>Rotation0</Rotation>
      <IsMirrored>False</IsMirrored>
      <IsVariable>False</IsVariable>
      <HorizontalAlignment>Left</HorizontalAlignment>
      <VerticalAlignment>Middle</VerticalAlignment>
      <TextFitMode>ShrinkToFit</TextFitMode>
      <UseFullFontHeight>True</UseFullFontHeight>
      <Verticalized>False</Verticalized>
      <StyledText>
        <Element>
          <String>${escXml(subText)}</String>
          <Attributes>
            <Font Family="Helvetica" Size="11" Bold="False" Italic="False" Underline="False" StrikeOut="False"/>
            <ForeColor Alpha="255" Red="0" Green="0" Blue="0"/>
          </Attributes>
        </Element>
      </StyledText>
    </TextObject>
    <Bounds X="${txtX}" Y="${mid}" Width="${txtW}" Height="${twipH - mid - pad}"/>
  </ObjectInfo>
</DYMOLabel>`;
    }

    // ── Dymo Connect REST-API (kein Framework-JS nötig) ──────
    const DYMO_API = 'https://localhost:41951/DYMO/DLS/Printing';

    async function dymoGetPrinters() {
        const resp = await fetch(`${DYMO_API}/GetPrinters`);
        if (!resp.ok) throw new Error(`GetPrinters HTTP ${resp.status}`);
        const xml = new DOMParser().parseFromString(await resp.text(), 'text/xml');
        return Array.from(xml.querySelectorAll('LabelWriterPrinter'))
            .map(p => ({
                name:      p.querySelector('Name')?.textContent?.trim() ?? '',
                modelName: p.querySelector('ModelName')?.textContent?.trim() ?? '',
            }))
            .filter(p => p.name);
    }

    async function dymoPrint(printerName, labelXml, copies) {
        const printParamsXml = `<LabelWriterPrintParams><Copies>${copies}</Copies><JobTitle>JuMa QR</JobTitle><PrintQuality>Auto</PrintQuality></LabelWriterPrintParams>`;
        const body = new URLSearchParams({ printerName, printParamsXml, labelXml, labelSetXml: '' });
        const resp = await fetch(`${DYMO_API}/PrintLabel`, { method: 'POST', body });
        if (!resp.ok) throw new Error(`PrintLabel HTTP ${resp.status}: ${await resp.text()}`);
    }

    (async () => {
        let printers = [];
        try {
            printers = await dymoGetPrinters();
        } catch (e) {
            setDymoStatus('error',
                `⚠ Dymo WebService nicht erreichbar (${e.message}). ` +
                'Dymo Connect muss als Admin laufen.');
            return;
        }

        if (!printers.length) {
            setDymoStatus('error', '⚠ Kein Dymo-Drucker gefunden. Drucker verbinden und Seite neu laden.');
            return;
        }

        printers.forEach(p => {
            const opt = document.createElement('option');
            opt.value       = p.name;
            opt.textContent = `${p.name}${p.modelName ? ' (' + p.modelName + ')' : ''}`;
            dymoSelect.appendChild(opt);
        });

        dymoSection.classList.add('visible');
        btnDymo.disabled = false;
        setDymoStatus('ok', `✓ Dymo WebService · ${printers.length} Drucker verfügbar`);

        btnDymo.addEventListener('click', async () => {
            btnDymo.disabled = true;
            btnDymo.textContent = 'Druckt…';

            const copies   = Math.max(1, parseInt(document.getElementById('copies').value) || 1);
            const printer  = dymoSelect.value;
            const qrBase64 = document.getElementById('qrImg').src.split(',')[1] ?? '';
            const labelXml = buildLabelXml(
                qrBase64,
                <?= json_encode($label) ?>,
                <?= json_encode($sublabel) ?>,
                sizeSelect.value
            );

            try {
                await dymoPrint(printer, labelXml, copies);
                btnDymo.disabled = false;
                btnDymo.innerHTML =
                    '<svg width="15" height="15" viewBox="0 0 18 18" fill="none"><path d="M3 9l5 5 7-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Gedruckt!';
                setDymoStatus('ok', `✓ ${copies} Etikett${copies > 1 ? 'en' : ''} gedruckt auf «${printer}»`);
            } catch (err) {
                btnDymo.disabled = false;
                btnDymo.innerHTML =
                    '<svg width="15" height="15" viewBox="0 0 18 18" fill="none"><rect x="1" y="5" width="16" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/></svg> Auf Dymo drucken';
                setDymoStatus('error', `✗ Druckfehler: ${err.message}`);
                console.error('Dymo Druckfehler:', err);
            }
        });
    })();

    // ── Kopien: Browser-Print ─────────────────────────────
    document.getElementById('btnBrowserPrint').addEventListener('click', () => {
        const n = parseInt(document.getElementById('copies').value) || 1;
        if (n <= 1) { window.print(); return; }
        // Mehrere Kopien: Label n-mal duplizieren
        const orig = document.querySelector('.prt_preview-label').outerHTML;
        const wrap = document.querySelector('.prt_preview-wrap');
        wrap.innerHTML = orig;
        for (let i = 1; i < n; i++) wrap.insertAdjacentHTML('beforeend', orig);
        window.print();
        // Nach dem Druck wieder auf 1 zurücksetzen
        setTimeout(() => { wrap.innerHTML = orig; applySize(sizeSelect.value); }, 500);
    });
})();
</script>
</body>
</html>
