<?php
$type      = $type      ?? 'station';
$label     = $label     ?? '';
$sublabel  = $sublabel  ?? '';
$qrDataUrl = $qrDataUrl ?? '';
$qrContent = $qrContent ?? '';

// Dymo-Vorlage laden und mit aktuellen Daten befüllen
$dymoLabelXml = '';
$dymoTemplatePath = dirname(__DIR__, 3) . '/info/Dymo_QRCode_KFV.dymo';
if ($qrContent && file_exists($dymoTemplatePath)) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    $dom->loadXML(file_get_contents($dymoTemplatePath));

    // QR-Code Inhalt setzen
    foreach ($dom->getElementsByTagName('DataString') as $node) {
        $node->textContent = $qrContent;
    }
    foreach ($dom->getElementsByTagName('Value') as $node) {
        $node->textContent = $qrContent;
    }

    // Textzeilen: Präfix + Name (+ optionaler Sublabel)
    $prefix    = $type === 'group' ? 'Gruppe:' : 'Station:';
    $lineTexts = array_filter([$prefix, $label, $sublabel], fn($s) => $s !== '');
    $lineSpans = $dom->getElementsByTagName('LineTextSpan');
    $parent    = $lineSpans->length > 0 ? $lineSpans->item(0)->parentNode : null;

    // Vorhandene Zeilen befüllen
    foreach ($lineTexts as $i => $text) {
        if ($i < $lineSpans->length) {
            $lineSpans->item($i)->getElementsByTagName('Text')->item(0)->textContent = $text;
        } elseif ($parent) {
            // Zusätzliche Zeile: letzte Zeile klonen
            $clone = $lineSpans->item($lineSpans->length - 1)->cloneNode(true);
            $clone->getElementsByTagName('Text')->item(0)->textContent = $text;
            $parent->appendChild($clone);
        }
    }
    // Überschüssige Zeilen entfernen
    while ($lineSpans->length > count($lineTexts)) {
        $parent->removeChild($lineSpans->item($lineSpans->length - 1));
    }

    $dymoLabelXml = $dom->saveXML();
}

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

    /** Label-XML — exakt nach Dymo_QRCode_KFV.dymo-Vorlage */
    function buildLabelXml(qrContent, mainText, subText, sizeKey) {
        const SIZES = {
            '89x36': { w: 3.50, h: 1.42, name: '30252 Address' },
            '89x51': { w: 3.50, h: 2.01, name: '30321 Large Address' },
            '89x28': { w: 3.50, h: 1.10, name: '30336 Small Address' },
            '54x25': { w: 2.13, h: 0.98, name: '30334 Return Address' },
            '57x32': { w: 2.24, h: 1.26, name: '1933084 Drbl 2-1/4 x 1-1/4 in' },
        };
        const s = SIZES[sizeKey] ?? SIZES['89x36'];
        const f = n => n.toFixed(6);

        // Layout: QR links (49% Breite), Text rechts
        const qrX  = 0.059, qrY = s.h * 0.18;
        const qrW  = s.w * 0.49,  qrH = s.h * 0.74;
        const txtX = s.w * 0.50,  txtY1 = s.h * 0.06;
        const txtW = s.w * 0.47;
        const txtH = (s.h * 0.88) / 2;

        const brushes = (bgA, bgR, bgG, bgB) =>
`          <Brushes>
            <BackgroundBrush><SolidColorBrush><Color A="${bgA}" R="${bgR}" G="${bgG}" B="${bgB}"></Color></SolidColorBrush></BackgroundBrush>
            <BorderBrush><SolidColorBrush><Color A="1" R="0" G="0" B="0"></Color></SolidColorBrush></BorderBrush>
            <StrokeBrush><SolidColorBrush><Color A="1" R="0" G="0" B="0"></Color></SolidColorBrush></StrokeBrush>
            <FillBrush><SolidColorBrush><Color A="1" R="0" G="0" B="0"></Color></SolidColorBrush></FillBrush>
          </Brushes>`;

        const base =
`          <Rotation>Rotation0</Rotation>
          <OutlineThickness>1</OutlineThickness>
          <IsOutlined>False</IsOutlined>
          <BorderStyle>SolidLine</BorderStyle>
          <Margin><DYMOThickness Left="0" Top="0" Right="0" Bottom="0" /></Margin>`;

        const layout = (x, y, w, h) =>
`          <ObjectLayout>
            <DYMOPoint><X>${f(x)}</X><Y>${f(y)}</Y></DYMOPoint>
            <Size><Width>${f(w)}</Width><Height>${f(h)}</Height></Size>
          </ObjectLayout>`;

        const lineSpan = (text, fontName, fontSize, bold) =>
`            <LineTextSpan>
              <TextSpan>
                <Text>${escXml(text)}</Text>
                <FontInfo>
                  <FontName>${fontName}</FontName>
                  <FontSize>${fontSize}</FontSize>
                  <IsBold>${bold}</IsBold>
                  <IsItalic>False</IsItalic>
                  <IsUnderline>False</IsUnderline>
                  <FontBrush><SolidColorBrush><Color A="1" R="0" G="0" B="0"></Color></SolidColorBrush></FontBrush>
                </FontInfo>
              </TextSpan>
            </LineTextSpan>`;

        const textObj = (name, lines, x, y, w, h) =>
`        <TextObject>
          <Name>${name}</Name>
${brushes('0','0','0','0')}
${base}
          <HorizontalAlignment>Left</HorizontalAlignment>
          <VerticalAlignment>Middle</VerticalAlignment>
          <FitMode>None</FitMode>
          <IsVertical>False</IsVertical>
          <FormattedText>
            <FitMode>None</FitMode>
            <HorizontalAlignment>Left</HorizontalAlignment>
            <VerticalAlignment>Middle</VerticalAlignment>
            <IsVertical>False</IsVertical>
${lines}
          </FormattedText>
${layout(x, y, w, h)}
        </TextObject>`;

        return '<' + '?xml version="1.0" encoding="utf-8"?>\n' +
`<DesktopLabel Version="1">
  <DYMOLabel Version="4">
    <Description>JuMa QR Label</Description>
    <Orientation>Portrait</Orientation>
    <LabelName>${escXml(s.name)}</LabelName>
    <InitialLength>0</InitialLength>
    <BorderStyle>SolidLine</BorderStyle>
    <DYMORect>
      <DYMOPoint><X>0.039999966</X><Y>0.060000002</Y></DYMOPoint>
      <Size><Width>${f(s.w - 0.08)}</Width><Height>${f(s.h - 0.10)}</Height></Size>
    </DYMORect>
    <BorderColor><SolidColorBrush><Color A="1" R="0" G="0" B="0"></Color></SolidColorBrush></BorderColor>
    <BorderThickness>1</BorderThickness>
    <Show_Border>False</Show_Border>
    <HasFixedLength>False</HasFixedLength>
    <FixedLengthValue>0</FixedLengthValue>
    <DynamicLayoutManager>
      <RotationBehavior>ClearObjects</RotationBehavior>
      <LabelObjects>
        <QRCodeObject>
          <Name>QRCodeObject0</Name>
${brushes('1','1','1','1')}
${base}
          <BarcodeFormat>QRCode</BarcodeFormat>
          <Data><DataString>${escXml(qrContent)}</DataString></Data>
          <HorizontalAlignment>Center</HorizontalAlignment>
          <VerticalAlignment>Middle</VerticalAlignment>
          <Size>AutoFit</Size>
          <EQRCodeType>QRCodeText</EQRCodeType>
          <TextDataHolder><Value>${escXml(qrContent)}</Value></TextDataHolder>
${layout(qrX, qrY, qrW, qrH)}
        </QRCodeObject>
${textObj('TextMain',
    lineSpan(mainText, 'Segoe UI', 10, 'True'),
    txtX, txtY1, txtW, txtH)}
${textObj('TextSub',
    lineSpan(subText || ' ', 'Segoe UI', 8, 'False'),
    txtX, txtY1 + txtH, txtW, txtH)}
      </LabelObjects>
    </DynamicLayoutManager>
  </DYMOLabel>
  <LabelApplication>Blank</LabelApplication>
  <DataTable>
    <Columns></Columns>
    <Rows></Rows>
  </DataTable>
</DesktopLabel>`;
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
            const labelXml = <?= json_encode($dymoLabelXml) ?>;

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
