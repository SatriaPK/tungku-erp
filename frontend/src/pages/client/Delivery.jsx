import ErrorNotice from "../../components/ErrorNotice";
import DataView from "../../components/DataView";
import PageTitle from "../../components/PageTitle";
import Loading from "../../components/Loading";
import Empty from "../../components/Empty";
import Pagination from "../../components/Pagination";
import { useState } from "react";
import { RefreshCw } from "lucide-react";
import { workflowContext, projectChoices } from "../../lib/workflow";
import { unwrap, rowsOf } from "../../lib/resource.js";
import useResource from "../../hooks/useResource.js";

export default function Delivery({ openAction }) {
  const [page, setPage] = useState(1),
    [id, setId] = useState(workflowContext().project);
  const invoices = useResource("/client/invoices", { page });
  const projects = projectChoices(rowsOf(invoices.data));
  const delivery = useResource(
    id ? `/client/projects/${id}/final-delivery` : null,
  );
  return (
    <>
      <PageTitle
        title="Hasil Akhir"
        subtitle="Akses hasil produksi setelah pelunasan diverifikasi studio."
      />
      <section className="panel">
        <h2>Karya yang siap didengarkan</h2>
        <p className="muted">
          Pilih project dari invoice, atau buka hasil melalui notifikasi studio.
        </p>
        <ErrorNotice error={invoices.error} />
        <div className="action-strip">
          <label className="field">
            <span>Project</span>
            <select value={id} onChange={(e) => setId(e.target.value)}>
              <option value="">Pilih project...</option>
              {id && !projects.some((p) => p.id === id) && (
                <option value={id}>
                  Project #{id} dari invoice / notifikasi
                </option>
              )}
              {projects.map((project) => (
                <option key={project.id} value={project.id}>
                  {project.name}
                </option>
              ))}
            </select>
          </label>
          <button disabled={!id} onClick={delivery.reload}>
            <RefreshCw size={16} />
            Periksa Hasil
          </button>
        </div>
        {invoices.data?.meta && (
          <Pagination meta={invoices.data.meta} onChange={setPage} />
        )}
      </section>
      {id && (
        <section className="panel">
          <h2>Hasil Project</h2>
          <ErrorNotice error={delivery.error} />
          {delivery.loading ? (
            <Loading />
          ) : (
            !delivery.error && <DataView data={unwrap(delivery.data)} />
          )}
        </section>
      )}
      {!id && !invoices.loading && !projects.length && (
        <Empty text="Belum ada project pada halaman invoice ini." />
      )}
    </>
  );
}
