import { apiFetch, base64UrlToBuffer, bufferToBase64Url, showMessage } from './app.js';

function getUsername() {
    const input = document.getElementById('username');
    return input ? input.value.trim() : '';
}

function showLoginError(message) {
    const wrap = document.getElementById('loginErrorWrap');
    const el = document.getElementById('loginError');
    if (!wrap || !el) return;
    el.textContent = message;
    wrap.style.display = 'block';
}

async function handlePasskeyLogin() {
    const username = getUsername();
    if (!username) {
        showLoginError('Bitte deinen Benutzernamen eingeben.');
        return;
    }

    // Prüfe WebAuthn-Unterstützung
    if (!window.PublicKeyCredential) {
        showLoginError('WebAuthn wird von diesem Browser nicht unterstützt.');
        return;
    }

    try {
        console.log('Starte Passkey-Login für:', username);

        const options = await apiFetch('/admin/login/passkey/options', {
            method: 'POST',
            body: JSON.stringify({ username }),
        });

        console.log('Erhaltene Options:', options);

        if (!options.allowCredentials || options.allowCredentials.length === 0) {
            throw new Error('Keine Passkeys für diesen Benutzer registriert.');
        }

        const publicKey = {
            ...options,
            challenge: base64UrlToBuffer(options.challenge),
            allowCredentials: options.allowCredentials.map((credential) => ({
                ...credential,
                id: base64UrlToBuffer(credential.id),
            })),
        };

        console.log('PublicKey für navigator.credentials.get:', publicKey);

        const credential = await navigator.credentials.get({ publicKey });
        if (!credential) {
            throw new Error('Passkey konnte nicht abgerufen werden.');
        }

        console.log('Erhaltene Credential:', credential);

        const authResponse = credential.response;
        const payload = {
            username,
            id: credential.id,
            rawId: bufferToBase64Url(credential.rawId),
            response: {
                clientDataJSON: bufferToBase64Url(authResponse.clientDataJSON),
                authenticatorData: bufferToBase64Url(authResponse.authenticatorData),
                signature: bufferToBase64Url(authResponse.signature),
                userHandle: authResponse.userHandle ? bufferToBase64Url(authResponse.userHandle) : null,
            },
        };

        console.log('Credential Response:', authResponse);
        console.log('Credential ID type:', typeof credential.id);
        console.log('Credential ID value:', credential.id);
        console.log('Credential ID length:', credential.id.length);
        console.log('Credential rawId length:', credential.rawId?.byteLength ?? null);
        console.log('Payload für Server:', payload);

        const result = await apiFetch('/admin/login/passkey/verify', {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        console.log('Verifikationsergebnis:', result);

        if (result.redirect) {
            window.location.href = result.redirect;
        }
    } catch (err) {
        console.error('Passkey-Login Fehler:', err);
        showLoginError(err.message || 'Passkey-Login fehlgeschlagen.');
    }
}

const passkeyButton = document.getElementById('passkeyLoginBtn');
if (passkeyButton) {
    if (!window.PublicKeyCredential) {
        passkeyButton.style.display = 'none';
    } else {
        passkeyButton.addEventListener('click', async (event) => {
            event.preventDefault();
            showLoginError('');
            await handlePasskeyLogin();
        });
    }
}
