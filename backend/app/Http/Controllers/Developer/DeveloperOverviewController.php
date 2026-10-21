<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\AiArchitectService;
use App\Services\DeveloperAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Controller providing aggregate developer overview KPIs, timeseries charts,
 * and AI support copilot chat endpoints.
 */
class DeveloperOverviewController extends Controller
{
    public function __construct(
        private readonly DeveloperAnalyticsService $analyticsService,
        private readonly AiArchitectService $aiArchitectService
    ) {}

    /**
     * Get aggregate overview metrics matching Cryptlex dashboard.
     */
    public function overview(Request $request): JsonResponse
    {
        $slug = $request->query('productSlug');
        $data = $this->analyticsService->getOverviewMetrics($slug ? (string) $slug : null);

        return response()->json([
            'IsSuccess' => true,
            'Data' => $data,
        ]);
    }

    /**
     * Send message to Developer AI Copilot / Support Chatbot.
     */
    public function chat(Request $request): JsonResponse
    {
        $message = trim((string) $request->input('message', ''));
        $history = (array) $request->input('history', []);
        $context = $request->input('context');

        if (empty($message)) {
            return response()->json([
                'IsSuccess' => false,
                'ErrorCode' => 'EmptyMessage',
                'Message' => 'Message is required.',
            ], 422);
        }

        try {
            $reply = $this->aiArchitectService->chatWithSupport(
                $message,
                $history,
                is_string($context) ? $context : null
            );

            return response()->json([
                'IsSuccess' => true,
                'Data' => [
                    'reply' => $reply,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'IsSuccess' => false,
                'ErrorCode' => 'ChatError',
                'Message' => $e->getMessage(),
            ], 500);
        }
    }
}
