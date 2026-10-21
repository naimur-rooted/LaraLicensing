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
        'gemini-3-flash-preview',
        'gemini-3.5-flash',
        'gemini-3.1-flash-lite',
        'gemini-2.5-flash-lite',
        'gemini-flash-latest',
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
