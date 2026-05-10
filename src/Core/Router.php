<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Einfacher URL-Router mit Platzhalter-Unterstützung
 */
class Router
{
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = $request->path();

        $methodRoutes = $this->routes[$method] ?? [];

        foreach ($methodRoutes as $pattern => $handler) {
            $params = $this->match($pattern, $path);
            if ($params !== null) {
                [$class, $action] = $handler;
                $controller = new $class($request);
                $controller->$action(...array_values($params));
                return;
            }
        }

        Response::notFound('Route nicht gefunden: ' . $path);
    }

    /**
     * Prüft ob ein Pfad auf ein Muster passt und gibt Platzhalter-Werte zurück.
     * Gibt null zurück wenn kein Match.
     */
    private function match(string $pattern, string $path): ?array
    {
        // Platzhalter {name} in Regex umwandeln
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        // Nur benannte Gruppen zurückgeben
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
