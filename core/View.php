<?php
declare(strict_types=1);

/**
 * View — Moteur de templates PHP natif
 *
 * Layouts, sections, partials — sans dépendance externe.
 */
class View
{
    private static ?string $layout = null;
    private static array $sections = [];
    private static ?string $currentSection = null;
    private static array $layoutData = [];

    /**
     * Rend une vue avec un layout optionnel
     */
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        // Forcer 200 (nginx try_files peut propager un 404 interne au FastCGI)
        if (http_response_code() === 404) {
            http_response_code(200);
        }

        self::$layout = $layout;
        self::$layoutData = $data;
        self::$sections = [];

        $viewPath = self::resolveViewPath($view);

        // Variables safe pour extract
        $safeKeys = array_filter(array_keys($data), fn($k) => preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k));
        $safeData = array_intersect_key($data, array_flip($safeKeys));

        // Rendre la vue
        ob_start();
        extract($safeData);
        require $viewPath;
        $content = ob_get_clean();

        // Si un layout est défini, l'utiliser
        if (self::$layout !== null) {
            // Ne pas écraser si la vue a défini ses sections via startSection/endSection
            if (!isset(self::$sections['content']) || self::$sections['content'] === '') {
                self::$sections['content'] = $content;
            }
            $layoutPath = self::resolveViewPath('layouts/' . self::$layout);
            extract($safeData);
            require $layoutPath;
        } else {
            echo $content;
        }
    }

    /**
     * Rend un partial (sous-template)
     */
    public static function partial(string $view, array $data = []): void
    {
        $viewPath = self::resolveViewPath($view);

        $safeKeys = array_filter(array_keys($data), fn($k) => preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k));
        $safeData = array_intersect_key($data, array_flip($safeKeys));

        extract($safeData);
        require $viewPath;
    }

    /**
     * Démarre une section nommée
     */
    public static function startSection(string $name): void
    {
        self::$currentSection = $name;
        ob_start();
    }

    /**
     * Termine la section courante
     */
    public static function endSection(): void
    {
        if (self::$currentSection !== null) {
            self::$sections[self::$currentSection] = ob_get_clean();
            self::$currentSection = null;
        }
    }

    /**
     * Affiche le contenu d'une section
     */
    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    /**
     * Vérifie si une section a été définie
     */
    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]) && self::$sections[$name] !== '';
    }

    /**
     * Inclut un partial inline (raccourci)
     */
    public static function include(string $view, array $data = []): void
    {
        self::partial('partials/' . $view, $data);
    }

    /**
     * Résout le chemin réel d'une vue avec protection path traversal
     */
    private static function resolveViewPath(string $view): string
    {
        $basePath = BASE_PATH . '/views';
        $viewPath = $basePath . '/' . str_replace('.', '/', $view) . '.php';

        $realPath = realpath($viewPath);
        if ($realPath === false || !str_starts_with($realPath, realpath($basePath))) {
            throw new RuntimeException("Vue introuvable ou accès refusé : {$view}");
        }

        return $realPath;
    }
}
