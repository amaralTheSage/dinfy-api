<?php

namespace App\Http\Controllers;

use App\Models\AssistantExecution;
use App\Services\Assistant\AssistantActionService;
use App\Services\Assistant\AssistantContextService;
use App\Services\Assistant\AssistantUserResolver;
use App\Support\PhoneNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssistantWebhookController extends Controller
{
    public function __construct(
        private readonly AssistantUserResolver $userResolver,
        private readonly AssistantContextService $contextService,
        private readonly AssistantActionService $actionService,
    ) {
    }

    public function context(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'recentTransactionsLimit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $user = $this->userResolver->resolve($validated['phone']);
        $phoneNormalized = PhoneNormalizer::normalize($validated['phone']) ?? '';
        $limit = (int) ($validated['recentTransactionsLimit'] ?? config('assistant.recent_transactions_limit', 8));

        return response()->json(
            $this->contextService->build($user, $phoneNormalized, $limit)
        );
    }

    public function execute(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'idempotencyKey' => ['required', 'string', 'max:191'],
            'intent' => ['required', 'string', Rule::in(config('assistant.intents', []))],
            'parameters' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        $user = $this->userResolver->resolve($validated['phone']);
        $phoneNormalized = PhoneNormalizer::normalize($validated['phone']) ?? '';
        $requestPayload = [
            'phone' => $validated['phone'],
            'phoneNormalized' => $phoneNormalized,
            'intent' => $validated['intent'],
            'parameters' => $validated['parameters'] ?? [],
            'metadata' => $validated['metadata'] ?? [],
        ];

        $execution = $this->createExecutionPlaceholder(
            $user->id,
            $phoneNormalized,
            $validated['idempotencyKey'],
            $validated['intent'],
            $requestPayload,
        );

        if (!$execution->wasRecentlyCreated) {
            if ($execution->status === 'completed' && is_array($execution->response_payload)) {
                return response()->json([
                    ...$execution->response_payload,
                    'replayed' => true,
                    'idempotencyKey' => $execution->idempotency_key,
                ]);
            }

            return response()->json([
                'message' => 'Essa requisição já está em processamento.',
                'idempotencyKey' => $execution->idempotency_key,
            ], 409);
        }

        try {
            $result = DB::transaction(function () use ($user, $validated): array {
                $result = $this->actionService->execute(
                    $user,
                    $validated['intent'],
                    $validated['parameters'] ?? [],
                    $validated['metadata'] ?? [],
                );

                return [
                    ...$result,
                    'replayed' => false,
                    'idempotencyKey' => $validated['idempotencyKey'],
                ];
            });

            $execution->status = 'completed';
            $execution->response_payload = $result;
            $execution->save();

            return response()->json($result);
        } catch (\Throwable $exception) {
            $execution->delete();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $requestPayload
     */
    private function createExecutionPlaceholder(
        int $userId,
        string $phoneNormalized,
        string $idempotencyKey,
        string $intent,
        array $requestPayload,
    ): AssistantExecution {
        try {
            return AssistantExecution::query()->create([
                'user_id' => $userId,
                'phone_normalized' => $phoneNormalized,
                'idempotency_key' => $idempotencyKey,
                'intent' => $intent,
                'status' => 'processing',
                'request_payload' => $requestPayload,
                'response_payload' => [],
            ]);
        } catch (QueryException $exception) {
            $existing = AssistantExecution::query()
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }
}
