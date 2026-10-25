<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Exceptions\InternalException;
use App\Exceptions\ValidationException;
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
        $rawUserId = (int) ($request->user()?->UserId ?? auth()->id() ?? auth('sanctum')->id());
        $userId = $rawUserId > 0 ? $rawUserId : null;
        $slug = $request->query('productSlug');
        $data = $this->analyticsService->getOverviewMetrics($userId, $slug ? (string) $slug : null);

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
        if ($message === '') {
            throw ValidationException::invalidInput('Message is required.');
        }
        $reply = $this->executeSupportChat($request, $message);
        return response()->json(['IsSuccess' => true, 'Data' => ['reply' => $reply]]);
    }

    private function executeSupportChat(Request $request, string $message): string
    {
        $history = (array) $request->input('history', []);
        $context = $request->input('context');
        try {
            return $this->aiArchitectService->chatWithSupport($message, $history, is_string($context) ? $context : null);
        } catch (Throwable $e) {
            throw InternalException::fatal($e->getMessage());
        }
    }
}
