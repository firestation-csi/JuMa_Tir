// QR-Code-Scan und -Anzeige – nutzt html5-qrcode CDN
// Wird dynamisch über CDN geladen um Bundle-Größe zu sparen

import { apiFetch, showMessage } from './app.js';

const QR_LIB_URL = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';

let scanner = null;

/** html5-qrcode Bibliothek lazy laden */
async function loadQrLib() {
    if (window.Html5Qrcode) return;
    await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = QR_LIB_URL;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

/** QR-Scanner starten */
export async function startScanner(containerId, onSuccess) {
    await loadQrLib();

    if (scanner) {
        await scanner.stop().catch(() => {});
    }

    scanner = new window.Html5Qrcode(containerId);

    await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
            onSuccess(decodedText);
        },
        () => {} // Fehler beim Lesen ignorieren (normaler Vorgang)
    );
}

/** Scanner stoppen */
export async function stopScanner() {
    if (scanner) {
        await scanner.stop().catch(() => {});
        scanner = null;
    }
}

// ============================================================
// Schiedsrichter-Login-Seite — 2-Schritt-Flow
// Schritt 1: Station-Hash per QR oder manuell prüfen
// Schritt 2: Name eingeben → Anmeldung abschließen
// ============================================================

let currentStationHash = null;

const startScanBtn = document.getElementById('startScanBtn');
if (startScanBtn) {
    startScanBtn.addEventListener('click', async () => {
        startScanBtn.disabled = true;
        startScanBtn.textContent = 'Scanner läuft…';

        try {
            await startScanner('qrReaderContainer', async (hash) => {
                await stopScanner();
                await verifyStationHash(hash);
                // Scanner-Button zurücksetzen falls verifyStationHash fehlschlägt
                startScanBtn.disabled = false;
                startScanBtn.textContent = 'QR-Code scannen';
            });
        } catch {
            setScanError('Kamera konnte nicht geöffnet werden.');
            startScanBtn.disabled = false;
            startScanBtn.textContent = 'QR-Code scannen';
        }
    });
}

// Manueller Hash-Eintrag
const hashForm = document.getElementById('hashForm');
if (hashForm) {
    hashForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const hash = document.getElementById('hashInput').value.trim();
        if (hash) await verifyStationHash(hash);
    });
}

/** Schritt 1: Hash gegen Backend prüfen */
async function verifyStationHash(hash) {
    setScanError('');
    try {
        const data = await apiFetch('/api/judge/verify-station', {
            method: 'POST',
            body: JSON.stringify({ hash }),
        });
        currentStationHash = hash;
        document.getElementById('stationCode').textContent = data.code;
        document.getElementById('stationName').textContent = data.name;
        document.getElementById('stepScan').hidden = true;
        document.getElementById('stepName').hidden = false;
        document.getElementById('judgeNameInput').focus();
    } catch (err) {
        setScanError(err.message);
    }
}

/** Zurück zu Schritt 1 */
const backBtn = document.getElementById('backBtn');
if (backBtn) {
    backBtn.addEventListener('click', () => {
        currentStationHash = null;
        setLoginError('');
        document.getElementById('stepName').hidden = true;
        document.getElementById('stepScan').hidden = false;
        document.getElementById('judgeNameInput').value = '';
        document.getElementById('hashInput').value = '';
        startScanBtn.disabled = false;
        startScanBtn.textContent = 'QR-Code scannen';
    });
}

/** Schritt 2: Anmeldung mit Name abschließen */
const loginBtn = document.getElementById('loginBtn');
if (loginBtn) {
    loginBtn.addEventListener('click', async () => {
        const name = document.getElementById('judgeNameInput').value.trim();
        if (!name) {
            setLoginError('Bitte deinen Namen eingeben.');
            return;
        }
        loginBtn.disabled = true;
        loginBtn.textContent = 'Anmelden…';
        try {
            const data = await apiFetch('/api/judge/login', {
                method: 'POST',
                body: JSON.stringify({ hash: currentStationHash, name }),
            });
            showMessage(`Willkommen, ${data.judge_name}!`, 'success');
            setTimeout(() => (location.href = '/judge/station'), 700);
        } catch (err) {
            setLoginError(err.message);
            loginBtn.disabled = false;
            loginBtn.textContent = 'Anmelden';
        }
    });

    // Enter-Taste im Namensfeld
    document.getElementById('judgeNameInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') loginBtn.click();
    });
}

function setScanError(msg) {
    const wrap = document.getElementById('scanErrorWrap');
    const el   = document.getElementById('scanError');
    if (!wrap || !el) return;
    el.textContent    = msg;
    wrap.style.display = msg ? 'block' : 'none';
}

