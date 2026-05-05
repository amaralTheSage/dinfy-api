<?php

namespace App\Jobs;

use App\Services\OpenFinance\OpenFinanceStatementSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncOpenFinanceStatementsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ?int $limit = null,
    ) {}

    public function handle(OpenFinanceStatementSyncService $syncService): void
    {
        Log::info('SyncOpenFinanceStatementsJob started.', [
            'limit' => $this->limit,
        ]);

        $stats = $syncService->handle($this->limit);

        Log::info('SyncOpenFinanceStatementsJob completed.', $stats);
    }
}
