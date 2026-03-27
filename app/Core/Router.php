<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<array{0:string,1:string,2:callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as [$method, $path, $handler]) {
            if ($method !== $request->method || $path !== $request->path) {
                continue;
            }
            $handler();
            return;
        }

        http_response_code(404);
        View::render('errors/404', [
            'title' => 'Page not found',
        ]);
    }
}
