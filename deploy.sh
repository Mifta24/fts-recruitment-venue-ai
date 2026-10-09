#!/usr/bin/env bash
#
# Deploys FTS Recruitment Venue AI to the Plesk server.
#
#   ./deploy.sh               build assets locally, upload them, pull the code, migrate, refresh caches
#   ./deploy.sh --skip-build  reuse the existing public/build (code-only change)
#   ./deploy.sh --dry-run     show what would happen without touching the server
#
# Needs: an `ssh plesk` alias with root access, npm locally, and the commit you want live pushed to origin.
# The server only runs `git pull`, so push first. public/build is not in git: it is built here and rsynced.

set -euo pipefail

SSH_HOST="${DEPLOY_SSH_HOST:-plesk}"
APP_DIR="${DEPLOY_APP_DIR:-/var/www/vhosts/fts-tech.co.id/recruitment-venue-ai.fts-tech.co.id}"
APP_USER="${DEPLOY_APP_USER:-fts-tech.co.id_md8klw10xxd}"
APP_URL="${DEPLOY_APP_URL:-https://recruitment-venue-ai.fts-tech.co.id}"
BRANCH="${DEPLOY_BRANCH:-master}"
PHP_BIN="${DEPLOY_PHP_BIN:-/opt/plesk/php/8.4/bin/php}"
COMPOSER_PHAR="${DEPLOY_COMPOSER_PHAR:-/usr/lib64/plesk-9.0/composer.phar}"

SKIP_BUILD=0
DRY_RUN=0

for arg in "$@"; do
    case "$arg" in
        --skip-build) SKIP_BUILD=1 ;;
        --dry-run) DRY_RUN=1 ;;
        -h|--help) sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

cd "$(dirname "$0")"

step() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
warn() { printf '\033[1;33m!! %s\033[0m\n' "$1"; }
fail() { printf '\033[1;31mxx %s\033[0m\n' "$1" >&2; exit 1; }
run() { if [ "$DRY_RUN" -eq 1 ]; then printf '[dry-run] %s\n' "$*"; else "$@"; fi; }

step "Checking that the commit to deploy is on origin/$BRANCH"
git fetch -q origin "$BRANCH"
[ -z "$(git status --porcelain --untracked-files=no)" ] || warn "You have uncommitted changes. They will NOT be deployed."
LOCAL_SHA="$(git rev-parse HEAD)"
REMOTE_SHA="$(git rev-parse "origin/$BRANCH")"
if [ "$LOCAL_SHA" != "$REMOTE_SHA" ]; then
    git merge-base --is-ancestor "$LOCAL_SHA" "$REMOTE_SHA" \
        && warn "Local HEAD is behind origin/$BRANCH; the server will get the newer origin commit." \
        || fail "Local HEAD is not pushed. Run: git push origin $BRANCH"
fi
echo "Deploying origin/$BRANCH at ${REMOTE_SHA:0:7}"

if [ "$SKIP_BUILD" -eq 0 ]; then
    step "Building frontend assets"
    run npm ci --ignore-scripts --no-audit --no-fund
    run npm run build
fi

[ "$DRY_RUN" -eq 1 ] || [ -f public/build/manifest.json ] || fail "public/build/manifest.json is missing. Run without --skip-build."

step "Uploading public/build"
run rsync -az --delete public/build/ "$SSH_HOST:/root/.rv_build_upload/"

step "Updating the server"
if [ "$DRY_RUN" -eq 1 ]; then
    echo "[dry-run] ssh $SSH_HOST: git pull, composer install, migrate, cache, queue:restart in $APP_DIR"
else
    ssh "$SSH_HOST" bash -s -- "$APP_DIR" "$APP_USER" "$BRANCH" "$PHP_BIN" "$COMPOSER_PHAR" <<'REMOTE'
set -euo pipefail
APP_DIR="$1"; APP_USER="$2"; BRANCH="$3"; PHP="$4"; COMPOSER="$5"
as_app() { sudo -u "$APP_USER" -H bash -c "cd '$APP_DIR' && $*"; }

as_app "git config core.fileMode false"
as_app "git fetch -q origin '$BRANCH' && git reset -q --hard 'origin/$BRANCH'"
echo "Server is at $(as_app 'git rev-parse --short HEAD')"

rm -rf "$APP_DIR/public/build"
mv /root/.rv_build_upload "$APP_DIR/public/build"
chown -R "$APP_USER":psacln "$APP_DIR/public/build"
find "$APP_DIR/public/build" -type d -exec chmod 755 {} +
find "$APP_DIR/public/build" -type f -exec chmod 644 {} +

as_app "COMPOSER_HOME='$APP_DIR/.composer' $PHP $COMPOSER install --no-dev --optimize-autoloader --no-interaction --no-scripts 2>&1 | tail -2"
as_app "$PHP artisan package:discover --ansi 2>&1 | tail -1"
as_app "$PHP artisan migrate --force --no-interaction"
as_app "$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache && $PHP artisan event:cache" | tail -1
as_app "$PHP artisan queue:restart"

chown -R "$APP_USER":psacln "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
REMOTE
fi

step "Smoke test"
if [ "$DRY_RUN" -eq 1 ]; then
    echo "[dry-run] curl $APP_URL/ and $APP_URL/admin/login"
else
    for path in / /admin/login; do
        code="$(curl -s -o /dev/null -w '%{http_code}' "$APP_URL$path")"
        printf '%s %s\n' "$code" "$path"
        [ "$code" = "200" ] || fail "Unexpected status $code for $path"
    done
fi

printf '\n\033[1;32mDeployed.\033[0m %s\n' "$APP_URL"
