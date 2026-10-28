import Badge from "../Badge";
import { formatDate } from "../../lib/contract";

export default function ProductionSidebar({ stage, operators, children }) {
  return (
    <aside className="production-sidebar">
      <section className="panel production-team">
        <h2>Operator</h2>
        <div className="production-team-list">
          {operators.map((name) => (
            <span className="avatar" title={name} key={name}>
              {name.slice(0, 1).toUpperCase()}
            </span>
          ))}
          {!operators.length && (
            <small className="muted">Belum ditugaskan</small>
          )}
        </div>
      </section>
      <section className="panel production-project-card">
        {stage && (
          <header>
            <div className="avatar">
              {(stage.client_name || stage.project_name || "P")
                .slice(0, 1)
                .toUpperCase()}
            </div>
            <div>
              <h2>{stage.project_name}</h2>
              {stage.client_name && <p>{stage.client_name}</p>}
            </div>
            <div className="production-project-state">
              <Badge value={stage.stage} label={stage.stage_label} />
              {stage.deadline_at && (
                <small>{formatDate(stage.deadline_at)}</small>
              )}
            </div>
          </header>
        )}
        {children}
      </section>
    </aside>
  );
}
