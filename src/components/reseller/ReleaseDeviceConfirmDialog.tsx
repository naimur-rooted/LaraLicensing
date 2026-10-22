import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "../ui/alert-dialog";
import { Loader2, AlertTriangle } from "lucide-react";

export interface ReleaseDeviceConfirmDialogProps {
  isOpen: boolean;
  isPending: boolean;
  fingerprintSnippet: string;
  onClose: () => void;
  onConfirm: () => void;
}

export function ReleaseDeviceConfirmDialog(props: ReleaseDeviceConfirmDialogProps) {
  return (
    <AlertDialog open={props.isOpen} onOpenChange={(open) => !open && props.onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <div className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="size-5" />
            <AlertDialogTitle>Release Machine Binding?</AlertDialogTitle>
          </div>
          <AlertDialogDescription className="space-y-2 pt-2">
            <span>
              Releasing device with hardware fingerprint <code className="font-mono font-bold text-foreground">{props.fingerprintSnippet}</code> will revoke its license seat immediately.
            </span>
            <span className="block font-medium text-amber-600 dark:text-amber-400">
              Note: A 15-minute rebind cooldown will be enforced before this machine can reactivate.
            </span>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={props.isPending} onClick={props.onClose}>
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            disabled={props.isPending}
            onClick={props.onConfirm}
            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
          >
            {props.isPending ? <Loader2 className="mr-1.5 size-4 animate-spin" /> : null}
            <span>{props.isPending ? "Releasing..." : "Release Device"}</span>
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
