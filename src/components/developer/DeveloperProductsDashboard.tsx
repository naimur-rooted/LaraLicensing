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

export interface ProductDealItem {
  ProductDealId?: number;
  dealName: string;
  dealCode: string;
  billingInterval: string;
  priceCents: number;
  allowedMachineSeats: number;
  allowedDomains: number;
  isDecoyTarget: boolean;
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

const INITIAL_PRODUCTS: DeveloperProductItem[] = [
  {
    ProductId: 1,
    productSlug: "elementor-assistant",
    productName: "Elementor Assistant Pro",
    category: "WordPressPlugin",
    description: "Autonomous styling and widget generation engine for Elementor.",
    ed25519PublicKey: "MCowBQYDK2VwAyEA9gM2jFq1n3i6jGk8n0y4q7w9e2r5t8y1u4i7o0p3a6s=",
    checkIntervalDays: 7,
    deals: [
      {
        dealName: "Single Site Monthly",
        dealCode: "ELM_1M_SINGLE",
        billingInterval: "monthly",
        priceCents: 999,
        allowedMachineSeats: 1,
        allowedDomains: 1,
        isDecoyTarget: false,
      },
      {
        dealName: "3 Sites Annual",
        dealCode: "ELM_1Y_THREE",
        billingInterval: "yearly",
        priceCents: 4999,
        allowedMachineSeats: 3,
        allowedDomains: 3,
        isDecoyTarget: false,
      },
      {
        dealName: "Unlimited Agency (2 Years + 3 Mo)",
        dealCode: "ELM_2Y_UNLIMITED",
        billingInterval: "biennial",
        priceCents: 8900,
        allowedMachineSeats: 10,
        allowedDomains: 10,
        isDecoyTarget: true,
      },
    ],
  },
];

export function DeveloperProductsDashboard() {
  const [products, setProducts] = useState<DeveloperProductItem[]>(INITIAL_PRODUCTS);
  const [selectedProduct, setSelectedProduct] = useState<DeveloperProductItem | null>(null);
  const [isBlueprintOpen, setIsBlueprintOpen] = useState(false);
  const [copied, setCopied] = useState(false);

  // Platform Subscription State
  const [currentTier, setCurrentTier] = useState("Pro");
  const [isPlatformModalOpen, setIsPlatformModalOpen] = useState(false);

  // New Product Modal State
  const [isNewProductOpen, setIsNewProductOpen] = useState(false);

  // Add Deal Modal State
  const [isAddDealOpen, setIsAddDealOpen] = useState(false);
  const [targetProduct, setTargetProduct] = useState<DeveloperProductItem | null>(null);
  const [dealName, setDealName] = useState("");
  const [dealCode, setDealCode] = useState("");
  const [dealInterval, setDealInterval] = useState("biennial");
  const [dealPrice, setDealPrice] = useState("69");
  const [dealSeats, setDealSeats] = useState("5");
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

  const handleAddDealSubmit = () => {
    if (!targetProduct || !dealName.trim()) return;
    const newDeal: ProductDealItem = {
      dealName,
      dealCode: dealCode.toUpperCase().trim() || `DEAL_${Date.now()}`,
      billingInterval: dealInterval,
      priceCents: Math.round(parseFloat(dealPrice || "0") * 100),
      allowedMachineSeats: parseInt(dealSeats, 10) || 1,
      allowedDomains: 1,
      isDecoyTarget: isDecoy,
    };
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
  };

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
              <Badge variant="outline" className="text-xs font-medium text-emerald-500 border-emerald-500/30">
                {currentTier} Tier Active
              </Badge>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setIsPlatformModalOpen(true)}
                className="h-6 px-2 text-xs text-primary hover:text-primary/80 gap-1"
              >
                <span>Upgrade / Change Plan</span>
                <ArrowUpRight className="size-3" />
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              Capacity: {products.length} / 15 Products • Daily Requests: 142 / 1,000 used today
            </p>
          </div>
        </div>
        <Button onClick={() => setIsNewProductOpen(true)} className="gap-1.5 self-start sm:self-auto">
          <Plus className="size-4" />
          Add New Product
        </Button>
      </div>

      {/* Product Cards Grid */}
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
                      <span className="ml-1 text-[10px] text-muted-foreground/80">({deal.allowedMachineSeats} seats)</span>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>

            <CardFooter className="flex items-center justify-between border-t bg-muted/20 pt-3">
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
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsAddDealOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleAddDealSubmit}>Save Deal</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
