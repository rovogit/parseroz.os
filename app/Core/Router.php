<?php

namespace App\Core;

class Router
{
    protected array $routes = [];

    public function addRoute(string $uri, string $controller, string $action, string $method = 'GET'): void
    {
        $uri = $this->normalizeUri($uri);
        $this->routes[$method][$uri] = [
            'controller' => $controller,
            'action'     => $action
        ];
    }

    public function dispatch(): void
    {
        $uri = $this->getCurrentUri();
        $method = $_SERVER['REQUEST_METHOD'];

        if (isset($this->routes[$method][$uri])) {
            $controllerClass = $this->routes[$method][$uri]['controller'];
            $action = $this->routes[$method][$uri]['action'];

            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
                if (method_exists($controller, $action)) {
                    $controller->$action();
                    return;
                }
            }
        }

        http_response_code(404);
        echo "404 Not Found (URI: " . htmlspecialchars($uri) . ")";
    }

    private function normalizeUri(string $uri): string
    {
        return ($uri !== '/') ? rtrim($uri, '/') : $uri;
    }

    private function getCurrentUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return $this->normalizeUri($uri);
    }
}