<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Risk-Based Assessment service assessing IP geo-velocity and HWID drift.
 */
class RiskAssessmentService
{
    private const CACHE_TTL_HOURS = 24;

    /**
     * Computes a risk score between 0 and 100 for a machine binding attempt.
     *
     * @return array{riskScore: int, isPermitted: bool, reasons: array<string>}
     */
    public function evaluateAttempt(string $serialKey, string $incomingIp, string $machineFingerprint): array
    {
        $reasons = [];
        $score = 0;
        $prevData = Cache::get('rba:serial:' . $serialKey);

        if ($prevData !== null) {
            $score += $this->evaluateGeoDrift($prevData['ip'], $incomingIp, $reasons);
            $score += $this->evaluateHwidDrift($prevData['hwid'], $machineFingerprint, $reasons);
        }

        Cache::put('rba:serial:' . $serialKey, [
            'ip' => $incomingIp,
            'hwid' => $machineFingerprint,
            'time' => time(),
        ], now()->addHours(self::CACHE_TTL_HOURS));

        return [
            'riskScore' => min(100, $score),
            'isPermitted' => $score < 75,
            'reasons' => $reasons,
        ];
    }

    private function evaluateGeoDrift(string $prevIp, string $currentIp, array &$reasons): int
    {
        if ($prevIp !== $currentIp && !$this->isLocalOrSubnet($prevIp, $currentIp)) {
            $reasons[] = 'IP geographic or subnet divergence detected';
            return 35;
        }
        return 0;
    }

    private function evaluateHwidDrift(string $prevHwid, string $currentHwid, array &$reasons): int
    {
        if ($prevHwid !== $currentHwid) {
            $matchingChars = similar_text($prevHwid, $currentHwid);
            if ($matchingChars > 8 && $matchingChars < strlen($prevHwid)) {
                $reasons[] = 'Hardware signature partial drift detected';
                return 40;
            }
        }
        return 0;
    }

    private function isLocalOrSubnet(string $ip1, string $ip2): bool
    {
        if ($ip1 === $ip2 || $ip1 === '127.0.0.1' || $ip2 === '127.0.0.1') {
            return true;
        }
        $sub1 = substr($ip1, 0, strrpos($ip1, '.') ?: null);
        $sub2 = substr($ip2, 0, strrpos($ip2, '.') ?: null);
        return $sub1 === $sub2;
    }
}
