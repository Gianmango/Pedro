<?php
declare(strict_types=1);

namespace App;

/**
 * Router minimale.
 *   $router->get('/t/{number}', [TableController::class, 'show']);
 * I valori tra graffe arrivano al metodo come argomenti, nell'ordine in cui compaiono.
 * Tutte le richieste POST devono avere un token CSRF valido (vedi csrf_field() in helpers.php).
 */
final class Router
{
    /** @var array<int, array{0:string,1:string,2:callable|array}> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        // "/t/{number}" -> "#^/t/(?P<number>[^/]+)$#"
        $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [$method, '#^' . $pattern . '$#', $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }
            if (preg_match($regex, $path, $matches)) {
                if ($method === 'POST' && !csrf_valid()) {
                    $this->csrfFailed();
                    return;
                }
                $params = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
                $this->call($handler, $params);
                return;
            }
        }

        $this->notFound();
    }

    private function call(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $handler = [new $class(), $method];
        }
        $handler(...$params);
    }

    private function notFound(): void
    {
        http_response_code(404);
        view('errors/404');
    }

    private function csrfFailed(): void
    {
        http_response_code(419);
        // Se il file inviato supera post_max_size, PHP svuota $_POST: il token sembra mancare.
        $tooBig = empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
        view('errors/419', ['tooBig' => $tooBig]);
    }
}
