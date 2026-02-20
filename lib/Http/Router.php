<?php
namespace NGN\Lib\Http;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, callable $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    public function patch(string $path, callable $handler): void
    {
        $this->routes['PATCH'][$path] = $handler;
    }

    /**
     * Group routes under a common prefix
     */
    public function group(string $prefix, callable $callback): void
    {
        $groupRouter = new class($this, $prefix) {
            private $parent;
            private $prefix;

            public function __construct($parent, $prefix) {
                $this->parent = $parent;
                $this->prefix = '/' . trim($prefix, '/');
            }

            public function get($path, $handler) { $this->parent->get($this->prefix . '/' . ltrim($path, '/'), $handler); }
            public function post($path, $handler) { $this->parent->post($this->prefix . '/' . ltrim($path, '/'), $handler); }
            public function put($path, $handler) { $this->parent->put($this->prefix . '/' . ltrim($path, '/'), $handler); }
            public function delete($path, $handler) { $this->parent->delete($this->prefix . '/' . ltrim($path, '/'), $handler); }
            public function patch($path, $handler) { $this->parent->patch($this->prefix . '/' . ltrim($path, '/'), $handler); }
            public function group($prefix, $callback) { $this->parent->group($this->prefix . '/' . ltrim($prefix, '/'), $callback); }
        };

        $callback($groupRouter);
    }

    public function dispatch(Request $req): ?callable
    {
        $method = $req->method();
        $path = $req->path();
        // Normalize prefix if deployed under /api/v1 directory
        $path = preg_replace('#^/api/v1#', '', $path);
        if ($path === '') $path = '/';

        // First try exact match
        if (isset($this->routes[$method][$path])) {
            return $this->routes[$method][$path];
        }

        // Then try pattern matching for parameterized routes
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $pattern => $handler) {
                // Convert pattern like /playlists/:id/items to regex
                // Match :param format and convert to regex group
                $regex = preg_replace('#:(\w+)#', '(?<$1>[^/]+)', $pattern);
                $regex = '#^' . $regex . '$#';

                if (preg_match($regex, $path, $matches)) {
                    // Extract and set route parameters on the request object
                    $params = array_filter($matches, function($key) {
                        return !is_numeric($key) && $key !== 0;
                    }, ARRAY_FILTER_USE_KEY);
                    $req->setParams($params);
                    return $handler;
                }
            }
        }

        return null;
    }

    /**
     * Extract route parameters from a path given a pattern
     */
    public function extractParams(string $pattern, string $path): array
    {
        $regex = preg_replace('#:(\w+)#', '(?<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // Filter out numeric keys and full match
            return array_filter($matches, function($key) {
                return !is_numeric($key) && $key !== 0;
            }, ARRAY_FILTER_USE_KEY);
        }

        return [];
    }
}
