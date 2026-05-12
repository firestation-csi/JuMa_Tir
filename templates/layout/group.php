<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1a1a2e">
    <title><?= htmlspecialchars($title ?? 'Gruppeninfo') ?> – JuMa</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --c-bg:       #0f0f1a;
            --c-surface:  #1a1a2e;
            --c-card:     #22223a;
            --c-border:   #333355;
            --c-text:     #e8e8f0;
            --c-muted:    #8888aa;
            --c-accent:   #C0392B;
            --c-ok:       #27AE60;
            --c-warn:     #E67E22;
            --c-blue:     #2980B9;
        }
        body { background: var(--c-bg); color: var(--c-text); font-family: system-ui, sans-serif; min-height: 100dvh; }

        .gi_header {
            background: var(--c-surface);
            border-bottom: 1px solid var(--c-border);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .gi_header__logo { font-size: 22px; font-weight: 900; letter-spacing: -1px; color: var(--c-accent); }
        .gi_header__title { font-size: 14px; color: var(--c-muted); }

        .gi_main { padding: 16px; max-width: 600px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }

        .gi_card {
            background: var(--c-card);
            border: 1px solid var(--c-border);
            border-radius: 14px;
            overflow: hidden;
        }
        .gi_card__head {
            padding: 12px 16px;
            border-bottom: 1px solid var(--c-border);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--c-muted);
        }
        .gi_card__body { padding: 16px; }

        .gi_scanner-wrap {
            border-radius: 12px;
            overflow: hidden;
            background: #000;
        }
        #gi-qr-reader { width: 100%; }
        #gi-qr-reader video { border-radius: 0 !important; }

        .gi_btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            padding: 12px 20px; border-radius: 10px; border: none; cursor: pointer;
            font-size: 15px; font-weight: 700; transition: opacity .15s; width: 100%;
        }
        .gi_btn:disabled { opacity: .5; cursor: default; }
        .gi_btn--primary  { background: var(--c-accent); color: #fff; }
        .gi_btn--help     { background: var(--c-warn);   color: #fff; }
        .gi_btn--ghost    { background: var(--c-border);  color: var(--c-text); }

        .gi_group-name { font-size: 22px; font-weight: 800; }
        .gi_group-num  { font-size: 13px; color: var(--c-muted); margin-top: 2px; }

        .gi_lw-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
            margin-top: 8px;
        }

        .gi_station-row {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 0; border-bottom: 1px solid var(--c-border);
        }
        .gi_station-row:last-child { border-bottom: none; }
        .gi_station-dot {
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: monospace; font-size: 12px; font-weight: 800;
            flex-shrink: 0;
        }
        .gi_station-dot--done { background: var(--c-ok); color: #fff; }
        .gi_station-dot--current { background: var(--c-warn); color: #fff; }
        .gi_station-dot--next { background: var(--c-blue); color: #fff; }
        .gi_station-dot--pending { background: var(--c-border); color: var(--c-muted); }

        .gi_station-info { flex: 1; }
        .gi_station-info__name { font-size: 14px; font-weight: 600; }
        .gi_station-info__time { font-size: 11px; color: var(--c-muted); margin-top: 1px; }

        .gi_dist-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
        }
        .gi_dist-card {
            background: var(--c-surface); border-radius: 10px; border: 1px solid var(--c-border);
            padding: 12px 14px; text-align: center;
        }
        .gi_dist-card__val { font-size: 22px; font-weight: 800; font-family: monospace; }
        .gi_dist-card__lbl { font-size: 11px; color: var(--c-muted); margin-top: 2px; }

        .gi_progress {
            height: 8px; background: var(--c-border); border-radius: 4px; overflow: hidden; margin-top: 8px;
        }
        .gi_progress__bar { height: 100%; border-radius: 4px; transition: width .4s; }

        #gi-map { height: 260px; border-radius: 0; }

        .gi_error {
            background: #3a1010; border: 1px solid #7a2020; border-radius: 10px;
            padding: 12px 16px; color: #f8a0a0; font-size: 14px; text-align: center;
        }
        .gi_hint { font-size: 13px; color: var(--c-muted); text-align: center; padding: 8px 0; }

        .gi_help-input {
            width: 100%; background: var(--c-surface); border: 1px solid var(--c-border);
            border-radius: 8px; color: var(--c-text); font-size: 14px;
            padding: 10px 12px; margin-bottom: 10px; resize: vertical; min-height: 60px;
        }
        .gi_help-input:focus { outline: none; border-color: var(--c-warn); }

        .gi_sent { text-align: center; padding: 12px; color: var(--c-ok); font-weight: 700; }
    </style>
</head>
<body>
    <header class="gi_header">
        <span class="gi_header__logo">JuMa</span>
        <span class="gi_header__title">Gruppeninfo</span>
    </header>
    <?= $content ?? '' ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</body>
</html>
