#!/bin/sh
set -e

export PORT="${PORT:-80}"

mkdir -p \
  /var/www/html/writable/cache \
  /var/www/html/writable/logs \
  /var/www/html/writable/session \
  /var/www/html/writable/uploads \
  /var/www/html/files/temp \
  /var/www/html/files/profile_images \
  /var/www/html/files/timeline_files \
  /var/www/html/files/project_files \
  /var/www/html/files/system \
  /var/log/supervisor

chown -R www-data:www-data /var/www/html/writable /var/www/html/files || true
chmod -R ug+rwX /var/www/html/writable /var/www/html/files || true

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
