<?php

declare(strict_types=1);

namespace App\Db;

use App\Exceptions\InternalException;
use App\Exceptions\NotFoundException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use PDO;

/**
 * Runtime factory that binds a per-Reseller shard connection.
 */
class ShardResolver
{
    private const CONNECTION_ALIAS = 'shard';
    private const TEMPLATE_ALIAS = 'shard_template';

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly DatabaseManager $db,
    ) {
    }

    public function bind(string $resellerSlug): void
    {
        if ($resellerSlug === '') {
            throw NotFoundException::notFound(
                'ResellerNotFound',
                'Reseller slug is required to open a shard connection.',
                [['Field' => 'ResellerSlug', 'Rule' => 'Required']]
            );
        }

        $template = $this->readTemplate();
        $template['database'] = str_replace('{reseller}', $resellerSlug, (string) $template['database']);

        $this->ensureSqliteFileIfNeeded($template);
        $this->config->set('database.connections.' . self::CONNECTION_ALIAS, $template);
        $this->db->purge(self::CONNECTION_ALIAS);
        $this->applySqlitePragmas($template);

        Log::info('shard.bind', ['ResellerSlug' => $resellerSlug, 'Database' => $template['database']]);
    }

    private function ensureSqliteFileIfNeeded(array &$template): void
    {
        if (($template['driver'] ?? '') !== 'sqlite') {
            return;
        }

        $rawPath = (string) $template['database'];
        $dbPath = str_starts_with($rawPath, '/') || (strlen($rawPath) > 1 && $rawPath[1] === ':')
            ? $rawPath
            : base_path($rawPath);

        $template['database'] = $dbPath;
        if (!file_exists($dbPath)) {
            $this->createAndInitializeSqliteFile($dbPath);
        }
    }

    private function createAndInitializeSqliteFile(string $dbPath): void
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        touch($dbPath);
        $this->initializeShardSchema($dbPath);
    }

    private function initializeShardSchema(string $dbPath): void
    {
        $pdo = new PDO("sqlite:{$dbPath}");
        $this->createLicensesTable($pdo);
        $this->createSerialsTable($pdo);
        $this->createMachineBindingsTable($pdo);
        $this->createLedgerAndQuotasTables($pdo);
    }

    private function createLicensesTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS Licenses (
            LicenseId INTEGER PRIMARY KEY AUTOINCREMENT,
            LicenseKey VARCHAR(128) NOT NULL UNIQUE,
            PrefixValue VARCHAR(12) NOT NULL,
            ResellerId INTEGER NOT NULL,
            IssuedByUserId INTEGER NOT NULL DEFAULT 1,
            IssuerActorType VARCHAR(32) NOT NULL DEFAULT "Reseller",
            LicenseCategoryId INTEGER NOT NULL DEFAULT 3,
            TierName VARCHAR(32) NOT NULL DEFAULT "Tier1",
            EnvironmentName VARCHAR(32) NOT NULL DEFAULT "Production",
            ProductVersion VARCHAR(16) NOT NULL DEFAULT "V1",
            Status VARCHAR(32) NOT NULL DEFAULT "Active",
            IssuedAt DATETIME,
            ExpiresAt DATETIME,
            RevokedAt DATETIME,
            RevokedByUserId INTEGER,
            RevokeReason VARCHAR(255),
            ResellerQuotaLedgerId INTEGER,
            Version INTEGER NOT NULL DEFAULT 1,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');
    }

    private function createSerialsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS Serials (
            SerialId INTEGER PRIMARY KEY AUTOINCREMENT,
            LicenseId INTEGER NOT NULL,
            SerialValue VARCHAR(128) NOT NULL UNIQUE,
            DeviceIdHash VARCHAR(64),
            EnvironmentName VARCHAR(32) NOT NULL DEFAULT "Production",
            FeaturePayloadHash VARCHAR(64),
            IdempotencyKey VARCHAR(64),
            IsRevoked INTEGER NOT NULL DEFAULT 0,
            IssuedAt DATETIME,
            LastVerifiedAt DATETIME,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');
    }

    private function createMachineBindingsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS MachineBindings (
            MachineBindingId INTEGER PRIMARY KEY AUTOINCREMENT,
            LicenseId INTEGER NOT NULL,
            FingerprintHash VARCHAR(64) NOT NULL,
            IpAddress VARCHAR(45),
            Hostname VARCHAR(128),
            FirstSeenAt DATETIME,
            LastSeenAt DATETIME,
            ReleasedAt DATETIME,
            RebindCooldownUntil DATETIME,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');
    }

    private function createLedgerAndQuotasTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS LicenseLedger (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            ResellerId INTEGER NOT NULL,
            LicenseCategoryId INTEGER,
            LicenseTierId INTEGER,
            TierName VARCHAR(32),
            Delta INTEGER NOT NULL,
            Action VARCHAR(32) NOT NULL,
            LedgerAction VARCHAR(32),
            ReferenceId VARCHAR(64),
            LicenseId INTEGER,
            OccurredAt DATETIME,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');

        $pdo->exec('CREATE TABLE IF NOT EXISTS Quotas (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            ResellerId INTEGER NOT NULL,
            LicenseCategoryId INTEGER NOT NULL DEFAULT 3,
            LicenseTierId INTEGER NOT NULL DEFAULT 1,
            LicensesGranted INTEGER NOT NULL DEFAULT 500,
            LicensesConsumed INTEGER NOT NULL DEFAULT 0,
            PeriodStart DATETIME,
            PeriodEnd DATETIME,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');
    }

    private function applySqlitePragmas(array $template): void
    {
        if (($template['driver'] ?? '') === 'sqlite') {
            $this->db->connection(self::CONNECTION_ALIAS)->statement('PRAGMA journal_mode = WAL;');
            $this->db->connection(self::CONNECTION_ALIAS)->statement('PRAGMA busy_timeout = 5000;');
        }
    }

    /** @return array<string, mixed> */
    private function readTemplate(): array
    {
        $template = $this->config->get('database.connections.' . self::TEMPLATE_ALIAS);
        if (is_array($template) === false) {
            throw InternalException::serverError(
                'ServerError',
                'Shard template connection missing from database config.'
            );
        }

        return $template;
    }

    public static function alias(): string
    {
        return self::CONNECTION_ALIAS;
    }
}
