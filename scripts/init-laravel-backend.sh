#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/apps/backend"

if ! command -v php >/dev/null 2>&1; then
  echo "[erro] PHP CLI nao encontrado. Instale e rode novamente." >&2
  echo "       Exemplo Ubuntu: sudo apt update && sudo apt install -y php-cli php-mbstring php-xml php-curl php-zip unzip" >&2
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "[erro] Composer nao encontrado. Instale e rode novamente." >&2
  echo "       Exemplo Ubuntu: sudo apt update && sudo apt install -y composer" >&2
  exit 1
fi

if [[ -d "$APP_DIR" && -f "$APP_DIR/package.json" ]]; then
  timestamp="$(date +%Y%m%d%H%M%S)"
  backup_dir="$ROOT_DIR/apps/backend-node-legacy-$timestamp"
  echo "[info] Backend Node detectado. Movendo para: $backup_dir"
  mv "$APP_DIR" "$backup_dir"
fi

echo "[info] Criando backend Laravel em apps/backend"
composer create-project laravel/laravel "$APP_DIR"

cd "$APP_DIR"

echo "[info] Instalando stack API"
php artisan install:api --force || true

if [[ ! -f ".env" && -f ".env.example" ]]; then
  cp .env.example .env
fi

echo "[ok] Backend Laravel criado com sucesso em $APP_DIR"
echo "[next] Configure DB_* e APP_KEY no arquivo apps/backend/.env"
echo "[next] Rode: cd apps/backend && php artisan key:generate && php artisan migrate"
