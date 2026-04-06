<?php

namespace App\Jobs;

use App\Services\Subscriptions\ExpireOverdueSubscriptions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExpireOverdueSubscriptionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        ExpireOverdueSubscriptions $expireOverdueSubscriptions,
    ): void {
        $expiredCount = $expireOverdueSubscriptions->handle();

        Log::info('ExpireOverdueSubscriptionsJob completed.', [
            'expired_subscriptions_count' => $expiredCount,
        ]);
    }
}
