<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Db\ShardResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Middleware enabling Sanctum authentication with preview mode fallback.
 */
final class ResellerAuthOrPreview
{
    private const CONN_ROOT = 'root';
    private const DEFAULT_RESELLER_ID = 1;
    private const DEFAULT_RESELLER_SLUG = 'alim';

    public function __construct(private readonly ShardResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $resellerId = $this->resolveResellerId($request);
        $resellerSlug = $this->resolveResellerSlug($resellerId, $request);

        $request->attributes->set('ResellerId', $resellerId);
        $request->attributes->set('ResellerSlug', $resellerSlug);

        $this->bindShardSafely($resellerSlug);

        return $next($request);
    }

    private function resolveResellerId(Request $request): int
    {
        $user = $request->user();
        if ($user !== null && isset($user->TenantId) && (int) $user->TenantId > 0) {
            return (int) $user->TenantId;
        }

        $headerId = $request->header('X-Reseller-Id');
        if ($headerId !== null && is_numeric($headerId) && (int) $headerId > 0) {
            return (int) $headerId;
        }

        $queryId = $request->query('ResellerId');
        if ($queryId !== null && is_numeric($queryId) && (int) $queryId > 0) {
            return (int) $queryId;
        }

        return self::DEFAULT_RESELLER_ID;
    }

    private function resolveResellerSlug(int $resellerId, Request $request): string
    {
        $headerSlug = (string) ($request->header('X-Reseller-Slug') ?? '');
        if ($headerSlug !== '') {
            return $headerSlug;
        }

        $querySlug = (string) ($request->query('ResellerSlug') ?? '');
        if ($querySlug !== '') {
            return $querySlug;
        }

        try {
            $slug = DB::connection(self::CONN_ROOT)->table('Resellers')
                ->where('ResellerId', $resellerId)
                ->value('ResellerSlug');
            if (is_string($slug) && $slug !== '') {
                return $slug;
            }
        } catch (Throwable) {
            // Fallback to default
        }

        return self::DEFAULT_RESELLER_SLUG;
    }

    private function bindShardSafely(string $slug): void
    {
        try {
            $this->resolver->bind($slug);
        } catch (Throwable) {
            // In single-root SQLite dev mode, keep root connection
        }
    }
}
