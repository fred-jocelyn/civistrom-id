# CIVISTROM ID — Guide développeur

## Qu'est-ce que c'est ?

CIVISTROM ID est un **authentificateur TOTP** (Time-based One-Time Password) — l'équivalent maison de Google Authenticator, brandé CIVISTROM. C'est une **PWA** (Progressive Web App) installable sur téléphone, fonctionnelle 100% offline.

L'utilisateur ouvre CIVISTROM ID → entre son PIN 4 chiffres → voit ses codes TOTP live (6 digits, 30s) → copie le code → le tape dans SENTINEL → JWT émis.

## Architecture

```
PWA 100% client-side (après chargement initial)

PHP backend (ultra-léger) :
  GET /       → SPA shell HTML
  GET /health → JSON {status, app, version}

JavaScript client :
  totp.js    → HMAC-SHA1 via Web Crypto API
  crypto.js  → PBKDF2 + AES-256-GCM (PIN → clé)
  storage.js → IndexedDB chiffré
  scanner.js → Caméra + jsQR (QR code scanner)
  app.js     → State machine + UI
```

**Zéro état côté serveur.** Pas de MySQL, pas de sessions, pas de Redis.

## Commandes

```bash
# Lancer les tests
php cli.php test

# Tester un fichier spécifique
php cli.php test Totp
php cli.php test Core
```

## Environnement dev

- **MAMP PRO** : site `CIVISTROMID`, port 8890 SSL
- **URL** : `https://CIVISTROMID:8890/`
- **PHP** : 8.3+ (CLI), 8.5+ (FastCGI MAMP)
- **Pas de BDD** : aucune connexion MySQL nécessaire

## Stack technique

| Composant | Techno |
|-----------|--------|
| Framework PHP | Fork allégé de SENTINEL core/ |
| TOTP | Web Crypto API (HMAC-SHA1) |
| Crypto PIN | PBKDF2 600K itérations → AES-256-GCM |
| Stockage | IndexedDB (chiffré par PIN) |
| QR Scanner | jsQR v1.4.0 (vendored) |
| PWA | Service Worker cache-first, manifest.json |
| CSS | Variables CSS, dark theme, responsive |
| Icônes | PNG 192/512 + maskable + apple-touch |

## Structure du projet

```
CIVISTROM-ID/
├── core/           # Framework PHP (fork SENTINEL allégé)
│   ├── App.php     # Singleton (pas de Database/Session)
│   ├── Controller.php  # Layout default = 'id'
│   ├── Router.php, Request.php, Response.php, View.php
│   ├── autoload.php    # subDirs = ['Controllers']
│   └── helpers.php     # env, config, e, url, asset, id_log, to_json
├── config/
│   ├── app.php     # name, version, color (#6366F1)
│   └── routes.php  # 2 routes : / et /health
├── app/Controllers/
│   ├── AppController.php     # Sert le shell SPA
│   └── HealthController.php  # JSON health check
├── views/
│   ├── layouts/id.php  # HTML5 PWA (meta, manifest, scripts)
│   ├── app.php         # Tous les écrans SPA (hidden/shown par JS)
│   └── errors/         # 404.php, 500.php
├── public/
│   ├── index.php       # Front controller
│   ├── .htaccess       # Rewrite rules
│   ├── manifest.json   # PWA manifest
│   ├── sw.js           # Service Worker
│   └── assets/
│       ├── css/id.css
│       ├── js/{totp,crypto,storage,scanner,app}.js
│       ├── js/vendor/jsqr.min.js
│       └── img/{icons PWA}
├── deploy/
│   ├── nginx/id.conf
│   ├── php/id-fpm.conf
│   └── setup-production.sh
├── tests/
│   ├── framework/{bootstrap,TestCase,TestRunner}.php
│   └── Unit/{CoreTest,FilesTest,HealthTest,TotpReferenceTest}.php
├── storage/logs/
├── .env, .gitignore, .htaccess
├── index.php, cli.php
└── CLAUDE.md
```

## Codes TOTP de référence (pour valider JS ↔ PHP)

| Seed | Timestamp | Code |
|------|-----------|------|
| JBSWY3DPEHPK3PXP | 1740000000 | 655327 |
| JBSWY3DPEHPK3PXP | 1740000030 | 126155 |

## Pièges

- **CSS `hidden` + `display: flex`** : `.screen[hidden] { display: none !important }` obligatoire
- **PIN inputs `type="tel"`** : pas `type="number"` (iOS keyboard layout)
- **jsQR chargé dynamiquement** : script ajouté au `<head>` au premier scan
- **Web Crypto = async** : `generateTOTP()` retourne une Promise
- **Service Worker scope** : enregistré à la racine, cache-first pour assets
- **PBKDF2 600K iterations** : ~200ms sur mobile, ~50ms sur desktop

## Couleur

**Indigo** `#6366F1` — `--id-primary` dans le CSS.

## Production

- **URL** : `https://civistromid.civistrom.ai`
- **VPS** : 51.79.70.186 (OVH, Ubuntu 24.04)
- **Deploy** : rsync + `sudo bash deploy/setup-production.sh`
- **Nginx** : document root = `public/`, pool FPM `[id]`
