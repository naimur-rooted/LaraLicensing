<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

/**
 * INV-BR-RB-6 Verification Drill Command.
 */
class BrKillSwitchDrillCommand extends Command
{
    protected $signature = 'br:kill-switch:drill';
    protected $description = 'Executes a kill-switch drill to assert audit trigger firing (INV-BR-RB-6).';

    public function handle(): int
    {
        $this->info('Starting kill-switch drill...');
        $requestId = 'drill-' . Uuid::uuid4()->toString();

        try {
            DB::connection('root')->transaction(fn() => $this->executeDrillTransaction($requestId));
        } catch (\Throwable $e) {
            $this->error("Drill failed: " . $e->getMessage());
            Log::error('br.drill.failed', ['RequestId' => $requestId, 'Reason' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function executeDrillTransaction(string $requestId): void
    {
        DB::connection('root')->select('SELECT pg_advisory_xact_lock(hashtext(?))', ['br.global']);
        $id = Uuid::uuid7()->toString();
        $this->insertDrillAuditRow($id, $requestId);
        $this->assertDrillTriggerFired($id);
        $this->info("Drill successful.");
        Log::info('br.drill.success', ['RequestId' => $requestId]);
    }

    private function insertDrillAuditRow(string $id, string $requestId): void
    {
        DB::connection('root')->table('BackupAuditEvents')->insert([
            'BackupAuditEventId' => $id,
            'OccurredAt'         => now(),
            'Code'               => 'backup.drill',
            'ActorKind'          => 'server',
            'RequestId'          => $requestId,
            'Payload'            => json_encode(['Type' => 'kill-switch-drill']),
            'PrevHash'           => DB::raw("'\\x0000000000000000000000000000000000000000000000000000000000000000'::bytea"),
            'RowHash'            => DB::raw("'\\x0000000000000000000000000000000000000000000000000000000000000000'::bytea"),
            'ShardId'            => 'br.global',
            'SchemaVersion'      => 1,
        ]);
    }

    private function assertDrillTriggerFired(string $id): void
    {
        $row = DB::connection('root')->table('BackupAuditEvents')->where('BackupAuditEventId', $id)->first();
        if (!$row) {
            throw new \RuntimeException("Drill audit row not found after insert.");
        }
        $emptyHash = pack('H*', '0000000000000000000000000000000000000000000000000000000000000000');
        if (stream_get_contents($row->RowHash) === $emptyHash || $row->RowHash === $emptyHash) {
            throw new \RuntimeException("Audit trigger failed to fire: RowHash is still zero-filled. INV-BR-RB-6 violated.");
        }
    }
}
