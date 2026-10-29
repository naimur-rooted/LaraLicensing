import React, { useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Bot,
  Copy,
  Check,
  Plus,
  Box,
  Layers,
  Sparkles,
  ShieldCheck,
  Activity,
  ArrowUpRight,
  Loader2,
  Code2,
  Terminal,
  FileCode,
  Pencil,
  Trash2,
} from "lucide-react";
import { CreateProductDialog } from "./CreateProductDialog";
import { AiPricingArchitectDialog } from "./AiPricingArchitectDialog";
import { getApiBaseUrl } from "@/lib/lara-api-client";
import { getLaraAccessToken } from "@/lib/lara-api-session";
import { getRuntimeMode } from "@/lib/runtime-mode";

function getDeveloperHeaders(): HeadersInit {
  const token = getLaraAccessToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  return headers;
}

export interface ProductDealItem {
  ProductDealId?: number;
  dealName: string;
  dealCode: string;
  billingInterval: string;
  priceCents: number;
  allowedMachineSeats: number;
  allowedDomains: number;
  isDecoyTarget: boolean;
  features?: string[];
  featuresText?: string;
}

export interface DeveloperProductItem {
  ProductId?: number;
  productSlug: string;
  productName: string;
  category: string;
  description?: string;
  ed25519PublicKey?: string;
  checkIntervalDays: number;
  deals: ProductDealItem[];
}

const INITIAL_PRODUCTS: DeveloperProductItem[] = [];

export interface DeveloperProductsDashboardProps {
  readonly onOpenPlanModal?: () => void;
}

