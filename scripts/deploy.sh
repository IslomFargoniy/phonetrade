#!/usr/bin/env bash
#
# Saytni serverga chiqaradi: frontend lokalda build qilinadi, fayllar rsync
# bilan yuboriladi, serverda composer, migratsiya va kesh bajariladi.
#
# Ishlatish:  scripts/deploy.sh
# Sozlash:    DEPLOY_HOST (SSH host yoki alias), DEPLOY_USER (sayt egasi), DEPLOY_PATH
#
set -euo pipefail

HOST="${DEPLOY_HOST:-younine}"
SITE_USER="${DEPLOY_USER:-phonetrade_s_usr}"
APP_PATH="${DEPLOY_PATH:-/var/www/${SITE_USER}/data/www/phonetrade.simulator.uz}"
PHP="${DEPLOY_PHP:-/opt/php84/bin/php}"

cd "$(dirname "$0")/.."

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Commit qilinmagan o'zgarishlar bor. Avval commit qiling." >&2
    exit 1
fi

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$BRANCH" != "main" && "${DEPLOY_ANY_BRANCH:-}" != "1" ]]; then
    echo "Deploy faqat main tarmog'idan qilinadi (hozir: $BRANCH)." >&2
    exit 1
fi

echo "==> Frontend build"
# Brauzer sarlavhasidagi ilova nomi build paytida yoziladi.
VITE_APP_NAME="${VITE_APP_NAME:-PhoneTrade}" npm run build

echo "==> Fayllarni yuborish"
rsync -az --delete \
    --exclude='/.git' --exclude='/.github' --exclude='/.env' \
    --exclude='/node_modules' --exclude='/vendor' \
    --exclude='/android' --exclude='/ios' --exclude='/docs' --exclude='/tests' \
    --exclude='/storage' --exclude='/public/storage' --exclude='/public/hot' \
    --exclude='/bootstrap/cache/*.php' --exclude='/database/*.sqlite*' \
    --exclude='.DS_Store' \
    --rsync-path="sudo -n -u ${SITE_USER} rsync" \
    ./ "${HOST}:${APP_PATH}/"

echo "==> Serverda o'rnatish"
ssh "$HOST" "sudo -n -u ${SITE_USER} env HOME=/var/www/${SITE_USER}/data COMPOSER_HOME=/var/www/${SITE_USER}/data/tmp/composer bash -se" <<REMOTE
set -euo pipefail
cd "${APP_PATH}"
mkdir -p storage/app/public storage/app/private storage/framework/{cache/data,sessions,views} storage/logs
${PHP} /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
${PHP} artisan migrate --force
${PHP} artisan storage:link --force >/dev/null
${PHP} artisan optimize
${PHP} artisan queue:restart >/dev/null
REMOTE

echo "==> Tayyor: $(git rev-parse --short HEAD)"
