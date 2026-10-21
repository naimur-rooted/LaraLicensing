<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\ClientHandshakeService;
use App\Services\FraudAlertService;
use App\Services\ResellerWholesaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller exposing client license handshake, reseller wholesale management,
 * and real-time fraud alert endpoints.
 */
class AdvancedLicensingController extends Controller
{
    public function __construct(
        private readonly ClientHandshakeService $handshakeService,
        private readonly ResellerWholesaleService $resellerService,
        private readonly FraudAlertService $fraudService,
    ) {}

    /** Verify client license, enforce periodic check cadence and bind hardware seats. */
    public function verifyClient(string $productSlug, Request $request): JsonResponse
    {
        $serial = strtoupper(trim((string) $request->input('serialValue', '')));
        $hwid = strtoupper(trim((string) $request->input('hardwareDigest', '')));
        $hostname = (string) $request->input('hostname', 'Client Workstation');

        if (empty($serial) || empty($hwid)) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidInput', 'Message' => 'serialValue and hardwareDigest are required.'], 422);
        }

        $countryCode = strtoupper(trim((string) ($request->header('CF-IPCountry') ?: $request->input('countryCode') ?: '')));
        $countryName = trim((string) ($request->input('countryName') ?: ''));

        $result = $this->handshakeService->verifyClient(
            $productSlug,
            $serial,
            $hwid,
            (string) $request->ip(),
            $hostname,
            $countryCode ?: null,
            $countryName ?: null
        );
        $status = $result['isValid'] ? 200 : 403;
        return response()->json(['IsSuccess' => $result['isValid'], 'Data' => $result], $status);
    }

    /** Reseller wholesale quota and custom offer overview. */
    public function resellerOverview(string $resellerSlug): JsonResponse
    {
        $data = $this->resellerService->getResellerOverview($resellerSlug);
        return response()->json(['IsSuccess' => $data['isSuccess'], 'Data' => $data]);
    }

    /** Allocate or top-up wholesale bulk quota for a reseller. */
    public function allocateQuota(string $resellerSlug, Request $request): JsonResponse
    {
        $tier = (string) $request->input('tierCode', 'ESSENTIAL');
        $count = (int) $request->input('count', 100);
        $res = $this->resellerService->allocateWholesaleQuota($resellerSlug, $tier, $count);
        return response()->json(['IsSuccess' => true, 'Data' => $res], 201);
    }

    /** Create custom wholesale package offer with custom pricing. */
    public function createResellerOffer(string $resellerSlug, Request $request): JsonResponse
    {
        $res = $this->resellerService->createCustomOffer($resellerSlug, $request->all());
        return response()->json(['IsSuccess' => true, 'Data' => $res], 201);
    }

    /** Get fraud and anomaly audit alerts for a product. */
    public function securityEvents(string $productSlug): JsonResponse
    {
        $data = $this->fraudService->getSecurityEvents($productSlug);
        return response()->json(['IsSuccess' => true, 'Data' => $data]);
    }

    /** 1-click quarantine a suspicious license. */
    public function quarantine(string $productSlug, Request $request): JsonResponse
    {
        $serial = strtoupper(trim((string) $request->input('serialValue', '')));
        $reason = (string) $request->input('reason', 'Suspicious activity');
        $res = $this->fraudService->quarantineLicense($productSlug, $serial, $reason);
        return response()->json(['IsSuccess' => $res['isSuccess'], 'Data' => $res]);
    }

    /** Unbind a specific machine hardware fingerprint from a license. */
    public function unbindHardware(string $productSlug, Request $request): JsonResponse
    {
        $serial = strtoupper(trim((string) $request->input('serialValue', '')));
        $hwid = strtoupper(trim((string) $request->input('hardwareDigest', '')));
        $res = $this->fraudService->unbindHardware($productSlug, $serial, $hwid);
        return response()->json(['IsSuccess' => $res['isSuccess'], 'Data' => $res]);
    }
}
