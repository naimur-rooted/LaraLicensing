<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\LicenseExpiry;
use App\Services\ProductService;
use App\Support\LicenseShardSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PDO;

class SandboxCheckoutController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {}

    /**
     * Display the Sandbox Payment Checkout Page.
     */
    public function showCheckout(string $slug, Request $request): Response
    {
        $product = Product::with('deals')->where('productSlug', $slug)->first();
        if (!$product) {
            return response($this->renderNotFoundHtml($slug), 404);
        }

        $deals = $product->deals->where('isActive', true)->values();
        $selectedDealCode = strtoupper(trim((string) $request->query('deal', '')));
        $selectedDeal = $deals->first(fn ($d) => strtoupper((string) $d->dealCode) === $selectedDealCode)
            ?? $deals->first();

        $html = $this->renderCheckoutHtml($product, $deals, $selectedDeal);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Process Sandbox Payment and Mint a Real License Key into LaraLicensing.
     */
    public function processPayment(string $slug, Request $request): JsonResponse
    {
        $product = Product::with('deals')->where('productSlug', $slug)->first();
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => "Product '{$slug}' not found.",
            ], 404);
        }

        $dealCode = strtoupper(trim((string) $request->input('dealCode', '')));
        $deal = $product->deals->first(fn ($d) => strtoupper((string) $d->dealCode) === $dealCode)
            ?? $product->deals->where('isActive', true)->first();

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'No active deal found for this product.',
            ], 422);
        }

        $email = strtolower(trim((string) $request->input('email', 'hotel-admin@reservations.local')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'hotel-admin@reservations.local';
        }

        $customerName = trim((string) $request->input('name', 'Front Desk Manager'));
        if ($customerName === '') {
            $customerName = 'Front Desk Manager';
        }

        $txId = 'TXN-SBX-' . strtoupper(bin2hex(random_bytes(6)));

        // Clean branded serial key format: HOTEL-ANNUAL-PRO-XXXX-XXXX
        $dealSuffix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $deal->dealCode));
        if (strlen($dealSuffix) > 12) {
            $dealSuffix = substr($dealSuffix, 0, 12);
        }
        $token1 = strtoupper(bin2hex(random_bytes(3)));
        $token2 = strtoupper(bin2hex(random_bytes(3)));
        $serial = "HOTEL-{$dealSuffix}-{$token1}-{$token2}";

        $seats = max(1, (int) $deal->allowedMachineSeats);
        $expiresAt = LicenseExpiry::derive((string) $deal->billingInterval);

        // Record in ProductLicenses shard
        $this->productService->recordShardLicense($product, $serial, (string) $deal->dealCode, $seats, $expiresAt, $email, $txId);

        // Dual write to Serials
        $this->dualWriteSerial($slug, $serial, $email);

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully in Sandbox test mode.',
            'licenseKey' => $serial,
            'productName' => $product->ProductName,
            'productSlug' => $product->productSlug,
            'dealName' => $deal->dealName,
            'dealCode' => $deal->dealCode,
            'billingInterval' => $deal->billingInterval,
            'priceFormatted' => '$' . number_format($deal->priceCents / 100, 2),
            'allowedSeats' => $seats,
            'customerName' => $customerName,
            'customerEmail' => $email,
            'transactionId' => $txId,
            'timestamp' => now()->toFormattedDateString(),
        ], 200);
    }

    private function dualWriteSerial(string $slug, string $serial, string $email): void
    {
        $now = now()->toDateTimeString();
        $paths = [database_path("product_{$slug}.sqlite")];
        $repodbPath = base_path("repodb/product_{$slug}.sqlite");
        if (file_exists($repodbPath)) {
            $paths[] = $repodbPath;
        }

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                @touch($path);
            }
            try {
                $pdo = new PDO("sqlite:{$path}");
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec('CREATE TABLE IF NOT EXISTS Serials (
                    SerialId INTEGER PRIMARY KEY AUTOINCREMENT,
                    SerialValue VARCHAR(128) NOT NULL UNIQUE,
                    LicenseKey VARCHAR(128),
                    ProductSlug VARCHAR(64) NOT NULL,
                    CustomerEmail VARCHAR(128) NOT NULL,
                    IsRevoked INTEGER NOT NULL DEFAULT 0,
                    IssuedAt DATETIME,
                    CreatedAt DATETIME,
                    UpdatedAt DATETIME
                );');
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO Serials (SerialValue, LicenseKey, ProductSlug, CustomerEmail, IsRevoked, IssuedAt, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, 0, ?, ?, ?)');
                $stmt->execute([$serial, $serial, $slug, $email, $now, $now, $now]);
            } catch (\Throwable $e) {
                // Ignore fallback error
            }
        }
    }

    private function renderNotFoundHtml(string $slug): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Not Found - Sandbox Gateway</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 480px; text-align: center; }
        h1 { color: #dc2626; font-size: 24px; margin-bottom: 12px; }
        p { color: #64748b; font-size: 15px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Product Not Found</h1>
        <p>Could not find product catalog with slug: <strong>{$slug}</strong>.</p>
        <p>Please check your configuration or licensing server endpoint.</p>
    </div>
</body>
</html>
HTML;
    }

    private function renderCheckoutHtml(Product $product, $deals, $selectedDeal): string
    {
        $dealsJson = json_encode($deals);
        $selectedCode = $selectedDeal ? $selectedDeal->dealCode : '';
        $productTitle = htmlspecialchars((string) $product->ProductName);
        $productSlug = htmlspecialchars($product->productSlug);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sandbox Payment Gateway - {$productTitle}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px 16px;
        }

        .header-bar {
            text-align: center;
            margin-bottom: 24px;
        }
        .header-bar .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(56, 189, 248, 0.1);
            color: #38bdf8;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(56, 189, 248, 0.2);
            margin-bottom: 12px;
        }
        .header-bar h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #ffffff;
        }
        .sandbox-badge {
            display: inline-block;
            background: #ca8a04;
            color: #000;
            font-weight: 800;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-left: 8px;
            vertical-align: middle;
        }

        .checkout-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
            max-width: 860px;
            width: 100%;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        @media (max-width: 768px) {
            .checkout-card {
                grid-template-columns: 1fr;
            }
        }

        /* Left Side: Plan & Product Summary */
        .summary-side {
            background: #0f172a;
            padding: 32px;
            border-right: 1px solid #334155;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .product-meta h2 {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }
        .product-meta .category {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 24px;
        }

        .deals-selector {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }
        .deal-option {
            background: #1e293b;
            border: 2px solid #334155;
            border-radius: 10px;
            padding: 12px 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .deal-option:hover {
            border-color: #38bdf8;
            transform: translateY(-1px);
        }
        .deal-option.active {
            border-color: #38bdf8;
            background: rgba(56, 189, 248, 0.08);
        }
        .deal-option .deal-title {
            font-weight: 600;
            font-size: 14px;
            color: #f8fafc;
        }
        .deal-option .deal-seats {
            font-size: 11px;
            color: #94a3b8;
        }
        .deal-option .deal-price {
            font-weight: 700;
            font-size: 16px;
            color: #38bdf8;
        }

        .selected-features-box {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 14px;
            border: 1px dashed #334155;
        }
        .features-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .features-list {
            list-style: none;
            font-size: 12px;
            color: #cbd5e1;
            line-height: 1.6;
        }
        .features-list li::before {
            content: "✓ ";
            color: #10b981;
            font-weight: bold;
        }

        /* Right Side: Payment Form */
        .payment-side {
            padding: 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .payment-side h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #ffffff;
        }
        .sandbox-hint {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sandbox-hint span.icon {
            color: #ca8a04;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .form-group input {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            color: #ffffff;
            transition: border-color 0.15s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #38bdf8;
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 12px;
        }

        .pay-button {
            width: 100%;
            background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
            border: none;
            border-radius: 8px;
            padding: 14px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .pay-button:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        .pay-button:active {
            transform: translateY(0);
        }
        .pay-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Success State View */
        .success-view {
            display: none;
            text-align: center;
            padding: 36px 24px;
        }
        .success-icon {
            width: 60px;
            height: 60px;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 16px auto;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .success-view h2 {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .success-view p.sub {
            color: #94a3b8;
            font-size: 13px;
            margin-bottom: 24px;
        }

        .license-key-card {
            background: #0f172a;
            border: 2px solid #10b981;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            position: relative;
        }
        .license-key-card .tag {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #10b981;
            margin-bottom: 8px;
        }
        .license-key-display {
            font-family: 'JetBrains Mono', monospace;
            font-size: 20px;
            font-weight: 700;
            color: #38bdf8;
            word-break: break-all;
            padding: 10px 0;
            letter-spacing: 0.04em;
        }
        .copy-button {
            background: #10b981;
            color: #0f172a;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
        }
        .copy-button:hover {
            filter: brightness(1.1);
        }

        .instructions-box {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            padding: 16px;
            text-align: left;
            font-size: 13px;
            color: #cbd5e1;
            line-height: 1.6;
            border: 1px solid #334155;
        }
        .instructions-box ol {
            padding-left: 20px;
            margin-top: 8px;
        }
        .instructions-box li {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>

    <div class="header-bar">
        <div class="logo-badge">
            <span>🛡️ LaraLicensing Portal</span>
            <span>•</span>
            <span>Gateway Engine</span>
        </div>
        <h1>Checkout Simulation <span class="sandbox-badge">Sandbox Test Mode</span></h1>
    </div>

    <div class="checkout-card" id="mainContainer">
        <!-- LEFT PANEL: SUMMARY -->
        <div class="summary-side" id="summarySide">
            <div>
                <div class="product-meta">
                    <h2>{$productTitle}</h2>
                    <div class="category">Desktop Application • Dynamic Subscription</div>
                </div>

                <div class="deals-selector" id="dealsContainer"></div>

                <div class="selected-features-box">
                    <div class="features-title">Plan Entitlements:</div>
                    <ul class="features-list" id="featuresContainer"></ul>
                </div>
            </div>

            <div style="margin-top: 24px; font-size: 12px; color: #64748b; text-align: center;">
                Powered by LaraLicensing Sharded Architecture v2.0
            </div>
        </div>

        <!-- RIGHT PANEL: PAYMENT FORM -->
        <div class="payment-side" id="formSide">
            <h3>Payment Information</h3>
            <div class="sandbox-hint">
                <span class="icon">⚠️</span>
                <span>Sandbox mode enabled. No actual credit card will be charged.</span>
            </div>

            <form id="checkoutForm" onsubmit="handlePayment(event)">
                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" id="custName" value="Front Desk Manager" required>
                </div>

                <div class="form-group">
                    <label>Email Address (For License Delivery)</label>
                    <input type="email" id="custEmail" value="hotel-manager@grandreservations.com" required>
                </div>

                <div class="form-group">
                    <label>Card Number (Sandbox Test Card)</label>
                    <input type="text" id="cardNumber" value="4242 •••• •••• 4242" readonly>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Expires</label>
                        <input type="text" value="12 / 28" readonly>
                    </div>
                    <div class="form-group">
                        <label>CVC</label>
                        <input type="text" value="888" readonly>
                    </div>
                </div>

                <button type="submit" class="pay-button" id="submitBtn">
                    <span>Pay with Sandbox Gateway</span>
                    <span id="btnAmount"></span>
                </button>
            </form>
        </div>

        <!-- SUCCESS STATE -->
        <div class="success-view" id="successSide" style="grid-column: 1 / -1;">
            <div class="success-icon">✓</div>
            <h2>Sandbox Payment Succeeded!</h2>
            <p class="sub">Your test transaction was approved and your product license has been minted directly into LaraLicensing.</p>

            <div class="license-key-card">
                <div class="tag">Your Generated License Key</div>
                <div class="license-key-display" id="displayLicenseKey">HOTEL-PRO-XXXX-XXXX</div>
                <button class="copy-button" onclick="copyLicenseKey()">
                    <span id="copyIcon">📋</span>
                    <span id="copyText">Copy License Key</span>
                </button>
            </div>

            <div class="instructions-box">
                <strong>How to activate your application:</strong>
                <ol>
                    <li>Click <strong>Copy License Key</strong> above to copy the key to your clipboard.</li>
                    <li>Return to your <strong>Hotel Reservation System</strong> application window.</li>
                    <li>Paste the license key into the <strong>License Key</strong> field and click <strong>Activate License</strong>.</li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        const allDeals = {$dealsJson};
        let activeDeal = allDeals.find(d => d.dealCode.toUpperCase() === "{$selectedCode}".toUpperCase()) || allDeals[0];

        function renderDeals() {
            const container = document.getElementById('dealsContainer');
            container.innerHTML = '';

            allDeals.forEach(deal => {
                const opt = document.createElement('div');
                opt.className = 'deal-option' + (deal.dealCode === activeDeal.dealCode ? ' active' : '');
                
                const priceFormatted = '$' + (deal.priceCents / 100).toFixed(2);
                const interval = deal.billingInterval === 'monthly' ? '/mo' : (deal.billingInterval === 'yearly' ? '/yr' : '/2yr');

                opt.innerHTML = `
                    <div>
                        <div class="deal-title">\${deal.dealName}</div>
                        <div class="deal-seats">\${deal.allowedMachineSeats} Machine Seat\${deal.allowedMachineSeats > 1 ? 's' : ''}</div>
                    </div>
                    <div class="deal-price">\${priceFormatted}<span style="font-size:11px;font-weight:normal;color:#94a3b8">\${interval}</span></div>
                `;
                opt.onclick = () => selectDeal(deal);
                container.appendChild(opt);
            });

            renderFeatures();
            updateSubmitBtn();
        }

        function selectDeal(deal) {
            activeDeal = deal;
            renderDeals();
        }

        function renderFeatures() {
            const list = document.getElementById('featuresContainer');
            list.innerHTML = '';
            
            let feats = [];
            if (Array.isArray(activeDeal.features)) {
                feats = activeDeal.features;
            } else if (typeof activeDeal.features === 'string') {
                feats = [activeDeal.features];
            }

            if (feats.length === 0) {
                feats = [
                    `\${activeDeal.allowedMachineSeats} Machine Seat(s) for Hotel Front Desk`,
                    "Full offline MySQL database sync",
                    "Real-time guest booking & invoice management"
                ];
            }

            feats.forEach(f => {
                const li = document.createElement('li');
                li.textContent = f;
                list.appendChild(li);
            });
        }

        function updateSubmitBtn() {
            const priceFormatted = '$' + (activeDeal.priceCents / 100).toFixed(2);
            document.getElementById('btnAmount').textContent = `(\${priceFormatted})`;
        }

        async function handlePayment(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span>Processing Sandbox Payment...</span>';

            const payload = {
                dealCode: activeDeal.dealCode,
                email: document.getElementById('custEmail').value,
                name: document.getElementById('custName').value,
            };

            try {
                const resp = await fetch('/checkout/{$productSlug}/process', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                const data = await resp.json();

                if (resp.ok && data.success) {
                    showSuccess(data.licenseKey);
                } else {
                    alert('Payment Simulation Error: ' + (data.message || 'Unknown error occurred.'));
                    btn.disabled = false;
                    btn.innerHTML = '<span>Pay with Sandbox Gateway</span> <span id="btnAmount"></span>';
                    updateSubmitBtn();
                }
            } catch (err) {
                alert('Network error connecting to payment gateway: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<span>Pay with Sandbox Gateway</span> <span id="btnAmount"></span>';
                updateSubmitBtn();
            }
        }

        function showSuccess(licenseKey) {
            document.getElementById('summarySide').style.display = 'none';
            document.getElementById('formSide').style.display = 'none';
            document.getElementById('displayLicenseKey').textContent = licenseKey;
            document.getElementById('successSide').style.display = 'block';
        }

        function copyLicenseKey() {
            const key = document.getElementById('displayLicenseKey').textContent.trim();
            navigator.clipboard.writeText(key).then(() => {
                document.getElementById('copyIcon').textContent = '✓';
                document.getElementById('copyText').textContent = 'Copied to Clipboard!';
                setTimeout(() => {
                    document.getElementById('copyIcon').textContent = '📋';
                    document.getElementById('copyText').textContent = 'Copy License Key';
                }, 3000);
            });
        }

        // Initial setup
        renderDeals();
    </script>
</body>
</html>
HTML;
    }
}
