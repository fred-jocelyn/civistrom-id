<?php
declare(strict_types=1);

/**
 * Controller — Classe de base pour les contrôleurs CIVISTROM ID
 *
 * Fournit render, json, redirect.
 * Pas de validation (pas de BDD, pas de formulaires côté serveur).
 */
class Controller
{
    /**
     * Rend une vue avec layout
     */
    protected function render(string $view, array $data = [], string $layout = 'id'): void
    {
        View::render($view, $data, $layout);
    }

    /**
     * Rend un partial (sans layout)
     */
    protected function partial(string $view, array $data = []): void
    {
        View::partial($view, $data);
    }

    /**
     * Réponse JSON
     */
    protected function json(mixed $data, int $statusCode = 200): void
    {
        Response::json($data, $statusCode);
    }

    /**
     * Redirection
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        Response::redirect($url, $statusCode);
    }
}
