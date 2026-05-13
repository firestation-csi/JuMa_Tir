<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\AdminUser;
use App\Model\AdminUserCredential;
use App\Model\Competition;
use App\Service\WebauthnService;

class AdminWebauthnController
{
    public function __construct(private Request $request)
    {
    }

    public function loginOptions(): void
    {
        $body = $this->request->json();
        $username = trim((string)($this->request->method() === 'GET'
            ? $this->request->get('username', '')
            : ($body['username'] ?? '')));

        if ($username === '') {
            Response::error('Benutzername ist erforderlich.', 400);
        }

        $userModel = new AdminUser();
        $user = $userModel->findByUsername($username);
        if (!$user) {
            Response::error('Benutzer nicht gefunden.', 404);
        }

        $credentialModel = new AdminUserCredential();
        $credentials = $credentialModel->findByUserId((int)$user['id']);
        if (empty($credentials)) {
            Response::error('Für diesen Benutzer sind keine Passkeys registriert.', 404);
        }

        $options = WebauthnService::createAuthenticationOptions(array_map(
            fn(array $credential) => [
                'type' => 'public-key',
                'id'   => WebauthnService::base64UrlEncode($credential['credential_id']),
            ],
            $credentials
        ));

        Response::json($options);
    }

    public function loginVerify(): void
    {
        try {
            $body = $this->request->json();
            $username = trim((string)($body['username'] ?? ''));
            $credentialId = (string)($body['id'] ?? '');
            $response = $body['response'] ?? [];

            error_log("WebAuthn Login Debug - Username: $username");
            error_log("WebAuthn Login Debug - CredentialId: $credentialId");
            error_log("WebAuthn Login Debug - Response keys: " . implode(', ', array_keys($response)));

            if ($username === '' || $credentialId === '' || !is_array($response)) {
                Response::error('Ungültige Anmeldedaten.', 400);
            }

            $userModel = new AdminUser();
            $user = $userModel->findByUsername($username);
            if (!$user) {
                Response::error('Benutzer nicht gefunden.', 404);
            }

            $credentialModel = new AdminUserCredential();
            $rawId = (string)($body['rawId'] ?? '');
            error_log("WebAuthn Login Debug - rawId length: " . strlen($rawId));
            error_log("WebAuthn Login Debug - raw credentialId length: " . strlen($credentialId));

            $credential = null;
            if ($rawId !== '') {
                try {
                    $decodedCredentialId = WebauthnService::base64UrlDecode(trim($rawId));
                    error_log("WebAuthn Login Debug - Decoded rawId: " . bin2hex($decodedCredentialId));
                    $credential = $credentialModel->findByCredentialId($decodedCredentialId);
                } catch (\Exception $e) {
                    error_log("WebAuthn Login Debug - rawId decode failed: " . $e->getMessage());
                }
            }

            if ($credential === null) {
                try {
                    $cleanCredentialId = trim($credentialId);
                    error_log("WebAuthn Login Debug - Clean CredentialId: '$cleanCredentialId'");
                    $decodedCredentialId = WebauthnService::base64UrlDecode($cleanCredentialId);
                    error_log("WebAuthn Login Debug - Decoded CredentialId: " . bin2hex($decodedCredentialId));
                    $credential = $credentialModel->findByCredentialId($decodedCredentialId);
                } catch (\Exception $e) {
                    error_log("WebAuthn Login Debug - CredentialId decode failed: " . $e->getMessage());
                }
            }

            if (!$credential) {
                Response::error('Passkey ungültig.', 400);
            }

            if (!$credential || (int)$credential['admin_user_id'] !== (int)$user['id']) {
                Response::error('Passkey ungültig.', 400);
            }

            $challenge = $_SESSION['webauthn_authentication_challenge'] ?? '';
            if ($challenge === '') {
                Response::error('Keine gültige Authentifizierungs-Challenge vorhanden.', 400);
            }

            try {
                $newSignCount = WebauthnService::verifyAuthenticationResponse(
                    (string)($response['clientDataJSON'] ?? ''),
                    WebauthnService::base64UrlDecode((string)($response['authenticatorData'] ?? '')),
                    WebauthnService::base64UrlDecode((string)($response['signature'] ?? '')),
                    $credential['public_key'],
                    $challenge,
                    WebauthnService::getRpId(),
                    (int)$credential['sign_count']
                );
            } catch (\Exception $e) {
                error_log("WebAuthn Login Debug - Verification failed: " . $e->getMessage());
                Response::error($e->getMessage(), 400);
            }

            $credentialModel->updateSignCount((int)$credential['id'], $newSignCount);

            $competition = (new Competition())->findActive();
            $competitionId = $competition ? (int)$competition['id'] : 0;
            Auth::loginAdmin($competitionId, (int)$user['id'], $username);
            Response::json(['redirect' => '/admin']);
        } catch (\Throwable $e) {
            error_log("WebAuthn Login Debug - Unexpected error: " . $e->getMessage());
            Response::error('Interner Serverfehler.', 500);
        }
    }

    public function options(): void
    {
        Response::json(['ok' => true]);
    }

    public function registrationOptions(string $id): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }

        $userModel = new AdminUser();
        $user = $userModel->findById((int)$id);
        if (!$user) {
            Response::notFound('Benutzer nicht gefunden.');
        }

        $credentialModel = new AdminUserCredential();
        $existingCredentials = $credentialModel->findByUserId((int)$user['id']);

        $options = WebauthnService::createRegistrationOptions([
            'id'          => WebauthnService::base64UrlEncode(pack('N', (int)$user['id'])),
            'name'        => $user['username'],
            'displayName' => $user['display_name'] ?: $user['username'],
        ], $existingCredentials);

        Response::json($options);
    }

    public function register(string $id): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }

        $userModel = new AdminUser();
        $user = $userModel->findById((int)$id);
        if (!$user) {
            Response::notFound('Benutzer nicht gefunden.');
        }

        $body = $this->request->json();
        $response = $body['response'] ?? [];

        if (!is_array($response)) {
            Response::error('Ungültige Registrierungsdaten.', 400);
        }

        $challenge = $_SESSION['webauthn_registration_challenge'] ?? '';
        if ($challenge === '') {
            Response::error('Keine gültige Registrierungs-Challenge vorhanden.', 400);
        }

        try {
            $result = WebauthnService::verifyAttestationResponse(
                (string)($response['clientDataJSON'] ?? ''),
                (string)($response['attestationObject'] ?? ''),
                $challenge,
                WebauthnService::getRpId()
            );
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }

        $credentialModel = new AdminUserCredential();
        $credentialModel->create(
            (int)$user['id'],
            $result['credentialId'],
            $result['publicKey'],
            $result['signCount'],
            $body['name'] ?? ''
        );

        Response::json(['redirect' => '/admin/users/' . (int)$user['id'] . '/edit']);
    }

    public function deleteCredential(string $id): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }

        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }

        $credentialId = trim((string)$this->request->post('credential_id', ''));
        if ($credentialId === '') {
            Response::error('Ungültige Passkey-ID.', 400);
        }

        $credentialModel = new AdminUserCredential();
        $credentialModel->deleteByCredentialId(WebauthnService::base64UrlDecode($credentialId));

        Response::redirect('/admin/users/' . (int)$id . '/edit');
    }
}
