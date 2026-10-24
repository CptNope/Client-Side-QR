#!/usr/bin/env bash
# Build the WordPress.org zip from the current commit.
#
#   bash tools/build-wporg.sh [output-dir]
#
# Writes <output-dir>/anderson-qr-code-generator/ (the folder that goes into
# SVN trunk) and <output-dir>/anderson-qr-code-generator.zip (for the first
# submission at wordpress.org/plugins/developers/add/). Files marked
# export-ignore in .gitattributes (design sources, tools, roadmap) are left out.
set -euo pipefail

SLUG=anderson-qr-code-generator
ROOT=$(git rev-parse --show-toplevel)
OUT=$(realpath -m "${1:-$ROOT/build}")
DEST="$OUT/$SLUG"

rm -rf "$DEST" "$OUT/$SLUG.zip"
mkdir -p "$DEST"
git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$DEST"

# Sanity checks.
if grep -rnE "api\.github\.com|pre_set_site_transient_update_plugins|Update URI" "$DEST" --include='*.php'; then
	echo "error: update code that talks to GitHub is left in the WordPress.org build" >&2
	exit 1
fi
if find "$DEST" -name '.*' -not -path "$DEST" | grep -q .; then
	echo "error: hidden files in the build" >&2
	exit 1
fi
V_HEADER=$(sed -n 's/^ \* Version: *//p' "$DEST/client-side-qr.php" | tr -d '[:space:]')
V_CONST=$(sed -n "s/^define( 'CSQR_VERSION', '\(.*\)' );/\1/p" "$DEST/client-side-qr.php")
V_README=$(sed -n 's/^Stable tag: *//p' "$DEST/readme.txt" | tr -d '[:space:]')
[ "$V_HEADER" = "$V_README" ] && [ "$V_HEADER" = "$V_CONST" ] || { echo "error: version $V_HEADER / CSQR_VERSION $V_CONST / Stable tag $V_README" >&2; exit 1; }

(cd "$OUT" && zip -qr "$SLUG.zip" "$SLUG")
echo "Built $SLUG $V_HEADER → $OUT/$SLUG.zip"
