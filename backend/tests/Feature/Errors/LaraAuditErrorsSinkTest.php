<?php

declare(strict_types=1);

namespace Tests\Feature\Errors;

use App\Http\Controllers\Admin\ErrorController;

use Tests\TestCase;
use App\Exceptions\InternalException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/**
 * Step 144: LaraAuditErrorsSinkTest
 * @group error-contract
 */
final class LaraAuditErrorsSinkTest extends TestCase
{
    public function test_exception_writes_to_audit_sink(): void
    {
        $capturedContext = null;
        $mockLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $mockLogger->shouldReceive('info')->once()->withArgs(function ($message, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        });
        
        Log::shouldReceive('channel')->with('lara-audit-errors')->andReturn($mockLogger);
        Log::shouldReceive('channel')->with(\Mockery::not('lara-audit-errors'))->zeroOrMoreTimes()->andReturn(null);
        Log::shouldReceive('withContext')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        Route::post('/_test/audit-sink', function() {
            throw InternalException::fatal('test_error', ['Password' => 'secret123']);
        });
        
        $res = $this->withHeaders([
            'X-Lara-Operation' => 'test.op',
        ])->postJson('/_test/audit-sink', []);
        
        $res->assertStatus(500);
        $this->assertNotNull($capturedContext);
        $this->assertArrayHasKey('RequestId', $capturedContext);
        $this->assertArrayHasKey('ErrorId', $capturedContext);
        $this->assertArrayHasKey('ErrorCode', $capturedContext);
        $this->assertArrayHasKey('HttpStatus', $capturedContext);
        $this->assertArrayNotHasKey('Password', $capturedContext);
    }
}
