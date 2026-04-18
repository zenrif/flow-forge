#!/bin/sh
# Tunggu DB ready (opsional tapi disarankan)
echo "Running migrations..."
php artisan migrate --force
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
