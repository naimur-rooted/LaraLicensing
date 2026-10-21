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
1. The Three-Tier Decoy Strategy (NordVPN style):
   - Tier 1 (Single Month Anchor): High monthly rate (e.g., $9.99 - $14.99). Anchors perceived value.
   - Tier 2 (Annual Pro): Discounted annual rate (e.g., $39 - $59/year).
   - Tier 3 (Decoy Target - isDecoyTarget: true): The irresistible 2-Year biennial plan (e.g., $69 - $89 for 2 Years + 3 Free Months), offering 70-82% savings and elevated capacity.
2. Category-Specific Feature Perks (No generic hardware labels for non-desktop apps):
   - ChromeExtension: Leads/mo, LinkedIn/web scrapes, CSV/CRM sync, Chrome profiles.
   - DesktopApp: Machine seats, offline Ed25519 tokens, export formats/resolutions.
   - WordPressPlugin: Domain activations, addon modules, updates & support.
   - DeveloperTool/CLI: Concurrency runners, private registries, API rate limits.
3. Strict Output JSON Schema:
{
  "explanation": "Brief 2-sentence rationale for the developer",
  "deals": [
    {
      "dealName": "string",
      "dealCode": "UPPERCASE_CODE",
      "billingInterval": "monthly | yearly | biennial | lifetime",
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
