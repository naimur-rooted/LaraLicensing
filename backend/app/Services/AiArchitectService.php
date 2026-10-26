<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InternalException;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Service managing Grounded AI Pricing Architecture generation via Google Gemini.
 */
class AiArchitectService
{
    private string $apiKey;
    private const CANDIDATE_MODELS = [
        'gemini-3.5-flash-lite',
        'gemini-3.1-flash-lite',
        'gemini-3.6-flash',
        'gemini-flash-lite-latest',
        'gemini-3-flash-preview',
        'gemini-3.5-flash',
    ];
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = (string) env('GEMINI_API_KEY', '');
    }

    /** Design tailored pricing deals and feature perks grounded in LaraLicensing invariants. */
    public function designPricingStrategy(Product $product, string $userPrompt): array
    {
        if (empty($this->apiKey)) {
            throw InternalException::fatal('GEMINI_API_KEY is not configured in backend/.env');
        }

        $systemInstruction = $this->buildGroundingConstitution();
        $userMessage = $this->buildUserMessage($product, $userPrompt);
        $payload = [
            'contents' => [['parts' => [['text' => $systemInstruction . "\n\n" . $userMessage]]]],
            'generationConfig' => ['responseMimeType' => 'application/json'],
        ];

        return $this->executePricingCandidates($payload);
    }

    private function executePricingCandidates(array $payload): array
    {
        $lastError = 'Unknown error';
        foreach (self::CANDIDATE_MODELS as $model) {
            $url = self::BASE_URL . '/' . $model . ':generateContent?key=' . $this->apiKey;
            $response = Http::withHeaders(['Content-Type' => 'application/json'])->timeout(20)->post($url, $payload);
            if ($response->successful()) {
                $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $decoded = json_decode($text, true);
                if (is_array($decoded) && !empty($decoded['deals'])) {
                    return $decoded;
                }
            }
            $lastError = 'Model ' . $model . ' failed (' . $response->status() . '): ' . $response->body();
        }

        throw InternalException::fatal($lastError);
    }

    /** Generate framework-tailored client integration code and AI prompt via Gemini. */
    public function generateClientBlueprintCode(Product $product, string $targetFramework, string $customNotes = ''): array
    {
        if (empty($this->apiKey)) {
            throw InternalException::fatal('GEMINI_API_KEY is not configured in backend/.env');
        }

        $verifyUrl = url("/api/v1/products/{$product->productSlug}/verify");
        $dealsUrl = url("/api/v1/products/{$product->productSlug}/deals");
        $dealsList = $product->deals ? $product->deals->map(fn($d) => "- {$d->dealName} ({$d->dealCode}): seats={$d->allowedMachineSeats}, features=" . json_encode($d->features ?? []))->implode("\n") : 'None';

        $prompt = <<<PROMPT
You are the Principal Lead Licensing & Cryptographic Systems Engineer for LaraLicensing.
Generate a complete, production-ready client integration blueprint for a developer integrating this product.

PRODUCT DETAILS:
- Name: {$product->productName}
- Slug: {$product->productSlug}
- Category: {$product->category}
- Description: {$product->description}
- Verification Endpoint (POST): {$verifyUrl}
- Live Deals Endpoint (GET): {$dealsUrl}
- Public Key (Ed25519): {$product->ed25519PublicKey}
- Cadence Check Interval: Every {$product->checkIntervalDays} days
- Configured Deals & Features (Ground Truth):
{$dealsList}

TARGET FRAMEWORK / LANGUAGE: {$targetFramework}
ADDITIONAL DEVELOPER NOTES: {$customNotes}

ARCHITECTURAL MANDATES (ZERO-HALLUCINATION):
1. Generate real, runnable client code (not pseudocode).
2. Hardware/Environment Fingerprinting:
   - For Desktop (Python/C#/Electron/Java): hash machine GUID or MAC.
   - For Chrome Extension: generate or retrieve a persistent client UUID stored in chrome.storage.local.
   - For WordPress: hash site home_url() and server hostname.
3. Offline Caching & Ed25519 Verification:
   - Store offline JWT token locally.
   - On startup, check if offline token exists and is valid. If network is offline, allow access if token is within {$product->checkIntervalDays} days.
4. Feature Gating:
   - Provide a clean helper method e.g. `hasFeature(featureName)` or `canAccess(module)`.
5. Dual-Section Storefront & Activation Dialog:
   - For any UI-facing framework (Java Swing, Python PyQt/Tkinter, C# WPF/WinForms, Electron, React/Web):
     When the software starts without an active license, it MUST NOT render a bare license key text box alone.
     It MUST render a dual-section UI:
     * Top Section: Display the configured pricing deals as cards showing Deal Name, formatted Price (\$priceCents / 100.0 / interval), allowed PC seats, and feature perks.
     * Bottom Section: "Already have a license key?" input box + "Activate License" button.
6. Zero Dummy Fallback Mandate (CRITICAL):
   - You MUST NOT hardcode invented dummy prices (\$29, \$290, \$490, etc.) in catch blocks or fallbacks.
   - Prices and features MUST strictly reflect the provided deals list above or be parsed from the Live Deals Endpoint: {$dealsUrl}.
   - If network or parsing fails, show a clean "Cannot reach licensing server. [Retry]" label. NEVER display fake numbers.
7. Zero-Dependency Parser Mandate:
   - For Java or C++, do NOT assume external JSON libraries (Gson, Jackson) exist.
   - Use standard library regex matching to parse `dealName`, `priceCents`, and `allowedMachineSeats` safely.
8. Buy Plan Action Mandate:
   - DO NOT open the raw API URL (.../deals) in the user's web browser.
   - When "Buy Plan" is clicked, display a purchase instruction modal (e.g., "To activate this package, contact vendor or visit vendor checkout") or open the vendor's actual checkout URL.

OUTPUT SCHEMA (JSON ONLY):
{
  "framework": "{$targetFramework}",
  "fileName": "filename with extension (e.g. licensing.py or license-verifier.js)",
  "dependencies": "required install command or none",
  "instructions": "1-3 step quick start setup instructions",
  "codeSnippet": "The complete, fully commented, production-ready code with no placeholders",
  "agentPrompt": "The tailored prompt to copy into Cursor, Windsurf, Claude, or ChatGPT to implement it automatically into their existing project"
}
PROMPT;

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ];

        $lastError = 'Unknown error';
        foreach (self::CANDIDATE_MODELS as $model) {
            $url = self::BASE_URL . '/' . $model . ':generateContent?key=' . $this->apiKey;
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $decoded = json_decode($text, true);
                if (is_array($decoded) && (!empty($decoded['codeSnippet']) || !empty($decoded['agentPrompt']))) {
                    return $decoded;
                }
            }

            \Illuminate\Support\Facades\Log::warning("Blueprint model {$model} failed: " . $response->status() . " - " . substr($response->body(), 0, 300));
            $lastError = 'Model ' . $model . ' failed (' . $response->status() . '): ' . $response->body();
        }

        throw InternalException::fatal($lastError);
    }

    /** Build the Grounding Constitution defining LaraLicensing pricing rules and schema. */
    private function buildGroundingConstitution(): string
    {
        return <<<PROMPT
You are the Principal Commercial Monetization Architect for LaraLicensing.
Your task is to analyze the developer's software and design a high-converting, psychologically grounded pricing matrix.

CORE ARCHITECTURAL INVARIANTS:
1. Flexible Monetization Strategies:
   - Three-Tier Decoy Strategy (NordVPN style): Monthly Anchor, Annual Pro, and 2-Year Best Value Target.
   - Micro / Daily Pass: If user asks for daily, 24-hour, weekend, or short-term access, generate Daily Pass (24 Hours), Weekly Pass (7 Days), or Monthly Access.
   - Lifetime (AppSumo LTD): Single Code, Double Code, Unlimited Agency Code.
   - B2B Team SaaS: Starter, Team Growth, Enterprise Agency.
2. Custom Entitlement Metric:
   - Identify the exact unit of 1 activation seat (e.g. Chrome Profiles, Domain Sites, Discord Servers, Shopify Stores, Machine Seats, API Nodes, Clusters). Output as "seatUnitName".
3. Category-Specific Feature Perks:
   - ChromeExtension: Leads/mo, scrapes, CSV/CRM sync, Chrome profiles.
   - Discord/Bot: Servers/Guilds, member count, automated moderation actions.
   - WordPressPlugin: Domain activations, addon modules, updates & support.
   - DesktopApp: Machine seats, offline Ed25519 tokens, export formats/resolutions.
4. Strict Output JSON Schema:
{
  "explanation": "Brief 2-sentence rationale for the developer",
  "seatUnitName": "string (e.g. Discord Servers, Chrome Profiles, Domain Sites, Machine Seats, Stores)",
  "deals": [
    {
      "dealName": "string",
      "dealCode": "UPPERCASE_CODE",
      "billingInterval": "daily | weekly | monthly | quarterly | yearly | biennial | lifetime",
      "priceCents": 1234,
      "allowedMachineSeats": 1,
      "allowedDomains": 1,
      "isDecoyTarget": false,
      "features": ["3 to 5 concise benefit bullets with quantities/units"]
    }
  ]
}
PROMPT;
    }

    /** Format the user message with product metadata. */
    private function buildUserMessage(Product $product, string $userPrompt): string
    {
        return <<<MSG
PRODUCT METADATA:
- Name: {$product->productName}
- Slug: {$product->productSlug}
- Category: {$product->category}
- Description: {$product->description}

DEVELOPER'S GOALS & NOTES:
{$userPrompt}

Generate the optimal high-converting pricing deals and feature bullets now.
MSG;
    }

    /**
     * Interactive AI Chatbot for Developer Support & Licensing Copilot.
     */
    public function chatWithSupport(string $message, array $history = [], ?string $context = null): string
    {
        if (empty($this->apiKey)) {
            return "I am LaraLicensing Copilot! Your Gemini API key is not yet configured in `backend/.env`. You can ask questions about our Ed25519 cryptographic node-locking, machine seat limits, or headless REST API verification.";
        }

        $systemInstruction = $this->getSupportKnowledgeBase();
        $contents = $this->buildSupportChatContents($systemInstruction, $context, $history, $message);

        return $this->dispatchSupportChatCall($contents);
    }

    private function buildSupportChatContents(string $sys, ?string $ctx, array $history, string $msg): array
    {
        $contents = [
            ['role' => 'user', 'parts' => [['text' => $sys . "\n\nContext: " . ($ctx ?: 'General developer support.')]]],
            ['role' => 'model', 'parts' => [['text' => "Understood. I am your LaraLicensing AI Copilot. How can I help you today?"]]],
        ];
        foreach ($history as $item) {
            $role = ($item['sender'] ?? 'user') === 'user' ? 'user' : 'model';
            $text = trim((string) ($item['text'] ?? ''));
            if (!empty($text)) {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $msg]]];

        return $contents;
    }

    private function dispatchSupportChatCall(array $contents): string
    {
        $payload = ['contents' => $contents, 'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 1024]];
        foreach (self::CANDIDATE_MODELS as $model) {
            $url = self::BASE_URL . '/' . $model . ':generateContent?key=' . $this->apiKey;
            $res = Http::withHeaders(['Content-Type' => 'application/json'])->timeout(25)->post($url, $payload);
            if ($res->successful()) {
                $reply = $res->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (!empty($reply)) {
                    return $reply;
                }
            }
        }

        return "I'm having trouble connecting to the AI service right now. However, you can verify your licenses using `POST /api/v1/products/{productSlug}/verify` or check your Overview tab for active machine seats!";
    }

    private function getSupportKnowledgeBase(): string
    {
        return <<<SYS
You are LaraLicensing AI Copilot, an expert licensing engineer and developer support assistant directly embedded in the LaraLicensing Developer Portal (comparable to Cryptlex/Keygen, but modernized with multi-tenant Split-DB SQLite sharding, Ed25519 offline signatures, and dynamic seat units).

YOUR KNOWLEDGE BASE:
1. Licensing Concepts:
   - Node-locking: Binds a license to hardware digest.
   - Machine Seats / Quotas: Enforced by the server. If a user exceeds allowed seats, returns 403 SeatLimitExceeded.
   - Offline Tokens: Signed JWTs issued by the server and verified offline via Ed25519 public key.
   - Periodic Cadence: Periodic online verification (e.g. 7 days).
   - Decoupled Webhooks: External checkouts (Stripe, LemonSqueezy, WooCommerce) send webhooks to mint licenses.
2. Architecture:
   - Headless REST APIs: POST /api/v1/products/{slug}/verify, GET /api/v1/products/{slug}/deals.
   - Multi-tenant Shards: Isolated SQLite database product_{slug}.sqlite per product.
3. Support Role:
   - Help developers integrate software, write client code, debug issues, design pricing models, and explain metrics.
SYS;
    }
}
