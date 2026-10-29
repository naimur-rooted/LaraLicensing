<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use PDO;

/** Fraud score from server-observed facts and tamper logs, for review, not auto-block. */
final class FraudScoringService
{
    public function __construct(private readonly LicenceGateService $gate)
    {
    }

    /** Score a licence, 0 to 100, with reasons and a review recommendation. */
    public function score(string $productSlug, string $serial): array
    {
        $gate = $this->gate->check($productSlug, $serial);
        if ($gate['status'] !== 'Valid') {
            return ['score' => 0, 'status' => $gate['status'], 'tamperCount' => 0, 'activeSeats' => $gate['activeSeats'], 'allowedSeats' => $gate['allowedSeats'], 'reasons' => [$gate['status']], 'recommendation' => 'none'];
        }
        $tamperCount = $this->countTamperEvents($productSlug, $serial);
        $reasons = [];
        $score = $this->tamperScore($tamperCount, $reasons);
        $score += $this->concurrentScore($gate, $reasons);
        $score = min(100, $score);
        $recommendation = $score >= 70 ? 'quarantine_review' : ($score >= 30 ? 'review' : 'none');

        return ['score' => $score, 'status' => 'Valid', 'tamperCount' => $tamperCount, 'activeSeats' => $gate['activeSeats'], 'allowedSeats' => $gate['allowedSeats'], 'reasons' => $reasons, 'recommendation' => $recommendation];
    }

    private function tamperScore(int $tamperCount, array &$reasons): int
    {
        if ($tamperCount <= 0) {
            return 0;
        }
        $reasons[] = "Tamper events in 7 days: {$tamperCount}";

        return min(90, $tamperCount * 30);
    }

    private function concurrentScore(array $gate, array &$reasons): int
    {
        if ($gate['activeSeats'] <= $gate['allowedSeats']) {
            return 0;
        }
        $reasons[] = "Active bindings {$gate['activeSeats']} above allowed seats {$gate['allowedSeats']}";

        return 40;
    }

    private function countTamperEvents(string $productSlug, string $serial): int
    {
        $shardPath = database_path("product_{$productSlug}.sqlite");
        if (file_exists($shardPath) === false) {
            return 0;
        }
        $pdo = new PDO("sqlite:{$shardPath}");
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='SecurityAuditEvents'")->fetch();
        if ($check === false) {
            return 0;
        }
        $since = Carbon::now()->subDays(7)->toISOString();
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM SecurityAuditEvents WHERE serialValue = :serial AND createdAt >= :since AND reasons LIKE '%Tamper:%'");
        $stmt->execute([':serial' => $serial, ':since' => $since]);

        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    }
}
