import {
  LayoutGrid,
  KeyRound,
  ShieldAlert,
  BarChart3,
  CreditCard,
  type LucideIcon,
} from "lucide-react";
import type { PortalTabId } from "./portal-types";

interface NavItemConfig {
  readonly id: PortalTabId;
  readonly label: string;
  readonly icon: LucideIcon;
  readonly badge: string | null;
}

const NAV_ITEMS: readonly NavItemConfig[] = [
  { id: "overview", label: "Overview", icon: BarChart3, badge: null },
  { id: "developer", label: "Products & Deals", icon: LayoutGrid, badge: "Tier 2" },
  { id: "billing", label: "Billing & Subscription", icon: CreditCard, badge: "Active" },
  { id: "security", label: "Fraud Guard", icon: ShieldAlert, badge: null },
  { id: "client", label: "Issued Licenses & Serials", icon: KeyRound, badge: null },
];

interface PortalSidebarProps {
  readonly isSidebarOpen: boolean;
  readonly activeTab: PortalTabId;
  readonly onSelectTab: (tab: PortalTabId) => void;
}

export function PortalSidebar({ isSidebarOpen, activeTab, onSelectTab }: PortalSidebarProps) {
  return (
    <aside
      className={`shrink-0 border-r bg-card/40 transition-all duration-300 flex flex-col justify-between select-none ${
        isSidebarOpen ? "w-64 p-3" : "w-0 p-0 overflow-hidden border-none"
      }`}
    >
      <div className="space-y-1">
        <div className="px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/70">
          Workspace
        </div>

        <nav className="space-y-1">
          {NAV_ITEMS.map((item) => {
            const Icon = item.icon;
            const isActive = activeTab === item.id;
            return (
              <button
                key={item.id}
                type="button"
                onClick={() => onSelectTab(item.id)}
                className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-all cursor-pointer ${
                  isActive
                    ? "bg-primary text-primary-foreground shadow-2xs font-semibold"
                    : "text-muted-foreground hover:bg-muted/60 hover:text-foreground"
                }`}
              >
                <div className="flex items-center gap-2.5 truncate">
                  <Icon className="size-4 shrink-0" />
                  <span className="truncate">{item.label}</span>
                </div>
                {item.badge && (
                  <span
                    className={`text-[9px] px-1.5 py-0.2 rounded font-mono ${
                      isActive
                        ? "bg-primary-foreground/20 text-primary-foreground"
                        : "bg-muted text-muted-foreground"
                    }`}
                  >
                    {item.badge}
                  </span>
                )}
              </button>
            );
          })}
        </nav>
      </div>

      <div className="p-3 border-t bg-muted/20 rounded-lg text-[11px] text-muted-foreground space-y-1">
        <div className="font-semibold text-foreground flex items-center gap-1.5">
          <span className="size-2 rounded-full bg-emerald-500 animate-pulse" />
          <span>Multi-Tenant Sharding</span>
        </div>
        <p className="text-[10px] text-muted-foreground/80 leading-relaxed">
          Every product is isolated in its own dedicated SQLite shard.
        </p>
      </div>
    </aside>
  );
}
