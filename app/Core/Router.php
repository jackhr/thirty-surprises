<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use Throwable;

final class Router
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function dispatch(Request $request): void
    {
        Auth::checkSession();

        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes[$method] ?? [] as $route) {
            $matches = [];
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $routeParams = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $routeParams[$key] = $value;
                }
            }
            $request->setRouteParams($routeParams);

            foreach ($route['middleware'] as $middleware) {
                if ($middleware === 'auth') {
                    Auth::requireLogin();
                }
            }

            try {
                $handler = $route['handler'];

                if (is_array($handler) && isset($handler[0], $handler[1])) {
                    [$className, $methodName] = $handler;
                    $instance = new $className();
                    $instance->{$methodName}($request);
                    return;
                }

                if ($handler instanceof Closure || is_callable($handler)) {
                    $handler($request);
                    return;
                }
            } catch (Throwable $exception) {
                View::render('error', [
                    'message' => $exception->getMessage(),
                    'status' => 500,
                ], 500);
            }
        }

        View::render('error', [
            'message' => 'Page not found',
            'status' => 404,
        ], 404);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $cleanPath = rtrim($path, '/');
        if ($cleanPath === '') {
            $cleanPath = '/';
        }

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $cleanPath);
        $regex = '#^' . $regex . '$#';

        $this->routes[$method][] = [
            'path' => $cleanPath,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }
}
