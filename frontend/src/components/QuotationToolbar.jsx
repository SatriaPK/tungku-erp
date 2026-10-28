import CollectionToolbar from "./CollectionToolbar";

const states = [
  ["", "Semua Penawaran", "quotation-handshake1", "secondary"],
  ["awaiting_studio", "Klien Menawarkan Harga", "quotation-mic", "warning"],
  ["awaiting_client", "Tungku Menawarkan Harga", "quotation-cut", "secondary"],
  ["accepted", "Diterima", "quotation-thumbup", "success"],
  ["declined", "Ditolak", "quotation-thumbdown", "error"],
];
export default function QuotationToolbar({
  rows,
  total,
  query,
  onQuery,
  view,
  onView,
  status,
  onStatus,
  sort,
  onSort,
}) {
  const filters = states.map(([key, label]) => ({
    key,
    label,
    count: key ? rows.filter((row) => row.status === key).length : rows.length,
  }));
  const metrics = states.map(([key, label, icon, tone], index) => ({
    key,
    label,
    icon,
    tone,
    value: key ? filters[index].count : (total ?? rows.length),
  }));
  return (
    <CollectionToolbar
      metrics={metrics}
      query={query}
      onQuery={onQuery}
      searchLabel="Cari penawaran"
      view={view}
      onView={onView}
      filter={status}
      onFilter={onStatus}
      filters={filters}
      sort={sort}
      onSort={onSort}
    />
  );
}
