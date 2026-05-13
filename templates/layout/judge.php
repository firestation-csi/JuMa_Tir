<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#D4263A">
    <title><?= htmlspecialchars($title ?? 'Schiedsrichter') ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/judge.css">
    <link rel="manifest" href="/manifest.json">
</head>
<body class="wt_body wt_body--judge">
    <?= $content ?? '' ?>
    <script src="/assets/js/app.js" type="module"></script>
    <script src="/assets/js/offline.js" type="module"></script>
</body>
</html>
