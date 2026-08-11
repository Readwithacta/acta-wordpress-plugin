#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Acta — bump the plugin version everywhere it needs to change.
#
# Usage:  ./bump-version.sh patch | minor | major | <exact version>
#
# Updates all four places that must agree, so WordPress.org does not end up
# serving one version while the file claims another:
#   acta-content.php      Version: header + ACTA_PLUGIN_VERSION
#   acta-content-dev.php  Version: header + ACTA_DEV_PLUGIN_VERSION  (-dev suffix)
#   readme.txt            Stable tag
#
# Does not commit. Add the changelog entries yourself, then commit.
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

BUMP="${1:-}"
[[ -n "$BUMP" ]] || { echo "Usage: ./bump-version.sh patch | minor | major | <version>"; exit 1; }

CURRENT="$(grep -m1 '^ \* Version:' acta-content.php | sed -E 's/.*Version: *//' | tr -d '[:space:]')"
IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT"

case "$BUMP" in
    major) NEW="$((MAJOR + 1)).0.0" ;;
    minor) NEW="$MAJOR.$((MINOR + 1)).0" ;;
    patch) NEW="$MAJOR.$MINOR.$((PATCH + 1))" ;;
    *)
        [[ "$BUMP" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Not a valid version: $BUMP"; exit 1; }
        NEW="$BUMP"
        ;;
esac

echo "$CURRENT  ->  $NEW"

sed -i '' "s/^ \* Version:.*/ * Version:     $NEW/"                                              acta-content.php
sed -i '' "s/define( 'ACTA_PLUGIN_VERSION', '.*' );/define( 'ACTA_PLUGIN_VERSION', '$NEW' );/"    acta-content.php
sed -i '' "s/^ \* Version:.*/ * Version:     $NEW-dev/"                                          acta-content-dev.php
sed -i '' "s/define( 'ACTA_DEV_PLUGIN_VERSION', '.*' );/define( 'ACTA_DEV_PLUGIN_VERSION', '$NEW-dev' );/" acta-content-dev.php
sed -i '' "s/^Stable tag:.*/Stable tag: $NEW/"                                                   readme.txt

echo "  acta-content.php      $(grep -m1 '^ \* Version:' acta-content.php | sed -E 's/.*Version: *//')"
echo "  acta-content-dev.php  $(grep -m1 '^ \* Version:' acta-content-dev.php | sed -E 's/.*Version: *//')"
echo "  readme.txt            $(grep -m1 '^Stable tag:' readme.txt | sed 's/Stable tag: *//')"
echo
echo "Now add changelog entries to readme.txt and CHANGELOG.md, then commit."
