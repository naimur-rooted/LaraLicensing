import React, { useState, type FormEvent } from "react";
import { ArrowRight, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DeveloperPlanPicker } from "./DeveloperPlanPicker";
import { DeveloperRegisterFields } from "./DeveloperRegisterFields";
import { registerDeveloperViaLara } from "@/lib/lara-auth";
import { formatLaraApiError } from "@/lib/lara-api-error";

interface DeveloperRegisterFormProps {
  initialPlan?: string;
  onSuccess: () => void;
  onError: (msg: string) => void;
}

export function DeveloperRegisterForm({ initialPlan = "STARTER", onSuccess, onError }: DeveloperRegisterFormProps) {
  const [selectedPlan, setSelectedPlan] = useState(initialPlan.toUpperCase());
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [displayName, setDisplayName] = useState("");
  const [companyName, setCompanyName] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (password.length < 12) {
      onError("Password must be at least 12 characters long.");
      return;
    }
    setIsSubmitting(true);
    try {
      await registerDeveloperViaLara({
        Email: email.trim(),
        Password: password,
        DisplayName: displayName.trim() || undefined,
        CompanyName: companyName.trim() || undefined,
        SelectedPlan: selectedPlan,
      });
      onSuccess();
    } catch (err) {
      onError(formatLaraApiError(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <DeveloperPlanPicker selectedPlan={selectedPlan} onSelectPlan={setSelectedPlan} />
      <DeveloperRegisterFields
        email={email}
        setEmail={setEmail}
        password={password}
        setPassword={setPassword}
        displayName={displayName}
        setDisplayName={setDisplayName}
        companyName={companyName}
        setCompanyName={setCompanyName}
        showPassword={showPassword}
        setShowPassword={setShowPassword}
      />
      <Button type="submit" disabled={isSubmitting} className="w-full h-10 mt-2 font-semibold cursor-pointer">
        {isSubmitting ? <Loader2 className="size-4 animate-spin mr-2" /> : <ArrowRight className="size-4 mr-2" />}
        Create Developer Account
      </Button>
    </form>
  );
}
