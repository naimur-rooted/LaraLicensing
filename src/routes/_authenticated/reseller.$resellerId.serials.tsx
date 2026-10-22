import { createFileRoute, useNavigate, useSearch } from "@tanstack/react-router";
import { Sparkles, Cpu, Search } from "lucide-react";

import { PageHeader } from "../../components/shell/PageHeader";
import { ResellerSerialMintingStudio } from "../../components/reseller/ResellerSerialMintingStudio";
import { MachineBindingTroubleshooter } from "../../components/reseller/MachineBindingTroubleshooter";
import { SerialLookupPanel } from "../../components/reseller/SerialLookupPanel";
import { TabButton } from "../../components/reseller/TabButton";

interface SerialsSearchParams {
  view?: "mint" | "troubleshoot" | "lookup";
}

export const Route = createFileRoute("/_authenticated/reseller/$resellerId/serials")({
  ssr: false,
  validateSearch: (raw: Record<string, unknown>): SerialsSearchParams => ({
    view: raw.view === "troubleshoot" || raw.view === "lookup" ? raw.view : "mint",
  }),
  head: () => ({
    meta: [
      { title: "Serial Studio & Hardware Diagnostics | Reseller Portal" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: ResellerSerialsPage,
});

function ResellerSerialsPage() {
  const { resellerId } = Route.useParams();
  const search = useSearch({ from: "/_authenticated/reseller/$resellerId/serials" });
  const navigate = useNavigate();
  const activeTab = search.view ?? "mint";
  const numResellerId = Number(resellerId);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Serial & Hardware Studio"
        breadcrumbs={[
          { label: "Reseller", to: `/reseller/${resellerId}/licenses` },
          { label: "Serials" },
        ]}
        description="Mint branded license keys with vanity prefixes and troubleshoot downstream hardware activations."
      />

      <div className="flex border-b border-border">
        <TabButton
          active={activeTab === "mint"}
          icon={Sparkles}
          label="Minting Studio"
          onClick={() => void navigate({ search: { view: "mint" } })}
        />
        <TabButton
          active={activeTab === "troubleshoot"}
          icon={Cpu}
          label="Hardware Troubleshooting"
          onClick={() => void navigate({ search: { view: "troubleshoot" } })}
        />
        <TabButton
          active={activeTab === "lookup"}
          icon={Search}
          label="Serial Lookup"
          onClick={() => void navigate({ search: { view: "lookup" } })}
        />
      </div>

      {activeTab === "mint" ? (
        <ResellerSerialMintingStudio resellerId={numResellerId} />
      ) : activeTab === "troubleshoot" ? (
        <MachineBindingTroubleshooter />
      ) : (
        <SerialLookupPanel />
      )}
    </div>
  );
}
