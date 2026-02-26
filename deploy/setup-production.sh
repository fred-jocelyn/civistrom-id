#!/bin/bash
# ============================================
# CIVISTROM ID — Setup initial production (OVH VPS)
# ============================================
#
# Ce script fait le setup initial :
# 1. Crée les dossiers
# 2. Crée le .env (minimal)
# 3. Installe PHP-FPM pool + Nginx
# 4. Obtient le certificat SSL
# 5. Health check
#
# Pas de BDD, pas de migrations — 100% client-side.
#
# Usage :
#   rsync -avz --exclude='.git' --exclude='.env' ... ubuntu@51.79.70.186:/var/www/civistrom-id/
#   ssh ubuntu@51.79.70.186
#   sudo bash /var/www/civistrom-id/deploy/setup-production.sh
#
# ============================================

set -euo pipefail

DEPLOY_DIR="/var/www/civistrom-id"
DOMAIN="civistromid.civistrom.ai"

# Couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
info() { echo -e "${CYAN}[i]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; exit 1; }

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║   CIVISTROM ID — Setup Production        ║${NC}"
echo -e "${CYAN}║   PWA Authenticator TOTP                 ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════╝${NC}"
echo ""

if [ "$EUID" -ne 0 ]; then
    error "Ce script doit être exécuté en tant que root"
fi

# ── 1. Dossiers + permissions ──
log "Configuration des permissions..."
chown -R www-data:www-data "$DEPLOY_DIR"
mkdir -p "${DEPLOY_DIR}/storage/logs"
chmod -R 755 "${DEPLOY_DIR}/storage"

# ── 2. .env production ──
if [ ! -f "${DEPLOY_DIR}/.env" ]; then
    cat > "${DEPLOY_DIR}/.env" << ENVFILE
# CIVISTROM ID — Production
APP_NAME=CIVISTROM ID
APP_URL=https://${DOMAIN}
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=fr
APP_TIMEZONE=America/Toronto
TRUSTED_PROXIES=
ENVFILE
    chown www-data:www-data "${DEPLOY_DIR}/.env"
    chmod 640 "${DEPLOY_DIR}/.env"
    log ".env production créé"
else
    warn ".env existe déjà — non modifié"
fi

# ── 3. PHP-FPM pool ──
cp "${DEPLOY_DIR}/deploy/php/id-fpm.conf" /etc/php/8.4/fpm/pool.d/id.conf
systemctl reload php8.4-fpm
log "Pool PHP-FPM 'id' activé"

# ── 4. Nginx ──
cp "${DEPLOY_DIR}/deploy/nginx/id.conf" /etc/nginx/sites-available/id.conf

# Config temporaire HTTP pour certbot
cat > /etc/nginx/sites-available/id-temp.conf << 'TEMPNGINX'
server {
    listen 80;
    listen [::]:80;
    server_name civistromid.civistrom.ai;
    root /var/www/civistrom-id/public;
    index index.php;

    location /.well-known/acme-challenge/ {
        root /var/www/civistrom-id/public;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.4-fpm-id.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
TEMPNGINX

ln -sf /etc/nginx/sites-available/id-temp.conf /etc/nginx/sites-enabled/id-temp.conf
nginx -t 2>&1 || error "Config Nginx temporaire invalide"
systemctl reload nginx
log "Nginx config temporaire activée (HTTP)"

# ── 5. SSL ──
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --email admin@civistrom.ai --redirect 2>&1 || {
    warn "Certbot a échoué — configurer SSL manuellement"
}

# Remettre la config finale
rm -f /etc/nginx/sites-enabled/id-temp.conf
rm -f /etc/nginx/sites-available/id-temp.conf
ln -sf /etc/nginx/sites-available/id.conf /etc/nginx/sites-enabled/id.conf
nginx -t 2>&1 && systemctl reload nginx
log "Nginx config finale activée (HTTPS)"

# ── 6. Health check ──
sleep 2
HTTP_CODE=$(curl -sk -o /dev/null -w "%{http_code}" "https://${DOMAIN}/health" 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ]; then
    log "Health check OK (HTTP 200) ✅"
else
    warn "Health check retourne HTTP ${HTTP_CODE}"
fi

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║   CIVISTROM ID — Déploiement terminé !   ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════╝${NC}"
echo ""
echo "  URL : https://${DOMAIN}"
echo ""
