<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router with route groups and middleware.
 * Paths support named parameters: /admin/users/{id}
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:array|callable,middleware:array}> */
    private array $routes = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $routes): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMiddleware = $this->groupMiddleware;
        $this->groupPrefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $routes($this);
        $this->groupPrefix = $prevPrefix;
        $this->groupMiddleware = $prevMiddleware;
    }

    private function add(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $pattern = rtrim($this->groupPrefix . $path, '/') ?: '/';
        // {id}-style parameters are numeric; {key} is a lowercase slug.
        $regex = '#^' . preg_replace_callback(
            '#\{([a-z_]+)\}#',
            fn ($m) => $m[1] === 'key' ? '(?P<key>[a-z_]+)' : '(?P<' . $m[1] . '>[0-9]+)',
            $pattern
        ) . '$#';
        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): mixed
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $allowed[] = $route['method'];
                continue;
            }
            $request->params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            $core = function (Request $req) use ($route) {
                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    return (new $class())->$action($req);
                }
                return $handler($req);
            };

            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                fn (callable $next, string $mw) => function (Request $req) use ($mw, $next) {
                    [$class, $args] = self::parseMiddleware($mw);
                    return (new $class(...$args))->handle($req, $next);
                },
                $core
            );
            return $pipeline($request);
        }

        throw new HttpException($allowed ? 405 : 404);
    }

    /** "role:admin,advisor" => [RoleMiddleware::class, [['admin','advisor']]] */
    private static function parseMiddleware(string $definition): array
    {
        [$name, $args] = array_pad(explode(':', $definition, 2), 2, null);
        $map = [
            'auth'     => \App\Middleware\AuthMiddleware::class,
            'guest'    => \App\Middleware\GuestMiddleware::class,
            'csrf'     => \App\Middleware\CsrfMiddleware::class,
            'role'     => \App\Middleware\RoleMiddleware::class,
            'password' => \App\Middleware\PasswordChangedMiddleware::class,
        ];
        $class = $map[$name] ?? throw new \LogicException("Unknown middleware: $name");
        return [$class, $args === null ? [] : [explode(',', $args)]];
    }
}
