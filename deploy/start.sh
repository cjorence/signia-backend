#!/bin/sh
set -eu
test "${RAILWAY_VOLUME_MOUNT_PATH:-}" = /data || { echo 'Attach the Backend persistent volume at /data.' >&2; exit 1; }
case "${PORT:-8080}" in *[!0-9]*|'') echo 'Invalid PORT' >&2; exit 1;; esac
php deploy/initialize-volume.php /data
chown www-data:www-data /data/media /data/media/app /data/media/app/public /data/media/app/private /data/media/app/stories /data/media/covers
printf 'Listen %s\n' "${PORT:-8080}" > /etc/apache2/ports.conf
# No cache:clear: the notification-settings migration may still need old cache values.
php artisan config:cache
case "${SIGNIA_SCHEDULER_ENABLED:-false}" in true|false) ;; *) echo 'Invalid SIGNIA_SCHEDULER_ENABLED' >&2; exit 1;; esac
export SIGNIA_SCHEDULER_ENABLED
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/signia.conf
