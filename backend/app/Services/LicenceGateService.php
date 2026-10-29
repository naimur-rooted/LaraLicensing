<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use PDO;

/** One read-only licence status check, used by gate, scoring, and tamper verify. */
final class LicenceGateService
{
    /** Check a licence without binding a seat or creating a row. */
    public function check(string $productSlug, string $serial): array
    {
        $product = Product::where('productSlug', $productSlug)->where('isActive', true)->first();
        if ($product === null) {
            return $this->status('ProductNotFound');
        }
        $shardPath = database_path("product_{$productSlug}.sqlite");
        if (file_exists($shardPath) === false) {
            return $this->status('LicenseNotFound');
        }
        $pdo = new PDO("sqlite:{$shardPath}");
        $license = $this->findLicense($pdo, $serial);
        if ($license === null) {
            return $this->status('LicenseNotFound');
        }
        if (empty($license['isActive']) || !empty($license['isRevoked'])) {
            return $this->status('LicenseRevoked', $license);
        }
        if (LicenseExpiry::isExpired($license['expiresAt'] ?? null)) {
            return $this->status('LicenseExpired', $license);
        }

        return $this->status('Valid', $license, $this->countBindings($pdo, (int) $license['id']));
    }

    private function status(string $status, ?array $license = null, int $activeSeats = 0): array
    {
        return [
            'status' => $status,
            'isValid' => $status === 'Valid',
            'licenseId' => $license ? (int) $license['id'] : null,
            'dealCode' => $license ? (string) $license['dealCode'] : null,
            'allowedSeats' => $license ? (int) $license['allowedSeats'] : 0,
            'activeSeats' => $activeSeats,
            'expiresAt' => $license['expiresAt'] ?? null,
        ];
    }

    private function findLicense(PDO $pdo, string $serial): ?array
    {
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ProductLicenses'")->fetch();
        if ($check === false) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM ProductLicenses WHERE serialValue = :serial LIMIT 1');
        $stmt->execute([':serial' => $serial]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function countBindings(PDO $pdo, int $licenseId): int
    {
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='MachineBindings'")->fetch();
        if ($check === false) {
            return 0;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM MachineBindings WHERE LicenseId = :lid AND ReleasedAt IS NULL');
        $stmt->execute([':lid' => $licenseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    }
}
