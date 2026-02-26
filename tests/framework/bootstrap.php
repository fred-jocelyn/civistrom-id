<?php
declare(strict_types=1);

/**
 * Bootstrap de test — Initialise l'environnement sans side effects
 *
 * CIVISTROM ID : pas de Database, pas de Session.
 */

// ─── 1. Définir BASE_PATH ─────────────────────
define('BASE_PATH', dirname(__DIR__, 2));

// ─── 2. Charger l'autoloader et les helpers ───
require_once BASE_PATH . '/core/autoload.php';
require_once BASE_PATH . '/core/helpers.php';

// ─── 3. Variables d'environnement de test ──────
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['APP_URL'] = 'https://CIVISTROMID:8890';
$_ENV['APP_LOCALE'] = 'fr';
$_ENV['APP_NAME'] = 'CIVISTROM ID';
$_ENV['APP_TIMEZONE'] = 'America/Toronto';

// ─── 4. Superglobaux HTTP par défaut ───────────
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'CIVISTROMID';
$_SERVER['SERVER_NAME'] = 'CIVISTROMID';
$_SERVER['SERVER_PORT'] = '8890';

// ─── 5. Mock App (singleton sans .env) ──────────
$appRef = new ReflectionClass(App::class);
$appInstance = $appRef->getProperty('instance');
$appInstance->setAccessible(true);
$mockApp = $appRef->newInstanceWithoutConstructor();

// Injecter la config de test
$configProp = $appRef->getProperty('config');
$configProp->setAccessible(true);
$configProp->setValue($mockApp, [
    'app' => require BASE_PATH . '/config/app.php',
]);

// Injecter Request + Router
$mockRequest = new Request();
$requestProp = $appRef->getProperty('request');
$requestProp->setAccessible(true);
$requestProp->setValue($mockApp, $mockRequest);

$mockRouter = new Router();
$routerProp = $appRef->getProperty('router');
$routerProp->setAccessible(true);
$routerProp->setValue($mockApp, $mockRouter);

// Activer le singleton
$appInstance->setValue(null, $mockApp);
