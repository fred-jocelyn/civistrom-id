<?php
declare(strict_types=1);

/**
 * App — Classe principale du framework CIVISTROM ID
 *
 * Fork allégé de SENTINEL : pas de Database, pas de Session.
 * 100% client-side après le chargement initial.
 */
class App
{
    private static ?App $instance = null;
    private array $config = [];
    private Router $router;
    private Request $request;

    private function __construct()
    {
        $this->loadEnvironment();
        $this->loadConfig();

        // Timezone — doit être défini AVANT toute manipulation de dates
        $tz = $this->config['app']['timezone'] ?? 'America/Toronto';
        date_default_timezone_set($tz);

        $this->request = new Request();
        $this->router = new Router();
    }

    /**
     * Singleton — retourne l'instance unique de l'application
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Charge les variables d'environnement depuis .env
     */
    private function loadEnvironment(): void
    {
        $envFile = BASE_PATH . '/.env';
        if (!file_exists($envFile)) {
            throw new RuntimeException('Fichier .env introuvable à la racine du projet.');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Ignorer les commentaires
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Retirer les guillemets englobants (simple ou double)
                if (preg_match('/^(["\'])(.*)(\\1)$/', $value, $m)) {
                    $value = $m[2];
                }
                // Ne pas écraser les variables déjà définies
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }
        }
    }

    /**
     * Charge les fichiers de configuration depuis config/
     */
    private function loadConfig(): void
    {
        $configDir = BASE_PATH . '/config';
        // Fichiers exclus du chargement auto (chargés ailleurs)
        $exclude = ['routes'];

        if (is_dir($configDir)) {
            foreach (glob($configDir . '/*.php') as $file) {
                $key = basename($file, '.php');
                if (in_array($key, $exclude, true)) {
                    continue;
                }
                $this->config[$key] = require $file;
            }
        }
    }

    /**
     * Récupère une valeur de configuration (notation pointée)
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $config = $this->config;

        foreach ($parts as $part) {
            if (!is_array($config) || !array_key_exists($part, $config)) {
                return $default;
            }
            $config = $config[$part];
        }

        return $config;
    }

    /**
     * Lance l'application — point d'entrée principal
     */
    public function run(): void
    {
        try {
            // Charger les routes
            $routesFile = BASE_PATH . '/config/routes.php';
            if (file_exists($routesFile)) {
                require $routesFile;
            }

            // Dispatcher la requête
            $this->router->dispatch($this->request);

        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Gestion centralisée des erreurs
     */
    private function handleException(\Throwable $e): void
    {
        $debug = env('APP_DEBUG', 'false') === 'true';

        if ($debug) {
            $response = new Response();
            $response->setStatusCode(500);
            $response->setBody(
                '<div style="font-family:monospace;padding:20px;background:#0a0a0f;color:#EF4444;min-height:100vh;">'
                . '<h1>Erreur ' . $e->getCode() . '</h1>'
                . '<p><strong>' . get_class($e) . ':</strong> ' . htmlspecialchars($e->getMessage()) . '</p>'
                . '<p><strong>Fichier:</strong> ' . $e->getFile() . ':' . $e->getLine() . '</p>'
                . '<pre style="color:#eaeaea;background:#111827;padding:15px;border-radius:8px;overflow-x:auto;">'
                . htmlspecialchars($e->getTraceAsString())
                . '</pre></div>'
            );
            $response->send();
        } else {
            http_response_code(500);
            if (file_exists(BASE_PATH . '/views/errors/500.php')) {
                require BASE_PATH . '/views/errors/500.php';
            } else {
                echo '<h1>Erreur interne du serveur</h1>';
            }
        }

        // Logger l'erreur
        error_log("[CIVISTROM ID] {$e->getMessage()} dans {$e->getFile()}:{$e->getLine()}");
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
