#!/bin/bash

set -euo pipefail

echo "::group::Running post-deploy script..."

echo "::notice::Caching framework..."
php artisan optimize

php artisan migrate --force --isolated

echo "::notice::Bring site back from maintenance mode..."
php artisan up

echo "::notice::Reset storage link just be sure..."
php artisan storage:unlink
php artisan storage:link

# echo "::notice::Starts Reverb"
# pkill -f "artisan reverb:start" || true
# nohup php artisan reverb:start --debug > storage/logs/reverb.log 2>&1 &

echo "::endgroup::"

echo "::notice::Post-deploy script was a success!"
