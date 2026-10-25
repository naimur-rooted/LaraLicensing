import { Button } from "@/components/ui/button";
import { Shield, Sparkles, User, LogOut, PanelLeftClose, PanelLeft } from "lucide-react";

interface PortalHeaderProps {
  readonly isSidebarOpen: boolean;
  readonly onToggleSidebar: () => void;
  readonly currentPlanTier: string;
  readonly onOpenPlanModal: () => void;
  readonly displayName: string;
  readonly onSignOut: () => void;
}

export function PortalHeader({
  isSidebarOpen,
  onToggleSidebar,
  currentPlanTier,
  onOpenPlanModal,
  displayName,
  onSignOut,
}: PortalHeaderProps) {
  return (
    <header className="sticky top-0 z-40 w-full border-b bg-card/80 backdrop-blur-md px-4 lg:px-6 h-14 flex items-center justify-between shadow-2xs">
      <div className="flex items-center gap-3">
        <Button
          variant="ghost"
          size="icon"
          onClick={onToggleSidebar}
          className="size-8 text-muted-foreground hover:text-foreground cursor-pointer"
          title={isSidebarOpen ? "Collapse Sidebar" : "Expand Sidebar"}
        >
          {isSidebarOpen ? <PanelLeftClose className="size-4" /> : <PanelLeft className="size-4" />}
        </Button>

        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-primary/10 border border-primary/20 text-primary flex items-center justify-center font-bold text-sm shadow-2xs">
            <Shield className="size-4 text-primary" />
          </div>
          <span className="font-bold tracking-tight text-sm text-foreground hidden sm:inline-block">
            LaraLicensing <span className="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-primary/10 text-primary font-semibold ml-1">Developer Studio</span>
          </span>
        </div>
      </div>

      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={onOpenPlanModal}
          className="flex items-center gap-1.5 px-3 py-1 rounded-full border border-primary/30 bg-primary/10 text-primary text-xs font-semibold hover:bg-primary/20 transition-colors shadow-2xs cursor-pointer"
          title="Manage Platform Subscription"
        >
          <Sparkles className="size-3.5 text-primary" />
          <span>{currentPlanTier}</span>
          <span className="text-[10px] text-muted-foreground uppercase ml-0.5">Upgrade</span>
        </button>

        <div className="hidden sm:flex items-center gap-2 text-xs text-muted-foreground">
          <User className="size-3.5" />
          <span>Hello, <strong className="text-foreground">{displayName}</strong></span>
        </div>

        <Button
          variant="outline"
          size="sm"
          onClick={onSignOut}
          className="h-8 text-xs text-muted-foreground hover:text-foreground gap-1.5 shadow-2xs cursor-pointer"
        >
          <LogOut className="size-3.5" />
          <span className="hidden sm:inline">Sign out</span>
        </Button>
      </div>
    </header>
  );
}
