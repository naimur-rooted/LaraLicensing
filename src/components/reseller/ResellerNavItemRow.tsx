import { Link } from "@tanstack/react-router";
import type { LucideIcon } from "lucide-react";

export interface ResellerNavItemRowProps {
  icon: LucideIcon;
  label: string;
  to: string;
  search?: Record<string, string>;
  isActive: boolean;
}

export function ResellerNavItemRow(props: ResellerNavItemRowProps) {
  const Icon = props.icon;
  const activeStyle = props.isActive ? getActiveStyle() : undefined;

  return (
    <Link
      to={props.to}
      search={props.search}
      aria-current={props.isActive ? "page" : undefined}
      className="focus-ring relative flex items-center gap-3 rounded-lg px-3 py-2 text-foreground transition-colors surface-hover"
      style={activeStyle}
    >
      {props.isActive ? <ActiveIndicator /> : null}
      <Icon className="size-4 shrink-0" aria-hidden />
      <span className="truncate" data-collapse-hide="true">
        {props.label}
      </span>
    </Link>
  );
}

function ActiveIndicator() {
  return (
    <span
      aria-hidden
      className="absolute inset-y-1.5 left-0 w-[3px] rounded-full"
      style={{
        backgroundImage: "linear-gradient(180deg, var(--primary), var(--accent))",
      }}
    />
  );
}

function getActiveStyle() {
  return {
    backgroundImage:
      "linear-gradient(90deg, color-mix(in oklab, var(--primary) 18%, transparent), color-mix(in oklab, var(--primary) 6%, transparent))",
    color: "var(--primary)",
    fontWeight: 600,
    boxShadow: "inset 0 0 0 1px color-mix(in oklab, var(--primary) 22%, transparent)",
  };
}
