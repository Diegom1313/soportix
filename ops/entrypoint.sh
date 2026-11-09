#!/bin/sh
set -e
cd /var/www/soportix
until php bin/migrate.php; do
  echo "Esperando a la base de datos..."
  sleep 2
done
php bin/create-admin.php
exec apache2-foreground
