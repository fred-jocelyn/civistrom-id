<?php
declare(strict_types=1);

/**
 * Autoloader — Découverte automatique des classes CIVISTROM ID
 *
 * Résolution en 2 niveaux (structure flat, pas de modules) :
 * 1. core/ (framework)
 * 2. app/Controllers/ (seul sous-dossier app)
 */
spl_autoload_register(function (string $class): void {
    // 1. core/
    $corePath = BASE_PATH . "/core/{$class}.php";
    if (file_exists($corePath)) {
        require_once $corePath;
        return;
    }

    // 2. app/Controllers/
    $subDirs = ['Controllers'];
    foreach ($subDirs as $subDir) {
        $path = BASE_PATH . "/app/{$subDir}/{$class}.php";
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});
