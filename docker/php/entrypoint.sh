#!/bin/sh
# Idempotente: se puede ejecutar en cada arranque sin efectos duplicados.
set -e

[ -d vendor ] || composer install --no-interaction --prefer-dist
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

# MySQL ya está "healthy" (depends_on); migrate solo aplica lo pendiente.
php artisan migrate --force

# Datos demo solo con la base vacía, para no duplicar ni pisar cambios.
if [ "$(php artisan tinker --execute='echo App\Models\User::count();')" = "0" ]; then
  php artisan db:seed --force
fi

exec "$@"
