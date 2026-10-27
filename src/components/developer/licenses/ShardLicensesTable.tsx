import { useState } from "react";
import type { ShardLicense } from "./types";
import { ShardLicensesTableRow } from "./ShardLicensesTableRow";

interface ShardLicensesTableProps {
  readonly licenses: readonly ShardLicense[];
  readonly onApplySerial?: (serial: string) => void;
}

export function ShardLicensesTable({ licenses, onApplySerial }: ShardLicensesTableProps) {
  const [copiedSerial, setCopiedSerial] = useState<string | null>(null);

  const handleCopy = (serial: string) => {
    navigator.clipboard.writeText(serial);
    setCopiedSerial(serial);
    setTimeout(() => setCopiedSerial(null), 2000);
  };

  return (
    <div className="overflow-x-auto rounded-lg border border-border/70 bg-card">
      <table className="w-full text-left border-collapse">
        <thead>
          <tr className="border-b border-border/80 bg-muted/30 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
            <th className="py-2.5 px-3">ID</th>
            <th className="py-2.5 px-3">Serial Key</th>
            <th className="py-2.5 px-3">Deal Code</th>
            <th className="py-2.5 px-3">Hardware Seats</th>
            <th className="py-2.5 px-3">Status</th>
            <th className="py-2.5 px-3 text-right">Created</th>
          </tr>
        </thead>
        <tbody>
          {licenses.map((lic) => (
            <ShardLicensesTableRow
              key={lic.licenseId}
              license={lic}
              copiedSerial={copiedSerial}
              onCopy={handleCopy}
              onApplySerial={onApplySerial}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}
