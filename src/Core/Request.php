<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Eingabe-Sanitierung und Request-Daten
 */
class Request
{
    private array $get;
    private array $post;
    private array $server;

    public function __construct()
    {
        $this->get    = $_GET;
        $this->post   = $_POST;
        $this->server = $_SERVER;
    }

    /** Bereinigter GET-Parameter */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->sanitize($this->get[$key] ?? $default);
    }

    /** Bereinigter POST-Parameter */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->sanitize($this->post[$key] ?? $default);
    }

    /** JSON-Body aus dem Request lesen */
    public function json(): array
    {
        $body = file_get_contents('php://input');
        if (empty($body)) {
            return [];
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    /** HTTP-Methode */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /** Anfrage-Pfad ohne Query-String */
    public function path(): string
    {
        $uri  = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return '/' . trim($path ?: '/', '/');
    }

    /** Prüft ob die Anfrage JSON erwartet */
    public function isJson(): bool
    {
        $accept = $this->server['HTTP_ACCEPT'] ?? '';
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json');
    }

    /** Wert bereinigen (rekursiv bei Arrays) */
    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        if (is_string($value)) {
            return trim(htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return $value;
    }

    /** CSRF-Token aus Session oder POST */
    public function getCsrfToken(): string
    {
        return $this->post['csrf_token'] ?? '';
    }
}
