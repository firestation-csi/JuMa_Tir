# Wettbewerbs-Auswertungstool

## Projektübersicht
Online-Tool zur Auswertung eines Wettbewerbs mit mehreren Stationen, Aufgaben und Schiedsrichtern.
- Schiedsrichter melden sich per QR-Code an einer Station an
- Gruppen legitimieren sich per QR-Code beim Schiedsrichter
- Wertungsbüro-Backend: responsiv, primär PC-Bedienung
- Schiedsrichter-WebApp: mobiloptimiert, Offline-Cache (Service Worker), spätere Sync-Übertragung

---

## Tech Stack

### Backend
- PHP 8.2+
- MySQL 8.x (via PDO, NIEMALS mysqli)
- Composer für Dependency-Management
- Pakete via `composer require`, Autoloading via PSR-4

### Frontend
- HTML5 (semantisch korrekt)
- CSS3 (keine Frameworks, eigene Klassen mit Präfix `wt_`)
- Vanilla JavaScript (ES2022+, keine jQuery)
- Service Worker für Offline-Cache (Schiedsrichter-App)
- QR-Code-Scan: JS-Bibliothek (z.B. `html5-qrcode`)

### Tools
- Composer (PHP-Pakete)
- npm nur für Build-Tools wenn nötig (kein Node-Framework)

---

## Projektstruktur

```
/project-root
├── public/                  # Webroot (einziger öffentlich erreichbarer Ordner)
│   ├── index.php            # Einstiegspunkt
│   ├── sw.js                # Service Worker (Offline-Cache)
│   ├── manifest.json        # PWA-Manifest
│   └── assets/
│       ├── css/
│       │   ├── main.css     # Globale Styles (Präfix wt_)
│       │   ├── judge.css    # Styles Schiedsrichter-App
│       │   └── admin.css    # Styles Wertungsbüro
│       ├── js/
│       │   ├── app.js       # Globale JS-Logik
│       │   ├── qr.js        # QR-Code-Scan/-Anzeige
│       │   ├── offline.js   # Offline-Sync-Logik
│       │   └── admin.js     # Wertungsbüro-JS
│       └── img/
│
├── src/                     # PHP-Anwendungslogik (PSR-4 Autoloading)
│   ├── Controller/          # Request-Handler pro Feature
│   │   ├── JudgeController.php
│   │   ├── StationController.php
│   │   ├── GroupController.php
│   │   └── AdminController.php
│   ├── Model/               # Datenbankmodelle
│   │   ├── Judge.php
│   │   ├── Station.php
│   │   ├── Group.php
│   │   ├── Score.php
│   │   └── Competition.php
│   ├── Service/             # Geschäftslogik
│   │   ├── QrCodeService.php    # QR-Code-Generierung
│   │   ├── SyncService.php      # Offline-Sync-Verarbeitung
│   │   └── ScoringService.php   # Auswertungslogik
│   └── Core/
│       ├── Database.php     # PDO-Singleton
│       ├── Router.php       # URL-Routing
│       ├── Auth.php         # Session/Auth-Logik
│       └── Response.php     # JSON/HTML-Response-Helper
│
├── templates/               # HTML-Templates (keine Logik)
│   ├── layout/
│   │   ├── base.php         # Haupt-Layout
│   │   ├── judge.php        # Layout Schiedsrichter
│   │   └── admin.php        # Layout Wertungsbüro
│   └── pages/
│
├── sql/
│   ├── schema.sql           # Vollständiges DB-Schema
│   └── migrations/          # Versionierte Änderungen (001_initial.sql, ...)
│
├── config/
│   ├── config.php           # Lädt .env, definiert Konstanten
│   └── routes.php           # Alle URL-Routen
│
├── vendor/                  # Composer (nicht in Git)
├── .env                     # DB-Zugangsdaten, Secrets (nicht in Git)
├── .env.example             # Template für .env (in Git)
├── composer.json
├── .gitignore
└── CLAUDE.md
```

---

## Datenbank-Konventionen

