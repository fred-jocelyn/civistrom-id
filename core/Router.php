<?php
declare(strict_types=1);

/**
 * Router — Routing HTTP
 *
 * Supporte GET/POST/PUT/DELETE, groupes avec préfixes
 * et middlewares, paramètres dynamiques {id}.
 */
class Router
{
    private array $routes = [];
    private array $groupStack = [];

    /**
     * Enregistre une route GET
     */
    public function get(string $uri, array|callable $action, array $middleware = []): void
    {
        $this->addRoute('GET', $uri, $action, $middleware);
    }

    /**
     * Enregistre une route POST
     */
    public function post(string $uri, array|callable $action, array $middleware = []): void
    {
        $this->addRoute('POST', $uri, $action, $middleware);
    }

    /**
     * Enregistre une route PUT
     */
    public function put(string $uri, array|callable $action, array $middleware = []): void
    {
        $this->addRoute('PUT', $uri, $action, $middleware);
    }

    /**
     * Enregistre une route DELETE
     */
    public function delete(string $uri, array|callable $action, array $middleware = []): void
    {
        $this->addRoute('DELETE', $uri, $action, $middleware);
    }

    /**
     * Groupe de routes avec préfixe et middleware communs
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Dispatche la requête vers le bon handler
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['pattern'], $uri);
            if ($params !== false) {
                // Injecter les paramètres dans la requête
                $request->setRouteParams($params);

                // Exécuter les middlewares
                foreach ($route['middleware'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $result = $middleware->handle($request);
                    if ($result === false) {
                        return;
                    }
                }

                // Exécuter l'action
                $this->executeAction($route['action'], $request);
                return;
            }
        }

        // 404
        http_response_code(404);
        if (file_exists(BASE_PATH . '/views/errors/404.php')) {
            require BASE_PATH . '/views/errors/404.php';
        } else {
            echo '<h1>404 — Page non trouvée</h1>';
        }
    }

    /**
     * Ajoute une route au registre
     */
    private function addRoute(string $method, string $uri, array|callable $action, array $middleware): void
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] ?? '';
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware'] ?? []);
        }

        $fullUri = rtrim($prefix . '/' . ltrim($uri, '/'), '/') ?: '/';

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $fullUri,
            'action'     => $action,
            'middleware'  => array_merge($groupMiddleware, $middleware),
        ];
    }

    /**
     * Teste si un pattern matche l'URI
     *
     * @return array|false Paramètres extraits ou false
     */
    private function matchRoute(string $pattern, string $uri): array|false
    {
        // Convertir {param} en regex nommée
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Ne garder que les captures nommées
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    /**
     * Exécute l'action (controller ou callable)
     */
    private function executeAction(array|callable $action, Request $request): void
    {
        if (is_callable($action) && !is_array($action)) {
            $action($request);
            return;
        }

        [$controllerClass, $method] = $action;
        $controller = new $controllerClass();
        $controller->$method($request);
    }
}
