import CollectionToolbar from "../CollectionToolbar";
import DesignIcon from "../DesignIcon";
import { stages } from "../../config/resources";

const icons = {
  recording: "modal-mic",
  editing: "modal-cut",
  mixing: "modal-mix",
  mastering: "modal-graphic-eq",
};
export default function PackageToolbar({
  rows,
  total,
  query,
  onQuery,
  stage,
  onStage,
  view,
  onView,
  onCreate,
  onCalculate,
}) {
  const counts = Object.fromEntries(
    stages.map((stage) => [
      stage,
      rows.filter((row) => row[`include_${stage}`]).length,
    ]),
  );
  const filters = [
    { key: "", label: "Semua Paket", count: rows.length },
    ...stages.map((stage) => ({
      key: stage,
      label: stage[0].toUpperCase() + stage.slice(1),
      count: counts[stage],
    })),
  ];
  const metrics = filters.map((item) => ({
    ...item,
    value: item.key ? item.count : (total ?? rows.length),
    icon: icons[item.key] || "package-summary",
  }));
  return (
    <>
      <CollectionToolbar
        metrics={metrics}
        query={query}
        onQuery={onQuery}
        searchLabel="Cari nama paket"
        view={view}
        onView={onView}
        filter={stage}
        onFilter={onStage}
        filters={filters}
      >
        <button className="primary" onClick={onCreate}>
          <span aria-hidden="true">+</span>Buat Paket
        </button>
        {onCalculate && (
          <details className="collection-more">
            <summary aria-label="Aksi paket lainnya">
              <DesignIcon name="more-vert" />
            </summary>
            <button onClick={onCalculate}>Kalkulator Harga</button>
          </details>
        )}
      </CollectionToolbar>
      {total > rows.length && (
        <small className="muted collection-pagination-note">
          Jumlah per tahap dihitung dari paket pada halaman ini.
        </small>
      )}
    </>
  );
}
