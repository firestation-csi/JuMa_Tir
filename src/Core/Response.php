<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Helper für JSON- und HTML-Antworten
 */
class Response
{
    /** Erfolgreiche JSON-Antwort */
    public static function json(mixed $data = null, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $payload = json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            error_log('Response JSON-Encoding fehlgeschlagen: ' . json_last_error_msg());
            $payload = '{"success":false,"error":"Interner Antwortfehler"}';
        }
        echo $payload;
        exit;
    }

    /** Fehler-JSON-Antwort */
    public static function error(string $message, int $status = 400): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $payload = json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            error_log('Response JSON-Encoding fehlgeschlagen: ' . json_last_error_msg() . ' - message: ' . $message);
            $payload = '{"success":false,"error":"Interner Antwortfehler"}';
        }
        echo $payload;
        exit;
    }

    /** Template rendern */
    public static function view(string $template, array $data = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        extract($data, EXTR_SKIP);
        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        if (!file_exists($templatePath)) {
            self::error('Template nicht gefunden: ' . $template, 500);
        }
        require $templatePath;
        exit;
    }

    /** Weiterleitung */
    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    /** 404-Seite */
    public static function notFound(string $message = 'Seite nicht gefunden'): never
    {
        self::error($message, 404);
    }
}
