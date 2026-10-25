import React from "react";
import { Eye, EyeOff, Lock, Mail } from "lucide-react";

interface DeveloperRegisterFieldsProps {
  email: string;
  setEmail: (val: string) => void;
  password: string;
  setPassword: (val: string) => void;
  displayName: string;
  setDisplayName: (val: string) => void;
  companyName: string;
  setCompanyName: (val: string) => void;
  showPassword: boolean;
  setShowPassword: (val: boolean) => void;
}

export function DeveloperRegisterFields({
  email,
  setEmail,
  password,
  setPassword,
  displayName,
  setDisplayName,
  companyName,
  setCompanyName,
  showPassword,
  setShowPassword,
}: DeveloperRegisterFieldsProps) {
  return (
    <>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div className="space-y-1">
          <label className="text-xs font-medium text-foreground">Work Email</label>
          <div className="relative">
            <Mail className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="developer@company.com"
              className="w-full h-9 pl-9 pr-3 rounded-lg border bg-background text-sm"
            />
          </div>
        </div>
        <div className="space-y-1">
          <label className="text-xs font-medium text-foreground">Password (min 12 chars)</label>
          <div className="relative">
            <Lock className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
            <input
              type={showPassword ? "text" : "password"}
              required
              minLength={12}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full h-9 pl-9 pr-8 rounded-lg border bg-background text-sm"
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute right-2.5 top-2.5 text-muted-foreground"
            >
              {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
          </div>
        </div>
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div className="space-y-1">
          <label className="text-xs font-medium text-muted-foreground">Display Name (Optional)</label>
          <input
            type="text"
            value={displayName}
            onChange={(e) => setDisplayName(e.target.value)}
            placeholder="Jane Doe"
            className="w-full h-9 px-3 rounded-lg border bg-background text-sm"
          />
        </div>
        <div className="space-y-1">
          <label className="text-xs font-medium text-muted-foreground">Company / Project (Optional)</label>
          <input
            type="text"
            value={companyName}
            onChange={(e) => setCompanyName(e.target.value)}
            placeholder="Acme Tools Inc."
            className="w-full h-9 px-3 rounded-lg border bg-background text-sm"
          />
        </div>
      </div>
    </>
  );
}
