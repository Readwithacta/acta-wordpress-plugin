#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Acta WordPress Plugin — Dev Build Script
# Usage: ./build-dev.sh
#
# Builds a fresh ZIP of the dev plugin ready to upload to WordPress.
# ─────────────────────────────────────────────────────────────────────────────

set -e

OUTPUT="acta-content-dev.zip"

# Remove old ZIP if it exists
if [ -f "$OUTPUT" ]; then
    rm "$OUTPUT"
    echo "🗑  Removed old $OUTPUT"
fi

# Build fresh ZIP
zip -r "$OUTPUT" \
    acta-content-dev.php \
    assets/ \
    lib/ \
    --exclude="*.DS_Store"

echo "✅ Built $OUTPUT ($(du -sh $OUTPUT | cut -f1))"
echo ""
echo "Upload to WordPress:"
echo "  WordPress Admin → Plugins → Add New → Upload Plugin → choose $OUTPUT"
