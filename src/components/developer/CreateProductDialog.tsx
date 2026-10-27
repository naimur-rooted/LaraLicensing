import React, { useState } from "react";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Plus, Trash2, Box, Sparkles, CheckCircle2, Loader2, Bot } from "lucide-react";
import type { DeveloperProductItem, ProductDealItem } from "./DeveloperProductsDashboard";
import { getApiBaseUrl } from "@/lib/lara-api-client";
import { getLaraAccessToken } from "@/lib/lara-api-session";

function getDeveloperHeaders(): HeadersInit {
  const token = getLaraAccessToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  return headers;
}

interface CreateProductDialogProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  onProductCreated: (product: DeveloperProductItem) => void;
}

export function CreateProductDialog({ isOpen, onOpenChange, onProductCreated }: CreateProductDialogProps) {
  const [productName, setProductName] = useState("");
  const [category, setCategory] = useState("ChromeExtension");
  const [customCategory, setCustomCategory] = useState("");
  const [seatUnitName, setSeatUnitName] = useState("Chrome Profiles");
  const [description, setDescription] = useState("");
  const [checkIntervalDays, setCheckIntervalDays] = useState("7");
  const [isAiGenerating, setIsAiGenerating] = useState(false);
  const [isAiPanelOpen, setIsAiPanelOpen] = useState(false);
  const [aiStrategy, setAiStrategy] = useState<"decoy-matrix" | "daily-pass" | "appsumo-ltd" | "b2b-agency" | "low-barrier">("decoy-matrix");
  const [aiKillerFeatures, setAiKillerFeatures] = useState("");
  const [aiTargetPrice, setAiTargetPrice] = useState("");
  const [submitError, setSubmitError] = useState<string | null>(null);

  const handleCategoryChange = (val: string) => {
    setCategory(val);
    if (val === "ChromeExtension") setSeatUnitName("Chrome Profiles");
    else if (val === "WordPressPlugin") setSeatUnitName("Domain Sites");
    else if (val === "DesktopApp") setSeatUnitName("Machine Seats");
    else if (val === "CliScript") setSeatUnitName("Runner / Worker Nodes");
    else setSeatUnitName("Allowed Units");
  };

  // Default 3 VPN-Style Decoy Deals matching meeting discussion
  const [deals, setDeals] = useState<ProductDealItem[]>([
    {
      dealName: "Single User Monthly",
      dealCode: "MONTHLY_1S",
      billingInterval: "monthly",
      priceCents: 999,
      allowedMachineSeats: 1,
      allowedDomains: 1,
      isDecoyTarget: false,
      features: ["Standard software access", "Automatic updates", "Standard email support"],
      featuresText: "Standard software access\nAutomatic updates\nStandard email support",
    },
    {
      dealName: "Annual Pro (3 Sites)",
      dealCode: "ANNUAL_3S",
      billingInterval: "yearly",
      priceCents: 4900,
      allowedMachineSeats: 3,
      allowedDomains: 3,
      isDecoyTarget: false,
      features: ["3 User activations", "Priority updates & patches", "Direct support channel"],
      featuresText: "3 User activations\nPriority updates & patches\nDirect support channel",
    },
    {
      dealName: "Unlimited Agency (2 Years + 3 Mo. Free)",
      dealCode: "BIENNIAL_DECOY",
      billingInterval: "biennial",
      priceCents: 6900,
      allowedMachineSeats: 10,
      allowedDomains: 10,
      isDecoyTarget: true,
      features: ["10 User activations", "All premium add-on modules", "27 months total access (+3 mo free)", "VIP Priority 24/7 support"],
      featuresText: "10 User activations\nAll premium add-on modules\n27 months total access (+3 mo free)\nVIP Priority 24/7 support",
    },
  ]);

  const handleAiAutoFill = async (useCustomContext = false) => {
    setIsAiGenerating(true);
    try {
      const name = productName.trim() || "My Software";
      const cleanSlug = name.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
      
      let promptText = `Generate high-converting pricing deals with realistic feature perks specifically for a ${category} named ${name}.`;
      if (useCustomContext) {
        const strategyNames = {
          "decoy-matrix": "Three-Tier Decoy Matrix (NordVPN style: Monthly Anchor, Annual Pro, 2-Year Best Value Target)",
          "daily-pass": "Daily & Micro Passes (24-Hour Day Pass, 7-Day Weekly Pass, Monthly Access)",
          "appsumo-ltd": "AppSumo-Style Lifetime Deals (Single Code LTD, Double Code, Unlimited Agency Code)",
          "b2b-agency": "High-Ticket B2B SaaS (Starter, Professional Team, Unlimited Agency)",
          "low-barrier": "Low-Barrier Impulse Utility ($9 - $29 impulse buy with high volume)",
        };
        promptText = `Strategy Model: ${strategyNames[aiStrategy]}. Killer Features & Value Proposition: ${aiKillerFeatures || 'Standard category capabilities'}. Target Price Range: ${aiTargetPrice || 'Market competitive'}. Output packages with software-specific feature perks that directly deliver on this value proposition and output the detected seatUnitName (e.g. Discord Servers, Profiles, Stores, Nodes).`;
      }

      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/ai/design-pricing`, {
        method: "POST",
        headers: getDeveloperHeaders(),
        body: JSON.stringify({
          productSlug: cleanSlug || "draft-product",
          productName: name,
          category: category === "CustomOther" ? (customCategory.trim() || "CustomSoftware") : category,
          description: description,
          userPrompt: promptText,
        }),
      });

      const data = await res.json();
      if (data.IsSuccess && data.Data && Array.isArray(data.Data.deals) && data.Data.deals.length > 0) {
        if (data.Data.seatUnitName) {
          setSeatUnitName(data.Data.seatUnitName);
        }
        const enriched = data.Data.deals.map((d: ProductDealItem) => ({
          ...d,
          featuresText: Array.isArray(d.features) ? d.features.join("\n") : (d.features || ""),
        }));
        setDeals(enriched);
        setIsAiPanelOpen(false);
      }
    } catch (e) {
      if (category === "ChromeExtension") {
        setDeals([
          { dealName: "Monthly Starter", dealCode: "MONTHLY_1P", billingInterval: "monthly", priceCents: 999, allowedMachineSeats: 1, allowedDomains: 1, isDecoyTarget: false, features: ["500 Verified Leads/month", "Basic CSV Export", "1 Chrome Profile License"], featuresText: "500 Verified Leads/month\nBasic CSV Export\n1 Chrome Profile License" },
          { dealName: "Annual Pro", dealCode: "ANNUAL_3P", billingInterval: "yearly", priceCents: 4900, allowedMachineSeats: 3, allowedDomains: 1, isDecoyTarget: false, features: ["2,500 Verified Leads/month", "Direct CRM & HubSpot Sync", "3 Chrome Profiles License"], featuresText: "2,500 Verified Leads/month\nDirect CRM & HubSpot Sync\n3 Chrome Profiles License" },
          { dealName: "Unlimited Agency (2 Years + 3 Mo. Free)", dealCode: "BIENNIAL_DECOY", billingInterval: "biennial", priceCents: 6900, allowedMachineSeats: 10, allowedDomains: 5, isDecoyTarget: true, features: ["10,000 Verified Leads/month", "Unlimited Chrome Profiles", "Advanced CRM Automation", "3 Months Free Bonus"], featuresText: "10,000 Verified Leads/month\nUnlimited Chrome Profiles\nAdvanced CRM Automation\n3 Months Free Bonus" },
        ]);
      }
    } finally {
      setIsAiGenerating(false);
    }
  };

  const handleDealChange = (index: number, field: keyof ProductDealItem, value: any) => {
    const updated = [...deals];
    updated[index] = { ...updated[index], [field]: value };
    setDeals(updated);
  };

  const handleAddDealRow = () => {
    setDeals([
      ...deals,
      {
        dealName: "Lifetime Deal (LTD)",
        dealCode: `LTD_${Date.now().toString().slice(-4)}`,
        billingInterval: "lifetime",
        priceCents: 9900,
        allowedMachineSeats: 5,
        allowedDomains: 5,
        isDecoyTarget: false,
        features: ["Lifetime license access", "All core features", "Community support"],
        featuresText: "Lifetime license access\nAll core features\nCommunity support",
      },
    ]);
  };

  const handleRemoveDealRow = (index: number) => {
    if (deals.length <= 1) return;
    setDeals(deals.filter((_, i) => i !== index));
  };

  const handleSubmit = async () => {
    if (!productName.trim()) return;
    const cleanSlug = productName.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
    const finalCategory =
      category === "CustomOther"
        ? customCategory.trim() || "CustomSoftware"
        : category;

    const processedDeals = deals.map((d) => {
      const feats = d.featuresText !== undefined
        ? d.featuresText.split("\n").map((f) => f.trim()).filter(Boolean)
        : (d.features || []);
      return {
        dealName: d.dealName,
        dealCode: d.dealCode,
        billingInterval: d.billingInterval,
        priceCents: d.priceCents,
        allowedMachineSeats: d.allowedMachineSeats,
        allowedDomains: d.allowedDomains,
        isDecoyTarget: d.isDecoyTarget,
        features: feats,
      };
    });

    setSubmitError(null);
    // Provision real SQLite shard and deals on the backend.
    // Do not fabricate a key locally and do not claim success on failure.
    // Only the server response may supply a public key, and only a
    // successful response closes the dialog and adds the product.
    let serverPublicKey: string | undefined;
    try {
      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products`, {
        method: "POST",
        headers: getDeveloperHeaders(),
        body: JSON.stringify({
          productSlug: cleanSlug,
          productName: productName.trim(),
          category: finalCategory,
          description: description.trim() || undefined,
          checkIntervalDays: parseInt(checkIntervalDays, 10) || 7,
          deals: processedDeals,
        }),
      });
      if (!res.ok) {
        setSubmitError("Product could not be created. Please try again.");
        return;
      }
      const payload = await res.json().catch(() => null);
      if (payload && payload.IsSuccess === false) {
        setSubmitError("Product could not be created. Please try again.");
        return;
      }
      if (payload && payload.Data && typeof payload.Data.ed25519PublicKey === "string") {
        serverPublicKey = payload.Data.ed25519PublicKey;
      }
    } catch {
      setSubmitError("Product could not be created. Please check your connection and try again.");
      return;
    }

    const newProduct: DeveloperProductItem = {
      ProductId: Date.now(),
      productSlug: cleanSlug,
      productName: productName.trim(),
      category: finalCategory,
      description: description.trim() || undefined,
      checkIntervalDays: parseInt(checkIntervalDays, 10) || 7,
      ed25519PublicKey: serverPublicKey,
      deals: processedDeals,
    };

    onProductCreated(newProduct);
    onOpenChange(false);
    setProductName("");
    setCustomCategory("");
    setDescription("");
    setSubmitError(null);
  };

  return (
    <Dialog open={isOpen} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2 text-primary font-semibold text-sm">
            <Box className="size-4" />
            <span>Developer Software Studio</span>
          </div>
          <DialogTitle className="text-xl">Register Product & Configure Pricing Deals</DialogTitle>
          <DialogDescription>
            Register your software product. LaraLicensing will provision an isolated SQLite shard database and configure your downstream client pricing tiers.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-5 py-2">
          {/* Step 1: Software Identity */}
          <div className="space-y-3 rounded-lg border bg-muted/20 p-3.5">
            <h4 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">1. Software Identity</h4>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="productName">Product Name *</Label>
                <Input
                  id="productName"
                  placeholder="e.g. LeadHunter Chrome Extension"
                  value={productName}
                  onChange={(e) => setProductName(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="category">Product Platform / Category</Label>
                <select
                  id="category"
                  value={category}
                  onChange={(e) => handleCategoryChange(e.target.value)}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option value="ChromeExtension">Chrome / Browser Extension</option>
                  <option value="WordPressPlugin">WordPress / WooCommerce Plugin</option>
                  <option value="DesktopApp">Desktop Software (Windows / macOS / Linux)</option>
                  <option value="CliScript">CLI Tool / Python Script / Automation Bot</option>
                  <option value="ElectronTauri">Electron / Tauri App</option>
                  <option value="IdeExtension">VS Code / JetBrains / IDE Extension</option>
                  <option value="DesignToolPlugin">Figma Plugin / Blender Addon</option>
                  <option value="MobileApp">Mobile App (iOS / Android / Flutter)</option>
                  <option value="ShopifyApp">Shopify / E-Commerce App</option>
                  <option value="WebService">Web API / SaaS Microservice</option>
                  <option value="CustomOther">Custom / Other Platform...</option>
                </select>
              </div>
            </div>

            {category === "CustomOther" && (
              <div className="space-y-1.5 rounded-md border border-primary/20 bg-primary/5 p-3">
                <Label htmlFor="customCategory" className="text-xs font-semibold text-primary">
                  Specify Your Custom Platform / Runtime
                </Label>
                <Input
                  id="customCategory"
                  placeholder="e.g. AutoCAD Plugin, Unity Asset, Trading Bot, Discord Bot"
                  value={customCategory}
                  onChange={(e) => setCustomCategory(e.target.value)}
                  className="bg-background"
                />
              </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="description">Short Description (Optional)</Label>
                <Input
                  id="description"
                  placeholder="Brief summary of your software features"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <Label htmlFor="seatUnitName">License Quota Unit / Metric</Label>
                  <span className="text-[10px] text-muted-foreground font-medium">What is 1 activation?</span>
                </div>
                <Input
                  id="seatUnitName"
                  placeholder="e.g. Chrome Profiles, Domain Sites, Discord Servers, Stores"
                  value={seatUnitName}
                  onChange={(e) => setSeatUnitName(e.target.value)}
                  className="bg-background"
                />
              </div>
            </div>
          </div>

          {/* Step 2: Consumer Deal Matrix */}
          <div className="space-y-3 rounded-lg border bg-muted/20 p-3.5">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                  2. Downstream Customer Deals & Machine Limits
                </h4>
                <p className="text-xs text-muted-foreground">
                  Configure the pricing packages your software users will buy (VPN-style Decoy Matrix).
                </p>
              </div>
              <div className="flex items-center gap-1.5">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={isAiGenerating}
                  onClick={() => setIsAiPanelOpen(!isAiPanelOpen)}
                  className={`h-7 text-xs gap-1 transition-colors ${
                    isAiPanelOpen 
                      ? "bg-primary text-primary-foreground border-primary" 
                      : "border-primary/40 text-primary hover:bg-primary/10"
                  }`}
                >
                  {isAiGenerating ? <Loader2 className="size-3 animate-spin" /> : <Sparkles className="size-3 text-amber-500" />}
                  {isAiGenerating ? "Architecting..." : isAiPanelOpen ? "Close AI Studio" : "✨ Auto-Fill with AI"}
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={handleAddDealRow} className="h-7 text-xs gap-1">
                  <Plus className="size-3" /> Add Deal
                </Button>
              </div>
            </div>

            {/* Expandable Grounded AI Pricing Strategy Studio */}
            {isAiPanelOpen && (
              <div className="rounded-lg border-2 border-primary/30 bg-primary/5 p-3.5 space-y-3 transition-all animate-in fade-in-50">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-primary flex items-center gap-1.5">
                    <Bot className="size-4" /> AI Monetization & Pricing Architect
                  </span>
                  <Badge variant="secondary" className="text-[10px] bg-primary/10 text-primary border-primary/20">
                    Gemini Intelligence
                  </Badge>
                </div>

                <div className="space-y-1">
                  <Label className="text-[11px] font-medium text-foreground">Select Monetization Strategy</Label>
                  <div className="grid grid-cols-2 sm:grid-cols-5 gap-1.5">
                    {[
                      { id: "decoy-matrix", label: "🛡️ 2-Yr Decoy", desc: "NordVPN style" },
                      { id: "daily-pass", label: "⚡ Daily Pass", desc: "24h / Weekly passes" },
                      { id: "appsumo-ltd", label: "⚡ AppSumo LTD", desc: "Lifetime codes" },
                      { id: "b2b-agency", label: "💼 B2B Agency", desc: "Team growth" },
                      { id: "low-barrier", label: "🚀 Low Impulse", desc: "$9 - $29 volume" },
                    ].map((s) => (
                      <button
                        key={s.id}
                        type="button"
                        onClick={() => setAiStrategy(s.id as any)}
                        className={`p-1.5 rounded-md text-left border transition-all ${
                          aiStrategy === s.id
                            ? "border-primary bg-primary/15 text-primary font-semibold shadow-xs"
                            : "border-border/60 bg-background/50 hover:bg-background text-muted-foreground"
                        }`}
                      >
                        <div className="text-[11px]">{s.label}</div>
                        <div className="text-[9px] opacity-70">{s.desc}</div>
                      </button>
                    ))}
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                  <div className="sm:col-span-2 space-y-1">
                    <Label className="text-[11px] font-medium text-foreground">
                      Killer Features & Value Proposition (What did you build?)
                    </Label>
                    <Input
                      placeholder={
                        category === "ChromeExtension"
                          ? "e.g. 10k LinkedIn scrapes, CRM auto-sync, proxy rotation"
                          : category === "WordPressPlugin"
                          ? "e.g. WooCommerce checkout booster, cart recovery, priority updates"
                          : "e.g. 4K GPU video upscaling, offline mode, multi-track audio"
                      }
                      value={aiKillerFeatures}
                      onChange={(e) => setAiKillerFeatures(e.target.value)}
                      className="h-8 text-xs bg-background"
                    />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-[11px] font-medium text-foreground">Target Price Range</Label>
                    <Input
                      placeholder="e.g. $29 - $89"
                      value={aiTargetPrice}
                      onChange={(e) => setAiTargetPrice(e.target.value)}
                      className="h-8 text-xs bg-background"
                    />
                  </div>
                </div>

                <div className="flex items-center justify-between pt-1 border-t border-primary/20">
                  <button
                    type="button"
                    onClick={() => handleAiAutoFill(false)}
                    className="text-[11px] text-muted-foreground hover:text-foreground underline"
                  >
                    Or quick 1-click auto-fill without prompts
                  </button>
                  <Button
                    type="button"
                    size="sm"
                    disabled={isAiGenerating}
                    onClick={() => handleAiAutoFill(true)}
                    className="h-7 text-xs gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90"
                  >
                    {isAiGenerating ? <Loader2 className="size-3 animate-spin" /> : <Sparkles className="size-3" />}
                    {isAiGenerating ? "Architecting Deals..." : "Generate Custom Deals"}
                  </Button>
                </div>
              </div>
            )}

            <div className="space-y-2.5">
              {deals.map((deal, index) => (
                <div
                  key={index}
                  className={`relative rounded-md border p-3 text-xs space-y-2 ${
                    deal.isDecoyTarget ? "border-emerald-500/50 bg-emerald-500/5" : "bg-card"
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="font-semibold text-foreground flex items-center gap-1.5">
                      Deal #{index + 1}: {deal.dealName}
                      {deal.isDecoyTarget && (
                        <Badge className="bg-emerald-600 text-white text-[10px] h-4 px-1.5">BEST VALUE TARGET</Badge>
                      )}
                    </span>
                    {deals.length > 1 && (
                      <button
                        type="button"
                        onClick={() => handleRemoveDealRow(index)}
                        className="text-muted-foreground hover:text-destructive"
                      >
                        <Trash2 className="size-3.5" />
                      </button>
                    )}
                  </div>

                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div>
                      <Label className="text-[10px] text-muted-foreground">Deal Name</Label>
                      <Input
                        value={deal.dealName}
                        onChange={(e) => handleDealChange(index, "dealName", e.target.value)}
                        className="h-7 text-xs"
                      />
                    </div>
                    <div>
                      <Label className="text-[10px] text-muted-foreground">Billing Interval</Label>
                      <select
                        value={deal.billingInterval}
                        onChange={(e) => handleDealChange(index, "billingInterval", e.target.value)}
                        className="w-full h-7 rounded-md border border-input bg-background px-2 text-xs"
                      >
                        <option value="daily">Daily Pass (24h)</option>
                        <option value="weekly">Weekly (7 Days)</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly (3 Mo.)</option>
                        <option value="yearly">Yearly / Annual</option>
                        <option value="biennial">2-Year (Decoy)</option>
                        <option value="lifetime">Lifetime Deal</option>
                      </select>
                    </div>
                    <div>
                      <Label className="text-[10px] text-muted-foreground">Price ($ USD)</Label>
                      <Input
                        type="number"
                        value={(deal.priceCents / 100).toFixed(2)}
                        onChange={(e) =>
                          handleDealChange(index, "priceCents", Math.round(parseFloat(e.target.value || "0") * 100))
                        }
                        className="h-7 text-xs font-mono"
                      />
                    </div>
                    <div>
                      <Label className="text-[10px] text-muted-foreground truncate block" title={seatUnitName || "Machine Seats"}>
                        {seatUnitName || "Machine Seats"}
                      </Label>
                      <Input
                        type="number"
                        value={deal.allowedMachineSeats}
                        onChange={(e) =>
                          handleDealChange(index, "allowedMachineSeats", parseInt(e.target.value, 10) || 1)
                        }
                        className="h-7 text-xs font-mono"
                      />
                    </div>
                  </div>

                  <div className="flex items-center gap-2 pt-1">
                    <input
                      type="checkbox"
                      id={`decoy-${index}`}
                      checked={deal.isDecoyTarget}
                      onChange={(e) => handleDealChange(index, "isDecoyTarget", e.target.checked)}
                      className="size-3.5 rounded border-input"
                    />
                    <label htmlFor={`decoy-${index}`} className="text-[11px] text-muted-foreground cursor-pointer">
                      Highlight as Decoy Target (Recommended Deal with highest discount)
                    </label>
                  </div>

                  {/* Custom Feature Perks (One per line) */}
                  <div className="space-y-1 pt-1.5 border-t border-border/40 mt-1.5">
                    <div className="flex items-center justify-between">
                      <Label className="text-[10px] font-semibold text-foreground">
                        Included Features & Capabilities (one per line)
                      </Label>
                      <span className="text-[9px] text-muted-foreground">Displayed with checkmarks on customer storefront</span>
                    </div>
                    <textarea
                      rows={3}
                      placeholder={
                        category === "ChromeExtension"
                          ? "500 Verified Leads / month\nExport to CSV & HubSpot\n1 Chrome Profile License"
                          : category === "WordPressPlugin"
                          ? "1 Domain License\nWooCommerce Addon\n1 Year Priority Updates"
                          : "4K Export Resolution\nOffline Verification\nPriority Support"
                      }
                      value={deal.featuresText !== undefined ? deal.featuresText : (deal.features || []).join("\n")}
                      onChange={(e) => {
                        const raw = e.target.value;
                        const updated = [...deals];
                        updated[index] = {
                          ...updated[index],
                          featuresText: raw,
                          features: raw.split("\n").map((f) => f.trim()).filter(Boolean),
                        };
                        setDeals(updated);
                      }}
                      onKeyDown={(e) => {
                        if (e.key === "Enter") {
                          // Prevent parent dialog / form handlers from intercepting Enter
                          e.stopPropagation();
                        }
                      }}
                      className="w-full rounded-md border border-input bg-background p-2.5 text-xs font-mono min-h-[64px] resize-y leading-relaxed"
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {submitError ? (
          <p className="text-xs text-red-600 px-1">{submitError}</p>
        ) : null}
        <DialogFooter className="sm:justify-between pt-2">
          <span className="text-xs text-muted-foreground flex items-center gap-1">
            <CheckCircle2 className="size-3.5 text-emerald-500" />
            Automatic SQLite Shard Provisioning Included
          </span>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button onClick={handleSubmit} disabled={!productName.trim()}>
              Provision Product & Shard
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
