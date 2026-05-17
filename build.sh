#!/usr/bin/env bash
# this_file: build.sh
# Build script for paragra-php
# Installs dependencies and runs the full QA suite (lint, static analysis, tests).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "==> Installing Composer dependencies..."
composer install --no-interaction --prefer-dist

echo "==> Running QA suite (lint + static analysis + tests)..."
composer qa

echo "Build complete."
