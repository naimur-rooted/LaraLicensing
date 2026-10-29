import React from "react";
import { Receipt, Download, CreditCard, Shield } from "lucide-react";
import { Button } from "@/components/ui/button";

export function BillingInvoicesCard() {
  const invoices: { id: string; date: string; amount: string; status: string; plan: string }[] = [];

  return (
    <div className="rounded-xl border border-border/80 bg-card/80 p-5 shadow-xs space-y-4">
      <div className="flex items-center justify-between">
        <h4 className="text-sm font-bold text-foreground flex items-center gap-2">
          <Receipt className="size-4 text-primary" />
          <span>Billing History & Statements</span>
        </h4>
        <span className="text-[11px] text-muted-foreground">Download receipts for tax records</span>
      </div>

      <div className="divide-y divide-border/60">
        {invoices.length === 0 ? (
          <div className="py-6 text-center text-xs text-muted-foreground">
            No invoices or billing statements recorded yet.
          </div>
        ) : invoices.map((inv) => (
          <div key={inv.id} className="py-2.5 flex items-center justify-between text-xs">
            <div className="flex items-center gap-3">
              <CreditCard className="size-4 text-muted-foreground" />
              <div>
                <p className="font-semibold text-foreground font-mono">{inv.id}</p>
                <p className="text-[10px] text-muted-foreground">{inv.date} • {inv.plan}</p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <span className="font-mono font-bold text-foreground">{inv.amount}</span>
              <span className="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                {inv.status}
              </span>
              <Button variant="ghost" size="icon" className="size-7" title="Download Receipt">
                <Download className="size-3.5 text-muted-foreground" />
              </Button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
