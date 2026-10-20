#!/usr/bin/env bash
# BOSS FISH — ahost/cPanel o‘rnatish (tozadan)
# Ishlatish: cd ~/public_html && bash install.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

echo "==> BOSS FISH install (bossfish.uz)"
echo "    Papka: $ROOT"
echo

if [[ ! -f artisan ]]; then
  echo "XATO: artisan topilmadi. Scriptni Laravel ildizida ishga tushiring."
  exit 1
fi

PHP_BIN="$(command -v php || true)"
if [[ -z "$PHP_BIN" ]]; then
  echo "XATO: php topilmadi. cPanel Terminal da PHP yoqilganligini tekshiring."
  exit 1
fi

echo "==> PHP: $($PHP_BIN -v | head -1)"

if [[ ! -f .env ]]; then
  if [[ -f .env.cpanel.example ]]; then
    cp .env.cpanel.example .env
    echo "==> .env yaratildi (.env.cpanel.example dan)"
    echo "    DB_DATABASE / DB_USERNAME / DB_PASSWORD ni to‘ldiring, keyin install.sh ni qayta ishga tushiring."
    exit 0
  fi
  echo "XATO: .env yo‘q. Avval .env.cpanel.example ni .env qilib DB ma’lumotlarini yozing."
  exit 1
fi

# DB parol placeholder tekshiruvi
if grep -q 'CHANGE_ME_DB_PASSWORD' .env 2>/dev/null; then
  echo "XATO: .env da DB_PASSWORD hali CHANGE_ME_DB_PASSWORD."
  echo "    cPanel MySQL parolini yozib, qayta ishga tushiring."
  exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  echo "==> APP_KEY generatsiya"
  $PHP_BIN artisan key:generate --force
fi

echo "==> Huquqlar (storage, bootstrap/cache)"
mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/public bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

echo "==> Storage link"
rm -f public/storage
$PHP_BIN artisan storage:link || true

echo "==> Kesh tozalash"
$PHP_BIN artisan config:clear || true
$PHP_BIN artisan cache:clear || true
$PHP_BIN artisan route:clear || true
$PHP_BIN artisan view:clear || true

echo "==> Bazani 0 dan (migrate:fresh --seed) — ESKI MA’LUMOT O‘CHADI"
$PHP_BIN artisan migrate:fresh --seed --force

echo "==> Production kesh"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo
echo "✅ Tayyor: https://bossfish.uz"
echo
echo "Test loginlar:"
echo "  Egasi:    /egasi/kirish     ega@baliq.uz / Ega@2026!Strong"
echo "  Direktor: /direktor/kirish  901234567 / dir1234"
echo "  Hodim:    /hodim/kirish     907654321 / work1234"
echo "  Venger:   /venger/kirish    hodim@vengercarp.uz / Venger@2026!Strong"
echo
