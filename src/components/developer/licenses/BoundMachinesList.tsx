import type { BoundMachine } from "./types";

interface BoundMachinesListProps {
  readonly machines: readonly BoundMachine[];
}

export function BoundMachinesList({ machines }: BoundMachinesListProps) {
  return (
    <tr className="bg-muted/15 border-b border-border/40">
      <td colSpan={7} className="py-2.5 px-6">
        <div className="text-[11px] font-semibold text-muted-foreground uppercase mb-1.5">
          Bound Hardware Devices ({machines.length}):
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
          {machines.map((m) => (
            <div key={m.hardwareDigest} className="p-2 rounded bg-background border border-border/70 text-xs">
              <div className="font-semibold text-foreground truncate">{m.hostname}</div>
              <div className="font-mono text-[10px] text-muted-foreground truncate">HWID: {m.hardwareDigest}</div>
              <div className="text-[10px] text-muted-foreground/80 mt-0.5">Bound: {m.boundAt.substring(0, 10)}</div>
            </div>
          ))}
        </div>
      </td>
    </tr>
  );
}
