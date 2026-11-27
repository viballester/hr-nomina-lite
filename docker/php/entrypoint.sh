#!/bin/sh
# Idempotente: se puede ejecutar en cada arranque sin efectos duplicados.
set -e

[ -d vendor ] || composer install --no-interaction --prefer-dist
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

# MySQL ya está "healthy" (depends_on); migrate solo aplica lo pendiente.
php artisan migrate --force

# Datos demo (contraseñas conocidas): solo si se piden explícitamente y la base está vacía.
if [ "$SEED_DEMO_DATA" = "true" ] && [ "$(php artisan tinker --execute='echo App\Models\User::count();')" = "0" ]; then
  php artisan db:seed --force
fi

exec "$@"
