#!/usr/bin/env bash
# this_file: publish.sh
# Build, bump version, tag a release, and notify Packagist for paragra-php.
# PHP packages are distributed via Packagist on git tag push.
# Packagist auto-detects GitHub pushes via the GitHub Service hook (preferred),
# or via the manual webhook below if the GitHub Service hook is not configured.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "==> Building and running QA..."
bash "$SCRIPT_DIR/build.sh"

echo "==> Bumping version..."
uvx gitnextver@latest .

echo "==> Pushing tag to remote..."
git push --follow-tags

# ---------------------------------------------------------------------------
# Packagist webhook notification
# Packagist crawls GitHub push events automatically when the GitHub Service
# hook is enabled on your repository. If you need to trigger crawling manually
# (e.g. from a local release script), set PACKAGIST_TOKEN and PACKAGIST_USER
# in your environment and this block will POST to the Packagist API.
# ---------------------------------------------------------------------------
PACKAGIST_PACKAGE="vexy/paragra-php"

if [[ -n "${PACKAGIST_TOKEN:-}" && -n "${PACKAGIST_USER:-}" ]]; then
    echo "==> Notifying Packagist webhook for ${PACKAGIST_PACKAGE}..."
    HTTP_STATUS=$(curl --silent --output /dev/null --write-out "%{http_code}" \
        -X POST \
        "https://packagist.org/api/update-package?username=${PACKAGIST_USER}&apiToken=${PACKAGIST_TOKEN}" \
        -H "Content-Type: application/json" \
        -d "{\"repository\":{\"url\":\"https://github.com/twardoch/rag-projects\"}}")

    if [[ "$HTTP_STATUS" == "202" ]]; then
        echo "    Packagist notified successfully (HTTP 202)."
    else
        echo "    Warning: Packagist returned HTTP ${HTTP_STATUS}. Check credentials." >&2
    fi
else
    echo "    Skipping Packagist webhook (PACKAGIST_TOKEN / PACKAGIST_USER not set)."
    echo "    Packagist will still update automatically via the GitHub Service hook."
fi

echo "Release tagged and pushed. Packagist will pick it up automatically."
