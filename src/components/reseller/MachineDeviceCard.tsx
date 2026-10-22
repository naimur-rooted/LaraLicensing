import { Laptop, Unlink } from "lucide-react";
import {
  getBindingStatus,
  MachineBindingStatusType,
  type MachineBinding,
} from "../../lib/lara-machine-binding";

export interface MachineDeviceCardProps {
  binding: MachineBinding;
  onReleaseClick: (binding: MachineBinding) => void;
}

export function MachineDeviceCard({ binding, onReleaseClick }: MachineDeviceCardProps) {
  const status = getBindingStatus(binding);
  const isActive = status === MachineBindingStatusType.Active;
  const isCooldown = status === MachineBindingStatusType.Cooldown;
  const truncatedHash = `${binding.FingerprintHash.slice(0, 12)}...${binding.FingerprintHash.slice(-8)}`;

  return (
    <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
        <div className="flex items-center gap-2">
          <Laptop className="size-4 text-primary" />
          <code className="font-mono text-xs font-semibold text-foreground">{truncatedHash}</code>
        </div>
        <StatusBadge status={status} isCooldown={isCooldown} />
      </div>

      <div className="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
        <div>
          <span className="text-muted-foreground">Client IP</span>
          <p className="font-mono font-medium text-foreground">{binding.ClientIp ?? "127.0.0.1"}</p>
        </div>
        <div>
          <span className="text-muted-foreground">First Seen</span>
          <p className="font-medium text-foreground">{new Date(binding.FirstSeenAt).toLocaleDateString()}</p>
        </div>
        <div>
          <span className="text-muted-foreground">Last Seen</span>
          <p className="font-medium text-foreground">{new Date(binding.LastSeenAt).toLocaleTimeString()}</p>
        </div>
      </div>

      <div className="mt-4 flex justify-end border-t border-border pt-3">
        {isActive ? (
          <button
            type="button"
            onClick={() => onReleaseClick(binding)}
            className="focus-ring inline-flex h-8 items-center gap-1.5 rounded-md border border-destructive/40 bg-destructive/5 px-3 text-xs font-medium text-destructive transition-colors hover:bg-destructive hover:text-destructive-foreground"
          >
            <Unlink className="size-3.5" />
            <span>Release Device</span>
          </button>
        ) : (
          <span className="text-xs text-muted-foreground italic">Hardware Seat Unbound</span>
        )}
      </div>
    </div>
  );
}

function StatusBadge({ status, isCooldown }: { status: string; isCooldown: boolean }) {
  if (isCooldown) {
    return <span className="rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400">Under 15-min Cooldown</span>;
  }
  const isActive = status === MachineBindingStatusType.Active;

  return (
    <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${isActive ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400" : "bg-muted text-muted-foreground"}`}>
      {isActive ? "Active Binding" : "Released"}
    </span>
  );
}
