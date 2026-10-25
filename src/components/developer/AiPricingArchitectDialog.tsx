import React, { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import {
  Sparkles,
  Bot,
  CheckCircle2,
  Check,
  Loader2,
  ArrowRight,
  TrendingUp,
} from "lucide-react";
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

interface AiPricingArchitectDialogProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  product: DeveloperProductItem | null;
  onDealsApplied: (updatedProduct: DeveloperProductItem) => void;
}

export function AiPricingArchitectDialog({
  isOpen,
  onOpenChange,
  product,
  onDealsApplied,
}: AiPricingArchitectDialogProps) {
  const [prompt, setPrompt] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [isApplying, setIsApplying] = useState(false);
  const [explanation, setExplanation] = useState<string | null>(null);
  const [suggestedDeals, setSuggestedDeals] = useState<ProductDealItem[] | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [appliedSuccess, setAppliedSuccess] = useState(false);

  if (!product) return null;

  const handleGenerate = async (customPrompt?: string) => {
    const finalPrompt = customPrompt || prompt || "Design high-converting VPN-style decoy pricing deals tailored to this software category.";
    setIsLoading(true);
    setErrorMessage(null);
    setAppliedSuccess(false);

    try {
      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/ai/design-pricing`, {
        method: "POST",
        headers: getDeveloperHeaders(),
        body: JSON.stringify({
          productSlug: product.productSlug,
          userPrompt: finalPrompt,
        }),
      });

      const data = await res.json();
      if (!res.ok || !data.IsSuccess || !data.Data) {
        throw new Error(data.Message || "AI Architect generation failed.");
      }

      setExplanation(data.Data.explanation || null);
      setSuggestedDeals(data.Data.deals || []);
    } catch (err: any) {
      setErrorMessage(err.message || "Failed to connect to AI Architect.");
    } finally {
      setIsLoading(false);
    }
  };

  const handleApplyDeals = async () => {
    if (!suggestedDeals || suggestedDeals.length === 0) return;
    setIsApplying(true);
    setErrorMessage(null);

    try {
      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/products/${product.productSlug}/apply-ai-deals`, {
        method: "POST",
        headers: getDeveloperHeaders(),
        body: JSON.stringify({ deals: suggestedDeals }),
      });

      const data = await res.json();
      if (!res.ok || !data.IsSuccess) {
        throw new Error(data.Message || "Failed to apply deals.");
      }

      setAppliedSuccess(true);
      if (data.Data) {
        onDealsApplied(data.Data);
      }
      setTimeout(() => {
        onOpenChange(false);
      }, 1500);
    } catch (err: any) {
      setErrorMessage(err.message || "Failed to apply deals to product.");
    } finally {
      setIsApplying(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <Sparkles className="size-4 text-amber-500" />
            </div>
            <div>
              <DialogTitle className="text-lg font-bold flex items-center gap-2">
                Grounded AI Pricing Architect
                <Badge variant="outline" className="text-[10px] uppercase font-mono text-emerald-500 border-emerald-500/30">
                  Google Gemini Live
                </Badge>
              </DialogTitle>
              <DialogDescription className="text-xs">
                Design custom pricing tiers and feature perks grounded in LaraLicensing's Decoy architecture.
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        {/* Product Identity Banner */}
        <div className="flex items-center justify-between rounded-lg border bg-muted/40 p-3 text-xs">
          <div>
            <span className="font-semibold text-foreground">{product.productName}</span>
            <span className="text-muted-foreground ml-2 font-mono">({product.productSlug})</span>
          </div>
          <Badge variant="secondary" className="text-[10px]">
            {product.category}
          </Badge>
        </div>

        {/* Input & Quick Chips */}
        <div className="space-y-2">
          <label className="text-xs font-semibold text-foreground">
            Describe your monetization goals or target limits:
          </label>
          <Textarea
            value={prompt}
            onChange={(e) => setPrompt(e.target.value)}
            placeholder={`e.g. "I want a monthly plan for $9.99 and an irresistible 2-year deal for $69 with high usage limits and bonus months..."`}
            className="text-xs h-20 resize-none"
          />

          {/* Quick Starter Chips */}
          <div className="flex flex-wrap items-center gap-2 pt-1">
            <span className="text-[11px] text-muted-foreground flex items-center gap-1">
              <TrendingUp className="size-3 text-primary" /> Suggestions:
            </span>
            <button
              type="button"
              onClick={() => {
                const text = "Design high-converting VPN-style decoy pricing with 75% savings on 2-year deal.";
                setPrompt(text);
                handleGenerate(text);
              }}
              className="text-[11px] bg-secondary hover:bg-secondary/80 text-foreground px-2.5 py-1 rounded-md transition-colors"
            >
              🚀 3-Tier Decoy Strategy
            </button>
            <button
              type="button"
              onClick={() => {
                const text = "Create high-volume agency tiers with multi-profile and bulk data export perks.";
                setPrompt(text);
                handleGenerate(text);
              }}
              className="text-[11px] bg-secondary hover:bg-secondary/80 text-foreground px-2.5 py-1 rounded-md transition-colors"
            >
              🏢 Agency / Bulk Quotas
            </button>
            <button
              type="button"
              onClick={() => {
                const text = "Include a high-value Lifetime Deal (LTD) option alongside monthly subscription.";
                setPrompt(text);
                handleGenerate(text);
              }}
              className="text-[11px] bg-secondary hover:bg-secondary/80 text-foreground px-2.5 py-1 rounded-md transition-colors"
            >
              💎 Lifetime Deal (LTD) Anchor
            </button>
          </div>
        </div>

        {/* Error Banner */}
        {errorMessage && (
          <div className="rounded-lg bg-destructive/10 border border-destructive/30 p-3 text-xs text-destructive">
            {errorMessage}
          </div>
        )}

        {/* Loading State */}
        {isLoading && (
          <div className="flex flex-col items-center justify-center p-8 space-y-3">
            <Loader2 className="size-8 animate-spin text-primary" />
            <p className="text-xs text-muted-foreground font-medium animate-pulse">
              Architecting tailored pricing deals via Gemini AI...
            </p>
          </div>
        )}

        {/* AI Explanation & Generated Deals Preview */}
        {!isLoading && suggestedDeals && suggestedDeals.length > 0 && (
          <div className="space-y-3 pt-2">
            {explanation && (
              <div className="rounded-lg border border-primary/20 bg-primary/5 p-3 text-xs text-foreground/90">
                <div className="flex items-center gap-1.5 font-semibold text-primary mb-1">
                  <Bot className="size-3.5" />
                  Architect Rationale:
                </div>
                <p className="text-[11px] leading-relaxed text-muted-foreground">{explanation}</p>
              </div>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
              {suggestedDeals.map((deal, idx) => {
                const isDecoy = deal.isDecoyTarget;
                return (
                  <div
                    key={idx}
                    className={`relative rounded-xl border p-3.5 flex flex-col justify-between ${
                      isDecoy
                        ? "border-emerald-500/60 bg-emerald-500/5 shadow-sm"
                        : "border-border bg-card/60"
                    }`}
                  >
                    {isDecoy && (
                      <span className="absolute -top-2.5 right-3 bg-emerald-600 text-white text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">
                        ⭐ Best Value
                      </span>
                    )}
                    <div>
                      <h4 className="text-xs font-bold text-foreground">{deal.dealName}</h4>
                      <p className="text-[10px] font-mono text-muted-foreground mt-0.5">{deal.dealCode}</p>
                      <div className="mt-2 mb-3">
                        <span className="text-lg font-extrabold text-foreground">
                          ${(deal.priceCents / 100).toFixed(2)}
                        </span>
                        <span className="text-[11px] text-muted-foreground ml-1">/ {deal.billingInterval}</span>
                      </div>

                      {/* Feature Perks */}
                      <ul className="space-y-1.5 text-[11px] text-muted-foreground">
                        {(deal.features || []).map((perk, pIdx) => (
                          <li key={pIdx} className="flex items-start gap-1.5 leading-snug">
                            <Check className="size-3 text-emerald-500 shrink-0 mt-0.5" />
                            <span>{perk}</span>
                          </li>
                        ))}
                      </ul>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* Success Banner */}
        {appliedSuccess && (
          <div className="flex items-center gap-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 p-3 text-xs text-emerald-400">
            <CheckCircle2 className="size-4 shrink-0" />
            <span>Success! Deals saved to database and live customer storefront at :5050 updated.</span>
          </div>
        )}

        <DialogFooter className="gap-2 sm:gap-0">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => onOpenChange(false)}
            className="text-xs"
          >
            Close
          </Button>
          {!suggestedDeals ? (
            <Button
              type="button"
              size="sm"
              disabled={isLoading}
              onClick={() => handleGenerate()}
              className="text-xs gap-1.5 bg-primary hover:bg-primary/90"
            >
              {isLoading ? <Loader2 className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5 text-amber-500" />}
              Generate Pricing Strategy
            </Button>
          ) : (
            <Button
              type="button"
              size="sm"
              disabled={isApplying || appliedSuccess}
              onClick={handleApplyDeals}
              className="text-xs gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold"
            >
              {isApplying ? (
                <Loader2 className="size-3.5 animate-spin" />
              ) : (
                <CheckCircle2 className="size-3.5" />
              )}
              {appliedSuccess ? "Applied Successfully!" : "Apply Deals to Live Storefront"}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
