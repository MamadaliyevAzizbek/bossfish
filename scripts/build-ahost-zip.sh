#!/usr/bin/env bash
# Mahalliy mashinada: boss-ahost-cpanel.zip yig‘adi (ahostga yuklash uchun)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

OUT="$ROOT/boss-ahost-cpanel.zip"
STAGING="$(mktemp -d)"
NAME="bossfish-deploy"

cleanup() { rm -rf "$STAGING"; }
trap cleanup EXIT

echo "==> Staging: $STAGING/$NAME"
mkdir -p "$STAGING/$NAME"

# Asosiy loyiha (og‘ir/chiqindi papkalarsiz)
rsync -a \
  --exclude '.git/' \
  --exclude '.tmp-psysh/' \
  --exclude 'node_modules/' \
  --exclude 'Архив.zip' \
  --exclude 'boss-ahost-cpanel.zip' \
  --exclude '.env' \
  --exclude '.env.backup' \
  --exclude '.env.production' \
  --exclude '.DS_Store' \
  --exclude '.phpunit.result.cache' \
  --exclude 'webpack.mix.js.bak' \
  --exclude 'database/database.sqlite' \
  --exclude 'database/*.sqlite-journal' \
  --exclude 'storage/logs/*' \
  --exclude 'storage/framework/cache/data/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  --exclude 'public/storage' \
  --exclude 'public/hot' \
  --exclude 'tests/' \
  --exclude 'composer' \
  "$ROOT/" "$STAGING/$NAME/"

# Bo‘sh storage struktura
mkdir -p \
  "$STAGING/$NAME/storage/app/public" \
  "$STAGING/$NAME/storage/framework/cache/data" \
  "$STAGING/$NAME/storage/framework/sessions" \
  "$STAGING/$NAME/storage/framework/views" \
  "$STAGING/$NAME/storage/logs" \
  "$STAGING/$NAME/bootstrap/cache"
touch "$STAGING/$NAME/storage/logs/.gitignore"
touch "$STAGING/$NAME/bootstrap/cache/.gitignore"

chmod +x "$STAGING/$NAME/install.sh" "$STAGING/$NAME/artisan" || true

# Zip ildizida fayllar (public_html ga extract qilganda to‘g‘ri tushishi uchun)
rm -f "$OUT"
(
  cd "$STAGING/$NAME"
  zip -r -q "$OUT" . \
    -x '*.DS_Store' \
    -x '*/.DS_Store'
)

SIZE="$(du -h "$OUT" | awk '{print $1}')"
echo "==> Tayyor: $OUT ($SIZE)"
echo "    cPanel File Manager → public_html ga Upload → Extract"
echo "    Keyin: CPANEL.txt dagi Terminal buyruqlari"
