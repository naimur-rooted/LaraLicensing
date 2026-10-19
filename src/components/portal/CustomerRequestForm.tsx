import React, { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Send, CheckCircle2, AlertCircle } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { submitCustomerLicenseRequest } from "@/lib/lara-customer-request";

interface CustomerRequestFormProps {
  customerEmail: string;
  customerName: string;
  isOpen?: boolean;
  onOpenChange?: (open: boolean) => void;
  initialNotes?: string;
}

export function CustomerRequestForm({
  customerEmail,
  customerName,
  isOpen: controlledIsOpen,
  onOpenChange,
  initialNotes = "",
}: CustomerRequestFormProps) {
  const queryClient = useQueryClient();
  const [internalIsOpen, setInternalIsOpen] = useState(false);
  const isOpen = controlledIsOpen ?? internalIsOpen;
  const setIsOpen = (val: boolean) => {
    setInternalIsOpen(val);
    onOpenChange?.(val);
  };
  const [resellerId, setResellerId] = useState(1);
  const [categoryId, setCategoryId] = useState(3); // Monthly default
  const [tierId, setTierId] = useState(2); // Growth default
  const [notes, setNotes] = useState(initialNotes);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  React.useEffect(() => {
    if (initialNotes) setNotes(initialNotes);
  }, [initialNotes]);

  const mutation = useMutation({
    mutationFn: submitCustomerLicenseRequest,
    onSuccess: (data) => {
      setSuccessMsg(`Request #${data.RequestId} submitted to ${data.ResellerName}!`);
      setNotes("");
      void queryClient.invalidateQueries({ queryKey: ["CustomerRequests"] });
      setTimeout(() => {
        setIsOpen(false);
        setSuccessMsg(null);
      }, 3500);
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    mutation.mutate({
      CustomerEmail: customerEmail,
      CustomerName: customerName,
      ResellerId: resellerId,
      LicenseCategoryId: categoryId,
      LicenseTierId: tierId,
      Notes: notes.trim() || undefined,
    });
  };

  if (!isOpen) {
    return (
      <div className="flex items-center justify-between rounded-lg border border-border bg-card p-4">
        <div>
          <h3 className="text-sm font-semibold text-foreground">Need a new or renewed license?</h3>
          <p className="text-xs text-muted-foreground">
            Submit a direct request to your authorized reseller to get a serial key issued.
          </p>
        </div>
        <Button onClick={() => setIsOpen(true)} size="sm">
          <Send className="mr-2 size-3.5" />
          Request License
        </Button>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-primary/20 bg-card p-5 shadow-sm">
      <div className="flex items-center justify-between border-b pb-3 mb-4">
        <div>
          <h3 className="text-base font-semibold text-foreground">Request License from Reseller</h3>
          <p className="text-xs text-muted-foreground">
            Select your preferred plan and authorized reseller.
          </p>
        </div>
        <Button variant="ghost" size="sm" onClick={() => setIsOpen(false)}>
          Cancel
        </Button>
      </div>

      {successMsg ? (
        <div className="flex items-center gap-2 rounded-md bg-emerald-500/10 p-3 text-sm text-emerald-600 dark:text-emerald-400">
          <CheckCircle2 className="size-4 shrink-0" />
          <span>{successMsg}</span>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-medium text-foreground">Select Reseller</label>
              <select
                value={resellerId}
                onChange={(e) => setResellerId(Number(e.target.value))}
                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              >
                <option value={1}>Preview Reseller One (Main)</option>
                <option value={2}>Beta Reseller LLC</option>
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-medium text-foreground">License Category (Duration)</label>
              <select
                value={categoryId}
                onChange={(e) => setCategoryId(Number(e.target.value))}
                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              >
                <option value={1}>1 - Daily</option>
                <option value={2}>2 - Weekly</option>
                <option value={3}>3 - Monthly</option>
                <option value={4}>4 - Yearly</option>
                <option value={5}>5 - Lifetime</option>
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-medium text-foreground">Product Tier</label>
              <select
                value={tierId}
                onChange={(e) => setTierId(Number(e.target.value))}
                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              >
                <option value={1}>Starter</option>
                <option value={2}>Growth</option>
                <option value={3}>Scale</option>
                <option value={4}>Enterprise</option>
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-medium text-foreground">Your Email (Auto-filled)</label>
              <Input value={customerEmail} disabled className="bg-muted opacity-80" />
            </div>
          </div>

          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-foreground">Notes / Order Reference (Optional)</label>
            <Input
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="e.g. Order #1042 or department name"
            />
          </div>

          {mutation.isError && (
            <div className="flex items-center gap-2 rounded-md bg-destructive/10 p-2.5 text-xs text-destructive">
              <AlertCircle className="size-4 shrink-0" />
              <span>Failed to submit request. Please try again.</span>
            </div>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setIsOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={mutation.isPending}
            >
              {mutation.isPending ? "Submitting..." : "Send Request to Reseller"}
            </Button>
          </div>
        </form>
      )}
    </div>
  );
}
