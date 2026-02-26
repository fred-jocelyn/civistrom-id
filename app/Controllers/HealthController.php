<?php
declare(strict_types=1);

/**
 * HealthController — Health check endpoint
 *
 * Retourne un JSON simple pour le monitoring.
 */
class HealthController extends Controller
{
    public function index(Request $request): void
    {
        $this->json([
            'status'  => 'ok',
            'app'     => 'civistrom-id',
            'version' => config('app.version', '1.0.0'),
            'time'    => date('c'),
        ]);
    }
}
