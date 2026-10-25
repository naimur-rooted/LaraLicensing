import { Calendar } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ResponsiveContainer, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip } from "recharts";
import type { OverviewTimelinePoint } from "./overview-types";

interface OverviewTimeSeriesChartProps {
  readonly timeline: readonly OverviewTimelinePoint[];
}

export function OverviewTimeSeriesChart({ timeline }: OverviewTimeSeriesChartProps) {
  return (
    <Card className="rounded-xl border shadow-xs bg-card">
      <CardHeader className="py-3 px-5 border-b flex flex-row items-center justify-between space-y-0">
        <CardTitle className="text-sm font-semibold text-foreground">Activations</CardTitle>
        <div className="flex items-center gap-1.5 text-xs text-muted-foreground border rounded-md px-2.5 py-1 bg-muted/20">
          <Calendar className="size-3 text-muted-foreground" />
          <span>30-Day Period</span>
        </div>
      </CardHeader>
      <CardContent className="pt-4 pb-3 px-3">
        <div className="flex items-center justify-center gap-6 mb-3 text-xs">
          <div className="flex items-center gap-2">
            <span className="size-2.5 rounded-xs bg-[#3B82F6]" />
            <span className="text-muted-foreground font-medium">Activations</span>
          </div>
          <div className="flex items-center gap-2">
            <span className="size-2.5 rounded-xs bg-[#F43F5E]" />
            <span className="text-muted-foreground font-medium">Trial Activations</span>
          </div>
        </div>
        <div className="h-[280px] sm:h-[320px] w-full">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={timeline as OverviewTimelinePoint[]} margin={{ top: 10, right: 10, left: -20, bottom: 25 }}>
              <defs>
                <linearGradient id="activationGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#3B82F6" stopOpacity={0.25} />
                  <stop offset="95%" stopColor="#3B82F6" stopOpacity={0} />
                </linearGradient>
                <linearGradient id="trialGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#F43F5E" stopOpacity={0.25} />
                  <stop offset="95%" stopColor="#F43F5E" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" opacity={0.6} />
              <XAxis
                dataKey="shortDate"
                tickLine={false}
                axisLine={{ stroke: "hsl(var(--border))" }}
                tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }}
                interval={2}
                angle={-35}
                textAnchor="end"
              />
              <YAxis
                allowDecimals={false}
                tickLine={false}
                axisLine={false}
                tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }}
                domain={[0, (max: number) => Math.max(2, max + 1)]}
              />
              <Tooltip
                content={({ active, payload, label }) => {
                  if (!active || !payload?.length) return null;
                  return (
                    <div className="rounded-lg border bg-popover/95 p-2.5 text-xs shadow-md backdrop-blur-xs">
                      <p className="font-semibold text-foreground mb-1">{label}</p>
                      <div className="space-y-0.5 font-mono">
                        <p className="text-[#3B82F6]">Activations: <span className="font-bold">{payload[0]?.value}</span></p>
                        <p className="text-[#F43F5E]">Trial Activations: <span className="font-bold">{payload[1]?.value}</span></p>
                      </div>
                    </div>
                  );
                }}
              />
              <Area type="monotone" dataKey="activations" stroke="#3B82F6" strokeWidth={2} fillOpacity={1} fill="url(#activationGrad)" />
              <Area type="monotone" dataKey="trialActivations" stroke="#F43F5E" strokeWidth={2} fillOpacity={1} fill="url(#trialGrad)" />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </CardContent>
    </Card>
  );
}
