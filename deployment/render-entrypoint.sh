#!/bin/sh
set -eu

data_root="${EVENTNEST_DATA_ROOT:-/var/data}"
uploads_dir="$data_root/uploaded-content"
runtime_dir="$data_root/runtime"
documents_dir="$data_root/organiser-documents"

mkdir -p "$uploads_dir" "$runtime_dir" "$documents_dir"

# Populate an empty disk with the approved repository artwork without
# overwriting files that already exist on a persistent disk.
if [ -d /var/www/eventnest-seed/uploaded-content ]; then
    cp -a -n /var/www/eventnest-seed/uploaded-content/. "$uploads_dir/"
fi

replace_with_link() {
    target="$1"
    source="$2"
    if [ -L "$target" ]; then
        rm -f "$target"
    elif [ -e "$target" ]; then
        rm -rf "$target"
    fi
    mkdir -p "$(dirname "$target")"
    ln -s "$source" "$target"
}

replace_with_link /var/www/html/uploaded-content "$uploads_dir"
replace_with_link /var/www/html/database-config/runtime "$runtime_dir"
replace_with_link /var/www/eventnest-private/organiser-documents "$documents_dir"

chown -R www-data:www-data "$data_root"

# Docker is used by Render SIT. Upgrade schema before accepting requests;
# never run presentation seeds or category/data reconciliation at startup.
php /var/www/html/database/migrate_schema.php --apply

exec "$@"
