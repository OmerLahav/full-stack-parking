#!/bin/sh
set -e

echo "Waiting for MySQL..."
until php -r "
  try {
    new PDO(
      'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'),
      getenv('DB_USER'),
      getenv('DB_PASSWORD')
    );
    exit(0);
  } catch (Exception \$e) {
    exit(1);
  }
" 2>/dev/null; do
  sleep 2
done

echo "Running migrations..."
php bin/migrate.php

echo "Starting API server..."
exec php -S 0.0.0.0:8080 -t public
