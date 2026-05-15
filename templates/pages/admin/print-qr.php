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

        /* ── Dymo-Vorschau ── */
        .prt_dymo-preview {
            width: 100%;
            max-width: 600px;
            margin-top: 16px;
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .prt_dymo-preview.visible { display: flex; }
        .prt_dymo-preview img {
            max-width: 100%;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        .prt_dymo-preview span {
            font-size: 11px;
            color: #888;
        }

        /* ── Print Media ── */
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .prt_toolbar, .prt_settings, .prt_dymo-status,
            #dymoSection, .prt_dymo-preview span { display: none !important; }
            .prt_dymo-preview {
                display: flex !important;
                position: fixed;
                top: 0; left: 0;
                width: 100%; height: 100%;
                align-items: center;
                justify-content: center;
                margin: 0; padding: 0;
            }
            .prt_dymo-preview img { box-shadow: none; border: none; max-height: 100%; }
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
    <label>Kopien</label>
    <input type="number" id="copies" value="1" min="1" max="50" style="width:56px;">

    <div id="dymoSection">
        <label>Drucker</label>
        <select id="dymoSelect" style="min-width:180px;"></select>
    </div>
</div>

<div class="prt_dymo-status" id="dymoStatus"></div>

<div class="prt_dymo-preview" id="dymoPreview">
    <img id="dymoPreviewImg" src="" alt="Dymo-Vorschau">
    <span>Dymo-Druckvorschau</span>
</div>

<script>
(function () {
    const dymoStatus  = document.getElementById('dymoStatus');
    const dymoSection = document.getElementById('dymoSection');
    const dymoSelect  = document.getElementById('dymoSelect');
    const btnDymo     = document.getElementById('btnDymoPrint');

    function setDymoStatus(type, msg) {
        dymoStatus.className = 'prt_dymo-status prt_dymo-status--' + type;
        dymoStatus.innerHTML = msg;
    }

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

    const dymoPreviewEl    = document.getElementById('dymoPreview');
    const dymoPreviewImg   = document.getElementById('dymoPreviewImg');
    const LABEL_XML        = <?= json_encode($dymoLabelXml) ?>;

    async function updateDymoPreview(printerName) {
        if (!LABEL_XML || !printerName) return;
        try {
            const body = new URLSearchParams({ printerName, labelXml: LABEL_XML, renderParamsXml: '' });
            const resp = await fetch(`${DYMO_API}/RenderLabel`, { method: 'POST', body });
            if (!resp.ok) throw new Error(`RenderLabel HTTP ${resp.status}: ${await resp.text()}`);

            const ct = resp.headers.get('content-type') ?? '';
            console.log('[Dymo Preview] Content-Type:', ct);

            if (ct.includes('image/')) {
                // Binary-PNG direkt als Blob
                const blob = await resp.blob();
                if (dymoPreviewImg._blobUrl) URL.revokeObjectURL(dymoPreviewImg._blobUrl);
                dymoPreviewImg._blobUrl = URL.createObjectURL(blob);
                dymoPreviewImg.src = dymoPreviewImg._blobUrl;
            } else {
                // Base64-Text (ggf. XML-gewrapped)
                const text = await resp.text();
                const png  = ct.includes('json') ? JSON.parse(text) : text.replace(/\s+/g, '');
                if (!png) throw new Error('Leere Vorschau-Antwort');
                dymoPreviewImg.src = 'data:image/png;base64,' + png;
            }
            dymoPreviewEl.classList.add('visible');
        } catch (e) {
            console.warn('Dymo-Vorschau fehlgeschlagen:', e.message);
        }
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

        // Vorschau laden und bei Druckerwechsel aktualisieren
        dymoSelect.addEventListener('change', () => updateDymoPreview(dymoSelect.value));
        updateDymoPreview(dymoSelect.value);

        btnDymo.addEventListener('click', async () => {
            btnDymo.disabled = true;
            btnDymo.textContent = 'Druckt…';

            const copies  = Math.max(1, parseInt(document.getElementById('copies').value) || 1);
            const printer = dymoSelect.value;

            try {
                await dymoPrint(printer, LABEL_XML, copies);
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

    document.getElementById('btnBrowserPrint').addEventListener('click', () => window.print());
})();
</script>
</body>
</html>