export function DeveloperProductsDashboard({ onOpenPlanModal }: DeveloperProductsDashboardProps = {}) {
  const [products, setProducts] = useState<DeveloperProductItem[]>(INITIAL_PRODUCTS);
  const [selectedProduct, setSelectedProduct] = useState<DeveloperProductItem | null>(null);
  const [isBlueprintOpen, setIsBlueprintOpen] = useState(false);
  const [copied, setCopied] = useState(false);

  // AI Blueprint Code Generation State
  const [blueprintFramework, setBlueprintFramework] = useState("chrome-extension");
  const [blueprintNotes, setBlueprintNotes] = useState("");
  const [isGeneratingBlueprintCode, setIsGeneratingBlueprintCode] = useState(false);
  const [generatedBlueprint, setGeneratedBlueprint] = useState<{
    framework: string;
    fileName: string;
    dependencies: string;
    instructions: string;
    codeSnippet: string;
    agentPrompt: string;
  } | null>(null);
  const [blueprintTab, setBlueprintTab] = useState<"code" | "prompt">("code");

  const handleGenerateBlueprintCode = async (product: DeveloperProductItem, framework: string) => {
    setIsGeneratingBlueprintCode(true);
    try {
      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products/${product.productSlug}/blueprint-code`, {
        method: "POST",
        headers: getDeveloperHeaders(),
        body: JSON.stringify({
          framework,
          notes: blueprintNotes,
        }),
      });
      const data = await res.json();
      if (data.IsSuccess && data.Data) {
        setGeneratedBlueprint(data.Data);
        setBlueprintTab("code");
      }
    } catch {
      // Local fallback
    } finally {
      setIsGeneratingBlueprintCode(false);
    }
  };

  // Platform Subscription State (Default to Free / Unsubscribed)
  const [currentTier, setCurrentTier] = useState("Free Starter");

  React.useEffect(() => {
    if (getRuntimeMode().Mode === "preview") return;
    fetch(`${getApiBaseUrl()}/api/v1/developer/products`, {
      headers: getDeveloperHeaders(),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.IsSuccess && Array.isArray(data.Data)) {
          setProducts(data.Data);
        }
      })
      .catch(() => {});
  }, []);

  // New Product Modal State
  const [isNewProductOpen, setIsNewProductOpen] = useState(false);

  // AI Pricing Architect State
  const [isAiArchitectOpen, setIsAiArchitectOpen] = useState(false);
  const [aiArchitectProduct, setAiArchitectProduct] = useState<DeveloperProductItem | null>(null);

  // Add Deal Modal State
  const [isAddDealOpen, setIsAddDealOpen] = useState(false);
  const [targetProduct, setTargetProduct] = useState<DeveloperProductItem | null>(null);
  const [dealName, setDealName] = useState("");
  const [dealCode, setDealCode] = useState("");
  const [dealInterval, setDealInterval] = useState("biennial");
  const [dealPrice, setDealPrice] = useState("69");
  const [dealSeats, setDealSeats] = useState("5");
  const [dealFeatures, setDealFeatures] = useState("");
  const [isDecoy, setIsDecoy] = useState(true);
  const [addDealError, setAddDealError] = useState<string | null>(null);

  // Edit Deal Modal State
  const [isEditDealOpen, setIsEditDealOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState<DeveloperProductItem | null>(null);
  const [editingDealId, setEditingDealId] = useState<number | null>(null);
  const [editDealName, setEditDealName] = useState("");
  const [editDealCode, setEditDealCode] = useState("");
  const [editDealInterval, setEditDealInterval] = useState("monthly");
  const [editDealPrice, setEditDealPrice] = useState("29");
  const [editDealSeats, setEditDealSeats] = useState("1");
  const [editDealFeatures, setEditDealFeatures] = useState("");
  const [editIsDecoy, setEditIsDecoy] = useState(false);
  const [isSavingDeal, setIsSavingDeal] = useState(false);

  const handleOpenEditDeal = (product: DeveloperProductItem, deal: ProductDealItem) => {
    setEditingProduct(product);
    setEditingDealId(deal.ProductDealId || null);
    setEditDealName(deal.dealName);
    setEditDealCode(deal.dealCode);
    setEditDealInterval(deal.billingInterval);
    setEditDealPrice((deal.priceCents / 100).toFixed(2));
    setEditDealSeats(deal.allowedMachineSeats.toString());
    setEditDealFeatures((deal.features || []).join("\n"));
    setEditIsDecoy(deal.isDecoyTarget);
    setIsEditDealOpen(true);
  };

  const handleSaveEditDeal = async () => {
    if (!editingProduct || !editingDealId) return;
    setIsSavingDeal(true);
    const parsedFeatures = editDealFeatures
      ? editDealFeatures.split("\n").map((f) => f.trim()).filter(Boolean)
      : [];

    try {
      const res = await fetch(
        `${getApiBaseUrl()}/api/v1/developer/products/${editingProduct.productSlug}/deals/${editingDealId}`,
        {
          method: "PUT",
          headers: getDeveloperHeaders(),
          body: JSON.stringify({
            dealName: editDealName,
            dealCode: editDealCode,
            billingInterval: editDealInterval,
            priceCents: Math.round(parseFloat(editDealPrice || "0") * 100),
            allowedMachineSeats: parseInt(editDealSeats, 10) || 1,
            isDecoyTarget: editIsDecoy,
            features: parsedFeatures,
          }),
        }
      );

      const data = await res.json();
      if (data.IsSuccess) {
        setProducts((prev) =>
          prev.map((p) => {
            if (p.productSlug !== editingProduct.productSlug) return p;
            return {
              ...p,
              deals: p.deals.map((d) => (d.ProductDealId === editingDealId ? data.Data : d)),
            };
          })
        );
        setIsEditDealOpen(false);
      }
    } catch {
      // Local fallback
    } finally {
      setIsSavingDeal(false);
    }
  };

  const handleDeleteDeal = async (product: DeveloperProductItem, deal: ProductDealItem) => {
    if (!deal.ProductDealId) return;
    if (!window.confirm(`Are you sure you want to delete the deal "${deal.dealName}"?`)) return;

    try {
      const res = await fetch(
        `${getApiBaseUrl()}/api/v1/developer/products/${product.productSlug}/deals/${deal.ProductDealId}`,
        {
          method: "DELETE",
          headers: getDeveloperHeaders(),
        }
      );
      const data = await res.json();
      if (data.IsSuccess) {
        setProducts((prev) =>
          prev.map((p) => {
            if (p.productSlug !== product.productSlug) return p;
            return {
              ...p,
              deals: p.deals.filter((d) => d.ProductDealId !== deal.ProductDealId),
            };
          })
        );
      }
    } catch {}
  };

  const handleCopyPrompt = (prompt: string) => {
    navigator.clipboard.writeText(prompt);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const generateBlueprintText = (p: DeveloperProductItem) => {
    const dealsList =
      p.deals && p.deals.length > 0
        ? p.deals
            .map((d) => {
              const price = (d.priceCents / 100).toFixed(2);
              const features =
                d.features && d.features.length > 0
                  ? ` | Features: ${d.features.join(", ")}`
                  : "";
              const decoyTag = d.isDecoyTarget ? " [★ BEST VALUE / RECOMMENDED TARGET]" : "";
              return `- ${d.dealName} (${d.dealCode}): $${price} / ${d.billingInterval} (${d.allowedMachineSeats} seat(s))${decoyTag}${features}`;
            })
            .join("\n")
        : "- Standard Plan (STD): $49.00 / yearly (1 seat)";

    return `# Autonomous Client License Verification & Commercial Storefront Blueprint
Integrate LaraLicensing into this application adhering strictly to the contract below.

## 1. Product Identity & Cryptographic Contract
- Product Name: ${p.productName}
- Product Slug: ${p.productSlug}
- Activation Endpoint (POST): ${getApiBaseUrl()}/api/v1/products/${p.productSlug}/activate
- Verification Endpoint (POST): ${getApiBaseUrl()}/api/v1/products/${p.productSlug}/verify
- Deactivation Endpoint (POST): ${getApiBaseUrl()}/api/v1/products/${p.productSlug}/deactivate
- Live Deals Endpoint (GET): ${getApiBaseUrl()}/api/v1/products/${p.productSlug}/deals
- Public Key (Ed25519): ${p.ed25519PublicKey || "AUTO_GENERATED"}
- Cadence: Re-verify every ${p.checkIntervalDays} days

## 2. Configured Pricing Packages (Ground Truth):
${dealsList}
(Formula: Display Price = priceCents / 100.0, e.g., 3900 -> $39.00)

## 3. Strict Non-Negotiable Architecture Invariants (Zero-Hallucination):

1. Dual-Section Storefront & Activation Dialog:
   - When the software starts without an active license, it MUST NOT render a bare license key text box alone.
   - It MUST render a dual-section UI:
     * Top Section: Display the configured pricing deals as cards showing Deal Name, formatted Price ($xx.xx / interval), allowed PC seats, and feature perks.
       - Decoy Target / Recommended Badge (DYNAMIC): Parse "isDecoyTarget" boolean from deals JSON. If true, dynamically display a "★ BEST VALUE / RECOMMENDED" badge and highlighted border. Never hardcode which deal is best value; evaluate isDecoyTarget dynamically.
     * Bottom Section: "Already have a license key?" input box + "Activate License" button.

2. Zero Dummy Fallback Mandate (CRITICAL):
   - You MUST NOT hardcode invented dummy prices ($29, $290, $490, etc.) in catch blocks or fallbacks.
   - Prices and features MUST strictly reflect the provided deals list above or be parsed from the Live Deals Endpoint.
   - If network or parsing fails, show a clean "Cannot reach licensing server. [Retry]" label. NEVER display fake numbers.

3. Zero-Dependency Parser Mandate:
   - For Java or C++, do NOT assume external JSON libraries (Gson, Jackson) exist.
   - Use standard library regex matching to parse \`dealName\`, \`priceCents\`, and \`allowedMachineSeats\` safely.

4. Buy Plan Action Mandate:
   - DO NOT open the raw API URL (.../deals) in the user's web browser.
   - When "Buy Plan" is clicked, display a purchase instruction modal (e.g., "To activate this package, contact vendor or visit vendor checkout") or open the vendor's actual checkout URL.

5. Hardware Seat Activation & Offline Token:
   - Read the machine GUID / hardware digest.
   - On initial activation ("Activate License"), send POST to Activation Endpoint (${getApiBaseUrl()}/api/v1/products/${p.productSlug}/activate) with {"licenseKey": "...", "hardwareDigest": "...", "hostname": "..."} to bind the machine seat and increment active seats from 0 to 1.
   - Handle 409 Conflict gracefully if all allowed seats are occupied.
   - Cache the returned signed Ed25519 JWT offline token locally.
   - For background cadence checks (every ${p.checkIntervalDays} days), call Verification Endpoint (${getApiBaseUrl()}/api/v1/products/${p.productSlug}/verify) to verify status without consuming extra seats.`;
  };

  const handleProductCreated = (newProduct: DeveloperProductItem) => {
    setProducts((prev) => [...prev, newProduct]);
  };

  const handleAddDealSubmit = async () => {
    if (!targetProduct || !dealName.trim()) return;
    const parsedFeatures = dealFeatures
      ? dealFeatures.split("\n").map((f) => f.trim()).filter(Boolean)
      : [];

    const newDeal: ProductDealItem = {
      dealName,
      dealCode: dealCode.toUpperCase().trim() || `DEAL_${Date.now()}`,
      billingInterval: dealInterval,
      priceCents: Math.round(parseFloat(dealPrice || "0") * 100),
      allowedMachineSeats: parseInt(dealSeats, 10) || 1,
      allowedDomains: 1,
      isDecoyTarget: isDecoy,
      features: parsedFeatures,
    };

    setAddDealError(null);
    try {
      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products/${targetProduct.productSlug}/deals`, {
        method: "POST",
        headers: getDeveloperHeaders(),
        body: JSON.stringify(newDeal),
      });
      if (!res.ok) {
        setAddDealError("Deal could not be saved. Please try again.");
        return;
      }
      const payload = await res.json().catch(() => null);
      if (payload && payload.IsSuccess === false) {
        setAddDealError("Deal could not be saved. Please try again.");
        return;
      }
    } catch {
      setAddDealError("Deal could not be saved. Please check your connection and try again.");
      return;
    }

    const updated = products.map((p) => {
      if (p.productSlug === targetProduct.productSlug) {
        return { ...p, deals: [...p.deals, newDeal] };
      }
      return p;
    });
    setProducts(updated);
    setIsAddDealOpen(false);
    setAddDealError(null);
    setDealName("");
    setDealCode("");
    setDealFeatures("");
  };

  const isFreePlan = currentTier.includes("Free") || currentTier.includes("Starter");
  const maxProducts = isFreePlan ? 1 : currentTier.includes("Essential") ? 3 : currentTier.includes("Pro") ? 15 : 999;
  const maxRequests = isFreePlan ? 100 : currentTier.includes("Essential") ? 200 : currentTier.includes("Pro") ? 1000 : 10000;

  return (
    <div className="flex flex-col gap-6" data-component="developer-dashboard">
      {/* Top Banner: Metered Capability Quota */}
      <div className="flex flex-col gap-4 rounded-xl border bg-card/60 p-5 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Activity className="size-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h3 className="font-semibold text-foreground">Developer License Studio</h3>
              <Badge
                variant="outline"
                className={`text-xs font-medium ${
                  isFreePlan
                    ? "text-amber-500 border-amber-500/30 bg-amber-500/10"
                    : "text-emerald-500 border-emerald-500/30 bg-emerald-500/10"
                }`}
              >
                {currentTier}
              </Badge>
              <Button
                variant="ghost"
                size="sm"
                onClick={onOpenPlanModal}
                className="h-6 px-2 text-xs text-primary hover:text-primary/80 gap-1 font-semibold"
              >
                <span>{isFreePlan ? "Choose a Plan" : "Upgrade / Change Plan"}</span>
                <ArrowUpRight className="size-3" />
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              Capacity: {products.length} / {maxProducts >= 999 ? "Unlimited" : maxProducts} Products • Daily Requests: 0 / {maxRequests.toLocaleString()} used today
            </p>
          </div>
        </div>
        <Button onClick={() => setIsNewProductOpen(true)} className="gap-1.5 self-start sm:self-auto">
          <Plus className="size-4" />
          Add New Product
        </Button>
      </div>

      {/* Product Cards Grid or Empty State */}
      {products.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/80 bg-card/40 p-12 text-center">
          <div className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary mb-4">
            <Box className="size-7" />
          </div>
          <h3 className="text-base font-semibold text-foreground">No Software Products Registered Yet</h3>
          <p className="mt-1.5 max-w-md text-xs text-muted-foreground leading-relaxed">
            You are currently on the <strong>Free Starter Trial</strong>. You have not bought a LaraLicensing platform subscription or registered any software products yet.
          </p>
          <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
            <Button
              variant="outline"
              size="sm"
              onClick={onOpenPlanModal}
              className="text-xs gap-1.5 font-medium"
            >
              <Sparkles className="size-3.5 text-amber-500" />
              Browse LaraLicensing Platform Packages
            </Button>
            <Button
              size="sm"
              onClick={() => setIsNewProductOpen(true)}
              className="text-xs gap-1.5 bg-primary hover:bg-primary/90 font-medium"
            >
              <Plus className="size-3.5" />
              Register Your First Product
            </Button>
          </div>
        </div>
      ) : (
        <div className="grid gap-6 md:grid-cols-2">
          {products.map((p) => (
            <Card key={p.productSlug} className="relative flex flex-col justify-between overflow-hidden border-border/80 shadow-sm">
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="flex items-center gap-2.5">
                    <div className="flex size-9 items-center justify-center rounded-md bg-muted text-foreground">
                      <Box className="size-5" />
                    </div>
                    <div>
                      <CardTitle className="text-base font-semibold">{p.productName}</CardTitle>
                      <CardDescription className="text-xs font-mono">{p.productSlug}</CardDescription>
                    </div>
                  </div>
                  <Badge variant="secondary" className="text-[11px] font-medium">
                    {p.category}
                  </Badge>
                </div>
                {p.description && (
                  <p className="mt-2 text-xs text-muted-foreground line-clamp-2">{p.description}</p>
                )}
              </CardHeader>

              <CardContent className="flex flex-col gap-3 pb-3">
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                  <span className="flex items-center gap-1 font-medium text-foreground">
                    <Layers className="size-3.5 text-primary" />
                    Pricing Deals ({p.deals.length})
                  </span>
                  <span className="text-[11px]">Re-verify: every {p.checkIntervalDays} days</span>
                </div>

                <div className="space-y-1.5 rounded-lg border bg-muted/30 p-2.5 text-xs">
                  {p.deals.map((deal) => (
                    <div
                      key={deal.dealCode}
                      className="group flex items-center justify-between rounded p-1.5 hover:bg-background/80 transition-colors"
                    >
                      <div className="flex items-center gap-1.5">
                        <span className="font-medium text-foreground">{deal.dealName}</span>
                        {deal.isDecoyTarget && (
                          <Badge variant="default" className="h-4 px-1 text-[9px] bg-emerald-600 font-semibold">
                            DECOY TARGET
                          </Badge>
                        )}
                      </div>
                      <div className="flex items-center gap-2">
                        <div className="text-right font-mono text-[11px] text-muted-foreground">
                          <span className="font-semibold text-foreground">${(deal.priceCents / 100).toFixed(2)}</span>
                          <span> / {deal.billingInterval}</span>
                          {deal.features && deal.features.length > 0 ? (
                            <span className="ml-1 text-[10px] text-emerald-400 font-medium">({deal.features.length} perks)</span>
                          ) : (
                            <span className="ml-1 text-[10px] text-muted-foreground/80">({deal.allowedMachineSeats} seats)</span>
                          )}
                        </div>
                        <div className="flex items-center gap-0.5 opacity-60 group-hover:opacity-100 transition-opacity">
                          <button
                            type="button"
                            title="Edit Deal Price & Features"
                            onClick={() => handleOpenEditDeal(p, deal)}
                            className="p-1 rounded hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                          >
                            <Pencil className="size-3" />
                          </button>
                          <button
                            type="button"
                            title="Delete Deal"
                            onClick={() => handleDeleteDeal(p, deal)}
                            className="p-1 rounded hover:bg-muted text-muted-foreground hover:text-destructive transition-colors"
                          >
                            <Trash2 className="size-3" />
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>

              <CardFooter className="flex items-center justify-between border-t bg-muted/20 pt-3">
                <div className="flex items-center gap-1.5">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setTargetProduct(p);
                      setIsAddDealOpen(true);
                    }}
                    className="h-8 gap-1 text-xs"
                  >
                    <Plus className="size-3" />
                    Add Deal
                  </Button>

                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setAiArchitectProduct(p);
                      setIsAiArchitectOpen(true);
                    }}
                    className="h-8 gap-1.5 text-xs border-primary/40 text-primary hover:bg-primary/10"
                  >
                    <Sparkles className="size-3 text-amber-500" />
                    AI Architect
                  </Button>
                </div>

                <Button
                  variant="default"
                  size="sm"
                  onClick={() => {
                    setSelectedProduct(p);
                    const defaultFw =
                      p.category === "ChromeExtension"
                        ? "chrome-extension"
                        : p.category === "WordPressPlugin"
                        ? "wordpress-plugin"
                        : "python-desktop";
                    setBlueprintFramework(defaultFw);
                    setGeneratedBlueprint(null);
                    setIsBlueprintOpen(true);
                  }}
                  className="h-8 gap-1.5 text-xs bg-primary hover:bg-primary/90"
                >
                  <Bot className="size-3.5" />
                  Export AI Blueprint
                </Button>
              </CardFooter>
            </Card>
          ))}
        </div>
      )}

      {/* Export AI Blueprint & Source Generator Dialog */}
      <Dialog open={isBlueprintOpen} onOpenChange={setIsBlueprintOpen}>
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-lg">
              <Bot className="size-5 text-primary" />
              AI Integration Blueprint & Drop-in Source Generator
            </DialogTitle>
            <DialogDescription>
              Synthesize production-ready client verification code and tailored AI agent prompts (Cursor, Claude, Copilot) grounded in your product's Ed25519 keys, cadence intervals, and feature gates.
            </DialogDescription>
          </DialogHeader>

          {selectedProduct && (
            <div className="space-y-4 py-1">
              {/* Framework Selector & Gemini Generator Bar */}
              <div className="rounded-lg border bg-muted/20 p-3.5 space-y-3">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-semibold text-foreground">Select Target Language / Framework</Label>
                  <span className="text-[10px] text-muted-foreground font-mono">
                    Product: {selectedProduct.productSlug} ({selectedProduct.category})
                  </span>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-5 gap-1.5">
                  {[
                    { id: "chrome-extension", label: "🧩 Chrome (JS)" },
                    { id: "python-desktop", label: "🐍 Python Desktop" },
                    { id: "wordpress-plugin", label: "🔌 WordPress (PHP)" },
                    { id: "csharp-dotnet", label: "⚡ C# .NET" },
                    { id: "universal-agent", label: "🤖 Universal AI" },
                  ].map((fw) => (
                    <button
                      key={fw.id}
                      type="button"
                      onClick={() => setBlueprintFramework(fw.id)}
                      className={`px-2.5 py-1.5 rounded-md text-xs font-medium border text-center transition-all ${
                        blueprintFramework === fw.id
                          ? "border-primary bg-primary text-primary-foreground shadow-xs font-semibold"
                          : "border-border/60 bg-background/50 hover:bg-background text-muted-foreground"
                      }`}
                    >
                      {fw.label}
                    </button>
                  ))}
                </div>

                <div className="flex items-center gap-2 pt-1">
                  <Input
                    placeholder="Optional notes: e.g. 'Use electron-store', 'Integrate with PyQt6', 'Strict offline mode'"
                    value={blueprintNotes}
                    onChange={(e) => setBlueprintNotes(e.target.value)}
                    className="h-8 text-xs bg-background flex-1"
                  />
                  <Button
                    size="sm"
                    disabled={isGeneratingBlueprintCode}
                    onClick={() => handleGenerateBlueprintCode(selectedProduct, blueprintFramework)}
                    className="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90 shrink-0"
                  >
                    {isGeneratingBlueprintCode ? (
                      <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                      <Sparkles className="size-3.5 text-amber-400" />
                    )}
                    {isGeneratingBlueprintCode ? "Synthesizing with Gemini..." : "Synthesize Code with Gemini"}
                  </Button>
                </div>
              </div>

              {/* Generated Result or Default Blueprint Specification */}
              {generatedBlueprint ? (
                <div className="space-y-2">
                  <div className="flex flex-wrap items-center justify-between gap-2 px-1 text-xs">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-foreground flex items-center gap-1">
                        <FileCode className="size-3.5 text-primary" />
                        File: <code className="text-primary font-mono">{generatedBlueprint.fileName}</code>
                      </span>
                      {generatedBlueprint.dependencies && generatedBlueprint.dependencies !== "none" && (
                        <span className="text-[11px] font-mono bg-zinc-800 text-amber-300 px-2 py-0.5 rounded border border-zinc-700">
                          {generatedBlueprint.dependencies}
                        </span>
                      )}
                    </div>

                    <div className="flex items-center gap-1 border rounded-md p-0.5 bg-muted/30">
                      <button
                        type="button"
                        onClick={() => setBlueprintTab("code")}
                        className={`px-2 py-0.5 rounded text-xs font-medium transition-all ${
                          blueprintTab === "code" ? "bg-background text-foreground shadow-xs" : "text-muted-foreground"
                        }`}
                      >
                        Drop-in Code
                      </button>
                      <button
                        type="button"
                        onClick={() => setBlueprintTab("prompt")}
                        className={`px-2 py-0.5 rounded text-xs font-medium transition-all ${
                          blueprintTab === "prompt" ? "bg-background text-foreground shadow-xs" : "text-muted-foreground"
                        }`}
                      >
                        Agent Prompt (Cursor/Claude)
                      </button>
                    </div>
                  </div>

                  {generatedBlueprint.instructions && (
                    <p className="text-[11px] text-muted-foreground px-1 italic">
                      💡 {generatedBlueprint.instructions}
                    </p>
                  )}

                  <div className="relative rounded-lg border bg-zinc-950 p-4 font-mono text-xs text-zinc-100 dark:bg-zinc-900 shadow-inner">
                    <pre className="whitespace-pre-wrap leading-relaxed max-h-[360px] overflow-y-auto">
                      {blueprintTab === "code" ? generatedBlueprint.codeSnippet : generatedBlueprint.agentPrompt}
                    </pre>
                    <Button
                      size="sm"
                      variant="secondary"
                      onClick={() =>
                        handleCopyPrompt(
                          blueprintTab === "code"
                            ? generatedBlueprint.codeSnippet
                            : generatedBlueprint.agentPrompt
                        )
                      }
                      className="absolute top-3 right-3 gap-1.5 text-xs shadow-md bg-zinc-800 hover:bg-zinc-700 text-zinc-100"
                    >
                      {copied ? <Check className="size-3.5 text-emerald-400" /> : <Copy className="size-3.5" />}
                      {copied ? "Copied!" : blueprintTab === "code" ? "Copy Source Code" : "Copy Agent Prompt"}
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="space-y-2">
                  <div className="flex items-center justify-between px-1">
                    <span className="text-xs font-semibold text-muted-foreground flex items-center gap-1.5">
                      <Terminal className="size-3.5" /> Base Protocol Specification
                    </span>
                    <span className="text-[11px] text-amber-500 font-medium">
                      👆 Click "Synthesize Code with Gemini" above for ready-to-paste {blueprintFramework} code
                    </span>
                  </div>
                  <div className="relative rounded-lg border bg-zinc-950 p-4 font-mono text-xs text-zinc-100 dark:bg-zinc-900">
                    <pre className="whitespace-pre-wrap leading-relaxed max-h-[280px] overflow-y-auto">
                      {generateBlueprintText(selectedProduct)}
                    </pre>
                    <Button
                      size="sm"
                      variant="secondary"
                      onClick={() => handleCopyPrompt(generateBlueprintText(selectedProduct))}
                      className="absolute top-3 right-3 gap-1.5 text-xs shadow-md bg-zinc-800 hover:bg-zinc-700 text-zinc-100"
                    >
                      {copied ? <Check className="size-3.5 text-emerald-400" /> : <Copy className="size-3.5" />}
                      {copied ? "Copied Protocol!" : "Copy Protocol"}
                    </Button>
                  </div>
                </div>
              )}
            </div>
          )}

          <DialogFooter className="sm:justify-between pt-2">
            <span className="text-[11px] text-muted-foreground flex items-center gap-1">
              <ShieldCheck className="size-3.5 text-emerald-500" /> Ed25519 Cryptographic Signatures • Zero Cardholder Data
            </span>
            <Button variant="outline" size="sm" onClick={() => setIsBlueprintOpen(false)}>
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Create Product Modal with Downstream Pricing Matrix */}
      <CreateProductDialog
        isOpen={isNewProductOpen}
        onOpenChange={setIsNewProductOpen}
        onProductCreated={handleProductCreated}
      />

      {/* Add Deal Modal */}
      <Dialog open={isAddDealOpen} onOpenChange={setIsAddDealOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Add Pricing Deal: {targetProduct?.productName}</DialogTitle>
            <DialogDescription>
              Configure an end-user licensing deal for this product.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 py-2 text-sm">
            <div className="grid gap-1.5">
              <Label>Deal Name</Label>
              <Input
                placeholder="e.g. 2 Years + 3 Months Free (Decoy Target)"
                value={dealName}
                onChange={(e) => setDealName(e.target.value)}
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-1.5">
                <Label>Billing Interval</Label>
                <select
                  value={dealInterval}
                  onChange={(e) => setDealInterval(e.target.value)}
                  className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option value="daily">Daily Pass (24h)</option>
                  <option value="weekly">Weekly Pass (7 Days)</option>
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly (3 Months)</option>
                  <option value="yearly">Yearly</option>
                  <option value="biennial">2-Years (Decoy)</option>
                  <option value="lifetime">Lifetime Deal</option>
                </select>
              </div>
              <div className="grid gap-1.5">
                <Label>Price (USD)</Label>
                <Input
                  type="number"
                  placeholder="69.00"
                  value={dealPrice}
                  onChange={(e) => setDealPrice(e.target.value)}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-1.5">
                <Label>
                  {targetProduct?.category === "ChromeExtension"
                    ? "Chrome Profiles"
                    : targetProduct?.category === "WordPressPlugin"
                    ? "Domain Sites"
                    : "Machine Seats"}
                </Label>
                <Input
                  type="number"
                  value={dealSeats}
                  onChange={(e) => setDealSeats(e.target.value)}
                />
              </div>
              <div className="flex items-center gap-2 pt-6">
                <input
                  type="checkbox"
                  id="decoyCheck"
                  checked={isDecoy}
                  onChange={(e) => setIsDecoy(e.target.checked)}
                  className="size-4 rounded border-input"
                />
                <Label htmlFor="decoyCheck" className="text-xs font-semibold text-emerald-600">
                  Highlight as Decoy Target
                </Label>
              </div>
            </div>
            <div className="grid gap-1.5">
              <Label>Feature Perks (one bullet per line)</Label>
              <textarea
                rows={3}
                placeholder={"e.g.\n1,000 Verified Leads / month\nExport to CSV & HubSpot\n1 Chrome Profile License"}
                value={dealFeatures}
                onChange={(e) => setDealFeatures(e.target.value)}
                className="rounded-md border border-input bg-background px-3 py-2 text-xs font-mono"
              />
            </div>
          </div>
          {addDealError ? (
            <p className="text-xs text-red-600 px-1">{addDealError}</p>
          ) : null}
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsAddDealOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleAddDealSubmit}>Save Deal</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Edit Deal Modal */}
      <Dialog open={isEditDealOpen} onOpenChange={setIsEditDealOpen}>
        <DialogContent className="max-w-md max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Edit Pricing Deal: {editingProduct?.productName}</DialogTitle>
            <DialogDescription>
              Update price, billing interval, and software capability perks. Changes sync live to the REST API.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 py-2 text-sm">
            <div className="grid gap-1.5">
              <Label>Deal Name</Label>
              <Input
                value={editDealName}
                onChange={(e) => setEditDealName(e.target.value)}
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-1.5">
                <Label>Billing Interval</Label>
                <select
                  value={editDealInterval}
                  onChange={(e) => setEditDealInterval(e.target.value)}
                  className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option value="daily">Daily Pass (24h)</option>
                  <option value="weekly">Weekly Pass (7 Days)</option>
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly (3 Months)</option>
                  <option value="yearly">Yearly</option>
                  <option value="biennial">2-Years (Decoy)</option>
                  <option value="lifetime">Lifetime Deal</option>
                </select>
              </div>
              <div className="grid gap-1.5">
                <Label>Price (USD)</Label>
                <Input
                  type="number"
                  value={editDealPrice}
                  onChange={(e) => setEditDealPrice(e.target.value)}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-1.5">
                <Label>
                  {editingProduct?.category === "ChromeExtension"
                    ? "Chrome Profiles"
                    : editingProduct?.category === "WordPressPlugin"
                    ? "Domain Sites"
                    : "Machine Seats"}
                </Label>
                <Input
                  type="number"
                  value={editDealSeats}
                  onChange={(e) => setEditDealSeats(e.target.value)}
                />
              </div>
              <div className="flex items-center gap-2 pt-6">
                <input
                  type="checkbox"
                  id="editDecoyCheck"
                  checked={editIsDecoy}
                  onChange={(e) => setEditIsDecoy(e.target.checked)}
                  className="size-4 rounded border-input"
                />
                <Label htmlFor="editDecoyCheck" className="text-xs cursor-pointer font-semibold text-emerald-600">
                  Decoy Target (Best Value)
                </Label>
              </div>
            </div>

            {/* Custom Features & Capabilities */}
            <div className="grid gap-1.5">
              <div className="flex items-center justify-between">
                <Label className="text-xs font-semibold">
                  Included Features & Capabilities (one per line)
                </Label>
                <span className="text-[10px] text-muted-foreground">Bullets with checkmarks</span>
              </div>
              <textarea
                rows={4}
                value={editDealFeatures}
                onChange={(e) => setEditDealFeatures(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    e.stopPropagation();
                  }
                }}
                className="w-full rounded-md border border-input bg-background p-2.5 text-xs font-mono min-h-[90px] resize-y leading-relaxed"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" size="sm" onClick={() => setIsEditDealOpen(false)}>
              Cancel
            </Button>
            <Button size="sm" disabled={isSavingDeal} onClick={handleSaveEditDeal}>
              {isSavingDeal ? <Loader2 className="size-3.5 animate-spin mr-1" /> : null}
              Save Changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Grounded AI Pricing Architect Dialog */}
      <AiPricingArchitectDialog
        isOpen={isAiArchitectOpen}
        onOpenChange={setIsAiArchitectOpen}
        product={aiArchitectProduct}
        onDealsApplied={(updated) => {
          setProducts((prev) =>
            prev.map((p) => (p.productSlug === updated.productSlug ? updated : p))
          );
        }}
      />
    </div>
  );
}
