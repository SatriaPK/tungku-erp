import DesignIcon from "../DesignIcon";
import { stages } from "../../config/resources";
import { money } from "../../lib/contract";
const icons = {
  recording: "mic1",
  editing: "cut1",
  mixing: "mix1",
  mastering: "graphic-eq1",
};
export default function PackageCard({ row, onOpen, quotation = false }) {
  const original = row;
  row = { ...row.specifications, ...row };
  const price = quotation
    ? (row.agreed_price ?? row.latest_offer?.price ?? row.auto_price)
    : row.price;
  return (
    <button
      className={`item-card package-card ${quotation ? `quotation-card ${row.status}` : ""}`}
      onClick={() => onOpen(original)}
    >
      <div className="package-card-heading">
        <h3 className="type-title-large">
          {row.name || row.package_display_name}
        </h3>
        <DesignIcon name="more-vert" />
      </div>
      <p className="type-body-small">
        {quotation ? row.status_label : row.description}
      </p>
      <div className="tags phase-tags">
        {stages
          .filter((stage) => row[`include_${stage}`])
          .map((stage) => (
            <span className={stage} key={stage}>
              <DesignIcon name={icons[stage]} />
              {stage[0].toUpperCase() + stage.slice(1)}
            </span>
          ))}
      </div>
      <footer>
        <strong className="type-title-large">{money(price)}</strong>
        <small>
          {row.recording_hours || 0} Jam Rekaman <span>|</span>{" "}
          {row.track_count} Lagu
        </small>
      </footer>
    </button>
  );
}
