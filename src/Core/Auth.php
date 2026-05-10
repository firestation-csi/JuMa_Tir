<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session- und Authentifizierungslogik
 */
class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** Admin-Session setzen */
    public static function loginAdmin(int $competitionId): void
    {
        session_regenerate_id(true);
        $_SESSION['admin']          = true;
        $_SESSION['competition_id'] = $competitionId;
        $_SESSION['csrf_token']     = self::generateCsrfToken();
    }

    /** Schiedsrichter-Session setzen */
    public static function loginJudge(int $judgeId, int $stationId): void
    {
        session_regenerate_id(true);
        $_SESSION['judge_id']   = $judgeId;
        $_SESSION['station_id'] = $stationId;
        $_SESSION['csrf_token'] = self::generateCsrfToken();
    }

    /** Admin-Prüfung */
    public static function isAdmin(): bool
    {
        return !empty($_SESSION['admin']);
    }

    /** Schiedsrichter-Prüfung */
    public static function isJudge(): bool
    {
        return !empty($_SESSION['judge_id']);
    }

    /** Aktuelle Judge-ID */
    public static function getJudgeId(): ?int
    {
        return isset($_SESSION['judge_id']) ? (int)$_SESSION['judge_id'] : null;
    }

    /** Aktuelle Station-ID */
    public static function getStationId(): ?int
    {
        return isset($_SESSION['station_id']) ? (int)$_SESSION['station_id'] : null;
    }

    /** Aktuelle Competition-ID */
    public static function getCompetitionId(): ?int
    {
        return isset($_SESSION['competition_id']) ? (int)$_SESSION['competition_id'] : null;
    }

    /** Session beenden */
    public static function logout(): void
    {
        session_unset();
        session_destroy();
    }

    /** CSRF-Token aus Session lesen */
    public static function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateCsrfToken();
        }
        return $_SESSION['csrf_token'];
    }

    /** CSRF-Token validieren */
    public static function validateCsrf(string $token): bool
    {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    private static function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
