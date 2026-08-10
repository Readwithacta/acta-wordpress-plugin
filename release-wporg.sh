#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Acta — publish the current release to the WordPress.org directory.
#
# Usage:  ./release-wporg.sh
#
# Run this AFTER `git push origin develop:main` has finished building, and it
# does the rest: downloads the release zip, renames the main file to match the
# WP.org slug, resets the CI CalVer stamp back to the semver in the source,
# copies it into the SVN working copy, commits trunk, then tags.
#
# It exists because that middle part is manual, and skipping it is what broke
# the 4.2.0 release: the tag was created from a trunk that still held 4.1.0,
# and `svn cp` onto an existing tag nested it as tags/4.2.0/trunk/.
#
# Guards, in order:
#   - the release zip must match the local source (ignoring version stamps)
#   - no directory assets (screenshots/banners/icons) in the plugin zip
#   - the tag must not already exist
#   - trunk must actually differ before anything is committed
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

REPO="Readwithacta/acta-wordpress-plugin"
PLUGIN_SLUG="acta-pay-per-article"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SVN_DIR="${ACTA_SVN_DIR:-$(cd "$PLUGIN_DIR/../.." && pwd)/$PLUGIN_SLUG}"
SRC="$PLUGIN_DIR/acta-content.php"

red()  { printf '\033[31m%s\033[0m\n' "$*"; }
grn()  { printf '\033[32m%s\033[0m\n' "$*"; }
ylw()  { printf '\033[33m%s\033[0m\n' "$*"; }
die()  { red "ERROR: $*"; exit 1; }

for cmd in curl unzip svn python3; do
    command -v "$cmd" >/dev/null || die "$cmd is required but not installed."
done

[[ -f "$SRC" ]]        || die "Cannot find $SRC"
[[ -d "$SVN_DIR" ]]    || die "SVN working copy not found at $SVN_DIR (override with ACTA_SVN_DIR)"
[[ -d "$SVN_DIR/trunk" ]] || die "$SVN_DIR does not look like the plugin SVN checkout (no trunk/)"

# ── Version comes from the source file, never from CI ────────────────────────
VERSION="$(grep -m1 '^ \* Version:' "$SRC" | sed -E 's/.*Version: *//' | tr -d '[:space:]')"
[[ -n "$VERSION" ]] || die "Could not read Version from $SRC"

echo "Publishing Acta $VERSION to WordPress.org"
echo "  source:  $SRC"
echo "  svn:     $SVN_DIR"
echo

# ── Locate the GitHub release ────────────────────────────────────────────────
echo "Fetching latest GitHub release…"
RELEASE_JSON="$(curl -sfL "https://api.github.com/repos/$REPO/releases/latest")" \
    || die "Could not reach the GitHub API."

REL_TAG="$(printf '%s' "$RELEASE_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("tag_name",""))')"
ZIP_URL="$(printf '%s' "$RELEASE_JSON" | python3 -c '
import json,sys
d = json.load(sys.stdin)
for a in d.get("assets", []):
    if a["name"] == "acta-content-wporg.zip":
        print(a["browser_download_url"]); break
')"

[[ -n "$ZIP_URL" ]] || die "Release $REL_TAG has no acta-content-wporg.zip asset. Has CI finished?"
echo "  release: $REL_TAG"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

curl -sfL -o "$WORK/wporg.zip" "$ZIP_URL" || die "Download failed."
unzip -q "$WORK/wporg.zip" -d "$WORK/x"

BUILD="$WORK/x/acta-content-wporg"
[[ -f "$BUILD/acta-content.php" ]] || die "Unexpected zip layout — no acta-content-wporg/acta-content.php"

# ── Guard: the release must be built from the code sitting here ──────────────
# CI stamps CalVer into the version lines, so those are excluded from the
# comparison; everything else must match, or you are shipping a stale build.
awk '/@wporg-strip-start/{skip=1} !skip{print} /@wporg-strip-end/{skip=0}' "$SRC" | cat -s \
    | grep -vE "^ \* Version:|ACTA_PLUGIN_VERSION', '" > "$WORK/local.php"
