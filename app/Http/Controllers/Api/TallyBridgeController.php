<?php

namespace App\Http\Controllers\Api;

use App\Domain\Integrations\TallySyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyBridgeController extends Controller
{
    protected function authorizeBridge(Request $request, TallySyncService $syncService): void
    {
        $token = $request->bearerToken() ?: $request->header('X-Tally-Bridge-Token');

        if (! $syncService->verifyBridgeToken($token)) {
            abort(401, 'Unauthorized: invalid or unconfigured Tally bridge token.');
        }
    }

    public function pullPending(Request $request, TallySyncService $syncService): JsonResponse
    {
        $this->authorizeBridge($request, $syncService);

        $limit = max(1, min(200, (int) $request->query('limit', 50)));

        return response()->json($syncService->pullPendingBatch($limit));
    }

    public function acknowledgeBatch(Request $request, TallySyncService $syncService): JsonResponse
    {
        $this->authorizeBridge($request, $syncService);

        $validated = $request->validate([
            'outbox_ids' => 'required|array|min:1|max:200',
            'outbox_ids.*' => 'integer',
            'status' => 'required|string|in:acknowledged,rejected,error',
            'error_message' => 'nullable|string|max:1000',
        ]);

        $result = $syncService->acknowledgePullBatch(
            outboxIds: $validated['outbox_ids'],
            resultStatus: $validated['status'],
            errorMessage: $validated['error_message'] ?? null,
        );

        return response()->json($result);
    }

    public function downloadMasters(Request $request, TallySyncService $syncService): JsonResponse
    {
        $this->authorizeBridge($request, $syncService);

        return response()->json($syncService->buildMastersXmlExport());
    }
}

