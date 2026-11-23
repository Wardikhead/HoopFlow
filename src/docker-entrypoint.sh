#!/bin/bash
set -e

echo "Running migrations..."
php artisan migrate --force

COUNT=$(php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Models\Tournament::count();")
if [ "$COUNT" = "0" ]; then
  echo "Seeding demo data..."
  php artisan db:seed --class=Database\Seeders\DemoSeeder --force
fi

echo "Optimizing..."
php artisan optimize:clear
php artisan optimize

echo "Starting server on port ${PORT:-8080}..."
php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"