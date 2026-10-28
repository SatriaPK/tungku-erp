import DesignIcon from "../DesignIcon";
import { ArrowRight, RefreshCw } from "lucide-react";
import Badge from "../Badge";
import Empty from "../Empty";
import { money, formatDate } from "../../lib/contract";
import navigate from "../../lib/navigation";

const colors = [
  "var(--ts-primary)",
  "var(--ts-secondary)",
  "var(--ts-tertiary)",
  "var(--ts-success)",
  "var(--ts-warning)",
];
function comparisonLabel(metric) {
  return metric?.percentage == null
    ? 'Belum ada pembanding'
    : `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(metric.percentage)}% dari bulan lalu`;
}
function Metric({ label, value, detail, icon, tone, path }) {
  return (
    <a className={`overview-metric ${tone}`} href={path} aria-label={`Buka ${label}`}
      onClick={event => { if (!event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) { event.preventDefault(); navigate(path); } }}>
      <div className="metric-caption">
        <small>{detail}</small>
        <ArrowRight size={16} />
      </div>
      <div className="metric-value">
        <div>
          <span>{label}</span>
          <strong>{value}</strong>
        </div>
        <span className="metric-icon">
          <DesignIcon name={icon} />
        </span>
      </div>
    </a>
  );
}
function Panel({ title, path, children, className = "", onRefresh }) {
  return (
    <section className={`panel overview-panel ${className}`}>
      <header>
        <h2>{title}</h2>
        {(path || onRefresh) && (
          <button
            className="icon-button"
            aria-label={onRefresh ? "Muat ulang dashboard" : `Buka ${title}`}
            onClick={onRefresh || (() => navigate(path))}
          >
            {onRefresh ? <RefreshCw size={16} /> : <ArrowRight size={16} />}
          </button>
        )}
      </header>
      <div className="overview-panel-body">{children}</div>
    </section>
  );
}
function MonthlyChart({ rows }) {
  const max = Math.max(
    1,
    ...rows.flatMap((row) => [row.revenue || 0, row.expense || 0]),
  );
  const points = (key) =>
    rows
      .map(
        (row, index) =>
          `${48 + (index * 640) / Math.max(1, rows.length - 1)},${240 - ((Number(row[key]) || 0) / max) * 210}`,
      )
      .join(" ");
  return (
    <>
      <div className="finance-legend">
        <span>Pendapatan</span>
        <span>Pengeluaran</span>
      </div>
      {rows.length ? (
        <div className="monthly-line-chart">
          <svg
            viewBox="0 0 720 280"
            role="img"
            aria-label="Grafik pendapatan dan pengeluaran bulanan"
          >
            {[0, 1, 2, 3, 4].map((i) => (
              <g key={i}>
                <line
                  x1="48"
                  x2="688"
                  y1={30 + i * 52.5}
                  y2={30 + i * 52.5}
                  stroke="var(--ts-secondary-container)"
                />
                <text x="40" y={34 + i * 52.5} textAnchor="end">
                  {Math.round(((4 - i) * max) / 4 / 1000)}k
                </text>
              </g>
            ))}
            <polyline
              points={points("revenue")}
              fill="none"
              stroke="var(--ts-success)"
              strokeWidth="2"
            />
            <polyline
              points={points("expense")}
              fill="none"
              stroke="var(--ts-primary)"
              strokeWidth="2"
            />
            {rows.flatMap((row, index) =>
              ["revenue", "expense"].map((key) => (
                <circle
                  key={`${row.month}-${key}`}
                  cx={48 + (index * 640) / Math.max(1, rows.length - 1)}
                  cy={240 - ((Number(row[key]) || 0) / max) * 210}
                  r="3"
                  fill={
                    key === "revenue"
                      ? "var(--ts-success)"
                      : "var(--ts-primary)"
                  }
                >
                  <title>
                    {key === "revenue" ? "Pendapatan" : "Pengeluaran"}:{" "}
                    {money(row[key])}
                  </title>
                </circle>
              )),
            )}
            {rows.map((row, i) => (
              <text
                key={row.month}
                x={48 + (i * 640) / Math.max(1, rows.length - 1)}
                y="267"
                textAnchor="middle"
              >
                {new Intl.DateTimeFormat("id-ID", {
                  month: "short",
                  timeZone: "UTC",
                }).format(new Date(Date.UTC(2026, Number(row.month) - 1, 1)))}
              </text>
            ))}
          </svg>
        </div>
      ) : (
        <Empty text="Belum ada data keuangan." />
      )}
    </>
  );
}
function Distribution({ items, labelKey, valueKey, donut }) {
  const positive = items.filter((row) => Number(row[valueKey]) > 0);
  const total = positive.reduce((sum, row) => sum + Number(row[valueKey]), 0);
  const gradient = positive
    .map((row, i) => {
      const start = positive.slice(0, i).reduce((sum, item) => sum + Number(item[valueKey]), 0) / total * 100;
      const end = start + (Number(row[valueKey]) / total) * 100;
      return `${colors[i % colors.length]} ${start}% ${end}%`;
    })
    .join(",");
  return positive.length ? (
    <>
      <div
        className={`distribution-chart ${donut ? "donut" : ""}`}
        role="img"
        aria-label={positive
          .map(
            (row) =>
              `${row[labelKey]}: ${Math.round((Number(row[valueKey]) / total) * 100)}%`,
          )
          .join(", ")}
        style={{ background: `conic-gradient(${gradient})` }}
      />
      <ul className="distribution-legend">
        {positive.map((row, i) => (
          <li key={row[labelKey]}>
            <i style={{ background: colors[i % colors.length] }} />
            <span>{row[labelKey]}</span>
            <strong>
              {Math.round((Number(row[valueKey]) / total) * 100)}%
            </strong>
          </li>
        ))}
      </ul>
    </>
  ) : (
    <Empty text="Belum ada data pada periode ini." />
  );
}
export default function ManagerOverview({ data, reload }) {
  return (
    <div className="manager-overview">
      <h1 className="sr-only">Dashboard</h1>
      <div className="overview-metrics">
        <Metric
          label="Pendapatan Bulan Ini"
          value={money(data.revenue?.current)}
          detail={comparisonLabel(data.revenue)}
          path="/manager/reports"
          icon="dashboard-trendingup"
          tone="green"
        />
        <Metric
          label="Pengeluaran Bulan Ini"
          value={money(data.expense?.current)}
          detail={comparisonLabel(data.expense)}
          path="/manager/expenses"
          icon="dashboard-trendingdown1"
          tone="red"
        />
        <Metric
          label="Pembayaran Belum Lunas"
          value={money(data.awaiting_final_payment?.amount)}
          detail={`${data.awaiting_final_payment?.project_count || 0} Project`}
          path="/manager/payments"
          icon="dashboard-accountbalancewallet"
          tone="blue"
        />
        <Metric
          label="Project Aktif"
          value={data.in_progress_projects?.count || 0}
          detail={`${data.in_progress_projects?.track_count || 0} Lagu`}
          path="/manager/project"
          icon="dashboard-folder1"
          tone="purple"
        />
        <Metric
          label="Dalam Penawaran"
          value={
            data.quotations?.open_count ?? data.quotation_progress?.length ?? 0
          }
          detail={`${data.quotations?.awaiting_studio_count || 0} Menunggu Studio`}
          path="/manager/quotation"
          icon="dashboard-handshake1"
          tone="orange"
        />
      </div>
      <div className="overview-charts">
        <Panel
          title="Laporan Keuangan"
          path="/manager/reports"
          onRefresh={reload}
        >
          <MonthlyChart rows={data.monthly || []} />
        </Panel>
        <Panel title="Paket Terlaris Bulan Ini" path="/manager/packages">
          <Distribution
            items={data.package_sales?.items || []}
            labelKey="name"
            valueKey="project_count"
          />
        </Panel>
        <Panel title="Pengeluaran Bulan Ini" path="/manager/expenses">
          <Distribution
            items={data.expense_categories?.items || []}
            labelKey="category_name"
            valueKey="amount"
            donut
          />
        </Panel>
      </div>
      <div className="overview-lists">
        <Panel title="Jadwal Rekaman Hari Ini" path="/manager/booking">
          {data.recording_schedule?.length ? (
            data.recording_schedule.map((row) => (
              <article className="overview-row" key={row.id}>
                <div>
                  <strong>{row.project_name || "Sesi Rekaman"}</strong>
                  <small>{row.client_name}</small>
                </div>
                <span>{formatDate(row.start_at)}</span>
              </article>
            ))
          ) : (
            <Empty text="Belum ada jadwal rekaman." />
          )}
        </Panel>
        <Panel title="Progress Project" path="/manager/project">
          {data.project_progress?.length ? (
            data.project_progress.map((row) => (
              <article className="overview-row" key={row.id}>
                <div>
                  <strong>{row.name}</strong>
                  <small>{row.client_name}</small>
                </div>
                <Badge
                  value={row.status}
                  label={row.current_stage_label || row.status_label}
                />
              </article>
            ))
          ) : (
            <Empty />
          )}
        </Panel>
        <Panel title="Progress Penawaran" path="/manager/quotation">
          {data.quotation_progress?.length ? (
            data.quotation_progress.map((row) => (
              <article className="overview-row" key={row.id}>
                <div>
                  <strong>{row.name || row.package_display_name}</strong>
                  <small>{row.client_name}</small>
                </div>
                <Badge value={row.status} label={row.status_label} />
              </article>
            ))
          ) : (
            <Empty text="Belum ada penawaran." />
          )}
        </Panel>
        <Panel title="Aktivitas Operator">
          {data.operator_activities?.length ? (
            data.operator_activities.map((row) => (
              <article className="overview-row" key={row.id}>
                <div>
                  <strong>{row.description}</strong>
                  <small>{formatDate(row.occurred_at)}</small>
                </div>
              </article>
            ))
          ) : (
            <Empty text="Belum ada aktivitas operator." />
          )}
        </Panel>
      </div>
    </div>
  );
}
