#!/bin/bash
set -e

# Dynamically bind Apache to the port assigned by Render ($PORT) or default to 80
PORT="${PORT:-80}"
sed -i "s/80/$PORT/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

exec "$@"
