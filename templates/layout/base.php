<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Wettbewerb') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/css/main.css')) ?>">
    <?php if (!empty($extraCss)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/css/' . $extraCss . '.css')) ?>">
    <?php endif; ?>
    <link rel="manifest" href="/manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#D4263A">
</head>
<body class="wt_body">
    <?= $content ?? '' ?>
    <script src="<?= htmlspecialchars(asset('assets/js/app.js')) ?>" type="module"></script>
    <?php if (!empty($extraJs)): ?>
        <script src="<?= htmlspecialchars(asset('assets/js/' . $extraJs . '.js')) ?>" type="module"></script>
    <?php endif; ?>
</body>
</html>
