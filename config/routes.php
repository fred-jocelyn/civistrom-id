<?php
/**
 * CIVISTROM ID — Routes
 *
 * 2 routes seulement :
 * - GET /        → SPA (single page app)
 * - GET /health  → Health check JSON
 */

$router = App::getInstance()->getRouter();

$router->get('/', [AppController::class, 'index']);
$router->get('/health', [HealthController::class, 'index']);
