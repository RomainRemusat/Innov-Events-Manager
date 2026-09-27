#!/bin/sh
set -eu

if [ -n "${PERSISTENT_DATA_DIR:-}" ]; then
    mkdir -p "$PERSISTENT_DATA_DIR/uploads/events" "$PERSISTENT_DATA_DIR/devis"

    # Conserve les images du jeu de démonstration lors du premier montage.
    if [ ! -e "$PERSISTENT_DATA_DIR/.initialized" ]; then
        cp -a /var/www/html/public/uploads/events/. "$PERSISTENT_DATA_DIR/uploads/events/"
        cp -a /var/www/html/storage/devis/. "$PERSISTENT_DATA_DIR/devis/"
        touch "$PERSISTENT_DATA_DIR/.initialized"
    fi

    rm -rf /var/www/html/public/uploads/events /var/www/html/storage/devis
    ln -s "$PERSISTENT_DATA_DIR/uploads/events" /var/www/html/public/uploads/events
    ln -s "$PERSISTENT_DATA_DIR/devis" /var/www/html/storage/devis
    chown -R www-data:www-data "$PERSISTENT_DATA_DIR"
fi

exec "$@"
