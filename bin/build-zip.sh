#!/usr/bin/env bash
#
# Builds a clean, installable plugin zip that honors .distignore.
#
#   npm run zip                 # builds JS, then the zip
#   SKIP_JS_BUILD=1 npm run zip # reuse an existing assets/build (CI)
#
# Output:
#   build/costa-rica-kit-for-woocommerce/          staging folder (used by Plugin Check)
#   dist/costa-rica-kit-for-woocommerce-<ver>.zip  distributable
#
set -euo pipefail

SLUG="costa-rica-kit-for-woocommerce"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT/build"
STAGE_DIR="$BUILD_DIR/$SLUG"
DIST_DIR="$ROOT/dist"

cd "$ROOT"

for cmd in rsync zip composer; do
	command -v "$cmd" >/dev/null 2>&1 || { echo "Error: '$cmd' is required." >&2; exit 1; }
done

# Header Version, CRKIT_VERSION and readme Stable tag must match.
HEADER_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$SLUG.php" | tr -d '[:space:]')"
CONST_VERSION="$(sed -n "s/^define( 'CRKIT_VERSION', '\([^']*\)' );/\1/p" "$SLUG.php")"
STABLE_TAG="$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | tr -d '[:space:]')"

if [[ -z "$HEADER_VERSION" || "$HEADER_VERSION" != "$CONST_VERSION" || "$HEADER_VERSION" != "$STABLE_TAG" ]]; then
	echo "Error: version mismatch (header: '$HEADER_VERSION', CRKIT_VERSION: '$CONST_VERSION', Stable tag: '$STABLE_TAG')." >&2
	exit 1
fi

echo "Building $SLUG $HEADER_VERSION"

if [[ "${SKIP_JS_BUILD:-0}" != "1" ]]; then
	npm run build --silent
fi

rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR" "$DIST_DIR"

rsync -a --prune-empty-dirs --exclude-from="$ROOT/.distignore" "$ROOT/" "$STAGE_DIR/"

# Production autoloader only (no dev dependencies). composer.lock is needed to install, not to ship.
cp "$ROOT/composer.lock" "$STAGE_DIR/composer.lock"
composer install --working-dir="$STAGE_DIR" --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
rm -f "$STAGE_DIR/composer.lock"

ZIP_FILE="$DIST_DIR/$SLUG-$HEADER_VERSION.zip"
rm -f "$ZIP_FILE"
( cd "$BUILD_DIR" && zip -rqX "$ZIP_FILE" "$SLUG" )

echo "Created ${ZIP_FILE#"$ROOT"/} ($(du -h "$ZIP_FILE" | cut -f1 | tr -d ' '))"
