import { Box, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";

interface OverviewEmptyStateProps {
  readonly onNavigateToProducts?: () => void;
}

export function OverviewEmptyState({ onNavigateToProducts }: OverviewEmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 bg-card/40 p-12 text-center shadow-xs">
      <div className="flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-4 shadow-2xs">
        <Box className="size-8" />
      </div>
      <h3 className="text-lg font-bold text-foreground">No Products Registered Yet</h3>
      <p className="mt-2 max-w-md text-sm text-muted-foreground leading-relaxed">
        No products created yet. Create your first product to generate licenses, configure deals, and distribute software.
      </p>
      {onNavigateToProducts && (
        <div className="mt-6 flex items-center justify-center">
          <Button
            onClick={onNavigateToProducts}
            className="gap-2 shadow-sm font-semibold"
          >
            <Plus className="size-4" />
            <span>Create Your First Product</span>
          </Button>
        </div>
      )}
    </div>
  );
}
