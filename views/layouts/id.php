<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

    <title><?= e($appName ?? 'CIVISTROM ID') ?></title>

    <!-- PWA Meta -->
    <meta name="theme-color" content="#0a0a0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CIVISTROM ID">
    <meta name="description" content="Authentificateur TOTP CIVISTROM">

    <!-- Manifest PWA -->
    <link rel="manifest" href="<?= (defined('PUBLIC_PREFIX') ? PUBLIC_PREFIX : '') ?>/manifest.json">

    <!-- Favicons -->
    <link rel="apple-touch-icon" href="<?= asset('img/apple-touch-icon.png') ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= asset('img/icon-192.png') ?>">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= asset('css/id.css') ?>">
</head>
<body>

    <?= View::section('content') ?>

    <!-- JS (ordre important — jsQR chargé dynamiquement par scanner.js) -->
    <script src="<?= asset('js/totp.js') ?>"></script>
    <script src="<?= asset('js/crypto.js') ?>"></script>
    <script src="<?= asset('js/storage.js') ?>"></script>
    <script src="<?= asset('js/scanner.js') ?>"></script>
    <script src="<?= asset('js/app.js') ?>"></script>

    <!-- Service Worker PWA -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const swPath = '<?= (defined('PUBLIC_PREFIX') ? PUBLIC_PREFIX : '') ?>/sw.js';
            navigator.serviceWorker.register(swPath).catch(() => {});
        });
    }
    </script>

</body>
</html>
