import { Link } from "@tanstack/react-router";
import { Home, Menu, PanelLeft } from "lucide-react";

import { useMobileSidebar } from "../shell/AppShell";
import { ProfileMenu } from "../shell/ProfileMenu";
import { RoleChip } from "../shell/RoleChip";
import { TopbarSearch } from "../shell/TopbarSearch";
import { NotificationBell } from "../notifications/NotificationBell";
import { useSidebarCollapsed } from "../../lib/lara-sidebar-collapsed";

export function ResellerTopbar({ onSignOut }: { onSignOut: () => void }) {
  return (
    <div className="flex w-full items-center justify-between gap-2 sm:gap-3 min-w-0">
      <ResellerIdentity />
      <div className="hidden flex-1 justify-center md:flex min-w-0">
        <TopbarSearch />
      </div>
      <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
        <NotificationBell />
        <Link
          to="/"
          className="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground shrink-0"
          aria-label="Back to Homepage"
        >
          <Home className="size-4" />
        </Link>
        <ProfileMenu onSignOut={onSignOut} />
      </div>
    </div>
  );
}

function ResellerIdentity() {
  return (
    <div className="flex items-center gap-2 sm:gap-3 shrink-0">
      <SidebarToggle />
      <span
        className="text-sm font-semibold text-foreground hidden sm:inline"
        style={{ fontFamily: "var(--font-display)" }}
      >
        Reseller portal
      </span>
      <RoleChip />
    </div>
  );
}

function SidebarToggle() {
  const [collapsed, toggleDesktop] = useSidebarCollapsed();
  const { toggle: toggleMobile, open: mobileOpen } = useMobileSidebar();

  return (
    <>
      <button
        type="button"
        onClick={toggleMobile}
        aria-label={mobileOpen ? "Close navigation menu" : "Open navigation menu"}
        aria-expanded={mobileOpen}
        className="focus-ring inline-flex size-9 items-center justify-center rounded-md border border-input surface-hover md:hidden shrink-0"
      >
        <Menu aria-hidden="true" className="size-4" />
      </button>

      <button
        type="button"
        onClick={toggleDesktop}
        aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        aria-pressed={collapsed}
        className="focus-ring hidden size-9 items-center justify-center rounded-md border border-input surface-hover md:inline-flex shrink-0"
      >
        <PanelLeft aria-hidden="true" className="size-4" />
      </button>
    </>
  );
}
