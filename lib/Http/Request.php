<?php
namespace NGN\Lib\Http;

class Request
{
    private ?string $cachedBody = null;
    private array $params = [];

    public function method(): string { return $_SERVER['REQUEST_METHOD'] ?? 'GET'; }
    public function uri(): string { return $_SERVER['REQUEST_URI'] ?? '/'; }
    public function path(): string {
        $uri = $this->uri();
        $q = strpos($uri, '?');
        return $q === false ? $uri : substr($uri, 0, $q);
    }
    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
        $val = $_SERVER[$key] ?? null;
        if ($val === null && strcasecmp($name, 'Content-Type') === 0) {
            $val = $_SERVER['CONTENT_TYPE'] ?? null;
        }
        if ($val === null && strcasecmp($name, 'Content-Length') === 0) {
            $val = $_SERVER['CONTENT_LENGTH'] ?? null;
        }
        return $val ?? $default;
    }
    public function contentLength(): int { return (int)($this->header('Content-Length') ?? 0); }
    public function body(): string {
        if ($this->cachedBody !== null) return $this->cachedBody;
        $b = file_get_contents('php://input') ?: '';
        $this->cachedBody = $b;
        return $b;
    }
    public function json(): mixed { $b = $this->body(); return $b !== '' ? json_decode($b, true) : null; }
    public function query(): array { return $_GET ?? []; }

    /**
     * Set route parameters (called by router after matching parameterized routes)
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Get a route parameter by name (e.g., 'id' from /playlists/:id/items)
     */
    public function param(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Return the Bearer token from the Authorization header, if present.
     * Falls back to NGN_ADMIN_BEARER cookie for convenience in Admin UI.
     */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if (is_string($auth) && stripos($auth, 'Bearer ') === 0) {
            $tok = trim(substr($auth, 7));
            if ($tok !== '') return $tok;
        }
        // Fallback: cookie set by admin pages to ease local testing
        $cookie = $_COOKIE['NGN_ADMIN_BEARER'] ?? '';
        $cookie = is_string($cookie) ? trim($cookie) : '';
        return $cookie !== '' ? $cookie : null;
    }
}