- Tabellennamen: Plural, snake_case (`judges`, `stations`, `groups`, `scores`)
- Primärschlüssel: immer `id INT AUTO_INCREMENT`
- Timestamps: `created_at`, `updated_at` bei jeder Tabelle
- Foreign Keys explizit definieren
- IMMER prepared statements via PDO

### Kern-Tabellen (Überblick)
```sql
competitions   -- Wettbewerb (Name, Datum, Status)
stations       -- Stationen (Name, Aufgabe, competition_id)
judges         -- Schiedsrichter (Name, QR-Token, station_id)
groups         -- Teilnehmergruppen (Name, QR-Token, competition_id)
scores         -- Bewertungen (judge_id, group_id, station_id, value, synced_at)
offline_queue  -- Offline zwischengespeicherte Bewertungen (pending sync)
```

---

## Coding-Regeln

### PHP
- PSR-12 Code Style
- Typisierung: immer Type Hints verwenden (`string`, `int`, `?int`, `array`)
- Fehlerbehandlung: try/catch, Exceptions werfen statt `die()`
- NIEMALS `$_GET`/`$_POST` direkt verwenden — immer über sanitize-Funktion in `Core/Request.php`
- Keine Logik in Templates (nur Echo und einfache foreach/if)
- Kommentare auf Deutsch

### JavaScript
- Kein jQuery, kein Framework
- `async/await` statt Callbacks
- Offline-Daten in `IndexedDB` speichern (nicht localStorage)
- Service Worker: Cache-First für Assets, Network-First für API-Calls
- Sync: Beim Wiederherstellen der Verbindung `offline_queue` via `POST /api/sync` übertragen

### CSS
- Alle Klassen mit Präfix `wt_` (z.B. `wt_card`, `wt_btn`, `wt_score-grid`)
- CSS Custom Properties für Farben und Abstände in `:root`
- Mobile-first: Basis-Styles für Mobil, `@media (min-width: 1024px)` für Desktop
- Kein Inline-CSS

---

## API-Endpunkte (REST)

```
POST /api/judge/login        -- QR-Login Schiedsrichter
POST /api/group/verify       -- QR-Legitimierung Gruppe
POST /api/score              -- Bewertung speichern
POST /api/sync               -- Offline-Queue übertragen (Array von scores)
GET  /api/station/{id}       -- Stationsdaten + Gruppen
GET  /admin/results          -- Auswertung (HTML oder JSON)
```
- Alle API-Responses als JSON: `{"success": true, "data": {...}}`
- Fehler: `{"success": false, "error": "Fehlermeldung"}`

---

## Zwei Frontends

### 1. Schiedsrichter-App (`/judge`)
- Mobiloptimiert, Touch-freundlich (große Buttons)
- PWA mit Offline-Cache (Service Worker + IndexedDB)
- Flow: QR scannen → Station bestätigt → Gruppe scannt → Bewertung eingeben → Speichern
- Bei Offline: lokal in IndexedDB, Sync-Indikator anzeigen
- Bei Reconnect: automatisch sync via `/api/sync`

### 2. Wertungsbüro-Backend (`/admin`)
- Responsiv, primär PC
- Echtzeit-Übersicht aller Stationen, Gruppen, Ergebnisse
- QR-Code-Verwaltung (generieren, drucken)
- Auswertung und Export

---

## Sicherheit
- QR-Tokens: zufällige UUID/Hash, serverseitig validiert (nie nur clientseitig)
- Sessions: `session_regenerate_id()` nach Login
- CSRF-Schutz bei allen POST-Formularen
- SQL: ausschließlich PDO prepared statements
- `.env` niemals in Git committen

---

## Sprache & Benennung
- Code-Kommentare: Deutsch
- PHP-Klassen/Methoden: PascalCase / camelCase, englisch (`getScoresByStation()`)
- CSS-Klassen: Deutsch erlaubt, aber konsistent (`wt_ergebnis-liste` oder `wt_result-list` — einheitlich entscheiden)
- DB-Spalten: Englisch, snake_case
- Fehlermeldungen im UI: Deutsch