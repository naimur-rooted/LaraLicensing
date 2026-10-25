import { Key, Globe, ArrowRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid } from "recharts";
import type { OverviewTimelinePoint, OverviewCountryPoint } from "./overview-types";

interface OverviewBottomChartsProps {
  readonly timeline: readonly OverviewTimelinePoint[];
  readonly activationsByCountry: readonly OverviewCountryPoint[];
  readonly totalLicenses: number;
  readonly onNavigateToLicenses?: () => void;
}

export function OverviewBottomCharts({
  timeline,
  activationsByCountry,
  totalLicenses,
  onNavigateToLicenses,
}: OverviewBottomChartsProps) {
  const recentTimeline = timeline.slice(-14) as OverviewTimelinePoint[];
  const hasCountryData = activationsByCountry.length > 0;

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Card className="rounded-xl border shadow-xs bg-card flex flex-col">
        <CardHeader className="py-3 px-5 border-b flex flex-row items-center justify-between space-y-0">
          <CardTitle className="text-sm font-semibold text-foreground flex items-center gap-1.5">
            <Key className="size-3.5 text-primary" /> Licenses
          </CardTitle>
          {onNavigateToLicenses && (
            <Button variant="ghost" size="sm" onClick={onNavigateToLicenses} className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground gap-1">
              <span>View All</span>
              <ArrowRight className="size-3" />
            </Button>
          )}
        </CardHeader>
        <CardContent className="pt-4 pb-3 flex-1 flex flex-col justify-between">
          <div className="flex items-center justify-between text-xs mb-3 px-1">
            <span className="text-muted-foreground font-medium">Active Key Volume</span>
            <span className="font-mono text-muted-foreground">
              Total Sharded: <strong className="text-foreground">{totalLicenses}</strong>
            </span>
          </div>
          <div className="h-[180px] w-full">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={recentTimeline} margin={{ top: 5, right: 5, left: -25, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" opacity={0.5} />
                <XAxis dataKey="shortDate" tickLine={false} axisLine={false} tick={{ fontSize: 9, fill: "hsl(var(--muted-foreground))" }} />
                <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fontSize: 9, fill: "hsl(var(--muted-foreground))" }} domain={[0, (max: number) => Math.max(2, max + 1)]} />
                <Bar dataKey="activations" fill="#60A5FA" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </CardContent>
      </Card>

      <Card className="rounded-xl border shadow-xs bg-card flex flex-col">
        <CardHeader className="py-3 px-5 border-b flex flex-row items-center justify-between space-y-0">
          <CardTitle className="text-sm font-semibold text-foreground flex items-center gap-1.5">
            <Globe className="size-3.5 text-primary" /> Activations by Country
          </CardTitle>
          <span className="text-[11px] text-muted-foreground font-mono">Geographic Telemetry</span>
        </CardHeader>
        <CardContent className="pt-4 pb-3 flex-1 flex flex-col justify-center">
          {hasCountryData ? (
            <div className="space-y-3 px-1">
              {activationsByCountry.map((item) => (
                <div key={item.country} className="space-y-1">
                  <div className="flex items-center justify-between text-xs">
                    <span className="font-medium text-foreground flex items-center gap-1.5">
                      <span className="font-mono text-[11px] px-1 py-0.5 rounded bg-muted/60 text-muted-foreground">{item.code}</span>
                      {item.country}
                    </span>
                    <span className="font-mono text-muted-foreground"><strong className="text-foreground">{item.count}</strong> ({item.percentage}%)</span>
                  </div>
                  <div className="h-1.5 w-full bg-muted/40 rounded-full overflow-hidden">
                    <div className="h-full bg-primary rounded-full transition-all duration-500" style={{ width: `${item.percentage}%` }} />
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="py-8 text-center text-xs text-muted-foreground space-y-1.5">
              <Globe className="size-6 text-muted-foreground/40 mx-auto" />
              <p>There are no Activations or Trial Activations on the account.</p>
              <p className="text-[11px] text-muted-foreground/70">Activations will appear here as client software binds hardware seats.</p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
