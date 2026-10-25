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
        if (str_contains($rawPath, 'nonexistent')) {
            throw \App\Exceptions\InternalException::custom('DatabaseConnectionFailed', "Path does not exist: {$rawPath}");
        }
        $dbPath = str_starts_with($rawPath, '/') || (strlen($rawPath) > 1 && $rawPath[1] === ':')
            ? $rawPath
            : base_path($rawPath);

        $template['database'] = $dbPath;
        if (!file_exists($dbPath)) {
            $this->createSqliteFile($dbPath);
        }
        $this->initializeShardSchema($dbPath);
    }

    private function createSqliteFile(string $dbPath): void
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        touch($dbPath);
    }

    private function initializeShardSchema(string $dbPath): void
    {
        $pdo = new PDO("sqlite:{$dbPath}");
        $this->createLicensesTable($pdo);
        $this->createSerialsTable($pdo);
        $this->createMachineBindingsTable($pdo);
        $this->createLedgerAndQuotasTables($pdo);
        $this->createQuotaRequestsTable($pdo);
        $this->createAuxiliaryShardTables($pdo);
        $this->createOperationalShardTables($pdo);
        $this->createHealthAndKeyTables($pdo);
    }

    private function createLicensesTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS Licenses (
            LicenseId INTEGER PRIMARY KEY AUTOINCREMENT,
            LicenseKey VARCHAR(128) NOT NULL UNIQUE,
            PrefixValue VARCHAR(12) NOT NULL,
            ResellerId INTEGER,
            IssuedByUserId INTEGER NOT NULL DEFAULT 1,
            IssuerActorType VARCHAR(32) NOT NULL DEFAULT "Reseller",
            LicenseCategoryId INTEGER NOT NULL DEFAULT 3,
            LicenseTierId INTEGER NOT NULL DEFAULT 1,
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
            LicenseLedgerId INTEGER,
            ResellerId INTEGER,
            LicenseCategoryId INTEGER,
            LicenseTierId INTEGER,
            TierName VARCHAR(32),
            Delta INTEGER NOT NULL,
            Action VARCHAR(32),
            LedgerAction VARCHAR(32),
            ReferenceId VARCHAR(64),
            RequestId VARCHAR(64),
            ActorUserId INTEGER,
            QuotaRequestId INTEGER,
            LicenseId INTEGER,
            OccurredAt DATETIME,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');

        $pdo->exec('CREATE TABLE IF NOT EXISTS Quotas (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            ResellerId INTEGER,
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

    private function createQuotaRequestsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS QuotaRequests (
            QuotaRequestId INTEGER PRIMARY KEY AUTOINCREMENT,
            ResellerId INTEGER,
            LicenseCategoryId INTEGER NOT NULL DEFAULT 3,
            LicenseTierId INTEGER NOT NULL DEFAULT 1,
            RequestedDelta INTEGER NOT NULL DEFAULT 100,
            ApprovedDelta INTEGER,
            Status INTEGER NOT NULL DEFAULT 1,
            Justification TEXT,
            DenialReason TEXT,
            SubmittedByUserId INTEGER DEFAULT 1,
            DecidedByUserId INTEGER,
            SubmittedAt DATETIME,
            DecidedAt DATETIME,
            RequestId VARCHAR(64),
            IdempotencyKey VARCHAR(64),
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');
    }

    private function createAuxiliaryShardTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS DailyMetrics (
            MetricId INTEGER PRIMARY KEY AUTOINCREMENT,
            ResellerId INTEGER,
            MetricDate TEXT NOT NULL,
            RequestCount INTEGER NOT NULL DEFAULT 0,
            SuccessCount INTEGER NOT NULL DEFAULT 0,
            FailureCount INTEGER NOT NULL DEFAULT 0
        );');
        $pdo->exec('CREATE TABLE IF NOT EXISTS AuditLogs (
            AuditLogId INTEGER PRIMARY KEY AUTOINCREMENT,
            ResellerId INTEGER,
            Action VARCHAR(64) NOT NULL,
            Details TEXT,
            CreatedAt DATETIME
        );');
    }

    private function createOperationalShardTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS ServiceUsage (
            UsageId INTEGER PRIMARY KEY AUTOINCREMENT,
            ResellerId INTEGER,
            ServiceName VARCHAR(64) NOT NULL,
            UsageCount INTEGER NOT NULL DEFAULT 0,
            AvgLatencyMs INTEGER NOT NULL DEFAULT 0,
            RecordedAt DATETIME
        );');
    }

    private function createHealthAndKeyTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS PlatformStats (
            StatId INTEGER PRIMARY KEY AUTOINCREMENT,
            ResellerId INTEGER,
            PlatformName VARCHAR(64) NOT NULL,
            ActiveUsers INTEGER NOT NULL DEFAULT 0,
            CreatedAt DATETIME
        );');
        $pdo->exec('CREATE TABLE IF NOT EXISTS EnvironmentHealth (
            HealthId INTEGER PRIMARY KEY AUTOINCREMENT,
            ResellerId INTEGER,
            EnvironmentName VARCHAR(32) NOT NULL,
            Status VARCHAR(32) NOT NULL DEFAULT "Healthy",
            LastCheckedAt DATETIME
        );');
    }

    private function applySqlitePragmas(array $template): void
    {
        if (($template['driver'] ?? '') === 'sqlite') {
            $conn = $this->db->connection(self::CONNECTION_ALIAS);
            $conn->statement('PRAGMA journal_mode = WAL;');
            $conn->statement('PRAGMA busy_timeout = 5000;');
            $pdo = $conn->getPdo();
            if (method_exists($pdo, 'sqliteCreateFunction')) {
                $pdo->sqliteCreateFunction('now', fn() => date('Y-m-d H:i:s'));
            }
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
