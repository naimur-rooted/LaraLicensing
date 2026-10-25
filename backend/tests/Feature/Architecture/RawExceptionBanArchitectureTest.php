<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Plan 11 step 40. Backend mirror of the frontend ESLint
 * `no-restricted-syntax` gate shipped in v0.435.0 (step 39). Bans raw
 * `throw new \Exception(...)` / `\RuntimeException(...)` /
 * `\InvalidArgumentException(...)` / `\LogicException(...)` /
 * `\DomainException(...)` across the whole of `backend/app/`, not just
 * the Controllers + Services subtree covered by
 * `ErrorContractArchitectureTest` (step 15).
 *
 * Root cause this guards: `LaraException::make(...)` is the only path
 * that mints an `ErrorId`, attaches `Retry-After` for rate-limit codes,
 * writes the redacted trace to the `lara-diag` daily channel, and pins
 * the closed-set `ErrorCode`. Any raw `throw new \RuntimeException`
 * outside the documented infra allowlist bypasses all four contracts,
 * so operators cannot correlate the caller-reported id back to the
 * stack trace and the FE Global Error Modal degrades to a generic
 * `ServerError`.
 *
 * A discrete allowlist (not a wholesale directory carve-out) is used
 * so every legitimate infra throw has to be renewed by hand when the
 * surrounding file changes, and net-new violations fail the build.
 *
 * Spec anchors: 02-spec/03-error-manage/02-error-architecture,
 * 02-spec/02-coding-guidelines/04-php.
 */
final class RawExceptionBanArchitectureTest extends TestCase
{
    /**
     * Documented baseline of raw exception throws that predate the ban.
     * Each entry is `relative/path.php:line` under `backend/app/`.
     *
     * Renewal rule: when you edit one of these files, keep the line
     * numbers accurate or convert the throw to `LaraException::make`.
     * Do NOT add new entries; move the failing site to `LaraException`
     * instead. The allowlist exists only to avoid a big-bang cleanup
     * of infra guards that fire before the request lifecycle.
     */
    private const BASELINE_ALLOWLIST = [
        'Console/Commands/BrBackfillCommand.php:53',
        'Console/Commands/BrKillSwitchDrillCommand.php:67',
        'Console/Commands/BrKillSwitchDrillCommand.php:71',
        'Console/Commands/BrOpsCommand.php:78',
        'Console/Commands/BrOpsCommand.php:118',
        'Console/Commands/BrOpsCommand.php:128',
        'Console/Commands/DbMigrationIdempotencyCommand.php:125',
        'Console/Commands/DbMigrationIdempotencyCommand.php:138',
        'Console/Commands/ShardRouteCommand.php:92',
        'Domain/ClosedSets/ClosedSetCatalogue.php:97',
        'Domain/ClosedSets/ClosedSetCatalogue.php:115',
        'Exceptions/LaraException.php:91',
        'Http/Middleware/AssertEnvelopeMiddleware.php:78',
        'Policies/HasRolePolicy.php:39',
        'Policies/HasRolePolicy.php:61',
        'Support/AssetSigner.php:53',
        'Support/AssetSigner.php:58',
        'Support/AssetSigner.php:63',
        'Support/AssetSigner.php:76',
        'Support/AssetSigner.php:80',
        'Support/AssetSigner.php:87',
        'Support/LoginCaptcha.php:82',
        'Support/LoginCaptcha.php:112',
    ];

    public function test_no_new_raw_exception_throws_across_backend_app(): void
    {
        $pattern = '/throw\s+new\s+\\\\?(Exception|RuntimeException|InvalidArgumentException|LogicException|DomainException)\s*\(/';
        $hits = $this->scanForPattern($pattern);
        $baseline = array_flip(self::BASELINE_ALLOWLIST);
        $newViolations = [];
        $staleAllowlistEntries = array_flip(self::BASELINE_ALLOWLIST);
        foreach ($hits as $hit) {
            $key = $this->stripContentSuffix($hit);
            if (isset($baseline[$key])) {
                unset($staleAllowlistEntries[$key]);
                continue;
            }
            $newViolations[] = $hit;
        }
        $this->assertSame(
            [],
            $newViolations,
            "Forbidden raw exception throw. Use LaraException::make('CodeName', ...) with a closed-set code from backend/config/lara.php; see docs/contributing/error-management-cheatsheet.md.\n".implode("\n", $newViolations),
        );
        $this->assertSame(
            [],
            array_keys($staleAllowlistEntries),
            "Stale RawExceptionBan allowlist entries (no longer match a real throw). Remove them from BASELINE_ALLOWLIST:\n".implode("\n", array_keys($staleAllowlistEntries)),
        );
    }

    private function stripContentSuffix(string $hit): string
    {
        $parts = explode(':', $hit, 3);
        $key = isset($parts[0], $parts[1]) ? $parts[0].':'.$parts[1] : $hit;

        return str_replace('\\', '/', $key);
    }

    /**
     * @return list<string> Human-readable "path:line: content" hits.
     */
    private function scanForPattern(string $regex): array
    {
        $hits = [];
        $base = base_path('app');
        if (! is_dir($base)) {
            return $hits;
        }
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
        foreach ($iter as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $i => $line) {
                $stripped = ltrim($line);
                if (str_starts_with($stripped, '//') || str_starts_with($stripped, '*') || str_starts_with($stripped, '#')) {
                    continue;
                }
                if (preg_match($regex, $line) === 1) {
                    $rel = str_replace($base.DIRECTORY_SEPARATOR, '', $path);
                    $hits[] = sprintf('%s:%d: %s', $rel, $i + 1, trim($line));
                }
            }
        }
        sort($hits);

        return $hits;
    }
}
