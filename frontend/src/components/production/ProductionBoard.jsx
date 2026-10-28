import ProductionSidebar from "./ProductionSidebar";
import DesignIcon from "../DesignIcon";
import ProductionTimeline from "./ProductionTimeline";
import ErrorNotice from "../ErrorNotice";
import Badge from "../Badge";
import SafeLink from "../SafeLink";
import DataView from "../DataView";
import Loading from "../Loading";
import Empty from "../Empty";
import { useState } from "react";
import { RefreshCw } from "lucide-react";
import { formatDate, humanize } from "../../lib/contract";
import { workflowContext } from "../../lib/workflow";
import { unwrap } from "../../lib/resource.js";
import { make } from "../../lib/actions.js";
import useResource from "../../hooks/useResource.js";

export default function ProductionBoard({
  stages,
  operator,
  openAction,
  reload,
}) {
  const initial = workflowContext();
  const [stageId, setStageId] = useState(
    initial.stage || String(stages[0]?.id || ""),
  );
  const stage = stages.find((row) => String(row.id) === stageId);
  const [trackId, setTrackId] = useState(initial.track || "");
  const track =
    stage?.tracks?.find((row) => String(row.id) === trackId) ||
    stage?.tracks?.[0];
  const [historyPath, setHistoryPath] = useState(null);
  const history = useResource(historyPath);
  const active =
    stage?.project_status === "in_progress" &&
    ["ready", "in_progress"].includes(stage?.status);
  const canSubmit =
    active &&
    (!stage.requires_deadline || Boolean(stage.deadline_at)) &&
    ["pending", "revision"].includes(track?.status);
  if (!stages.length)
    return (
      <section className="panel">
        <Empty
          text={
            operator
              ? "Belum ada tahap yang ditugaskan."
              : "Belum ada tahap produksi aktif."
          }
        />
      </section>
    );
  const operators = [
    ...new Set(
      stages
        .filter((row) => row.project_id === stage?.project_id)
        .map((row) => row.operator_name)
        .filter(Boolean),
    ),
  ];
  return (
    <section className="production-board production-workspace">
      <div className="production-main">
        <section className="panel production-timeline-panel">
          {" "}
          {stage && (
            <ProductionTimeline
              stages={stages}
              current={stage}
              onSelect={(id) => {
                setStageId(String(id));
                setTrackId("");
                setHistoryPath(null);
              }}
            />
          )}
        </section>
        <section className="panel production-work-table">
          <header className="production-table-heading">
            <h2>{stage?.stage_label || humanize(stage?.stage)}</h2>{" "}
            {operator &&
              active &&
              stage.requires_deadline &&
              !stage.deadline_at && (
                <button
                  onClick={() =>
                    openAction(
                      make(
                        "Konfirmasi Tenggat",
                        `/operator/stages/${stage.id}/confirm-deadline`,
                        "ConfirmStageDeadlineRequest",
                      ),
                      reload,
                    )
                  }
                >
                  Tetapkan Tenggat
                </button>
              )}
          </header>
          {stage && (
            <>
              {" "}
              <div className="table-scroll production-tracks">
                <table>
                  <thead>
                    <tr>
                      <th>Lagu</th>
                      <th>Status</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(stage.tracks || []).map((row) => (
                      <tr
                        key={row.id}
                        className={track?.id === row.id ? "selected" : ""}
                      >
                        <td>
                          <span className="avatar">
                            {(
                              stage.client_name ||
                              stage.project_name ||
                              row.title
                            )
                              .slice(0, 1)
                              .toUpperCase()}
                          </span>
                          <button
                            className="track-title"
                            onClick={() => {
                              setTrackId(row.id);
                              setHistoryPath(null);
                            }}
                          >
                            {row.title}
                          </button>
                        </td>
                        <td>
                          <Badge value={row.status} />
                        </td>
                        <td>
                          {operator && (
                            <button
                              className="primary"
                              aria-label={`Upload ${row.title}`}
                              disabled={
                                !active ||
                                (stage.requires_deadline &&
                                  !stage.deadline_at) ||
                                !["pending", "revision"].includes(row.status)
                              }
                              onClick={() =>
                                openAction(
                                  make(
                                    "Kirim Hasil Track",
                                    `/operator/track-progress/${row.progress_id}/tracks/${row.id}/submissions`,
                                    "SubmitTrackResultRequest",
                                    {
                                      submit: "Kirim Hasil",
                                      description:
                                        "Kirim tautan hasil. Revisi disimpan sebagai versi baru.",
                                    },
                                  ),
                                  reload,
                                )
                              }
                            >
                              <DesignIcon name="timeline-addlink" />
                              <span>Upload</span>
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
          <div className="production-track-count">
            {stage?.tracks?.length || 0} lagu pada tahap ini
          </div>
          {historyPath && (
            <section className="panel">
              <h3>Riwayat Hasil</h3>
              <ErrorNotice error={history.error} />
              {history.loading ? (
                <Loading />
              ) : (
                <DataView data={unwrap(history.data)} />
              )}
            </section>
          )}
        </section>
      </div>
      <ProductionSidebar stage={stage} operators={operators}>
        <details className="production-reference-controls">
          <summary>Pilih Project, Tahap & Lagu</summary>{" "}
          <div className="form-grid">
            <label className="field">
              <span>Tahap Produksi</span>
              <select
                value={stageId}
                onChange={(e) => {
                  setStageId(e.target.value);
                  setTrackId("");
                  setHistoryPath(null);
                }}
              >
                {stages.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.project_name} ·{" "}
                    {row.stage_label || humanize(row.stage)} ·{" "}
                    {humanize(row.status)}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Lagu</span>
              <select
                value={track?.id || ""}
                onChange={(e) => {
                  setTrackId(e.target.value);
                  setHistoryPath(null);
                }}
              >
                {(stage?.tracks || []).map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.title} · {humanize(row.status)}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </details>
        {stage && (
          <>
            {" "}
            <div className="action-strip">
              <Badge value={stage.status} />
              <span className="muted">
                {stage.operator_name || "Belum ditugaskan"}
                {stage.deadline_at
                  ? ` · Tenggat ${formatDate(stage.deadline_at)}`
                  : ""}
              </span>
            </div>
            <div className="production-selected-actions">
              {" "}
              {track?.submission && (
                <section className="data-item">
                  <strong>Versi {track.submission.version}</strong>
                  <p>
                    <SafeLink value={track.submission.result_url}>
                      Dengarkan Hasil
                    </SafeLink>
                  </p>
                  {track.submission.review_notes && (
                    <p>{track.submission.review_notes}</p>
                  )}
                </section>
              )}
              <div className="detail-actions">
                {operator ? (
                  <>
                    <button
                      className="primary"
                      disabled={!canSubmit}
                      onClick={() =>
                        openAction(
                          make(
                            "Kirim Hasil Track",
                            `/operator/track-progress/${track.progress_id}/tracks/${track.id}/submissions`,
                            "SubmitTrackResultRequest",
                            {
                              submit: "Kirim Hasil",
                              description:
                                "Kirim tautan hasil. Revisi disimpan sebagai versi baru.",
                            },
                          ),
                          reload,
                        )
                      }
                    >
                      Kirim Hasil / Upload
                    </button>
                    {active && stage.stage === "recording" && (
                      <button
                        onClick={() =>
                          openAction(
                            make(
                              "Perpanjang Rekaman",
                              `/operator/stages/${stage.id}/recording-extensions`,
                              "RequestRecordingExtensionRequest",
                            ),
                            reload,
                          )
                        }
                      >
                        Perpanjang Rekaman
                      </button>
                    )}
                  </>
                ) : (
                  <button
                    className="primary"
                    disabled={
                      !active ||
                      track?.status !== "in_review" ||
                      !track?.submission
                    }
                    onClick={() =>
                      openAction(
                        make(
                          "Review Hasil Track",
                          `/manager/track-progress/${track.progress_id}/submissions/${track.submission.id}/review`,
                          "ReviewTrackResultRequest",
                        ),
                        reload,
                      )
                    }
                  >
                    Review Hasil
                  </button>
                )}
                <button
                  disabled={!track}
                  onClick={() =>
                    setHistoryPath(
                      operator
                        ? `/operator/track-progress/${track.progress_id}/submissions`
                        : `/manager/projects/${stage.project_id}/stages/${stage.id}/tracks/${track.id}/history`,
                    )
                  }
                >
                  Riwayat Hasil
                </button>
                <button onClick={reload}>
                  <RefreshCw size={16} />
                  Muat Ulang
                </button>
              </div>
              {stage.status === "locked" && (
                <p className="muted">
                  Tahap ini terbuka setelah tahap sebelumnya selesai.
                </p>
              )}
              {operator &&
                active &&
                stage.requires_deadline &&
                !stage.deadline_at && (
                  <p className="muted">
                    Tetapkan tenggat sebelum mengirim hasil.
                  </p>
                )}
            </div>
          </>
        )}
      </ProductionSidebar>
    </section>
  );
}
