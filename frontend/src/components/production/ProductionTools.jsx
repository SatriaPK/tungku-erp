import ErrorNotice from "../ErrorNotice";
import DataView from "../DataView";
import Field from "../Field";
import Loading from "../Loading";
import Pagination from "../Pagination";
import { useState } from "react";
import {
  notificationRoute,
  workflowContext,
  positiveId,
} from "../../lib/workflow";
import { unwrap, rowsOf } from "../../lib/resource.js";
import { f, make } from "../../lib/actions.js";
import useResource from "../../hooks/useResource.js";
import ProductionBoard from "./ProductionBoard.jsx";

export default function ProductionTools({
  openAction,
  reload = () => {},
  operator = false,
}) {
  const context = useResource(
    operator ? "/operator/sessions" : "/manager/dashboard",
  );
  const [ids, setIds] = useState(workflowContext);
  const [notificationPage, setNotificationPage] = useState(1),
    [selected, setSelected] = useState(""),
    [historyPath, setHistoryPath] = useState(null);
  const notifications = useResource("/notifications", {
    page: notificationPage,
    per_page: 15,
  });
  const history = useResource(historyPath);
  const role = operator ? "operator" : "manager";
  const assignments = rowsOf(notifications.data)
    .map((item) => ({ item, route: notificationRoute(role, item) }))
    .filter(({ route }) =>
      route?.startsWith(`/${role}/${operator ? "tasks" : "project"}?`),
    );
  const productionStages = operator
    ? context.data?.meta?.production_stages
    : (
        context.data?.meta?.workflow || unwrap(context.data)?.workflow
      )?.projects?.flatMap((project) => project.stages);
  if (context.loading) return <Loading />;
  if (Array.isArray(productionStages))
    return (
      <ProductionBoard
        stages={productionStages}
        operator={operator}
        openAction={openAction}
        reload={() => {
          context.reload();
          reload();
        }}
      />
    );
  function pathIds(names) {
    for (const name of names)
      if (!/^\d+$/.test(String(ids[name])) || Number(ids[name]) < 1)
        return false;
    return true;
  }
  const progress = encodeURIComponent(ids.progress),
    stage = encodeURIComponent(ids.stage),
    track = encodeURIComponent(ids.track),
    project = encodeURIComponent(ids.project),
    submission = encodeURIComponent(ids.submission);
  return (
    <section className="panel">
      <h2>{operator ? "Pekerjaan Produksi" : "Review Hasil Produksi"}</h2>
      <p className="muted">
        Buka pekerjaan dari sesi atau pilih notifikasi penugasan. Referensi yang
        tersedia akan terisi otomatis.
      </p>
      <ErrorNotice error={notifications.error} />
      <label className="field">
        <span>Penugasan dari Notifikasi</span>
        <select
          value={selected}
          onChange={(e) => {
            const route = e.target.value;
            setSelected(route);
            if (route) {
              setIds(workflowContext(route.split("?")[1]));
              setHistoryPath(null);
            }
          }}
        >
          <option value="">Pilih penugasan...</option>
          {assignments.map(({ item, route }) => (
            <option key={item.id} value={route}>
              {item.data?.title || "Pekerjaan Studio"} ·{" "}
              {item.data?.body || `#${item.data?.target?.id}`}
            </option>
          ))}
        </select>
      </label>
      {notifications.data?.meta && (
        <Pagination
          meta={notifications.data.meta}
          onChange={setNotificationPage}
        />
      )}
      {ids.project && (
        <p>
          Project #{ids.project}
          {ids.stage ? ` · Tahap #${ids.stage}` : ""}
        </p>
      )}
      <details className="panel" open={!ids.stage && !ids.progress}>
        <summary>Referensi Pekerjaan</summary>
        <p className="muted">
          Lengkapi hanya referensi yang belum tersedia pada detail atau
          notifikasi. Studio memverifikasi akses dan kesiapan setiap tahap.
        </p>
        <div className="form-grid">
          {(operator
            ? ["stage", "progress", "track"]
            : ["project", "stage", "track", "progress", "submission"]
          ).map((name) => (
            <Field
              key={name}
              field={f(name, "integer; minimum=1", false)}
              value={ids[name]}
              onChange={(value) => {
                setIds((v) => ({ ...v, [name]: value }));
                setHistoryPath(null);
              }}
            />
          ))}
        </div>
      </details>
      <div className="detail-actions">
        {operator ? (
          <>
            <button
              disabled={!pathIds(["stage"])}
              onClick={() =>
                openAction(
                  make(
                    "Konfirmasi Tenggat",
                    `/operator/stages/${stage}/confirm-deadline`,
                    "ConfirmStageDeadlineRequest",
                    {
                      description:
                        "Tenggat hanya dapat ditetapkan sekali pada tahap yang siap.",
                    },
                  ),
                  reload,
                )
              }
            >
              Tetapkan Tenggat
            </button>
            <button
              className="primary"
              disabled={!pathIds(["progress", "track"])}
              onClick={() =>
                openAction(
                  make(
                    "Kirim Hasil Track",
                    `/operator/track-progress/${progress}/tracks/${track}/submissions`,
                    "SubmitTrackResultRequest",
                    {
                      description:
                        "Kirim tautan hasil. Setiap revisi dibuat sebagai versi baru.",
                      submit: "Kirim Hasil",
                    },
                  ),
                  reload,
                )
              }
            >
              Kirim Hasil / Upload
            </button>
            <button
              disabled={!pathIds(["stage"])}
              onClick={() =>
                openAction(
                  make(
                    "Perpanjang Rekaman",
                    `/operator/stages/${stage}/recording-extensions`,
                    "RequestRecordingExtensionRequest",
                    {
                      description:
                        "Biaya tambahan dihitung studio dan masuk tagihan pelunasan.",
                    },
                  ),
                  reload,
                )
              }
            >
              Perpanjang Rekaman
            </button>
            <button
              disabled={!pathIds(["progress"])}
              onClick={() =>
                setHistoryPath(
                  `/operator/track-progress/${progress}/submissions`,
                )
              }
            >
              Riwayat Hasil
            </button>
          </>
        ) : (
          <>
            <button
              className="primary"
              disabled={!pathIds(["progress", "submission"])}
              onClick={() =>
                openAction(
                  make(
                    "Review Hasil Track",
                    `/manager/track-progress/${progress}/submissions/${submission}/review`,
                    "ReviewTrackResultRequest",
                    {
                      description:
                        "Catatan revisi wajib 10–5000 karakter. Approval terakhir membuka tahap selanjutnya atau membuat tagihan pelunasan.",
                    },
                  ),
                  reload,
                )
              }
            >
              Review Hasil
            </button>
            <button
              disabled={!pathIds(["project", "stage", "track"])}
              onClick={() =>
                setHistoryPath(
                  `/manager/projects/${project}/stages/${stage}/tracks/${track}/history`,
                )
              }
            >
              Lihat Riwayat Track
            </button>
          </>
        )}
      </div>
      {historyPath && (
        <section className="panel">
          <h3>Riwayat Hasil Track</h3>
          <ErrorNotice error={history.error} />
          {history.loading ? (
            <Loading />
          ) : (
            rowsOf(history.data).map((item) => (
              <article className="data-item" key={item.id}>
                <DataView data={item} />
                {!operator && positiveId(item.track_stage_progress_id) && (
                  <button
                    onClick={() => {
                      setIds((v) => ({
                        ...v,
                        progress: String(item.track_stage_progress_id),
                        submission: String(item.id),
                      }));
                      setHistoryPath(null);
                    }}
                  >
                    Pilih Versi {item.version} untuk Review
                  </button>
                )}
              </article>
            ))
          )}
        </section>
      )}
    </section>
  );
}
