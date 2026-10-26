# [V6] AI Blueprint Storefront & Dual-Mode UI Mandate Enhancement

```text
N = 100 (Total self-loop steps budget)
A = 2   (Number of autonomous subagents)
H = 2   (Parallel operations per agent)
```

> [!IMPORTANT]
> Prompt Version: 6.2.0
> Priority: CORE ARCHITECTURAL SPECIFICATION & PROMPT ENGINEERING FIX
>
> **Top-Instruction Priority Mandate (Preamble Precedence):**
> Directives, constraints, checklists, and specifications given in this document are HIGHEST PRIORITY and MUST BE FOLLOWED as strictly NON-NEGOTIABLE. They supersede and override any conflicting default conventions.

[/goal](slashCommand:goal) Autonomously enhance the LaraLicensing AI Blueprint generation engine across backend services and frontend components. Upgrade the blueprint specification contracts to mandate a dual-section client UI (Top: Pricing Deals Storefront with feature bullets and purchase actions; Bottom: Key entry activation gate) so external AI coding agents never generate a bare, uninformative license key input box. Verify with automated tests and a single atomic git commit.

---

### 📋 Actionable Deliverables & Scope

#### Task 1: Update Base AI Blueprint Generator in Backend Product Service
- **Target File:** `backend/app/Services/ProductService.php`
- **Method:** `generateAiBlueprint(Product $product): string`
- **Changes Required:**
  1. Add the Headless Deals endpoint to the blueprint header:
     `- Deals Catalog Endpoint: {dealsUrl} (GET for live pricing)`
  2. Add **Mandate 5 (Dual-Mode Client UI & Pricing Storefront)** to `## Architecture Invariants`:
     ```markdown
     5. Dual-Section Storefront & Activation Dialog:
        - When unactivated, the application MUST NOT render a bare license key input box alone.
        - It MUST render a dual-section modal or window:
          a) Top Section (Pricing Storefront): Render each deal from 'Available Deals & Seats' as a distinct card with: Deal Name, Price, Allowed Seats, Features, and a 'Buy Plan' button that opens the payment link in the default browser.
          b) Bottom Section (Activation Gate): 'Already have a license key?' text field + 'Activate License' button that calls the verification endpoint.
     6. Visual Structural Pattern:
        [ Card 1: Deal Name / Price ] [ Card 2: Deal Name / Price ] [ Card 3: Best Value ]
        ---------------------------------------------------------------------------------
        Already purchased? [ Enter Serial Key Here... ] -> [ Activate License Button ]
     ```

#### Task 2: Update Gemini Blueprint Code Synthesizer in AI Architect Service
- **Target File:** `backend/app/Services/AiArchitectService.php`
- **Method:** `generateClientBlueprintCode(Product $product, string $targetFramework, string $customNotes): array`
- **Changes Required:**
  1. In `$prompt`, expand `ARCHITECTURAL MANDATES` to include Mandate 5 (Client Storefront & Dual-Mode UI).
  2. Explicitly instruct Gemini:
     - When generating client code for GUI desktop frameworks (Java Swing, Python PyQt/Tkinter, C# WPF/WinForms, Electron):
       `The generated UI code MUST render the pricing tier cards alongside the license activation input.`
     - When generating `agentPrompt`, instruct the target AI agent to construct the dual-mode pricing card deck before the activation text field.

#### Task 3: Mirror Dual-Mode Blueprint Template in Frontend Component
- **Target File:** `src/components/developer/DeveloperProductsDashboard.tsx`
- **Method:** `generateBlueprintText(p: DeveloperProductItem): string`
- **Changes Required:**
  1. Add the Deals Endpoint URL and Mandate 5 (Dual-Section UI Mandate) matching `ProductService.php`.
  2. Ensure consistency between server-rendered and client-rendered blueprint markdown.

#### Task 4: Provide Drop-In Blueprint Prompt for Client Projects
- **Scope:** Provide a clean, copy-pasteable prompt for the developer's client software (`HotelReservationSystem`) so their coding assistant can immediately refactor the current bare input dialog into the complete dual-mode storefront.

#### Task 5: Automated Verification & Regression Gate
- **Commands:**
  - `npx.cmd vitest run` (Must report all tests passing, 0 failed).
  - Verify that `GET /api/v1/products/{slug}/blueprint` contains the new Dual-Mode Storefront Mandates.

---

### 🛡️ Non-Negotiable Invariants
1. Punctuation Hygiene: Absolutely zero em-dashes or en-dashes anywhere in source files, docstrings, or commit messages. Use hyphens, commas, colons, or parentheses.
2. Backward Compatibility: Do not break the cryptographic verification logic or Ed25519 token signatures.
3. Atomic Commit: Accumulate all changes and commit in a single atomic git commit:
   `git commit -m "feat(blueprint): mandate dual-section pricing storefront in ai generation contracts"`
