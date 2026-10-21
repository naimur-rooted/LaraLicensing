import React, { useState } from "react";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Plus, Trash2, Box, Sparkles, CheckCircle2 } from "lucide-react";
import type { DeveloperProductItem, ProductDealItem } from "./DeveloperProductsDashboard";

interface CreateProductDialogProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  onProductCreated: (product: DeveloperProductItem) => void;
}

export function CreateProductDialog({ isOpen, onOpenChange, onProductCreated }: CreateProductDialogProps) {
  const [productName, setProductName] = useState("");
  const [category, setCategory] = useState("ChromeExtension");
  const [customCategory, setCustomCategory] = useState("");
  const [description, setDescription] = useState("");
  const [checkIntervalDays, setCheckIntervalDays] = useState("7");

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
    },
    {
      dealName: "Annual Pro (3 Sites)",
      dealCode: "ANNUAL_3S",
      billingInterval: "yearly",
      priceCents: 4900,
      allowedMachineSeats: 3,
      allowedDomains: 3,
      isDecoyTarget: false,
    },
    {
      dealName: "Unlimited Agency (2 Years + 3 Mo. Free)",
      dealCode: "BIENNIAL_DECOY",
      billingInterval: "biennial",
      priceCents: 6900,
      allowedMachineSeats: 10,
      allowedDomains: 10,
      isDecoyTarget: true,
    },
  ]);

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

    const newProduct: DeveloperProductItem = {
      ProductId: Date.now(),
      productSlug: cleanSlug,
      productName: productName.trim(),
      category: finalCategory,
      description: description.trim() || undefined,
      checkIntervalDays: parseInt(checkIntervalDays, 10) || 7,
      ed25519PublicKey: "MCowBQYDK2VwAyEA" + Math.random().toString(36).substring(2, 15) + "=",
      deals,
    };

    // Provision real SQLite shard and deals on the backend
    try {
      await fetch("http://127.0.0.1:8000/api/v1/developer/products", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          productSlug: cleanSlug,
          productName: productName.trim(),
          category: finalCategory,
          description: description.trim() || undefined,
          checkIntervalDays: parseInt(checkIntervalDays, 10) || 7,
          deals: deals.map((d) => ({
            dealName: d.dealName,
            dealCode: d.dealCode,
            billingInterval: d.billingInterval,
            priceCents: d.priceCents,
            allowedMachineSeats: d.allowedMachineSeats,
            allowedDomains: d.allowedDomains,
            isDecoyTarget: d.isDecoyTarget,
          })),
        }),
      });
    } catch {
      // Local fallback
    }

    onProductCreated(newProduct);
    onOpenChange(false);
    setProductName("");
    setCustomCategory("");
    setDescription("");
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
                  onChange={(e) => setCategory(e.target.value)}
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

            <div className="space-y-1.5">
              <Label htmlFor="description">Short Description (Optional)</Label>
              <Input
                id="description"
                placeholder="Brief summary of your software features"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
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
              <Button type="button" variant="outline" size="sm" onClick={handleAddDealRow} className="h-7 text-xs gap-1">
                <Plus className="size-3" /> Add Deal
              </Button>
            </div>

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
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
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
                      <Label className="text-[10px] text-muted-foreground">Machine Seats</Label>
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
                </div>
              ))}
            </div>
          </div>
        </div>

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
