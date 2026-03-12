#!/bin/bash

set -euo pipefail

echo "::group::running pre-deploy script..."

echo "::notice::set site to maintenance mode..."
php artisan down || true

echo "::notice::cleaning cache..."
php artisan optimize:clear

echo "::endgroup::"

echo "::notice::pre-deploy script was a success!"
