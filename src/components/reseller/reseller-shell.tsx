import { Link, Outlet, useNavigate } from "@tanstack/react-router";
import { Building2 } from "lucide-react";

import { AppShell } from "../shell/AppShell";
import { CommandPalette } from "../shell/CommandPalette";
import { ResellerSidebarNav } from "./ResellerSidebarNav";
import { ResellerTopbar } from "./ResellerTopbar";
import { clearLaraSession } from "../../lib/lara-api-session";
import { LaraShellRoleContext } from "../../lib/lara-shell-role";

export interface ResellerShellProps {
  resellerId: string;
  children?: React.ReactNode;
}

export function ResellerShell({ resellerId, children }: ResellerShellProps) {
  const signOut = useResellerSignOut();

  return (
    <LaraShellRoleContext.Provider value="Reseller">
      <AppShell
        sidebar={<ResellerSidebar resellerId={resellerId} />}
        topbar={<ResellerTopbar onSignOut={signOut} />}
        pageContent={
          <div className="w-full px-6 lg:px-8 py-6">
            {children ?? <Outlet />}
          </div>
        }
      />
      <CommandPalette />
    </LaraShellRoleContext.Provider>
  );
}

function useResellerSignOut() {
  const navigate = useNavigate();

  return () => {
    clearLaraSession();
    void navigate({ to: "/admin/login", replace: true });
  };
}

function ResellerSidebar({ resellerId }: { resellerId: string }) {
  return (
    <div className="flex h-full flex-col gap-4">
      <ResellerBrand resellerId={resellerId} />
      <ResellerSidebarNav resellerId={resellerId} />
    </div>
  );
}

function ResellerBrand({ resellerId }: { resellerId: string }) {
  return (
    <Link
      to="/reseller/$resellerId/licenses"
      params={{ resellerId: Number(resellerId) }}
      className="focus-ring flex h-11 items-center gap-2.5 rounded-lg px-2 font-semibold text-foreground surface-hover"
    >
      <span aria-hidden="true" className="brand-tile">
        <Building2 className="size-4" />
      </span>
      <span
        data-collapse-hide="true"
        className="font-display tracking-tight text-[0.975rem]"
      >
        Reseller Portal
      </span>
    </Link>
  );
}
