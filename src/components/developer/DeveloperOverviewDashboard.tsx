import React, { useState, useEffect } from "react";
import {
  RefreshCw,
  Calendar,
  Maximize2,
  ArrowRight,
  Globe,
  ShieldCheck,
  TrendingUp,
  Key,
  Users,
  Layers,
  ChevronDown,
  CheckCircle2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  ResponsiveContainer,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  BarChart,
  Bar,
} from "recharts";

interface OverviewKpi {
  current: number;
  max: number | null;
  label: string;
}

interface OverviewData {
  kpis: {
    activations: OverviewKpi;
    trialActivations: OverviewKpi;
    licenses: OverviewKpi;
    users: OverviewKpi;
    releases: OverviewKpi;
  };
  timeline: Array<{
    date: string;
    shortDate: string;
    activations: number;
    trialActivations: number;
  }>;
  activationsByCountry: Array<{
    country: string;
    code: string;
    count: number;
    percentage: number;
  }>;
  productsCount: number;
  trialExpiresOn: string;
}

interface DeveloperOverviewDashboardProps {
  onNavigateToProducts?: () => void;
  onNavigateToLicenses?: () => void;
}

export function DeveloperOverviewDashboard({
  onNavigateToProducts,
  onNavigateToLicenses,
}: DeveloperOverviewDashboardProps) {
  const [data, setData] = useState<OverviewData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<string>("all");
  const [productsList, setProductsList] = useState<Array<{ slug: string; name: string }>>([]);

  const fetchOverview = async (slug = selectedProduct) => {
    setIsRefreshing(true);
    try {
      const url =
        slug && slug !== "all"
          ? `http://127.0.0.1:8000/api/v1/developer/overview?productSlug=${slug}`
          : "http://127.0.0.1:8000/api/v1/developer/overview";
      const res = await fetch(url);
      const json = await res.json();
      if (json.IsSuccess) {
        setData(json.Data);
      }
    } catch (e) {
      console.error("Failed to load developer overview metrics", e);
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  };

  useEffect(() => {
    fetchOverview();

    // Fetch product list for the filter
    fetch("http://127.0.0.1:8000/api/v1/developer/products")
      .then((r) => r.json())
      .then((json) => {
        if (json.IsSuccess && Array.isArray(json.Data)) {
          setProductsList(
            json.Data.map((p: any) => ({
              slug: p.productSlug,
              name: p.productName,
            }))
          );
        }
      })
      .catch(() => {});
  }, []);

  const handleProductChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const slug = e.target.value;
    setSelectedProduct(slug);
    fetchOverview(slug);
  };

  const kpis = data?.kpis;

  return (
    <div className="space-y-5 animate-in fade-in duration-300">
      {/* Top Breadcrumb & Environment Status Bar matching Cryptlex */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-2 border-b border-border/60">
        <div>
          <div className="flex items-center gap-2 text-xs text-muted-foreground font-medium">
            <span className="text-primary hover:underline cursor-pointer">Home</span>
            <span>/</span>
            <span className="text-foreground">Overview</span>
          </div>
          <h1 className="text-xl font-bold tracking-tight text-foreground mt-0.5">Overview</h1>
        </div>

        <div className="flex items-center gap-3 self-end sm:self-auto">
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <span>
              Trial expires on:{" "}
              <strong className="text-foreground font-medium font-mono">
                {data?.trialExpiresOn || "Nov 20, 2026"}
              </strong>
            </span>
          </div>

          <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-md border bg-background text-xs font-medium shadow-2xs">
            <span className="size-2 rounded-full bg-emerald-500 animate-pulse" />
            <span className="text-foreground">Production</span>
          </div>

          <div className="flex items-center gap-1.5">
            <select
              value={selectedProduct}
              onChange={handleProductChange}
              className="h-8 rounded-md border border-input bg-background px-2.5 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              <option value="all">All Sharded Products</option>
              {productsList.map((p) => (
                <option key={p.slug} value={p.slug}>
                  {p.name}
                </option>
              ))}
            </select>

            <Button
              variant="outline"
              size="icon"
              onClick={() => fetchOverview()}
              disabled={isRefreshing}
              className="size-8 text-primary hover:text-primary hover:bg-primary/10 transition-colors"
              title="Refresh Dashboard"
            >
              <RefreshCw className={`size-3.5 ${isRefreshing ? "animate-spin" : ""}`} />
            </Button>
          </div>
        </div>
      </div>

      {/* Top 5 Metric Cards matching Cryptlex Layout */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        {/* 1. Activations */}
        <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors">
          <CardContent className="p-4 space-y-1">
            <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
              {kpis ? (
                <>
                  {kpis.activations.current}
                  <span className="text-muted-foreground/60 text-lg font-normal">
                    {" "}
                    / {kpis.activations.max}
                  </span>
                </>
              ) : (
                "--"
              )}
            </div>
            <p className="text-xs font-medium text-muted-foreground">{kpis?.activations.label || "Activations"}</p>
          </CardContent>
        </Card>

        {/* 2. Trial Activations */}
        <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors">
          <CardContent className="p-4 space-y-1">
            <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
              {kpis ? (
                <>
                  {kpis.trialActivations.current}
                  <span className="text-muted-foreground/60 text-lg font-normal">
                    {" "}
                    / {kpis.trialActivations.max}
                  </span>
                </>
              ) : (
                "--"
              )}
            </div>
            <p className="text-xs font-medium text-muted-foreground">
              {kpis?.trialActivations.label || "Trial Activations"}
            </p>
          </CardContent>
        </Card>

        {/* 3. Licenses */}
        <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors">
          <CardContent className="p-4 space-y-1">
            <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
              {kpis?.licenses.current ?? 0}
            </div>
            <p className="text-xs font-medium text-muted-foreground">{kpis?.licenses.label || "Licenses"}</p>
          </CardContent>
        </Card>

        {/* 4. Users */}
        <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors">
          <CardContent className="p-4 space-y-1">
            <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
              {kpis ? (
                <>
                  {kpis.users.current}
                  <span className="text-muted-foreground/60 text-lg font-normal"> / {kpis.users.max}</span>
                </>
              ) : (
                "--"
              )}
            </div>
            <p className="text-xs font-medium text-muted-foreground">{kpis?.users.label || "Users"}</p>
          </CardContent>
        </Card>

        {/* 5. Releases */}
        <Card className="rounded-lg border bg-card/60 backdrop-blur-xs shadow-xs hover:border-primary/40 transition-colors col-span-2 sm:col-span-1">
          <CardContent className="p-4 space-y-1">
            <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
              {kpis ? (
                <>
                  {kpis.releases.current}
                  <span className="text-muted-foreground/60 text-lg font-normal"> / {kpis.releases.max}</span>
                </>
              ) : (
                "--"
              )}
            </div>
            <p className="text-xs font-medium text-muted-foreground">{kpis?.releases.label || "Releases"}</p>
          </CardContent>
        </Card>
      </div>

      {/* Main Activations Time Series Chart matching Cryptlex */}
      <Card className="rounded-xl border shadow-xs bg-card">
        <CardHeader className="py-3 px-5 border-b flex flex-row items-center justify-between space-y-0">
          <CardTitle className="text-sm font-semibold text-foreground">Activations</CardTitle>

          <div className="flex items-center gap-2">
            <div className="hidden sm:flex items-center gap-1.5 text-xs text-muted-foreground border rounded-md px-2.5 py-1 bg-muted/20">
              <Calendar className="size-3 text-muted-foreground" />
              <span>30-Day Period</span>
            </div>
            <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-foreground">
              <Maximize2 className="size-3.5" />
            </Button>
          </div>
        </CardHeader>

        <CardContent className="pt-4 pb-3 px-3">
          {/* Legend */}
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
              <AreaChart
                data={data?.timeline || []}
                margin={{ top: 10, right: 10, left: -20, bottom: 25 }}
              >
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
                  domain={[0, (dataMax: number) => Math.max(2, dataMax + 1)]}
                />
                <Tooltip
                  content={({ active, payload, label }) => {
                    if (active && payload && payload.length) {
                      return (
                        <div className="rounded-lg border bg-popover/95 p-2.5 text-xs shadow-md backdrop-blur-xs">
                          <p className="font-semibold text-foreground mb-1">{label}</p>
                          <div className="space-y-0.5 font-mono">
                            <p className="text-[#3B82F6]">
                              Activations: <span className="font-bold">{payload[0]?.value}</span>
                            </p>
                            <p className="text-[#F43F5E]">
                              Trial Activations: <span className="font-bold">{payload[1]?.value}</span>
                            </p>
                          </div>
                        </div>
                      );
                    }
                    return null;
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="activations"
                  stroke="#3B82F6"
                  strokeWidth={2}
                  fillOpacity={1}
                  fill="url(#activationGrad)"
                />
                <Area
                  type="monotone"
                  dataKey="trialActivations"
                  stroke="#F43F5E"
                  strokeWidth={2}
                  fillOpacity={1}
                  fill="url(#trialGrad)"
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </CardContent>
      </Card>

      {/* Bottom Split Row: Licenses & Activations by Country matching Cryptlex */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Left: Licenses Overview */}
        <Card className="rounded-xl border shadow-xs bg-card flex flex-col">
          <CardHeader className="py-3 px-5 border-b flex flex-row items-center justify-between space-y-0">
            <CardTitle className="text-sm font-semibold text-foreground flex items-center gap-1.5">
              <Key className="size-3.5 text-primary" /> Licenses
            </CardTitle>
            {onNavigateToLicenses && (
              <Button
                variant="ghost"
                size="sm"
                onClick={onNavigateToLicenses}
                className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground gap-1"
              >
                <span>View All</span>
                <ArrowRight className="size-3" />
              </Button>
            )}
          </CardHeader>
          <CardContent className="pt-4 pb-3 flex-1 flex flex-col justify-between">
            <div className="flex items-center justify-between text-xs mb-3 px-1">
              <div className="flex items-center gap-2">
                <span className="size-2.5 rounded-xs bg-[#60A5FA]" />
                <span className="text-muted-foreground font-medium">Active Key Volume</span>
              </div>
              <span className="font-mono text-muted-foreground">
                Total Sharded: <strong className="text-foreground">{data?.kpis.licenses.current ?? 0}</strong>
              </span>
            </div>

            <div className="h-[180px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={(data?.timeline || []).slice(-14)} margin={{ top: 5, right: 5, left: -25, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" opacity={0.5} />
                  <XAxis
                    dataKey="shortDate"
                    tickLine={false}
                    axisLine={false}
                    tick={{ fontSize: 9, fill: "hsl(var(--muted-foreground))" }}
                  />
                  <YAxis
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                    tick={{ fontSize: 9, fill: "hsl(var(--muted-foreground))" }}
                    domain={[0, (dataMax: number) => Math.max(2, dataMax + 1)]}
                  />
                  <Bar dataKey="activations" fill="#60A5FA" radius={[3, 3, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        {/* Right: Activations by Country */}
        <Card className="rounded-xl border shadow-xs bg-card flex flex-col">
          <CardHeader className="py-3 px-5 border-b flex flex-row items-center justify-between space-y-0">
            <CardTitle className="text-sm font-semibold text-foreground flex items-center gap-1.5">
              <Globe className="size-3.5 text-primary" /> Activations by Country
            </CardTitle>
            <span className="text-[11px] text-muted-foreground font-mono">Geographic Telemetry</span>
          </CardHeader>
          <CardContent className="pt-4 pb-3 flex-1 flex flex-col justify-center">
            {data?.activationsByCountry && data.activationsByCountry.length > 0 ? (
              <div className="space-y-3 px-1">
                {data.activationsByCountry.map((item) => (
                  <div key={item.country} className="space-y-1">
                    <div className="flex items-center justify-between text-xs">
                      <span className="font-medium text-foreground flex items-center gap-1.5">
                        <span className="font-mono text-[11px] px-1 py-0.5 rounded bg-muted/60 text-muted-foreground">
                          {item.code}
                        </span>
                        {item.country}
                      </span>
                      <span className="font-mono text-muted-foreground">
                        <strong className="text-foreground">{item.count}</strong> ({item.percentage}%)
                      </span>
                    </div>
                    <div className="h-1.5 w-full bg-muted/40 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-primary rounded-full transition-all duration-500"
                        style={{ width: `${item.percentage}%` }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="py-8 text-center text-xs text-muted-foreground space-y-1.5">
                <Globe className="size-6 text-muted-foreground/40 mx-auto" />
                <p>There are no Activations or Trial Activations on the account.</p>
                <p className="text-[11px] text-muted-foreground/70">
                  Activations will appear here as client software binds hardware seats.
                </p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