function setLoginError(msg) {
    const wrap = document.getElementById('loginErrorWrap');
    const el   = document.getElementById('loginError');
    if (!wrap || !el) return;
    el.textContent    = msg;
    wrap.style.display = msg ? 'block' : 'none';
}

// ============================================================
// Station-Seite: Gruppe scannen
// ============================================================
const scanGroupBtn = document.getElementById('scanGroupBtn');
if (scanGroupBtn) {
    scanGroupBtn.addEventListener('click', async () => {
        scanGroupBtn.disabled = true;
        scanGroupBtn.textContent = 'Scanner läuft…';

        try {
            await startScanner('groupQrContainer', async (token) => {
                await stopScanner();
                await verifyGroup(token);
                scanGroupBtn.disabled = false;
                scanGroupBtn.textContent = 'Gruppen-QR scannen';
            });
        } catch {
            showMessage('Kamera konnte nicht geöffnet werden.', 'error');
            scanGroupBtn.disabled = false;
            scanGroupBtn.textContent = 'Gruppen-QR scannen';
        }
    });
}

async function verifyGroup(token) {
    try {
        const data = await apiFetch('/api/group/verify', {
            method: 'POST',
            body: JSON.stringify({ token }),
        });

        document.getElementById('currentGroupId').value   = data.group_id;
        document.getElementById('currentGroupName').textContent = data.group_name;
        document.getElementById('scoreCard').classList.remove('wt_card--hidden');
        document.getElementById('groupScanCard').classList.add('wt_card--hidden');

        showMessage(`Gruppe „${data.group_name}" verifiziert.`, 'success');
    } catch (err) {
        showMessage(err.message, 'error');
    }
}

// ============================================================
// Bewertungsformular
// ============================================================
const scoreGrid = document.getElementById('scoreGrid');
if (scoreGrid) {
    let selectedValue = null;

    scoreGrid.addEventListener('click', (e) => {
        const btn = e.target.closest('.wt_score-btn');
        if (!btn) return;

        scoreGrid.querySelectorAll('.wt_score-btn').forEach((b) => b.classList.remove('wt_score-btn--selected'));
        btn.classList.add('wt_score-btn--selected');

        selectedValue = parseFloat(btn.dataset.value);
        document.getElementById('selectedScoreDisplay').textContent = selectedValue;
        document.getElementById('saveScoreBtn').disabled = false;
    });

    const scoreForm = document.getElementById('scoreForm');
    scoreForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const stationEl = document.querySelector('[data-station-id]');
        const stationId = parseInt(stationEl?.dataset.stationId ?? '0');
        const groupId   = parseInt(document.getElementById('currentGroupId').value);
        const notes     = document.getElementById('scoreNotes').value;

        if (!groupId || selectedValue === null) {
            showMessage('Bitte Gruppe scannen und Wert auswählen.', 'error');
            return;
        }

        const payload = { group_id: groupId, station_id: stationId, value: selectedValue, notes };

        try {
            await apiFetch('/api/score', { method: 'POST', body: JSON.stringify(payload) });
            addToHistory(document.getElementById('currentGroupName').textContent, selectedValue);
            showMessage('Bewertung gespeichert!', 'success');
            resetScoreForm();
        } catch {
            // Offline: in IndexedDB speichern
            window.dispatchEvent(new CustomEvent('wt:save-offline', { detail: payload }));
            addToHistory(document.getElementById('currentGroupName').textContent, selectedValue, true);
            showMessage('Offline gespeichert – wird synchronisiert.', 'info');
            resetScoreForm();
        }
    });
}

function resetScoreForm() {
    document.getElementById('currentGroupId').value = '';
    document.getElementById('currentGroupName').textContent = '';
    document.getElementById('selectedScoreDisplay').textContent = '–';
    document.getElementById('scoreNotes').value = '';
    document.getElementById('saveScoreBtn').disabled = true;
    document.querySelectorAll('.wt_score-btn--selected').forEach((b) => b.classList.remove('wt_score-btn--selected'));
    document.getElementById('scoreCard').classList.add('wt_card--hidden');
    document.getElementById('groupScanCard').classList.remove('wt_card--hidden');
}

function addToHistory(groupName, value, offline = false) {
    const list = document.getElementById('scoreHistory');
    if (!list) return;

    const empty = list.querySelector('.wt_score-history__empty');
    if (empty) empty.remove();

    const li = document.createElement('li');
    li.innerHTML = `
        <span>${groupName}</span>
        <span class="wt_score-history__value">${value} Pkt.</span>
        <span class="wt_score-history__done">${offline ? '⏳ Offline' : '✓'}</span>
    `;
    list.prepend(li);
}
