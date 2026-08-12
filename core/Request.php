<?php

declare(strict_types=1);

namespace Core;

class Request
{
    private ?array $jsonBody = null;

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        return rtrim($uri, '/') ?: '/';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post($key, $this->get($key, $default));
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->json());
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function json(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (is_string($contentType) && str_contains(strtolower($contentType), 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->jsonBody = $decoded;
                return $this->jsonBody;
            }
        }

        $this->jsonBody = [];
        return $this->jsonBody;
    }

    public function isJson(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains(strtolower($contentType), 'application/json');
    }

    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
