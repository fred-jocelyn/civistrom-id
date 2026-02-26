<?php
declare(strict_types=1);

/**
 * CIVISTROM ID — Front Controller
 *
 * Point d'entrée unique. Toutes les requêtes sont
 * redirigées ici via .htaccess (Apache/MAMP).
 */

// Définir le chemin racine (un niveau au-dessus de public/)
define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');

// Auto-detect : si le document root est la racine projet (MAMP),
// les assets sont dans public/assets/ → préfixe /public
// Si le document root est public/ (Nginx prod) → pas de préfixe
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$publicDir = rtrim(str_replace('\\', '/', __DIR__), '/');
define('PUBLIC_PREFIX', ($docRoot === $publicDir) ? '' : '/public');

// Charger le framework
require_once BASE_PATH . '/core/helpers.php';
require_once BASE_PATH . '/core/autoload.php';

// Lancer l'application
$app = App::getInstance();
$app->run();
