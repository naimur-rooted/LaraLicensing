<?php

declare(strict_types=1);

namespace App\Services;

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
            throw new RuntimeException('GEMINI_API_KEY is not configured in backend/.env');
        }

        $systemInstruction = $this->buildGroundingConstitution();
        $userMessage = $this->buildUserMessage($product, $userPrompt);
        $payload = [
            'contents' => [
                ['parts' => [['text' => $systemInstruction . "\n\n" . $userMessage]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ];

        $lastError = 'Unknown error';
        foreach (self::CANDIDATE_MODELS as $model) {
            $url = self::BASE_URL . '/' . $model . ':generateContent?key=' . $this->apiKey;
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(20)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $decoded = json_decode($text, true);
                if (is_array($decoded) && !empty($decoded['deals'])) {
                    return $decoded;
                }
            }

            $lastError = 'Model ' . $model . ' failed (' . $response->status() . '): ' . $response->body();
        }

        throw new RuntimeException($lastError);
    }

    /** Generate framework-tailored client integration code and AI prompt via Gemini. */
    public function generateClientBlueprintCode(Product $product, string $targetFramework, string $customNotes = ''): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY is not configured in backend/.env');
        }

        $verifyUrl = url("/api/v1/products/{$product->productSlug}/verify");
        $dealsList = $product->deals ? $product->deals->map(fn($d) => "- {$d->dealName} ({$d->dealCode}): seats={$d->allowedMachineSeats}, features=" . json_encode($d->features ?? []))->implode("\n") : 'None';

        $prompt = <<<PROMPT
You are the Principal Lead Licensing & Cryptographic Systems Engineer for LaraLicensing.
Generate a complete, production-ready client integration blueprint for a developer integrating this product.

PRODUCT DETAILS:
- Name: {$product->productName}
- Slug: {$product->productSlug}
- Category: {$product->category}
- Description: {$product->description}
- Verification Endpoint: {$verifyUrl}
- Public Key (Ed25519): {$product->ed25519PublicKey}
- Cadence Check Interval: Every {$product->checkIntervalDays} days
- Configured Deals & Features:
{$dealsList}

TARGET FRAMEWORK / LANGUAGE: {$targetFramework}
ADDITIONAL DEVELOPER NOTES: {$customNotes}

ARCHITECTURAL MANDATES:
1. Generate real, runnable client code (not pseudocode).
2. Hardware/Environment Fingerprinting:
   - For Desktop (Python/C#/Electron): hash machine GUID or MAC.
   - For Chrome Extension: generate or retrieve a persistent client UUID stored in chrome.storage.local.
   - For WordPress: hash site home_url() and server hostname.
3. Offline Caching & Ed25519 Verification:
   - Store offline JWT token locally.
   - On startup, check if offline token exists and is valid. If network is offline, allow access if token is within {$product->checkIntervalDays} days.
4. Feature Gating:
   - Provide a clean helper method e.g. `hasFeature(featureName)` or `canAccess(module)`.

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

        throw new RuntimeException($lastError);
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
}
