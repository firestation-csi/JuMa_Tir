<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Schiedsrichter') ?> – JuMa Tirol</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/judge.css">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1a4b8c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body class="wt_body wt_body--judge">
    <header class="wt_header">
        <div class="wt_header__logo">JuMa Tirol</div>
        <div class="wt_header__title"><?= htmlspecialchars($title ?? '') ?></div>
        <div class="wt_sync-indicator" id="syncIndicator" aria-live="polite"></div>
    </header>

    <main class="wt_main">
        <?= $content ?? '' ?>
    </main>

    <script src="/assets/js/app.js" type="module"></script>
    <script src="/assets/js/offline.js" type="module"></script>
</body>
</html>
