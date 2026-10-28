import DesignIcon from "../DesignIcon";
import { stages as phaseOrder } from "../../config/resources";
import { humanize } from "../../lib/contract";

const icons = {
  recording: "timeline-record",
  editing: "timeline-edit",
  mixing: "timeline-mix",
  mastering: "timeline-group1",
};
export default function ProductionTimeline({ stages, current, onSelect }) {
  const projectStages = stages.filter(
    (stage) => stage.project_id === current.project_id,
  );
  return (
    <div className="production-timeline" aria-label="Progres tahap produksi">
      {phaseOrder.map((phase) => {
        const item = projectStages.find((stage) => stage.stage === phase);
        const done = item?.status === "completed";
        return (
          <button
            key={phase}
            type="button"
            className={`${done ? "done" : ""} ${current.stage === phase ? "current" : ""}`}
            disabled={!item}
            aria-current={current.stage === phase ? "step" : undefined}
            onClick={() => onSelect(item.id)}
            title={
              item
                ? humanize(item.status)
                : "Tahap tidak tersedia untuk akun ini"
            }
          >
            <span>
              <DesignIcon name={icons[phase]} />
            </span>
            <strong>{humanize(phase)}</strong>
            {item && current.stage === phase && (
              <small>
                {(item.tracks || []).length
                  ? `${Math.round(((item.tracks || []).filter((track) => track.status === "approved").length / item.tracks.length) * 100)}%`
                  : humanize(item.status)}
              </small>
            )}
          </button>
        );
      })}
      <div
        className={`timeline-finish ${current.project_status === "completed" ? "done" : ""}`}
      >
        <span>
          <DesignIcon name="timeline-done" />
        </span>
        <strong>Finish</strong>
      </div>
    </div>
  );
}
