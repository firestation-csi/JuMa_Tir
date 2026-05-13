<?php

declare(strict_types=1);

namespace App\Service;

use UnexpectedValueException;

class WebauthnService
{
    public const ALG_ES256 = -7;
    public const KTY_EC2 = 2;
    public const CRV_P256 = 1;

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        // Entferne mögliche Leerzeichen oder andere Zeichen
        $value = trim($value);

        // Prüfe, ob es bereits binäre Daten sind (nicht base64)
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            // Enthält nicht-ASCII-Zeichen - könnte bereits binär sein
            return $value;
        }

        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new UnexpectedValueException('Ungültiges base64url-codiertes Feld: ' . $value);
        }
        return $decoded;
    }

    public static function generateChallenge(int $length = 32): string
    {
        return random_bytes($length);
    }

    public static function getRpId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return preg_replace('/:[0-9]+$/', '', $host);
    }

    public static function getOrigin(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            ? 'https'
            : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    public static function createRegistrationOptions(array $user, array $existingCredentials = []): array
    {
        $challenge = self::generateChallenge();
        $_SESSION['webauthn_registration_challenge'] = self::base64UrlEncode($challenge);

        return [
            'challenge'              => self::base64UrlEncode($challenge),
            'rp'                     => [
                'name' => 'JuMa Wettbewerbs-Auswertung',
                'id'   => self::getRpId(),
            ],
            'user'                   => $user,
            'pubKeyCredParams'       => [
                ['type' => 'public-key', 'alg' => self::ALG_ES256],
            ],
            'authenticatorSelection' => [
                'userVerification' => 'preferred',
            ],
            'timeout'                => 60000,
            'attestation'            => 'none',
            'excludeCredentials'     => array_map(fn(array $credential) => [
                'type' => 'public-key',
                'id'   => self::base64UrlEncode($credential['credential_id']),
            ], $existingCredentials),
        ];
    }

    public static function createAuthenticationOptions(array $credentials = []): array
    {
        $challenge = self::generateChallenge();
        $_SESSION['webauthn_authentication_challenge'] = self::base64UrlEncode($challenge);

        return [
            'challenge'        => self::base64UrlEncode($challenge),
            'rpId'             => self::getRpId(),
            'timeout'          => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => $credentials,
        ];
    }

    public static function verifyAttestationResponse(
        string $clientDataJson,
        string $attestationObject,
        string $expectedChallenge,
        string $rpId
    ): array {
        $clientDataJsonDecoded = self::base64UrlDecode($clientDataJson);
        $clientData = json_decode($clientDataJsonDecoded, true);
        if (!is_array($clientData)) {
            throw new UnexpectedValueException('Ungültige clientDataJSON.');
        }

        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new UnexpectedValueException('Ungültiger WebAuthn-Typ.');
        }

        if (self::base64UrlEncode(self::base64UrlDecode((string)($clientData['challenge'] ?? ''))) !== $expectedChallenge) {
            throw new UnexpectedValueException('Challenge stimmt nicht überein.');
        }

        if (($clientData['origin'] ?? '') !== self::getOrigin()) {
            throw new UnexpectedValueException('Ursprung nicht gültig.');
        }

        $attestation = self::parseAttestationObject(self::base64UrlDecode($attestationObject));
        if ($attestation['fmt'] !== 'none') {
            throw new UnexpectedValueException('Nur Attestation vom Typ "none" wird unterstützt.');
        }

        $authData = self::parseAuthenticatorData($attestation['authData']);
        if (($authData['flags'] & 0x01) === 0) {
            throw new UnexpectedValueException('Benutzerpräsenz-Flag fehlt.');
        }

        $rpIdHash = hash('sha256', $rpId, true);
        if ($authData['rpIdHash'] !== $rpIdHash) {
            throw new UnexpectedValueException('RP-ID-Hash ungültig.');
        }

        if (empty($authData['credentialId']) || empty($authData['credentialPublicKey'])) {
            throw new UnexpectedValueException('Kein gültiger Passkey gefunden.');
        }

        return [
            'credentialId' => $authData['credentialId'],
            'publicKey'    => $authData['credentialPublicKeyRaw'] ?? '',
            'signCount'    => $authData['signCount'],
        ];
    }

    public static function verifyAuthenticationResponse(
        string $clientDataJson,
        string $authenticatorData,
        string $signature,
        string $publicKeyBytes,
        string $expectedChallenge,
        string $rpId,
        int $previousSignCount
    ): int {
        $clientDataJsonDecoded = self::base64UrlDecode($clientDataJson);
        $clientData = json_decode($clientDataJsonDecoded, true);
        if (!is_array($clientData)) {
            throw new UnexpectedValueException('Ungültige clientDataJSON.');
        }

        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new UnexpectedValueException('Ungültiger WebAuthn-Typ.');
        }

        if (self::base64UrlEncode(self::base64UrlDecode((string)($clientData['challenge'] ?? ''))) !== $expectedChallenge) {
            throw new UnexpectedValueException('Challenge stimmt nicht überein.');
        }

        if (($clientData['origin'] ?? '') !== self::getOrigin()) {
            throw new UnexpectedValueException('Ursprung nicht gültig.');
        }

        $authData = self::parseAuthenticatorData(self::base64UrlDecode($authenticatorData));
        $rpIdHash = hash('sha256', $rpId, true);
        if ($authData['rpIdHash'] !== $rpIdHash) {
            throw new UnexpectedValueException('RP-ID-Hash ungültig.');
        }

        if (($authData['flags'] & 0x01) === 0) {
            throw new UnexpectedValueException('Benutzerpräsenz-Flag fehlt.');
        }

        $publicKey = self::coseKeyToPem(self::decodeCbor($publicKeyBytes));
        $verifyData = self::base64UrlDecode($authenticatorData) . hash('sha256', $clientDataJsonDecoded, true);

        $decodedSignature = self::base64UrlDecode($signature);

        // WebAuthn signatures are raw r,s values - convert to DER format for OpenSSL
        $derSignature = self::encodeEcdsaSignatureDer($decodedSignature);

        $signatureResult = openssl_verify($verifyData, $derSignature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($signatureResult !== 1) {
            throw new UnexpectedValueException('Fehler bei der Signaturprüfung.');
        }

        if ($authData['signCount'] > $previousSignCount) {
            return $authData['signCount'];
        }

        return $previousSignCount;
    }

    public static function parseAttestationObject(string $raw): array
    {
        $data = self::decodeCbor($raw);
        if (!is_array($data) || !isset($data['fmt'], $data['authData'], $data['attStmt'])) {
            throw new UnexpectedValueException('Ungültiges Attestationsobjekt.');
        }
        return $data;
    }

    public static function parseAuthenticatorData(string $raw): array
    {
        if (strlen($raw) < 37) {
            throw new UnexpectedValueException('Ungültige Authenticator-Daten.');
        }

        $rpIdHash = substr($raw, 0, 32);
        $flags = ord($raw[32]);
        $signCount = unpack('N', substr($raw, 33, 4))[1];
        $pos = 37;

        $result = [
            'rpIdHash'             => $rpIdHash,
            'flags'                => $flags,
            'signCount'            => $signCount,
            'credentialId'         => '',
            'credentialPublicKey'  => null,
            'aaguid'               => null,
        ];

        if ($flags & 0x40) {
            $result['aaguid'] = substr($raw, $pos, 16);
            $pos += 16;
            $credentialIdLength = unpack('n', substr($raw, $pos, 2))[1];
            $pos += 2;
            $result['credentialId'] = substr($raw, $pos, $credentialIdLength);
            $pos += $credentialIdLength;
            $publicKeyStart = $pos;
            $publicKey = self::decodeCbor($raw, $pos);
            $result['credentialPublicKey'] = $publicKey;
            $result['credentialPublicKeyRaw'] = substr($raw, $publicKeyStart, $pos - $publicKeyStart);
        }

        return $result;
    }

    public static function decodeCbor(string $input, int &$pos = 0): mixed
    {
        if ($pos >= strlen($input)) {
            throw new UnexpectedValueException('CBOR-Daten sind zu kurz.');
        }

        $initialByte = ord($input[$pos++]);
        $majorType = $initialByte >> 5;
        $additionalInfo = $initialByte & 0x1f;
        $length = self::readCborLength($input, $pos, $additionalInfo);

        switch ($majorType) {
            case 0:
                return $length;
            case 1:
                return -1 - $length;
            case 2:
                $data = substr($input, $pos, $length);
                $pos += $length;
                return $data;
            case 3:
                $data = substr($input, $pos, $length);
                $pos += $length;
                return $data;
            case 4:
                $items = [];
                if ($length < 0) {
                    while (true) {
                        if ($pos >= strlen($input)) {
                            throw new UnexpectedValueException('Unvollständiges CBOR-Array.');
                        }
                        if (ord($input[$pos]) === 0xff) {
                            $pos++;
                            break;
                        }
                        $items[] = self::decodeCbor($input, $pos);
                    }
                } else {
                    for ($i = 0; $i < $length; $i++) {
                        $items[] = self::decodeCbor($input, $pos);
                    }
                }
                return $items;
            case 5:
                $map = [];
                if ($length < 0) {
                    while (true) {
                        if ($pos >= strlen($input)) {
                            throw new UnexpectedValueException('Unvollständige CBOR-Map.');
                        }
                        if (ord($input[$pos]) === 0xff) {
                            $pos++;
                            break;
                        }
                        $key = self::decodeCbor($input, $pos);
                        $value = self::decodeCbor($input, $pos);
                        $map[$key] = $value;
                    }
                } else {
                    for ($i = 0; $i < $length; $i++) {
                        $key = self::decodeCbor($input, $pos);
                        $value = self::decodeCbor($input, $pos);
                        $map[$key] = $value;
                    }
                }
                return $map;
            case 6:
                return self::decodeCbor($input, $pos);
            case 7:
                if ($additionalInfo === 20) {
                    return false;
                }
                if ($additionalInfo === 21) {
                    return true;
                }
                if ($additionalInfo === 22) {
                    return null;
                }
                if ($additionalInfo === 23) {
                    return null;
                }
                if ($additionalInfo === 24) {
                    $simple = ord($input[$pos++]);
                    return $simple;
                }
                if ($additionalInfo === 25) {
                    $data = substr($input, $pos, 2);
                    $pos += 2;
                    $value = unpack('n', $data)[1];
                    return $value;
                }
                if ($additionalInfo === 26) {
                    $data = substr($input, $pos, 4);
                    $pos += 4;
                    $value = unpack('N', $data)[1];
                    return $value;
                }
                if ($additionalInfo === 27) {
                    $data = substr($input, $pos, 8);
                    $pos += 8;
                    $value = unpack('J', $data)[1] ?? null;
                    return $value;
                }
                return null;
            default:
                throw new UnexpectedValueException('Unbekannter CBOR-Haupttyp.');
        }
    }

    private static function readCborLength(string $input, int &$pos, int $additionalInfo): int
    {
        if ($additionalInfo < 24) {
            return $additionalInfo;
        }

        if ($additionalInfo === 24) {
            $value = ord($input[$pos++]);
            return $value;
        }

        if ($additionalInfo === 25) {
            $data = substr($input, $pos, 2);
            $pos += 2;
            return unpack('n', $data)[1];
        }

        if ($additionalInfo === 26) {
            $data = substr($input, $pos, 4);
            $pos += 4;
            return unpack('N', $data)[1];
        }

        if ($additionalInfo === 27) {
            $data = substr($input, $pos, 8);
            $pos += 8;
            return unpack('J', $data)[1] ?? 0;
        }

        if ($additionalInfo === 31) {
            return -1;
        }

        throw new UnexpectedValueException('Ungültige CBOR-Länge.');
    }

    private static function coseKeyToPem(array $cose): string
    {
        if (($cose[1] ?? null) !== self::KTY_EC2 || ($cose[3] ?? null) !== self::ALG_ES256 || ($cose[-1] ?? null) !== self::CRV_P256) {
            throw new UnexpectedValueException('Nur EC2 P-256-Public-Key wird unterstützt.');
        }

        $x = $cose[-2] ?? null;
        $y = $cose[-3] ?? null;
        if (!is_string($x) || !is_string($y)) {
            throw new UnexpectedValueException('Ungültiges COSE Public Key.');
        }

        $rawPublicKey = "\x04" . $x . $y;
        $oidEcPublicKey = hex2bin('06072a8648ce3d0201');
        $oidPrime256v1 = hex2bin('06082a8648ce3d030107');
        $algorithm = "\x30" . self::encodeLength(strlen($oidEcPublicKey . $oidPrime256v1)) . $oidEcPublicKey . $oidPrime256v1;
        $bitString = "\x03" . self::encodeLength(strlen($rawPublicKey) + 1) . "\x00" . $rawPublicKey;
        $spki = "\x30" . self::encodeLength(strlen($algorithm . $bitString)) . $algorithm . $bitString;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $hex = dechex($length);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        $binary = hex2bin($hex);
        return chr(0x80 | strlen($binary)) . $binary;
    }

    private static function encodeEcdsaSignatureDer(string $rawSignature): string
    {
        if (strlen($rawSignature) !== 64) {
            throw new UnexpectedValueException('Rohe ECDSA-Signatur muss 64 Bytes lang sein.');
        }

        $r = substr($rawSignature, 0, 32);
        $s = substr($rawSignature, 32, 32);

        // Remove leading zeros from r and s
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        // Ensure r and s are not empty (add back one zero if they were all zeros)
        if ($r === '') {
            $r = "\x00";
        }
        if ($s === '') {
            $s = "\x00";
        }

        // Add leading zero if high bit is set (to ensure positive integer)
        if ((ord($r[0]) & 0x80) !== 0) {
            $r = "\x00" . $r;
        }
        if ((ord($s[0]) & 0x80) !== 0) {
            $s = "\x00" . $s;
        }

        $rEncoded = "\x02" . chr(strlen($r)) . $r;
        $sEncoded = "\x02" . chr(strlen($s)) . $s;

        $sequence = $rEncoded . $sEncoded;
        return "\x30" . chr(strlen($sequence)) . $sequence;
    }
}
}
