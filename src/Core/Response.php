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

        $payload = self::safeJsonEncode(['success' => true, 'data' => $data]);
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

        $payload = self::safeJsonEncode(['success' => false, 'error' => $message]);
        if ($payload === false) {
            error_log('Response JSON-Encoding fehlgeschlagen: ' . json_last_error_msg() . ' - message: ' . $message);
            $payload = '{"success":false,"error":"Interner Antwortfehler"}';
        }

        echo $payload;
        exit;
    }

    private static function safeJsonEncode(mixed $value): string|false
    {
        $payload = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($payload !== false) {
            return $payload;
        }

        $cleanValue = self::utf8ize($value);
        $payload = json_encode($cleanValue, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($payload !== false) {
            return $payload;
        }

        return false;
    }

    private static function utf8ize(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::utf8ize($item);
            }
            return $value;
        }

        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
            return $value;
        }

        return $value;
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
