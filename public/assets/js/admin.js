// Wertungsbüro JavaScript

import { showMessage, base64UrlToBuffer, bufferToBase64Url, apiFetch } from './app.js';

// ---- Hash/Token per Klick in Zwischenablage ----
document.querySelectorAll('.adm_hash').forEach(el => {
    const fullToken = el.getAttribute('title') || el.textContent.trim();
    el.style.cursor = 'copy';
    el.title = fullToken + ' · Klicken zum Kopieren';

    el.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(fullToken);
            const prev = el.innerHTML;
            el.textContent = '✓ Kopiert';
            el.classList.add('adm_hash--copied');
            setTimeout(() => {
                el.innerHTML = prev;
                el.classList.remove('adm_hash--copied');
            }, 1400);
        } catch {
            showMessage('Kopieren nicht verfügbar', 'error');
        }
    });
});

// ---- WebAuthn / Passkey-Registrierung ----
const webauthnRegisterBtn = document.getElementById('webauthnRegisterBtn');
if (webauthnRegisterBtn) {
    if (!window.PublicKeyCredential) {
        webauthnRegisterBtn.disabled = true;
        webauthnRegisterBtn.textContent = 'WebAuthn nicht verfügbar';
    } else {
        webauthnRegisterBtn.addEventListener('click', async () => {
            const userId = webauthnRegisterBtn.dataset.userId;
            if (!userId) {
                showMessage('Benutzer-ID fehlt.', 'error');
                return;
            }

            try {
                const options = await apiFetch(`/admin/users/${userId}/webauthn/register/options`, { method: 'GET' });
                const publicKey = {
                    ...options,
                    challenge: base64UrlToBuffer(options.challenge),
                    user: {
                        ...options.user,
                        id: base64UrlToBuffer(options.user.id),
                    },
                    excludeCredentials: (options.excludeCredentials || []).map((credential) => ({
                        ...credential,
                        id: base64UrlToBuffer(credential.id),
                    })),
                };

                const credential = await navigator.credentials.create({ publicKey });
                if (!credential) {
                    throw new Error('Passkey-Registrierung fehlgeschlagen.');
                }

                const response = credential.response;
                await apiFetch(`/admin/users/${userId}/webauthn/register`, {
                    method: 'POST',
                    body: JSON.stringify({
                        response: {
                            clientDataJSON: bufferToBase64Url(response.clientDataJSON),
                            attestationObject: bufferToBase64Url(response.attestationObject),
                        },
                    }),
                });

                showMessage('Passkey erfolgreich hinzugefügt.', 'success');
                setTimeout(() => window.location.reload(), 800);
            } catch (err) {
                showMessage(err.message || 'Passkey-Registrierung fehlgeschlagen.', 'error');
            }
        });
    }
}

// ---- CSV-Export ----
const exportBtn = document.getElementById('exportCsvBtn');
if (exportBtn) {
    exportBtn.addEventListener('click', () => {
        const table = document.getElementById('rankingTable');
        if (!table) return;

        const rows   = Array.from(table.querySelectorAll('tr'));
        const csvLines = rows.map((row) => {
            const cells = Array.from(row.querySelectorAll('th, td'));
            return cells.map((c) => `"${c.textContent.trim().replace(/"/g, '""')}"`).join(';');
        });

        const blob = new Blob(['﻿' + csvLines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'ergebnisse.csv';
        a.click();
        URL.revokeObjectURL(url);
    });
}

// ---- Ergebnisse automatisch aktualisieren (alle 30 Sekunden) ----
if (document.getElementById('rankingTable')) {
    setInterval(async () => {
        try {
            const response = await fetch('/admin/results', {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (data.success) {
                updateRankingTable(data.data.ranking);
            }
        } catch {
            // Keine Fehlermeldung bei Auto-Refresh
        }
    }, 30_000);
}

function updateRankingTable(ranking) {
    const tbody = document.querySelector('#rankingTable tbody');
    if (!tbody || !ranking) return;

    tbody.innerHTML = ranking.map((entry) => {
        const rankClass = entry.rank === 1 ? 'wt_row--gold'
            : entry.rank === 2 ? 'wt_row--silver'
            : entry.rank === 3 ? 'wt_row--bronze'
            : '';

        const pct = entry.completion_pct ?? 0;

        return `<tr class="${rankClass}">
            <td class="wt_rank">${entry.rank}.</td>
            <td>${entry.group_name}</td>
            <td class="wt_score">${parseFloat(entry.total_score ?? 0).toFixed(1).replace('.', ',')}</td>
            <td>${entry.stations_completed} / ${entry.stations_total}</td>
            <td>
                <div class="wt_progress">
                    <div class="wt_progress__bar" style="width:${pct}%"></div>
                    <span>${pct}%</span>
                </div>
            </td>
        </tr>`;
    }).join('');
}
