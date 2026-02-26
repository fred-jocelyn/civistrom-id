<?php
declare(strict_types=1);

/**
 * Response — Abstraction de la réponse HTTP
 *
 * Gère le status code, les headers, le body,
 * et les réponses JSON.
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';

    /**
     * Définit le code de statut HTTP
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Ajoute un header HTTP
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Définit le corps de la réponse
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Envoie la réponse au client
     */
    public function send(): void
    {
        self::sendStatusCode($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }

    /**
     * Réponse JSON
     */
    public static function json(mixed $data, int $statusCode = 200): void
    {
        self::sendStatusCode($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Redirection HTTP
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        self::sendStatusCode($statusCode);
        header("Location: {$url}");
        exit;
    }

    /**
     * Réponse 403 — Accès refusé
     */
    public static function forbidden(string $message = 'Accès refusé'): void
    {
        self::sendStatusCode(403);
        if (file_exists(BASE_PATH . '/views/errors/403.php')) {
            require BASE_PATH . '/views/errors/403.php';
        } else {
            echo "<h1>403 — {$message}</h1>";
        }
        exit;
    }

    /**
     * Envoie le status code HTTP de manière fiable
     */
    private static function sendStatusCode(int $code): void
    {
        http_response_code($code);

        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        $phrases = [
            200 => 'OK', 201 => 'Created', 204 => 'No Content',
            301 => 'Moved Permanently', 302 => 'Found', 304 => 'Not Modified',
            400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required',
            403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed',
            409 => 'Conflict', 422 => 'Unprocessable Entity', 429 => 'Too Many Requests',
            500 => 'Internal Server Error', 503 => 'Service Unavailable',
        ];
        $phrase = $phrases[$code] ?? '';
        header("{$protocol} {$code} {$phrase}", true, $code);
    }
}
