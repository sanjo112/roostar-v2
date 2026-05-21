<?php

declare(strict_types=1);

namespace Roostar\Core\Http;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response|string
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $route = $this->routes[$method][$request->path] ?? null;

        if (!$route) {
            return Response::json(['error' => 'Not found'], 404);
        }

        return $this->pipeline($route['middleware'], $route['handler'])($request);
    }

    private function pipeline(array $middleware, callable $handler): callable
    {
        return array_reduce(
            array_reverse($middleware),
            static fn (callable $next, object $middleware): callable => static fn (Request $request): Response|string => $middleware->handle($request, $next),
            static fn (Request $request): Response|string => $handler($request),
        );
    }
}
