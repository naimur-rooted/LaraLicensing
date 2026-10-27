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
 * Controller exposing client license handshake, node-locking, and fraud alert endpoints.
 */
class AdvancedLicensingController extends Controller
{
    public function __construct(
        private readonly ClientHandshakeService $handshakeService,
        private readonly ResellerWholesaleService $resellerService,
        private readonly FraudAlertService $fraudService,
    ) {}

    /** Verify client license entitlements and active seat state. */
    public function verifyClient(Request $request, ?string $slug = null): JsonResponse
    {
        $inputs = $this->extractClientInputs($request, $slug);
        if ($inputs['serial'] === '' || $inputs['hwid'] === '') {
            return $this->invalidInputResponse($request);
        }
        $result = $this->handshakeService->verifyClient(
            $inputs['slug'], $inputs['serial'], $inputs['hwid'], $inputs['ip'],
            $inputs['hostname'], $inputs['countryCode'], $inputs['countryName']
        );
        $status = $result['isValid'] ? 200 : (($result['errorCode'] ?? '') === 'SeatLimitExceeded' && !$request->is('*/products/*/verify') ? 409 : 403);

        return $this->envelopeResponse($result, $status, $result['message'] ?? 'Verification decision produced.', $request);
    }

    /** Activate license on a hardware device seat with 409 limit enforcement. */
    public function activate(Request $request, ?string $slug = null): JsonResponse
    {
        $inputs = $this->extractClientInputs($request, $slug);
        if ($inputs['serial'] === '' || $inputs['hwid'] === '') {
            return $this->invalidInputResponse($request);
        }
        $result = $this->handshakeService->activate($inputs['slug'], $inputs['serial'], $inputs['hwid'], $inputs['ip'], $inputs['hostname']);
        if (!$result['isValid']) {
            $code = ($result['errorCode'] ?? '') === 'SeatLimitExceeded' ? 409 : 403;
            return $this->envelopeResponse($result, $code, $result['message'] ?? 'Activation failed.', $request);
        }

        return $this->envelopeResponse($result, 200, 'Device seat activated successfully.', $request);
    }

    /** Deactivate license and release hardware binding seat. */
    public function deactivate(Request $request, ?string $slug = null): JsonResponse
    {
        $inputs = $this->extractClientInputs($request, $slug);
        if ($inputs['serial'] === '' || $inputs['hwid'] === '') {
            return $this->invalidInputResponse($request);
        }
        $result = $this->handshakeService->deactivate($inputs['slug'], $inputs['serial'], $inputs['hwid']);
        if (!$result['isSuccess']) {
            $code = in_array($result['errorCode'] ?? '', ['MachineNotBound', 'LicenseNotFound']) ? 404 : 400;
            return $this->envelopeResponse($result, $code, $result['message'] ?? 'Deactivation failed.', $request);
        }

        return $this->envelopeResponse($result, 200, 'Device seat released successfully.', $request);
    }

    /** Heartbeat cadence check-in updating LastSeenAt without incrementing seat count. */
    public function heartbeat(Request $request, ?string $slug = null): JsonResponse
    {
        $inputs = $this->extractClientInputs($request, $slug);
        if ($inputs['serial'] === '' || $inputs['hwid'] === '') {
            return $this->invalidInputResponse($request);
        }
        $result = $this->handshakeService->heartbeat($inputs['slug'], $inputs['serial'], $inputs['hwid']);
        if (!$result['isSuccess']) {
            $code = ($result['errorCode'] ?? '') === 'MachineNotBound' ? 404 : 400;
            return $this->envelopeResponse($result, $code, $result['message'] ?? 'Heartbeat failed.', $request);
        }

        return $this->envelopeResponse($result, 200, 'Heartbeat telemetry recorded.', $request);
    }

    /** Extract standardized client handshake inputs from request. */
    private function extractClientInputs(Request $request, ?string $slug): array
    {
        $resolvedSlug = (string) ($slug ?: $request->route('slug') ?: $request->input('productSlug') ?: '');
        if ($resolvedSlug === '') {
            throw \App\Exceptions\ValidationException::validationFailed('Product slug is required.');
        }

        $serial = strtoupper(trim((string) (
            $request->input('serialValue')
            ?: $request->input('serial')
            ?: $request->input('license_key')
            ?: $request->input('licenseKey')
            ?: $request->input('key')
            ?: ''
        )));

        $hwid = strtolower(trim((string) (
            $request->input('hardwareDigest')
            ?: $request->input('hwid')
            ?: $request->input('machine_id')
            ?: $request->input('machineId')
            ?: $request->input('machineGuid')
            ?: $request->input('fingerprintHash')
            ?: ''
        )));

        $hostname = (string) (
            $request->input('hostname')
            ?: $request->input('machineName')
            ?: $request->input('computerName')
            ?: 'Client Device'
        );

        $cc = strtoupper(trim((string) ($request->header('CF-IPCountry') ?: $request->input('countryCode') ?: '')));

        return [
            'slug' => $resolvedSlug,
            'serial' => $serial,
            'hwid' => $hwid,
            'hostname' => $hostname,
            'ip' => (string) $request->ip(),
            'countryCode' => $cc ?: null,
            'countryName' => trim((string) ($request->input('countryName') ?: '')),
        ];
    }

    /** Return 422 envelope response for invalid input parameters. */
    private function invalidInputResponse(Request $request): JsonResponse
    {
        $err = ['isValid' => false, 'isSuccess' => false, 'errorCode' => 'InvalidInput', 'message' => 'serialValue and hardwareDigest required.'];

        return $this->envelopeResponse($err, 422, 'Invalid input parameters.', $request);
    }

    /** Formats response into canonical envelope with backwards-compatible fields. */
    private function envelopeResponse(array $result, int $code, string $msg, Request $request): JsonResponse
    {
        $reqId = (string) ($request->header('X-Request-Id') ?: 'req-' . bin2hex(random_bytes(8)));
        $isSuccess = $result['isValid'] ?? $result['isSuccess'] ?? false;
        $attrs = ['RequestId' => $reqId];
        if (!$isSuccess && isset($result['errorCode'])) {
            $attrs['Error'] = ['ErrorCode' => $result['errorCode'], 'ErrorMessage' => $result['message'] ?? ''];
        }

        return response()->json([
            'Status' => ['IsSuccess' => $isSuccess, 'Code' => $code, 'Message' => $msg],
            'Attributes' => $attrs, 'Results' => $isSuccess ? [$result] : [],
            'IsSuccess' => $isSuccess, 'Data' => $result,
        ], $code);
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
