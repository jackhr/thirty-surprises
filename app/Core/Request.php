<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $routeParams = [];

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $server
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = $uriPath ? rtrim($uriPath, '/') : '/';
        if ($path === '') {
            $path = '/';
        }

        $query = $_GET;
        $body = $_POST;

        $rawInput = file_get_contents('php://input') ?: '';
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if ($method !== 'GET' && $method !== 'POST' && $rawInput !== '') {
            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($rawInput, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            } else {
                $parsed = [];
                parse_str($rawInput, $parsed);
                if (is_array($parsed) && $parsed !== []) {
                    $body = $parsed;
                }
            }
        }

        $override = $body['_method'] ?? $query['_method'] ?? null;
        if (is_string($override) && $override !== '') {
            $method = strtoupper($override);
            unset($body['_method']);
        }

        return new self($method, $path, $query, $body, $_SERVER);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    public function isAjax(): bool
    {
        return strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }
}
