<?php
declare(strict_types=1);

/**
 * Request — Abstraction de la requête HTTP
 *
 * Accès unifié aux données GET, POST, JSON, fichiers,
 * et paramètres de route.
 */
class Request
{
    private array $routeParams = [];
    private ?string $rawBody = null;

    /**
     * Méthode HTTP (GET, POST, PUT, DELETE)
     * Supporte _method override pour PUT/DELETE depuis les formulaires
     */
    public function method(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $override = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
            if ($override && in_array(strtoupper($override), ['PUT', 'DELETE', 'PATCH'])) {
                return strtoupper($override);
            }
        }
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * URI normalisée (sans query string)
     */
    public function uri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $uri = rawurldecode($uri);
        $uri = rtrim($uri, '/') ?: '/';
        return $uri;
    }

    /**
     * Récupère un paramètre GET
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Récupère tous les paramètres GET
     */
    public function queryAll(): array
    {
        return $_GET;
    }

    /**
     * Récupère un paramètre POST
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Récupère tous les paramètres POST
     */
    public function inputAll(): array
    {
        return $_POST;
    }

    /**
     * Retourne le body brut de la requête (lu une seule fois, puis caché)
     */
    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }
        return $this->rawBody;
    }

    /**
     * Récupère le body JSON décodé
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        static $data = null;

        if ($data === null) {
            $raw = $this->rawBody();
            $decoded = json_decode($raw, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Récupère un fichier uploadé
     */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * Récupère un paramètre de route dynamique
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Définit les paramètres de route (appelé par le Router)
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Récupère l'adresse IP du client
     */
    public function ip(): string
    {
        $trustedProxies = env('TRUSTED_PROXIES', '');

        if ($trustedProxies !== '') {
            $proxies = array_map('trim', explode(',', $trustedProxies));
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            if (in_array($remoteAddr, $proxies, true)) {
                $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
                if ($forwarded !== '') {
                    $ips = array_map('trim', explode(',', $forwarded));
                    return $ips[0];
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Vérifie si la requête est AJAX
     */
    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * Vérifie si la requête est en HTTPS
     */
    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 0) == 443;
    }

    /**
     * Retourne le header Accept
     */
    public function accepts(): string
    {
        return $_SERVER['HTTP_ACCEPT'] ?? '';
    }

    /**
     * Vérifie si le client attend du JSON
     */
    public function wantsJson(): bool
    {
        return str_contains($this->accepts(), 'application/json');
    }

    /**
     * Retourne l'URL complète de la requête
     */
    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$scheme}://{$host}{$_SERVER['REQUEST_URI']}";
    }

    /**
     * Récupère un header HTTP
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }

    /**
     * Récupère le User-Agent
     */
    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}
