#!/bin/bash
#
# Prepares MaxtDesign Role-Based Pricing for WordPress.org SVN upload.
# Copies the allow-listed plugin files into svn-upload/trunk/. There is
# no build step — the plugin ships PHP and unminified CSS only.
#
# Usage: ./tools/prepare-svn.sh [version]
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$ROOT_DIR"

VERSION="${1:-$(node -p "require('./package.json').version" 2>/dev/null || echo "dev")}"
OUT_DIR="svn-upload/trunk"

echo "=================================================="
echo "MaxtDesign Role-Based Pricing - Prepare for SVN"
echo "=================================================="
echo "Version: $VERSION"
echo "Output:  $OUT_DIR/"
echo ""

# Lint before staging
echo "Running PHP lint..."
php -l maxtdesign-role-based-pricing.php > /dev/null
php -l uninstall.php > /dev/null
php -l includes/class-admin.php > /dev/null
php -l includes/class-core.php > /dev/null
php -l includes/class-frontend.php > /dev/null
echo "Lint OK."
echo ""

# Clean and recreate output
rm -rf "$OUT_DIR"
mkdir -p "$OUT_DIR/includes" "$OUT_DIR/assets/css"

# Plugin root
cp maxtdesign-role-based-pricing.php "$OUT_DIR/"
cp readme.txt "$OUT_DIR/"
cp uninstall.php "$OUT_DIR/"

# Includes
cp includes/class-admin.php    "$OUT_DIR/includes/"
cp includes/class-core.php     "$OUT_DIR/includes/"
cp includes/class-frontend.php "$OUT_DIR/includes/"

# Runtime CSS
cp assets/css/admin.css    "$OUT_DIR/assets/css/"
cp assets/css/frontend.css "$OUT_DIR/assets/css/"

# Verify Stable tag matches requested version
STABLE_TAG=$(grep -E "^Stable tag:" "$OUT_DIR/readme.txt" | awk '{print $3}')
HEADER_VER=$(grep -E "^ \* Version:" "$OUT_DIR/maxtdesign-role-based-pricing.php" | awk '{print $3}')
if [ "$STABLE_TAG" != "$VERSION" ] || [ "$HEADER_VER" != "$VERSION" ]; then
  echo "WARNING: version mismatch"
  echo "  Requested: $VERSION"
  echo "  Plugin header Version: $HEADER_VER"
  echo "  readme.txt Stable tag: $STABLE_TAG"
fi

echo ""
echo "Staged $(find "$OUT_DIR" -type f | wc -l | tr -d ' ') files in $OUT_DIR/"
echo ""
echo "Next: sync $OUT_DIR/* into your SVN checkout at"
echo "  C:/maxt/ops/wp-org-svn/maxtdesign-role-based-pricing/trunk/"
echo "Then: svn cp trunk tags/$VERSION && svn ci ... (atomic, single commit)"
