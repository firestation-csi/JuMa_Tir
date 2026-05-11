<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= htmlspecialchars($title ?? 'Wertungsbüro') ?> – JuMa</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="wt_body wt_body--admin">

    <nav class="adm_nav">
        <a href="/admin" class="adm_nav__brand">JuMa <span>·</span> Wertungsbüro</a>
        <ul class="adm_nav__links">
            <li><a href="/admin" class="adm_nav__link">Dashboard</a></li>
            <li><a href="/admin/competitions" class="adm_nav__link">Wettbewerbe</a></li>
            <li><a href="/admin/stations" class="adm_nav__link">Stationen</a></li>
            <li><a href="/admin/groups" class="adm_nav__link">Gruppen</a></li>
            <li><a href="/admin/results" class="adm_nav__link">Ergebnisse</a></li>
            <li><a href="/admin/qrcodes" class="adm_nav__link">QR-Codes</a></li>
            <li>
                <a href="/admin/messages" class="adm_nav__link" style="position:relative;">
                    Nachrichten
                    <span id="msgBadge" style="display:none;position:absolute;top:-4px;right:-10px;min-width:16px;height:16px;padding:0 4px;border-radius:999px;background:var(--wt-red);color:#fff;font-size:10px;font-weight:700;display:none;align-items:center;justify-content:center;"></span>
                </a>
            </li>
            <li>
                <form action="/admin/logout" method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                    <button type="submit" class="adm_nav__logout">Abmelden</button>
                </form>
            </li>
        </ul>
    </nav>

    <main class="adm_main">
        <h1 class="adm_page-title"><?= htmlspecialchars($title ?? '') ?></h1>
        <?= $content ?? '' ?>
    </main>

    <script src="/assets/js/app.js" type="module"></script>
    <script src="/assets/js/admin.js" type="module"></script>
    <script>
    // Nachrichten-Badge in der Nav live aktualisieren
    (function () {
        const badge = document.getElementById('msgBadge');
        if (!badge) return;
        async function checkUnread() {
            try {
                const res  = await fetch('/api/admin/message-count', { credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                const n    = data.unread || 0;
                if (n > 0) {
                    badge.textContent  = n > 9 ? '9+' : n;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            } catch { /* Offline */ }
        }
        checkUnread();
        setInterval(checkUnread, 30_000);
    })();
    </script>
</body>
</html>
