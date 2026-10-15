import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { createPortal } from "react-dom";

import { useSidebarCollapsed } from "@/lib/lara-sidebar-collapsed";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";

/**
 * AppShell: authenticated `shell-app` grid host per
 * 02-spec/24-app-ui-design-system/12-shell-layout.md §3. Renders the four named
 * regions (sidebar, topbar, main sub-grid: page-header, page-actions,
 * page-content) and installs the html scroll-lock opt-in flag so
 * `data-app-shell` gated CSS in styles.css activates.
 *
 * Route consumers rendered under `<Outlet />` inject primary actions into
 * the `page-actions` grid area via the `<PageActions>` portal helper rather
 * than emitting a sibling right-aligned row inside `page-content`.
 */
export interface AppShellProps {
  sidebar: ReactNode;
  topbar: ReactNode;
  pageHeader?: ReactNode;
  pageActions?: ReactNode;
  pageContent: ReactNode;
}

export interface MobileSidebarContextType {
  open: boolean;
  setOpen: (open: boolean) => void;
  toggle: () => void;
}

const MobileSidebarContext = createContext<MobileSidebarContextType>({
  open: false,
  setOpen: () => {},
  toggle: () => {},
});

export function useMobileSidebar() {
  return useContext(MobileSidebarContext);
}

/** Portal target for the `page-actions` grid area. */
const PageActionsSlotContext = createContext<HTMLElement | null>(null);

/** Toggles the html scroll-lock flag while the shell is mounted. */
function useAppShellScrollLock(): void {
  useEffect(() => {
    const html = document.documentElement;
    const prev = html.getAttribute("data-app-shell");
    html.setAttribute("data-app-shell", "true");

    return () => {
      if (prev === null) html.removeAttribute("data-app-shell");
      else html.setAttribute("data-app-shell", prev);
    };
  }, []);
}

/** Sidebar region wrapper. Sticky sidebar on desktop, responsive Sheet on mobile per spec §3.2-§3.3. */
function ShellSidebar({
  children,
  mobileOpen,
  onMobileOpenChange,
}: {
  children: ReactNode;
  mobileOpen: boolean;
  onMobileOpenChange: (open: boolean) => void;
}) {
  return (
    <>
      <aside
        className="sidebar-gradient border-r border-sidebar-border sticky top-0 h-dvh overflow-y-auto p-3 hidden md:block"
        style={{ gridArea: "sidebar", zIndex: "var(--z-sidebar)" }}
        data-shell-region="sidebar"
      >
        {children}
      </aside>

      <div className="md:hidden">
        <Sheet open={mobileOpen} onOpenChange={onMobileOpenChange}>
          <SheetContent
            side="left"
            className="sidebar-gradient border-r border-sidebar-border p-3 w-[280px] max-w-[85vw] overflow-y-auto"
            onClick={(e) => {
              const target = e.target as HTMLElement | null;
              if (target?.closest("a, button[data-nav-item]")) {
                setTimeout(() => {
                  onMobileOpenChange(false);
                }, 150);
              }
            }}
          >
            <SheetHeader className="sr-only">
              <SheetTitle>Navigation Menu</SheetTitle>
              <SheetDescription>Main navigation links for the portal</SheetDescription>
            </SheetHeader>
            {children}
          </SheetContent>
        </Sheet>
      </div>
    </>
  );
}

/** Topbar region wrapper. Sticky topbar per spec §3.4. */
function ShellTopbar({ children }: { children: ReactNode }) {
  return (
    <header
      className="glass-topbar sticky top-0 flex items-center px-4 lg:px-6"
      style={{
        gridArea: "topbar",
        height: "var(--shell-topbar)",
        zIndex: "var(--z-topbar)",
      }}
      data-shell-region="topbar"
    >
      {children}
    </header>
  );
}

/** Main sub-grid: page-header, page-actions, page-content per spec §3.5. */
function ShellMain(props: {
  header?: ReactNode;
  actions?: ReactNode;
  content: ReactNode;
  onActionsSlot: (el: HTMLDivElement | null) => void;
}) {
  return (
    <main
      className="app-canvas overflow-y-auto min-w-0 h-full w-full"
      style={{
        gridArea: "main",
        WebkitOverflowScrolling: "touch",
        overscrollBehavior: "contain",
      }}
      data-shell-region="main"
    >
      <div className="page-content-container fade-in grid gap-8 [grid-template-rows:auto_auto_1fr] min-w-0 pb-28">
        {props.header ? <div data-page-region="page-header">{props.header}</div> : null}
        <div
          data-page-region="page-actions"
          ref={props.onActionsSlot}
          className="flex flex-wrap items-center justify-end gap-2 empty:hidden"
        >
          {props.actions}
        </div>
        <div data-page-content data-page-region="page-content" className="min-w-0">
          {props.content}
        </div>
      </div>
    </main>
  );
}

export function AppShell(props: AppShellProps) {
  useAppShellScrollLock();
  const [actionsSlot, setActionsSlot] = useState<HTMLElement | null>(null);
  const [collapsed] = useSidebarCollapsed();
  const [mobileOpen, setMobileOpen] = useState(false);

  // Auto-close mobile drawer when browser navigates
  useEffect(() => {
    if (typeof window === "undefined") return;
    const handlePopState = () => setMobileOpen(false);
    window.addEventListener("popstate", handlePopState);
    return () => window.removeEventListener("popstate", handlePopState);
  }, []);

  const mobileContextValue = useMemo(
    () => ({
      open: mobileOpen,
      setOpen: setMobileOpen,
      toggle: () => setMobileOpen((prev) => !prev),
    }),
    [mobileOpen],
  );

  return (
    <MobileSidebarContext.Provider value={mobileContextValue}>
      <PageActionsSlotContext.Provider value={actionsSlot}>
        <div className="shell-app" data-sidebar-collapsed={collapsed ? "true" : undefined}>
          <ShellSidebar mobileOpen={mobileOpen} onMobileOpenChange={setMobileOpen}>
            {props.sidebar}
          </ShellSidebar>
          <ShellTopbar>{props.topbar}</ShellTopbar>
          <ShellMain
            header={props.pageHeader}
            actions={props.pageActions}
            content={props.pageContent}
            onActionsSlot={setActionsSlot}
          />
        </div>
      </PageActionsSlotContext.Provider>
    </MobileSidebarContext.Provider>
  );
}

/**
 * Portal helper that projects its children into the AppShell `page-actions`
 * grid area. Use for primary route-level actions (e.g. "New reseller") so
 * they render as a proper header sibling instead of a hand-rolled
 * `<div className="flex justify-end">` inside `page-content`.
 */
export function PageActions({ children }: { children: ReactNode }) {
  const slot = useContext(PageActionsSlotContext);
  if (slot === null) return null;

  return createPortal(children, slot);
}
