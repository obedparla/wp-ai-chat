#!/usr/bin/env bash
#
# Build a distributable plugin ZIP for upload to the Freemius dashboard.
#
# vendor/ (Freemius SDK + OpenAI/Guzzle/TNTSearch) and frontend/dist/ are
# gitignored, so they MUST be built here. Packaging from a clean git checkout
# would ship a plugin that fatals on activation (no SDK) and renders no widget
# (no built JS) — and Freemius would then push that broken build to every site.
#
# Usage:  bin/build-release.sh
# Output: build/wp-ai-chatbot-<version>.zip
#
set -euo pipefail

PLUGIN_SLUG="wp-ai-chatbot"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Composer is not assumed to be on PATH; allow an override.
if [ -n "${COMPOSER:-}" ]; then
  COMPOSER_CMD="$COMPOSER"
elif command -v composer >/dev/null 2>&1; then
  COMPOSER_CMD="composer"
elif [ -f composer.phar ]; then
  COMPOSER_CMD="php composer.phar"
else
  echo "ERROR: composer not found. Install it or run: COMPOSER=/path/to/composer $0" >&2
  exit 1
fi

# The plugin header is the single source of truth for the version. Fail loudly
# if the constant or the frontend package drift from it — a version mismatch is
# the classic reason an "update" silently does nothing.
VERSION="$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_SLUG.php" | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')"
[ -n "$VERSION" ] || { echo "ERROR: no Version header in $PLUGIN_SLUG.php" >&2; exit 1; }

CONST_VERSION="$(grep -m1 "WPAIC_VERSION'" "$PLUGIN_SLUG.php" | sed -E "s/.*WPAIC_VERSION',[[:space:]]*'([^']+)'.*/\1/")"
FE_VERSION="$(grep -m1 '"version"' frontend/package.json | sed -E 's/.*"version":[[:space:]]*"([^"]+)".*/\1/')"
if [ "$VERSION" != "$CONST_VERSION" ] || [ "$VERSION" != "$FE_VERSION" ]; then
  echo "ERROR: version mismatch — header=$VERSION  WPAIC_VERSION=$CONST_VERSION  frontend/package.json=$FE_VERSION" >&2
  exit 1
fi

echo "==> Packaging $PLUGIN_SLUG v$VERSION"

echo "==> Building frontend"
( cd frontend && npm ci && npm run build )
[ -f frontend/dist/.vite/manifest.json ] || { echo "ERROR: frontend build produced no manifest" >&2; exit 1; }

BUILD_DIR="$ROOT/build"
STAGE="$BUILD_DIR/$PLUGIN_SLUG"
ZIP="$BUILD_DIR/$PLUGIN_SLUG-$VERSION.zip"
rm -rf "$STAGE" "$ZIP"
mkdir -p "$STAGE/frontend"

# Stage the runtime files. vendor/ is excluded here and reinstalled clean below
# (so a release never ships dev deps); frontend/ is excluded and its built dist
# copied back. Leading-slash excludes are anchored to the repo root so they only
# drop the top-level dev/planning items, never anything inside dist/.
echo "==> Staging plugin files"
rsync -a ./ "$STAGE/" \
  --exclude='/build' \
  --exclude='/bin' \
  --exclude='/frontend' \
  --exclude='/vendor' \
  --exclude='.git' \
  --exclude='/.github' \
  --exclude='/.idea' \
  --exclude='/.claude' \
  --exclude='.DS_Store' \
  --exclude='*.iml' \
  --exclude='node_modules' \
  --exclude='/tests' \
  --exclude='/.phpunit.cache' \
  --exclude='/phpunit.xml' \
  --exclude='/phpstan.neon' \
  --exclude='/phpstan-bootstrap.php' \
  --exclude='/phpstan-bootstrap-cli.php' \
  --exclude='/.phpcs.xml.dist' \
  --exclude='/AGENTS.md' \
  --exclude='/CLAUDE.md' \
  --exclude='/README.md' \
  --exclude='/PRD.md' \
  --exclude='/PROMPT_prd.md' \
  --exclude='/plan.md' \
  --exclude='/prd.json' \
  --exclude='/todo.json' \
  --exclude='/ideas.json' \
  --exclude='/v1.json' \
  --exclude='/faq.csv' \
  --exclude='/progress.txt' \
  --exclude='/progress_done.txt'

echo "==> Installing production composer deps into the package"
( cd "$STAGE" && $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction )
rm -f "$STAGE/composer.json" "$STAGE/composer.lock"

echo "==> Adding built frontend"
rsync -a frontend/dist "$STAGE/frontend/"

echo "==> Zipping"
( cd "$BUILD_DIR" && zip -rqX "$ZIP" "$PLUGIN_SLUG" )
rm -rf "$STAGE"

# Refuse to hand over a package that is missing any load-bearing piece.
for required in \
  "$PLUGIN_SLUG/vendor/freemius/wordpress-sdk/start.php" \
  "$PLUGIN_SLUG/vendor/autoload.php" \
  "$PLUGIN_SLUG/frontend/dist/.vite/manifest.json" \
  "$PLUGIN_SLUG/$PLUGIN_SLUG.php"; do
  unzip -l "$ZIP" | grep -q "$required" || { echo "ERROR: package is missing $required" >&2; exit 1; }
done

echo ""
echo "==> Built $ZIP"
echo "    Verified: Freemius SDK, composer autoloader, frontend manifest, main file."
echo "    Upload it to the Freemius dashboard (product 27158) to cut a release."
