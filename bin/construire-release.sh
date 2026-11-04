#!/usr/bin/env bash
#
# Construit l'archive de release prête à installer :
#   grimpepan-vX.Y.Z.zip         (code + vendor/ sans les paquets de dev)
#   grimpepan-vX.Y.Z.zip.sha256  (empreinte vérifiée par la mise à jour en un clic)
#
# Usage : bin/construire-release.sh [dossier de sortie]   (par défaut : build/)
# La version est lue dans le fichier VERSION. Utilisé par .github/workflows/release.yml.

set -euo pipefail

racine="$(cd "$(dirname "$0")/.." && pwd)"
sortie="$(mkdir -p "${1:-$racine/build}" && cd "${1:-$racine/build}" && pwd)"
version="$(tr -d '[:space:]' < "$racine/VERSION")"
tag="v$version"
archive="grimpepan-$tag.zip"

[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "VERSION invalide : « $version »" >&2; exit 1; }

travail="$(mktemp -d)"
trap 'rm -rf "$travail"' EXIT
dest="$travail/grimpepan"

echo "→ Copie du code ($tag)"
rsync -a \
  --exclude '.git/' --exclude '.github/' --exclude 'build/' --exclude 'vendor/' --exclude 'var/' \
  --exclude 'node_modules/' --exclude '.env.local' --exclude '.env.local.php' --exclude '.env.*.local' \
  --exclude 'public/assets/' --exclude 'public/bundles/' --exclude '/assets/vendor/' \
  --exclude 'public/uploads/photos/*' --include 'public/uploads/photos/.gitkeep' \
  --exclude '.DS_Store' --exclude '.phpunit.cache/' --exclude '.idea/' --exclude '.vscode/' \
  "$racine/" "$dest/"
mkdir -p "$dest/var" "$dest/public/uploads/photos"
touch "$dest/public/uploads/photos/.gitkeep"

echo "→ Dépendances de production (composer install --no-dev)"
composer install --working-dir="$dest" --no-dev --optimize-autoloader \
  --no-scripts --no-interaction --no-progress --prefer-dist --quiet

echo "→ Paquets JavaScript de l'importmap (assets/vendor/, nécessaires à asset-map:compile)"
(cd "$dest" && APP_ENV=prod APP_DEBUG=0 APP_SECRET=construction php bin/console importmap:install --no-interaction)
rm -rf "$dest/var/cache"

echo "→ Archive $archive"
rm -f "$sortie/$archive" "$sortie/$archive.sha256"
(cd "$travail" && zip -rqX "$sortie/$archive" grimpepan)
(cd "$sortie" && shasum -a 256 "$archive" > "$archive.sha256")

echo "✔ $sortie/$archive ($(du -h "$sortie/$archive" | cut -f1))"
cat "$sortie/$archive.sha256"
