#!/usr/bin/env bash
#
# Build the wp.org-style distributable zip for this plugin.
#
# The version is read from the plugin header in agent-abilities-for-mcp.php and used
# for the filename. The archive itself is produced with `git archive`, so it honors
# .gitattributes export-ignore and contains exactly what ships to wordpress.org (no
# dev cruft, no wp/, no .claude/). Output lands in build/.
#
# Usage:
#   bin/build-zip.sh            # archive HEAD
#   bin/build-zip.sh <ref>      # archive a specific commit/tag/branch
#
# Note: the zip reflects the committed tree at <ref>, not uncommitted working changes.

set -euo pipefail

SLUG="agent-abilities-for-mcp"
REF="${1:-HEAD}"

# Resolve the repo root so the script works from any cwd.
ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

PLUGIN_FILE="$SLUG.php"
README="readme.txt"
BUILD_DIR="$ROOT/build"

if [[ ! -f "$PLUGIN_FILE" ]]; then
	echo "error: $PLUGIN_FILE not found at repo root ($ROOT)" >&2
	exit 1
fi

# --- Version: read from the plugin header, the single source of truth for the filename ---
VERSION="$(grep -iE '^[[:space:]]*\*?[[:space:]]*Version:' "$PLUGIN_FILE" \
	| head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]\r')"

if [[ -z "$VERSION" ]]; then
	echo "error: could not parse the Version header from $PLUGIN_FILE" >&2
	exit 1
fi

# --- Cross-check the constant and the readme stable tag; warn (do not fail) on drift ---
CONST_VERSION="$(grep -E "define\(\s*'AAFM_VERSION'" "$PLUGIN_FILE" \
	| head -1 | sed -E "s/.*,[[:space:]]*'([^']+)'.*/\1/" || true)"
STABLE_TAG=""
if [[ -f "$README" ]]; then
	STABLE_TAG="$(grep -iE '^Stable tag:' "$README" \
		| head -1 | sed -E 's/.*:[[:space:]]*//' | tr -d '[:space:]\r' || true)"
fi

if [[ -n "$CONST_VERSION" && "$CONST_VERSION" != "$VERSION" ]]; then
	echo "warning: AAFM_VERSION ($CONST_VERSION) does not match the header ($VERSION)" >&2
fi
if [[ -n "$STABLE_TAG" && "$STABLE_TAG" != "$VERSION" ]]; then
	echo "warning: readme Stable tag ($STABLE_TAG) does not match the header ($VERSION)" >&2
fi

# --- Heads-up if the working tree has staged/unstaged edits to tracked files ---
if ! git diff --quiet "$REF" -- 2>/dev/null || ! git diff --cached --quiet 2>/dev/null; then
	echo "note: working tree has uncommitted changes; the zip is built from $REF, not your edits" >&2
fi

mkdir -p "$BUILD_DIR"
ZIP="$BUILD_DIR/$SLUG-$VERSION.zip"
rm -f "$ZIP"

git archive --format=zip --prefix="$SLUG/" -o "$ZIP" "$REF"

# --- Sanity check: a couple of things that must be present, a couple that must not ---
present_count() { unzip -l "$ZIP" | grep -c "$SLUG/$1" || true; }
absent_count()  { unzip -l "$ZIP" | grep -c "$1" || true; }

echo
echo "Built: $ZIP ($(du -h "$ZIP" | cut -f1 | tr -d '[:space:]'))"
echo "Version: $VERSION   (ref: $REF)"
echo
printf '  %-32s %s\n' "composer.json (present)"        "$(present_count 'composer.json')"
printf '  %-32s %s\n' "vendor/autoload.php (present)"  "$(present_count 'vendor/autoload.php')"
printf '  %-32s %s\n' "readme.txt (present)"           "$(present_count 'readme.txt')"
printf '  %-32s %s\n' "dev vendor phpunit (absent=0)"  "$(absent_count 'vendor/phpunit')"
printf '  %-32s %s\n' "tests/ (absent=0)"              "$(absent_count "$SLUG/tests/")"
printf '  %-32s %s\n' ".claude/ (absent=0)"            "$(absent_count "$SLUG/.claude/")"
printf '  %-32s %s\n' "composer.lock (absent=0)"       "$(absent_count 'composer.lock')"
