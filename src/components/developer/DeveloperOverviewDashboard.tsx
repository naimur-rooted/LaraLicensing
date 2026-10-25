import { OverviewHeader } from "./overview/OverviewHeader";
import { OverviewKpiCards } from "./overview/OverviewKpiCards";
import { OverviewEmptyState } from "./overview/OverviewEmptyState";
import { OverviewTimeSeriesChart } from "./overview/OverviewTimeSeriesChart";
import { OverviewBottomCharts } from "./overview/OverviewBottomCharts";
import { ZERO_STATE_OVERVIEW } from "./overview/overview-types";
import { useDeveloperOverview } from "./overview/useDeveloperOverview";

interface DeveloperOverviewDashboardProps {
  readonly onNavigateToProducts?: () => void;
  readonly onNavigateToLicenses?: () => void;
}

export function DeveloperOverviewDashboard({
  onNavigateToProducts,
  onNavigateToLicenses,
}: DeveloperOverviewDashboardProps) {
  const {
    data,
    isRefreshing,
    selectedProduct,
    setSelectedProduct,
    productsList,
    isPreviewMode,
    fetchOverview,
  } = useDeveloperOverview();

  const hasProducts = (data?.productsCount ?? 0) > 0;

  return (
    <div className="space-y-5 animate-in fade-in duration-300">
      <OverviewHeader
        trialExpiresOn={data?.trialExpiresOn ?? "Active"}
        isPreviewMode={isPreviewMode}
        selectedProduct={selectedProduct}
        productsList={productsList}
        isRefreshing={isRefreshing}
        onProductChange={(slug) => {
          setSelectedProduct(slug);
          fetchOverview(slug);
        }}
        onRefresh={() => fetchOverview()}
      />

      <OverviewKpiCards kpis={data?.kpis ?? ZERO_STATE_OVERVIEW.kpis} />

      {hasProducts ? (
        <>
          <OverviewTimeSeriesChart timeline={data?.timeline ?? []} />
          <OverviewBottomCharts
            timeline={data?.timeline ?? []}
            activationsByCountry={data?.activationsByCountry ?? []}
            totalLicenses={data?.kpis?.licenses?.current ?? 0}
            onNavigateToLicenses={onNavigateToLicenses}
          />
        </>
      ) : (
        <OverviewEmptyState onNavigateToProducts={onNavigateToProducts} />
      )}
    </div>
  );
}
