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

    try {
        const options = await apiFetch('/admin/login/passkey/options', {
            method: 'POST',
            body: JSON.stringify({ username }),
        });

        const publicKey = {
            ...options,
            challenge: base64UrlToBuffer(options.challenge),
            allowCredentials: (options.allowCredentials || []).map((credential) => ({
                ...credential,
                id: base64UrlToBuffer(credential.id),
            })),
        };

        const credential = await navigator.credentials.get({ publicKey });
        if (!credential) {
            throw new Error('Passkey konnte nicht abgerufen werden.');
        }

        const authResponse = credential.response;
        const payload = {
            username,
            id: credential.id,
            response: {
                clientDataJSON: bufferToBase64Url(authResponse.clientDataJSON),
                authenticatorData: bufferToBase64Url(authResponse.authenticatorData),
                signature: bufferToBase64Url(authResponse.signature),
                userHandle: authResponse.userHandle ? bufferToBase64Url(authResponse.userHandle) : null,
            },
        };

        const result = await apiFetch('/admin/login/passkey/verify', {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        if (result.redirect) {
            window.location.href = result.redirect;
        }
    } catch (err) {
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
