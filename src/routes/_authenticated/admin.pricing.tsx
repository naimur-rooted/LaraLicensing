import { createFileRoute } from "@tanstack/react-router";
import { useState, useEffect } from "react";
import { PageHeader } from "../../components/shell/PageHeader";
import { RoutePending, RouteErrorState } from "../../components/shell/RouteFallbacks";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from "@/components/ui/dialog";
import { getApiBaseUrl } from "@/lib/lara-api-client";
import { DollarSign, Laptop, Sparkles, Check, Edit2 } from "lucide-react";

export const Route = createFileRoute("/_authenticated/admin/pricing")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Pricing & Deals Management | Licensing Portal" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminPricingPage,
});

interface PricingPlan {
  PricingPlanId: number;
  planCode: string;
  planName: string;
  billingInterval: string;
  retailPriceCents: number;
  discountedPriceCents: number;
  allowedMachineSlots: number;
  isBestValue: boolean | number;
  isActive: boolean | number;
}

function AdminPricingPage() {
  const [plans, setPlans] = useState<PricingPlan[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingPlan, setEditingPlan] = useState<PricingPlan | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const fetchPlans = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const baseUrl = getApiBaseUrl();
      const token = localStorage.getItem("lara_access_token") || localStorage.getItem("token");
      const resp = await fetch(`${baseUrl}/Admin/PricingPlans`, {
        headers: {
          "Accept": "application/json",
          ...(token ? { "Authorization": `Bearer ${token}` } : {}),
        },
      });

      if (!resp.ok) {
        throw new Error(`Failed to load plans: HTTP ${resp.status}`);
      }

      const json = await resp.json();
      setPlans(json.Results?.Items || []);
    } catch (err: any) {
      setError(err?.message || "Failed to load pricing deals.");
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchPlans();
  }, []);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingPlan) return;

    setIsSaving(true);
    try {
      const baseUrl = getApiBaseUrl();
      const token = localStorage.getItem("lara_access_token") || localStorage.getItem("token");
      const resp = await fetch(`${baseUrl}/Admin/PricingPlans/${editingPlan.PricingPlanId}`, {
        method: "PATCH",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          ...(token ? { "Authorization": `Bearer ${token}` } : {}),
        },
        body: JSON.stringify({
          planName: editingPlan.planName,
          retailPriceCents: Number(editingPlan.retailPriceCents),
          discountedPriceCents: Number(editingPlan.discountedPriceCents),
          allowedMachineSlots: Number(editingPlan.allowedMachineSlots),
          isBestValue: Boolean(editingPlan.isBestValue),
          isActive: Boolean(editingPlan.isActive),
        }),
      });

      if (!resp.ok) {
        throw new Error(`Save failed: HTTP ${resp.status}`);
      }

      setEditingPlan(null);
      await fetchPlans();
    } catch (err: any) {
      alert(`Error updating plan: ${err.message}`);
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return <RoutePending title="Pricing & Deals Management" description="Loading platform pricing plans..." />;
  }

  if (error) {
    return <RouteErrorState title="Pricing & Deals Management" error={new Error(error)} reset={fetchPlans} />;
  }

  return (
    <>
      <PageHeader
        title="Pricing Deals & Plans"
        breadcrumbs={[{ label: "Admin", to: "/admin" }, { label: "Pricing" }]}
        description="Manage commercial pricing anchors, promotional discounts, and machine seat quotas for developers."
      />

      <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
        {plans.map((p) => {
          const retail = (p.retailPriceCents / 100).toFixed(2);
          const discounted = (p.discountedPriceCents / 100).toFixed(2);
          const hasDiscount = p.retailPriceCents > p.discountedPriceCents;
          const bestValue = Boolean(p.isBestValue);
          const active = Boolean(p.isActive);

          return (
            <div
              key={p.PricingPlanId}
              className={`relative flex flex-col justify-between rounded-xl border bg-card p-6 shadow-sm transition-all hover:shadow-md ${
                bestValue ? "border-emerald-500 ring-1 ring-emerald-500/30" : "border-border"
              }`}
            >
              {bestValue && (
                <div className="absolute -top-3 right-4">
                  <Badge className="bg-emerald-600 text-white gap-1 py-0.5 px-2.5">
                    <Sparkles className="w-3.5 h-3.5" /> Best Value
                  </Badge>
                </div>
              )}

              <div>
                <div className="flex items-center justify-between">
                  <span className="font-mono text-xs font-semibold uppercase text-muted-foreground">
                    {p.planCode}
                  </span>
                  <Badge variant={active ? "default" : "secondary"}>
                    {active ? "Active" : "Archived"}
                  </Badge>
                </div>

                <h3 className="text-xl font-bold mt-2 text-foreground">{p.planName}</h3>
                <p className="text-xs text-muted-foreground capitalize mt-0.5">
                  Interval: <strong className="text-foreground">{p.billingInterval}</strong>
                </p>

                <div className="mt-4 flex items-baseline gap-2">
                  <span className="text-3xl font-extrabold tracking-tight text-foreground">
                    ${discounted}
                  </span>
                  {hasDiscount && (
                    <span className="text-sm font-medium line-through text-muted-foreground">
                      ${retail}
                    </span>
                  )}
                  <span className="text-xs text-muted-foreground">/ {p.billingInterval}</span>
                </div>

                <div className="mt-6 space-y-2 border-t pt-4 border-border text-sm">
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <Laptop className="w-4 h-4 text-emerald-600" />
                    <span>
                      <strong className="text-foreground">{p.allowedMachineSlots}</strong> Machine Activation Slots
                    </span>
                  </div>
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <Check className="w-4 h-4 text-emerald-600" />
                    <span>Ed25519 Cryptographic Verification</span>
                  </div>
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <Check className="w-4 h-4 text-emerald-600" />
                    <span>Offline License Token Sync</span>
                  </div>
                </div>
              </div>

              <div className="mt-8 pt-4 border-t border-border">
                <Button
                  variant="outline"
                  className="w-full gap-2 text-xs font-semibold"
                  onClick={() => setEditingPlan({ ...p })}
                >
                  <Edit2 className="w-3.5 h-3.5" /> Modify Plan Deal
                </Button>
              </div>
            </div>
          );
        })}
      </div>

      {editingPlan && (
        <Dialog open={true} onOpenChange={() => setEditingPlan(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Edit Pricing Deal: {editingPlan.planCode}</DialogTitle>
              <DialogDescription>
                Update pricing rates and hardware activation seats in real time.
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handleSave} className="space-y-4 py-2">
              <div>
                <Label htmlFor="planName">Plan Display Name</Label>
                <Input
                  id="planName"
                  value={editingPlan.planName}
                  onChange={(e) => setEditingPlan({ ...editingPlan, planName: e.target.value })}
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="retailPrice">Retail Price ($)</Label>
                  <div className="relative mt-1">
                    <DollarSign className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="retailPrice"
                      type="number"
                      step="0.01"
                      className="pl-8 font-mono"
                      value={(editingPlan.retailPriceCents / 100).toFixed(2)}
                      onChange={(e) => {
                        const val = Math.round(parseFloat(e.target.value || "0") * 100);
                        setEditingPlan({ ...editingPlan, retailPriceCents: val });
                      }}
                      required
                    />
                  </div>
                </div>

                <div>
                  <Label htmlFor="discountedPrice">Deal Price ($)</Label>
                  <div className="relative mt-1">
                    <DollarSign className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="discountedPrice"
                      type="number"
                      step="0.01"
                      className="pl-8 font-mono"
                      value={(editingPlan.discountedPriceCents / 100).toFixed(2)}
                      onChange={(e) => {
                        const val = Math.round(parseFloat(e.target.value || "0") * 100);
                        setEditingPlan({ ...editingPlan, discountedPriceCents: val });
                      }}
                      required
                    />
                  </div>
                </div>
              </div>

              <div>
                <Label htmlFor="machineSlots">Allowed Workstation / Machine Slots</Label>
                <Input
                  id="machineSlots"
                  type="number"
                  min="1"
                  max="100"
                  className="font-mono mt-1"
                  value={editingPlan.allowedMachineSlots}
                  onChange={(e) =>
                    setEditingPlan({ ...editingPlan, allowedMachineSlots: parseInt(e.target.value || "1", 10) })
                  }
                  required
                />
              </div>

              <div className="flex items-center justify-between border rounded-lg p-3">
                <div className="space-y-0.5">
                  <Label>Best Value Highlight</Label>
                  <p className="text-xs text-muted-foreground">Presents green highlight ribbon on cards</p>
                </div>
                <Switch
                  checked={Boolean(editingPlan.isBestValue)}
                  onCheckedChange={(val) => setEditingPlan({ ...editingPlan, isBestValue: val })}
                />
              </div>

              <div className="flex items-center justify-between border rounded-lg p-3">
                <div className="space-y-0.5">
                  <Label>Active Status</Label>
                  <p className="text-xs text-muted-foreground">Available for developer checkouts</p>
                </div>
                <Switch
                  checked={Boolean(editingPlan.isActive)}
                  onCheckedChange={(val) => setEditingPlan({ ...editingPlan, isActive: val })}
                />
              </div>

              <DialogFooter className="mt-6">
                <Button type="button" variant="outline" onClick={() => setEditingPlan(null)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={isSaving}>
                  {isSaving ? "Saving..." : "Save Changes"}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      )}
    </>
  );
}
