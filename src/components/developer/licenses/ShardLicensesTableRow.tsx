import { useState } from "react";
import { Copy, Check, ChevronDown, ChevronRight, Laptop, ShieldCheck, Ban } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { ShardLicense } from "./types";
import { BoundMachinesList } from "./BoundMachinesList";

interface ShardLicensesTableRowProps {
  readonly license: ShardLicense;
  readonly copiedSerial: string | null;
  readonly onCopy: (serial: string) => void;
  readonly onApplySerial?: (serial: string) => void;
}

export function ShardLicensesTableRow({
  license,
  copiedSerial,
  onCopy,
  onApplySerial,
}: ShardLicensesTableRowProps) {
  const [isExpanded, setIsExpanded] = useState<boolean>(false);
  const isCopied = copiedSerial === license.serialValue;
  const hasMachines = license.boundMachines.length > 0;

  return (
    <>
      <tr className="border-b border-border/50 hover:bg-muted/30 transition-colors text-xs">
        <td className="py-2.5 px-3">
          <button
            type="button"
            className="flex items-center gap-1 font-mono font-medium text-foreground hover:text-primary transition-colors text-left"
            onClick={() => setIsExpanded((prev) => !prev)}
          >
            {hasMachines ? (
              isExpanded ? <ChevronDown className="size-3.5 text-muted-foreground" /> : <ChevronRight className="size-3.5 text-muted-foreground" />
            ) : <span className="w-3.5" />}
            <span>#{license.licenseId}</span>
          </button>
        </td>

        <td className="py-2.5 px-3">
          <div className="flex items-center gap-1.5">
            <span className="font-mono text-xs font-semibold text-foreground bg-muted/40 px-2 py-0.5 rounded border border-border/60">
              {license.serialValue}
            </span>
            <Button type="button" variant="ghost" size="sm" className="h-6 w-6 p-0 text-muted-foreground hover:text-foreground" onClick={() => onCopy(license.serialValue)} title="Copy Serial">
              {isCopied ? <Check className="size-3 text-emerald-500" /> : <Copy className="size-3" />}
            </Button>
            {onApplySerial && (
              <Button type="button" variant="ghost" size="sm" className="h-6 px-1.5 text-[11px] text-primary hover:text-primary/80" onClick={() => onApplySerial(license.serialValue)}>
                Inspect
              </Button>
            )}
          </div>
        </td>

        <td className="py-2.5 px-3">
          <span className="font-mono text-muted-foreground bg-muted/20 px-1.5 py-0.5 rounded text-[11px]">
            {license.dealCode}
          </span>
        </td>

        <td className="py-2.5 px-3">
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-secondary text-secondary-foreground">
            <Laptop className="size-3" />
            {license.activeSeats} / {license.allowedSeats} Seats
          </span>
        </td>

        <td className="py-2.5 px-3">
          {license.isActive ? (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
              <ShieldCheck className="size-3" /> Active
            </span>
          ) : (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
              <Ban className="size-3" /> Revoked
            </span>
          )}
        </td>

        <td className="py-2.5 px-3 text-muted-foreground text-right whitespace-nowrap">
          {license.createdAt.length > 10 ? license.createdAt.substring(0, 10) : license.createdAt}
        </td>
      </tr>

      {isExpanded && hasMachines && <BoundMachinesList machines={license.boundMachines} />}
    </>
  );
}
