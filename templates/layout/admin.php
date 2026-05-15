<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= htmlspecialchars($title ?? 'Wertungsbüro') ?> – KFV-Tirschenreuth</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <?= $extraHead ?? '' ?>
</head>
<body class="wt_body wt_body--admin">

    <nav class="adm_nav">
        <a href="/admin" class="adm_nav__brand">KFV-Tirschenreuth <span>·</span> Wertungsbüro</a>
        <button class="adm_nav__burger" id="navBurger" aria-label="Menü">
            <span></span><span></span><span></span>
        </button>
        <ul class="adm_nav__links" id="navLinks">
            <li><a href="/admin" class="adm_nav__link">Dashboard</a></li>
            <li><a href="/admin/competitions" class="adm_nav__link">Wettbewerbe</a></li>
            <li><a href="/admin/stations" class="adm_nav__link">Stationen</a></li>
            <li><a href="/admin/groups" class="adm_nav__link">Gruppen</a></li>
            <li><a href="/admin/results" class="adm_nav__link">Ergebnisse</a></li>
            <li>
                <a href="/admin/messages" class="adm_nav__link">
                    Nachrichten
                    <span id="msgBadge" class="adm_nav-badge" style="display:none;"></span>
                </a>
            </li>
            <li><a href="/admin/users" class="adm_nav__link">Benutzer</a></li>
            <li>
                <form action="/admin/logout" method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                    <button type="submit" class="adm_nav__logout" title="Angemeldet als <?= htmlspecialchars(\App\Core\Auth::getAdminUsername()) ?>">
                        <?= htmlspecialchars(\App\Core\Auth::getAdminUsername()) ?> · Abmelden
                    </button>
                </form>
            </li>
        </ul>
    </nav>

    <main class="adm_main">
        <h1 class="adm_page-title"><?= htmlspecialchars($title ?? '') ?></h1>
        <?= $content ?? '' ?>
    </main>

    <?= $extraScripts ?? '' ?>
    <script src="/assets/js/app.js" type="module"></script>
    <script src="/assets/js/admin.js" type="module"></script>
    <script>
    // Hamburger-Menü
    (function () {
        const burger = document.getElementById('navBurger');
        const links  = document.getElementById('navLinks');
        if (!burger || !links) return;
        burger.addEventListener('click', () => {
            const open = links.classList.toggle('adm_nav__links--open');
            burger.classList.toggle('adm_nav__burger--open', open);
        });
    })();

    // Nachrichten-Badge
    (function () {
        const badge = document.getElementById('msgBadge');
        if (!badge) return;
        async function checkUnread() {
            try {
                const res  = await fetch('/api/admin/message-count', { credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                const n    = data.unread || 0;
                badge.textContent   = n > 9 ? '9+' : (n || '');
                badge.style.display = n > 0 ? 'inline-flex' : 'none';
            } catch { /* Offline */ }
        }
        checkUnread();
        setInterval(checkUnread, 30_000);
    })();
    </script>
</body>
</html>
