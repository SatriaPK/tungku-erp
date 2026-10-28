import DesignIcon from "./DesignIcon";

export default function CollectionToolbar({
  metrics,
  query,
  onQuery,
  searchLabel,
  view,
  onView,
  filter,
  onFilter,
  filters,
  children,
  sort,
  onSort,
}) {
  return (
    <>
      <div className="package-stats collection-stats">
        {metrics.map((metric) => (
          <section
            className={`package-stat ${metric.tone || metric.key}`}
            key={metric.key}
          >
            <div>
              <span>{metric.label}</span>
              <strong>{metric.value}</strong>
            </div>
            <DesignIcon name={metric.icon} />
          </section>
        ))}
      </div>
      <section className="package-toolbar panel collection-toolbar">
        <div className="package-toolbar-row">
          <label className="search">
            <DesignIcon name="search" />
            <input
              aria-label={searchLabel}
              placeholder={`${searchLabel}...`}
              value={query}
              onChange={(event) => onQuery(event.target.value)}
            />
          </label>
          <span className="view-label">Mode Lihat:</span>
          <div className="view-options">
            <button
              className={view === "cards" ? "selected" : ""}
              aria-pressed={view === "cards"}
              onClick={() => onView("cards")}
            >
              <DesignIcon name="grid-view" />
              Kartu
            </button>
            <button
              className={view === "table" ? "selected" : ""}
              aria-pressed={view === "table"}
              onClick={() => onView("table")}
            >
              <DesignIcon name="data-table" />
              Tabel
            </button>
          </div>
          {onSort && (
            <div className="collection-sort">
              <button
                className={sort === "name" ? "selected" : ""}
                aria-pressed={sort === "name"}
                onClick={() => onSort("name")}
              >
                A–Z
              </button>
              <button
                className={sort === "newest" ? "selected" : ""}
                aria-pressed={sort === "newest"}
                onClick={() => onSort("newest")}
              >
                Terbaru
              </button>
            </div>
          )}
          {children}
        </div>
        <div className="package-filters">
          {filters.map((item) => (
            <button
              key={item.key}
              className={filter === item.key ? "selected" : ""}
              aria-pressed={filter === item.key}
              onClick={() => onFilter(item.key)}
            >
              {item.label} ({item.count})
            </button>
          ))}
        </div>
      </section>
    </>
  );
}
