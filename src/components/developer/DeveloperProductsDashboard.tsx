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
} from "lucide-react";
import { CreateProductDialog } from "./CreateProductDialog";
import { PlatformSubscriptionModal } from "./PlatformSubscriptionModal";
import { AiPricingArchitectDialog } from "./AiPricingArchitectDialog";

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

export function DeveloperProductsDashboard() {
  const [products, setProducts] = useState<DeveloperProductItem[]>(INITIAL_PRODUCTS);
  const [selectedProduct, setSelectedProduct] = useState<DeveloperProductItem | null>(null);
  const [isBlueprintOpen, setIsBlueprintOpen] = useState(false);
  const [copied, setCopied] = useState(false);

  // Platform Subscription State (Default to Free / Unsubscribed)
  const [currentTier, setCurrentTier] = useState("Free Starter");
  const [isPlatformModalOpen, setIsPlatformModalOpen] = useState(false);

  React.useEffect(() => {
    fetch("http://127.0.0.1:8000/api/v1/developer/products")
      .then((res) => res.json())
      .then((data) => {
        if (data.IsSuccess && Array.isArray(data.Data) && data.Data.length > 0) {
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

  const handleCopyPrompt = (prompt: string) => {
    navigator.clipboard.writeText(prompt);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const generateBlueprintText = (p: DeveloperProductItem) => {
    const dealsList = p.deals
      .map(
        (d) =>
          `- ${d.dealName} (${d.dealCode}): $${(d.priceCents / 100).toFixed(2)} / ${d.billingInterval}, ${d.allowedMachineSeats} seat(s)`
      )
      .join("\n");

    return `# Autonomous Client License Verification Blueprint
Integrate LaraLicensing into this application using the following contract:

- Product Slug: ${p.productSlug}
- Product Category: ${p.category}
- Verification Endpoint: http://localhost:8000/api/v1/products/${p.productSlug}/verify
- Public Key (Ed25519): ${p.ed25519PublicKey || "AUTO_GENERATED"}
- Re-Verification Cadence: Every ${p.checkIntervalDays} days
- Available Deals & Seats:
${dealsList}

## Architecture Invariants:
1. Generate hardware fingerprint from Machine GUID or MAC hash.
2. Call verification endpoint on initial activation and cache the signed JWT offline token.
3. Verify the token signature locally using the public key on each startup.
4. Schedule background re-verification every ${p.checkIntervalDays} days.`;
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

    try {
      await fetch(`http://127.0.0.1:8000/api/v1/developer/products/${targetProduct.productSlug}/deals`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(newDeal),
      });
    } catch {}

    const updated = products.map((p) => {
      if (p.productSlug === targetProduct.productSlug) {
        return { ...p, deals: [...p.deals, newDeal] };
      }
      return p;
    });
    setProducts(updated);
    setIsAddDealOpen(false);
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
                onClick={() => setIsPlatformModalOpen(true)}
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
              onClick={() => setIsPlatformModalOpen(true)}
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
                      className="flex items-center justify-between rounded p-1 hover:bg-background/80 transition-colors"
                    >
                      <div className="flex items-center gap-1.5">
                        <span className="font-medium text-foreground">{deal.dealName}</span>
                        {deal.isDecoyTarget && (
                          <Badge variant="default" className="h-4 px-1 text-[9px] bg-emerald-600 font-semibold">
                            DECOY TARGET
                          </Badge>
                        )}
                      </div>
                      <div className="text-right font-mono text-[11px] text-muted-foreground">
                        <span className="font-semibold text-foreground">${(deal.priceCents / 100).toFixed(2)}</span>
                        <span> / {deal.billingInterval}</span>
                        {deal.features && deal.features.length > 0 ? (
                          <span className="ml-1 text-[10px] text-emerald-400 font-medium">({deal.features.length} perks)</span>
                        ) : (
                          <span className="ml-1 text-[10px] text-muted-foreground/80">({deal.allowedMachineSeats} seats)</span>
                        )}
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

      {/* Export AI Blueprint Dialog */}
      <Dialog open={isBlueprintOpen} onOpenChange={setIsBlueprintOpen}>
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Bot className="size-5 text-primary" />
              AI Integration Prompt
            </DialogTitle>
            <DialogDescription>
              Copy this ready-made blueprint and paste it directly into Cursor, Claude, ChatGPT, or Gemini to auto-integrate client license verification into your software.
            </DialogDescription>
          </DialogHeader>

          {selectedProduct && (
            <div className="relative rounded-lg border bg-zinc-950 p-4 font-mono text-xs text-zinc-100 dark:bg-zinc-900">
              <pre className="whitespace-pre-wrap leading-relaxed max-h-[320px] overflow-y-auto">
                {generateBlueprintText(selectedProduct)}
              </pre>
              <Button
                size="sm"
                variant="secondary"
                onClick={() => handleCopyPrompt(generateBlueprintText(selectedProduct))}
                className="absolute top-3 right-3 gap-1.5 text-xs shadow-md"
              >
                {copied ? <Check className="size-3.5 text-emerald-500" /> : <Copy className="size-3.5" />}
                {copied ? "Copied Prompt!" : "Copy Prompt"}
              </Button>
            </div>
          )}

          <DialogFooter className="sm:justify-between">
            <span className="text-[11px] text-muted-foreground flex items-center gap-1">
              <ShieldCheck className="size-3.5 text-emerald-500" /> Zero cardholder data processed
            </span>
            <Button variant="outline" size="sm" onClick={() => setIsBlueprintOpen(false)}>
              Done
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

      {/* Developer Platform Tier Subscription Modal */}
      <PlatformSubscriptionModal
        isOpen={isPlatformModalOpen}
        onOpenChange={setIsPlatformModalOpen}
        currentTier={currentTier}
        onTierSelect={(tierCode) => {
          const formatted = tierCode.charAt(0).toUpperCase() + tierCode.slice(1).toLowerCase();
          setCurrentTier(formatted);
        }}
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
                  <option value="monthly">Monthly</option>
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
                <Label>Machine Seats</Label>
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
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsAddDealOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleAddDealSubmit}>Save Deal</Button>
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
