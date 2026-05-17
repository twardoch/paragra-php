#!/usr/bin/env bash
# this_file: publish.sh
# Build, bump version, and tag a release for paragra-php.
# PHP packages are distributed via Packagist on git tag push.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "==> Building and running QA..."
bash "$SCRIPT_DIR/build.sh"

echo "==> Bumping version..."
uvx gitnextver@latest .

echo "Release tagged. Push the tag; Packagist will pick it up automatically."
