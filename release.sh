#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Acta WordPress Plugin — Release Script
# Usage: ./release.sh patch | minor | major
#
# What it does:
#   1. Bumps version in plugin header + PHP constant
#   2. Updates CHANGELOG.md (opens editor for you to add notes)
#   3. Commits + creates git tag
#   4. Pushes tag → triggers GitHub Action → builds ZIP → publishers auto-update
# ─────────────────────────────────────────────────────────────────────────────

set -e

PLUGIN_FILE="acta-content.php"
BUMP_TYPE="${1:-patch}"

# ── Validate ──────────────────────────────────────────────────────────────────
if [[ ! "$BUMP_TYPE" =~ ^(patch|minor|major)$ ]]; then
    echo "Usage: ./release.sh patch | minor | major"
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "❌ Working directory is not clean. Commit or stash changes first."
    exit 1
fi

# ── Read current version ──────────────────────────────────────────────────────
CURRENT=$(grep "^ \* Version:" "$PLUGIN_FILE" | sed "s/ \* Version: *//")
echo "Current version: $CURRENT"

IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT"

# ── Calculate new version ─────────────────────────────────────────────────────
case "$BUMP_TYPE" in
    major) MAJOR=$((MAJOR + 1)); MINOR=0; PATCH=0 ;;
    minor) MINOR=$((MINOR + 1)); PATCH=0 ;;
    patch) PATCH=$((PATCH + 1)) ;;
esac

NEW_VERSION="$MAJOR.$MINOR.$PATCH"
echo "New version:     $NEW_VERSION"
echo ""

read -p "Confirm release v$NEW_VERSION? (y/N) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

# ── Bump version in plugin file ───────────────────────────────────────────────
sed -i '' "s/ \* Version:.*/ * Version:     $NEW_VERSION/" "$PLUGIN_FILE"
sed -i '' "s/define( 'ACTA_PLUGIN_VERSION', '.*' );/define( 'ACTA_PLUGIN_VERSION', '$NEW_VERSION' );/" "$PLUGIN_FILE"

echo "✅ Version bumped in $PLUGIN_FILE"

# ── Update CHANGELOG.md ───────────────────────────────────────────────────────
DATE=$(date +%Y-%m-%d)
TEMP=$(mktemp)

cat > "$TEMP" << EOF
## v$NEW_VERSION — $DATE

### Changes
-

EOF

cat CHANGELOG.md >> "$TEMP"
mv "$TEMP" CHANGELOG.md

# Open CHANGELOG in editor so release notes can be filled in
${EDITOR:-nano} CHANGELOG.md

# ── Commit + tag ──────────────────────────────────────────────────────────────
git add "$PLUGIN_FILE" CHANGELOG.md
git commit -m "Release v$NEW_VERSION"
git tag "v$NEW_VERSION"

echo ""
echo "✅ Tagged v$NEW_VERSION"
echo ""
read -p "Push to GitHub? This will trigger the release workflow. (y/N) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    git push origin main
    git push origin "v$NEW_VERSION"
    echo ""
    echo "🚀 Pushed! GitHub Action will build the ZIP and create the release."
    echo "   Check progress: https://github.com/Readwithacta/acta-wordpress-plugin/actions"
    echo "   Release will appear: https://github.com/Readwithacta/acta-wordpress-plugin/releases"
    echo ""
    echo "   Publishers will silently auto-update within 12 hours."
else
    echo "Tag created locally. Push manually with:"
    echo "  git push origin main && git push origin v$NEW_VERSION"
fi
