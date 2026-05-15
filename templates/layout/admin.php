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

    <div id="helpBanner" style="display:none;align-items:center;gap:12px;
         padding:10px 20px;background:#fef2f2;border-bottom:2px solid #fca5a5;
         font-size:13px;font-weight:600;color:#991b1b;">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;">
            <path d="M10 3L2 17h16L10 3z" stroke="#991b1b" stroke-width="1.6" stroke-linejoin="round"/>
            <path d="M10 9v4M10 14.5v.5" stroke="#991b1b" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        <span>🆘 <span id="helpBannerCount">0</span> offene Hilfeanfrage(n) von Gruppen</span>
        <a href="/admin/messages" style="margin-left:auto;padding:4px 12px;border-radius:6px;
           background:#991b1b;color:#fff;font-size:12px;text-decoration:none;white-space:nowrap;">
            → Nachrichten öffnen
        </a>
    </div>

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

    // Nachrichten-Badge + Hilfe-Alert
    (function () {
        const badge     = document.getElementById('msgBadge');
        const helpBanner= document.getElementById('helpBanner');

        async function checkUnread() {
            try {
                const res  = await fetch('/api/admin/message-count', { credentials: 'same-origin' });
                if (!res.ok) return;
                const json = await res.json();
                const d    = json.data ?? json;           // Response wraps in {success,data}
                const n    = (d.unread || 0) + (d.help || 0);
                if (badge) {
                    badge.textContent   = n > 9 ? '9+' : (n || '');
                    badge.style.display = n > 0 ? 'inline-flex' : 'none';
                }
                if (helpBanner) {
                    helpBanner.style.display = d.help > 0 ? 'flex' : 'none';
                    const cnt = helpBanner.querySelector('#helpBannerCount');
                    if (cnt) cnt.textContent = d.help;
                }
            } catch { /* Offline */ }
        }
        checkUnread();
        setInterval(checkUnread, 30_000);
    })();
    </script>
</body>
</html>
