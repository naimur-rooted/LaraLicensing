import { Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";

interface DeleteLicenseButtonProps {
  readonly licenseId: number;
  readonly serialValue: string;
  readonly activeSeats: number;
  readonly onDelete?: (licenseId: number) => void;
}

export function DeleteLicenseButton({
  licenseId,
  serialValue,
  activeSeats,
  onDelete,
}: DeleteLicenseButtonProps) {
  if (activeSeats > 0) {
    return (
      <Button
        type="button"
        variant="ghost"
        size="sm"
        disabled
        className="h-6 w-6 p-0 opacity-30 cursor-not-allowed text-muted-foreground"
        title="Cannot delete license with active bound devices"
      >
        <Trash2 className="size-3.5" />
      </Button>
    );
  }

  const handleDelete = () => {
    if (window.confirm(`Permanently delete unused license key ${serialValue}?`)) {
      onDelete?.(licenseId);
    }
  };

  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      className="h-6 w-6 p-0 text-destructive hover:text-destructive hover:bg-destructive/10 transition-colors"
      onClick={handleDelete}
      title="Delete unused license"
    >
      <Trash2 className="size-3.5 text-rose-500" />
    </Button>
  );
}
