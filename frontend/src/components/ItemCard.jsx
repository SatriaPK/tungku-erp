import PackageCard from "./packages/PackageCard";
import Badge from "./Badge";
import { CalendarDays, FileText, Package, ChevronRight } from "lucide-react";
import { money, formatDate, humanize } from "../lib/contract";
import { stages } from "../config/resources.js";
import { itemTitle } from "../lib/resource.js";

export default function ItemCard({ row, config, onOpen }) {
  if (config.path === "quotations")
    return <PackageCard row={row} onOpen={onOpen} quotation />;
  if (config.path === "packages")
    return <PackageCard row={row} onOpen={onOpen} />;
  return (
    <button className="item-card" onClick={() => onOpen(row)}>
      <div className="card-icon">
        {config.path === "packages" ? (
          <Package size={24} />
        ) : config.path === "bookings" || config.path === "sessions" ? (
          <CalendarDays size={24} />
        ) : (
          <FileText size={24} />
        )}
      </div>
      <div className="card-top">
        <span className="muted">{row.id ? `#${row.id}` : "STUDIO"}</span>
        {row.status && <Badge value={row.status} label={row.status_label} />}
      </div>
      <h3>{itemTitle(row)}</h3>
      {row.description && <p>{row.description}</p>}
      {row.client_name && <p>{row.client_name}</p>}
      {row.client?.name && <p>{row.client.name}</p>}
      {row.project?.name && <p>{row.project.name}</p>}
      {row.start_at && (
        <p>
          {formatDate(row.start_at)}
          <br />
          s.d. {formatDate(row.end_at)}
        </p>
      )}
      {row.track_count != null && (
        <p>
          {row.track_count} lagu · {row.recording_hours} jam rekaman
        </p>
      )}
      {row.track_count != null && (
        <div className="tags">
          {stages
            .filter((s) => row[`include_${s}`])
            .map((s) => (
              <span key={s}>{humanize(s)}</span>
            ))}
        </div>
      )}
      <footer>
        <strong>
          {row.price != null
            ? money(row.price)
            : row.amount != null
              ? money(row.amount)
              : row.total != null
                ? money(row.total)
                : row.agreed_price != null
                  ? money(row.agreed_price)
                  : row.latest_offer?.price != null
                    ? money(row.latest_offer.price)
                    : "Lihat detail"}
        </strong>
        <ChevronRight size={18} />
      </footer>
    </button>
  );
}
