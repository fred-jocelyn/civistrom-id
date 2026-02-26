<?php
declare(strict_types=1);

/**
 * AppController — Page principale SPA
 *
 * Sert le shell HTML unique. Tout le reste est JS côté client.
 */
class AppController extends Controller
{
    public function index(Request $request): void
    {
        $this->render('app', [
            'appName'    => config('app.name', 'CIVISTROM ID'),
            'appVersion' => config('app.version', '1.0.0'),
            'appColor'   => config('app.color', '#6366F1'),
        ]);
    }
}
