<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Wertungsbüro') ?> – JuMa Tirol</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="wt_body wt_body--admin">
    <nav class="wt_nav">
        <div class="wt_nav__brand">JuMa Tirol – Wertungsbüro</div>
        <ul class="wt_nav__links">
            <li><a href="/admin" class="wt_nav__link">Dashboard</a></li>
            <li><a href="/admin/results" class="wt_nav__link">Ergebnisse</a></li>
            <li><a href="/admin/qrcodes" class="wt_nav__link">QR-Codes</a></li>
            <li>
                <form action="/admin/logout" method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                    <button type="submit" class="wt_btn wt_btn--ghost">Abmelden</button>
                </form>
            </li>
        </ul>
    </nav>

    <main class="wt_main wt_main--admin">
        <h1 class="wt_page-title"><?= htmlspecialchars($title ?? '') ?></h1>
        <?= $content ?? '' ?>
    </main>

    <script src="/assets/js/app.js" type="module"></script>
    <script src="/assets/js/admin.js" type="module"></script>
</body>
</html>