grep -vE "^ \* Version:|ACTA_PLUGIN_VERSION', '" "$BUILD/acta-content.php" > "$WORK/built.php"

if ! diff -q "$WORK/local.php" "$WORK/built.php" >/dev/null; then
    red "The release zip does not match your local acta-content.php."
    echo "This usually means CI has not finished, or you have uncommitted changes."
    echo "Differences:"
    diff "$WORK/local.php" "$WORK/built.php" | head -20
    exit 1
fi
grn "  ✓ release matches local source"

# ── Guard: no directory assets inside the plugin zip ─────────────────────────
if find "$BUILD" -type f \( -name 'screenshot-*' -o -name 'banner-*' -o -name 'icon-*' \) | grep -q .; then
    die "Directory assets found inside the plugin zip. They belong in SVN assets/ only."
fi
grn "  ✓ no directory assets in the package"

# ── Guard: tag must not already exist ────────────────────────────────────────
cd "$SVN_DIR"
svn up --quiet
if [[ -d "tags/$VERSION" ]]; then
    die "tags/$VERSION already exists. Remove it first:
    cd $SVN_DIR && svn rm tags/$VERSION && svn ci -m 'Remove bad $VERSION tag'
  (Copying onto an existing tag nests it as tags/$VERSION/trunk/ — that is the 4.2.0 bug.)"
fi
grn "  ✓ tags/$VERSION is free"

# ── Stage trunk ──────────────────────────────────────────────────────────────
cp "$BUILD/acta-content.php" "trunk/$PLUGIN_SLUG.php"
cp "$BUILD/readme.txt"       "trunk/readme.txt"
cp "$BUILD/uninstall.php"    "trunk/uninstall.php"
[[ -d "$BUILD/assets" ]] && cp -R "$BUILD/assets/." "trunk/assets/"

# Undo the CI CalVer stamp — WordPress.org stays on semver.
sed -i '' "s/^ \* Version:.*/ * Version:     $VERSION/"                              "trunk/$PLUGIN_SLUG.php"
sed -i '' "s/define( 'ACTA_PLUGIN_VERSION', '.*' );/define( 'ACTA_PLUGIN_VERSION', '$VERSION' );/" "trunk/$PLUGIN_SLUG.php"
sed -i '' "s/^Stable tag:.*/Stable tag: $VERSION/"                                   "trunk/readme.txt"

# Pick up anything new or removed so the commit is complete.
svn add --force --quiet trunk >/dev/null 2>&1 || true
svn status trunk | awk '/^!/ {print $2}' | xargs -r svn rm --quiet >/dev/null 2>&1 || true

echo
echo "Staged in trunk:"
svn status trunk | sed 's/^/  /'
if [[ -z "$(svn status trunk)" ]]; then
    ylw "Nothing changed in trunk — $VERSION appears to be published already. Stopping."
    exit 0
fi

echo
echo "  version:    $(grep -m1 '^ \* Version:' "trunk/$PLUGIN_SLUG.php" | sed -E 's/.*Version: *//')"
echo "  stable tag: $(grep -m1 '^Stable tag:' trunk/readme.txt | sed 's/Stable tag: *//')"
echo
ylw "About to commit trunk and create tags/$VERSION on WordPress.org."
read -r -p "Proceed? (y/N) " reply
[[ "$reply" =~ ^[Yy]$ ]] || { echo "Aborted. Undo staging with: cd $SVN_DIR && svn revert -R trunk"; exit 0; }

# ── Publish ──────────────────────────────────────────────────────────────────
# Trunk first, then tag — a tag copied before trunk is committed captures the
# previous release, which is exactly how 4.2.0 shipped 4.1.0's code.
svn ci -m "Release $VERSION" trunk
svn cp trunk "tags/$VERSION"
svn ci -m "Tag $VERSION"

echo
grn "Published $VERSION to WordPress.org."
echo "  https://wordpress.org/plugins/$PLUGIN_SLUG/"
echo "  Live in a few minutes. Verify with:"
echo "    curl -s https://api.wordpress.org/plugins/info/1.0/$PLUGIN_SLUG.json | python3 -c 'import json,sys; print(json.load(sys.stdin)[\"version\"])'"
