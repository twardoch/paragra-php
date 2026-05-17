#!/usr/bin/env bash
# this_file: install.sh
# Install paragra-php dependencies via Composer.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "==> Installing Composer dependencies (production)..."
composer install --no-interaction --prefer-dist --no-dev
echo "Done."
