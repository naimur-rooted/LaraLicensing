import React, { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { CheckCircle2, Building, Mail, Globe, Tag, Send } from "lucide-react";

interface ResellerApplicationModalProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
}

export function ResellerApplicationModal({
  isOpen,
  onOpenChange,
}: ResellerApplicationModalProps) {
  const [companyName, setCompanyName] = useState("");
  const [email, setEmail] = useState("");
  const [website, setWebsite] = useState("");
  const [desiredPrefix, setDesiredPrefix] = useState("");
  const [volume, setVolume] = useState("100-500");
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    // Simulate real partner request dispatch
    setTimeout(() => {
      setIsSubmitting(false);
      setIsSubmitted(true);
    }, 800);
  };

  const handleClose = () => {
    onOpenChange(false);
    setTimeout(() => {
      setIsSubmitted(false);
      setCompanyName("");
      setEmail("");
      setWebsite("");
      setDesiredPrefix("");
    }, 300);
  };

  return (
    <Dialog open={isOpen} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md p-6">
        {!isSubmitted ? (
          <form onSubmit={handleSubmit} className="space-y-4">
            <DialogHeader className="space-y-1 text-left">
              <div className="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider">
                <Building className="size-4" />
                <span>Partner Application</span>
              </div>
              <DialogTitle className="text-xl font-bold text-foreground">
                Apply to Become an Authorized Reseller
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                Distribute software with wholesale margin discounts, branded license keys, and self-managed client seats.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-3 pt-2">
              <div className="space-y-1.5">
                <Label htmlFor="companyName" className="text-xs font-semibold">
                  Company / Organization Name *
                </Label>
                <div className="relative">
                  <Building className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                  <Input
                    id="companyName"
                    required
                    placeholder="e.g. Apex Software Solutions Ltd"
                    value={companyName}
                    onChange={(e) => setCompanyName(e.target.value)}
                    className="pl-9 text-xs"
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="email" className="text-xs font-semibold">
                  Corporate / Contact Email *
                </Label>
                <div className="relative">
                  <Mail className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                  <Input
                    id="email"
                    type="email"
                    required
                    placeholder="partner@yourcompany.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="pl-9 text-xs"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                  <Label htmlFor="website" className="text-xs font-semibold">
                    Website / Portfolio
                  </Label>
                  <div className="relative">
                    <Globe className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input
                      id="website"
                      placeholder="https://..."
                      value={website}
                      onChange={(e) => setWebsite(e.target.value)}
                      className="pl-9 text-xs"
                    />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="prefix" className="text-xs font-semibold">
                    Desired Prefix
                  </Label>
                  <div className="relative">
                    <Tag className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input
                      id="prefix"
                      placeholder="e.g. APEX-"
                      value={desiredPrefix}
                      onChange={(e) => setDesiredPrefix(e.target.value.toUpperCase())}
                      className="pl-9 text-xs uppercase"
                    />
                  </div>
                </div>
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">
                  Expected Annual License Volume
                </Label>
                <Select value={volume} onValueChange={setVolume}>
                  <SelectTrigger className="text-xs">
                    <SelectValue placeholder="Select anticipated volume" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="10-50">Starter (10 – 50 licenses/yr)</SelectItem>
                    <SelectItem value="100-500">Regional (100 – 500 licenses/yr)</SelectItem>
                    <SelectItem value="500-2000">Enterprise Distributor (500 – 2,000 licenses/yr)</SelectItem>
                    <SelectItem value="2000+">Global Agency (2,000+ licenses/yr)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="pt-3 border-t border-border flex items-center justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onOpenChange(false)}
                className="text-xs"
              >
                Cancel
              </Button>
              <Button
                type="submit"
                size="sm"
                disabled={isSubmitting}
                className="text-xs font-semibold gap-1.5"
              >
                <Send className="size-3.5" />
                <span>{isSubmitting ? "Submitting Application..." : "Submit Partner Application"}</span>
              </Button>
            </div>
          </form>
        ) : (
          <div className="text-center py-6 space-y-4">
            <div className="size-12 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center">
              <CheckCircle2 className="size-6" />
            </div>
            <div className="space-y-1.5">
              <h3 className="text-lg font-bold text-foreground">
                Application Received!
              </h3>
              <p className="text-xs text-muted-foreground max-w-sm mx-auto leading-relaxed">
                Thank you for applying to the LaraLicensing Reseller Network. Our vendor team will review your application for <strong>{companyName || "your company"}</strong> within 24 hours.
              </p>
            </div>
            <div className="p-3 bg-muted/40 rounded-xl text-left text-[11px] text-muted-foreground space-y-1 border border-border/60">
              <div className="flex justify-between">
                <span>Account Status:</span>
                <span className="font-semibold text-amber-500">Under Review</span>
              </div>
              <div className="flex justify-between">
                <span>Confirmation Sent To:</span>
                <span className="font-semibold text-foreground">{email}</span>
              </div>
              <div className="flex justify-between">
                <span>Requested Vanity Prefix:</span>
                <span className="font-semibold text-foreground">{desiredPrefix || "Auto-Assigned"}</span>
              </div>
            </div>
            <Button
              type="button"
              onClick={handleClose}
              className="w-full text-xs font-semibold"
            >
              Back to Reseller Hub
            </Button>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
