import type { LucideIcon } from "lucide-react";

export interface TabButtonProps {
  active: boolean;
  icon: LucideIcon;
  label: string;
  onClick: () => void;
}

export function TabButton(props: TabButtonProps) {
  const Icon = props.icon;

  return (
    <button
      type="button"
      onClick={props.onClick}
      className={`focus-ring -mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
        props.active
          ? "border-primary text-primary"
          : "border-transparent text-muted-foreground hover:border-border hover:text-foreground"
      }`}
    >
      <Icon className="size-4" />
      <span>{props.label}</span>
    </button>
  );
}
