<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Ensures ProductLicenses shard tables carry the Phase 3 columns.
 * New shards get them in CREATE TABLE; existing shards get them
 * via ALTER TABLE, because CREATE TABLE IF NOT EXISTS never
 * retrofits a missing column. Columns: customerEmail, transactionId,
 * expiresAt (all nullable; lifetime licences have null expiresAt).
 */
class LicenseShardSchema
{
    public static function ensureProductLicenses(PDO $pdo): void
    {
        self::createTable($pdo);
        self::addMissingColumns($pdo);
    }

    private static function createTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS ProductLicenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            serialValue TEXT UNIQUE NOT NULL,
            dealCode TEXT NOT NULL,
            allowedSeats INTEGER DEFAULT 1,
            isActive INTEGER DEFAULT 1,
            isRevoked INTEGER DEFAULT 0,
            customerEmail TEXT NULL,
            transactionId TEXT NULL,
            expiresAt TEXT NULL,
            createdAt TEXT NOT NULL
        );');
    }

    private static function addMissingColumns(PDO $pdo): void
    {
        foreach (self::newColumns() as $col => $type) {
            if (!self::hasColumn($pdo, $col)) {
                $pdo->exec("ALTER TABLE ProductLicenses ADD COLUMN {$col} {$type}");
            }
        }
    }

    /** @return array<string,string> */
    private static function newColumns(): array
    {
        return [
            'customerEmail' => 'TEXT NULL',
            'transactionId' => 'TEXT NULL',
            'expiresAt' => 'TEXT NULL',
        ];
    }

    private static function hasColumn(PDO $pdo, string $col): bool
    {
        $rows = $pdo->query('PRAGMA table_info(ProductLicenses)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (($row['name'] ?? '') === $col) {
                return true;
            }
        }

        return false;
    }
}
