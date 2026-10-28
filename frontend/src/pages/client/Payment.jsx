import ErrorNotice from "../../components/ErrorNotice";
import DataView from "../../components/DataView";
import PageTitle from "../../components/PageTitle";
import Loading from "../../components/Loading";
import Empty from "../../components/Empty";
import { RefreshCw } from "lucide-react";
import { workflowContext, positiveId } from "../../lib/workflow";
import navigate from "../../lib/navigation.js";
import { unwrap } from "../../lib/resource.js";
import { finalPaymentAction } from "../../lib/actions.js";
import useResource from "../../hooks/useResource.js";

export default function ClientPayment({ openAction }) {
  const id = workflowContext().payment;
  const resource = useResource(id ? `/client/payments/${id}` : null);
  const data = unwrap(resource.data);
  return (
    <>
      <PageTitle
        title="Pembayaran Saya"
        subtitle="Status terbaru dari studio. Selesai di gateway belum berarti pembayaran lunas."
      >
        <button disabled={!id} onClick={resource.reload}>
          <RefreshCw size={16} />
          Periksa Status
        </button>
      </PageTitle>
      <ErrorNotice error={resource.error} />
      {resource.loading ? (
        <Loading />
      ) : data ? (
        <section className="panel">
          <DataView data={data} />
          <div className="detail-actions">
            {data.type === "final" &&
              ["pending", "failed", "expired"].includes(data.status) && (
                <button
                  className="primary"
                  onClick={() =>
                    openAction(finalPaymentAction(id), resource.reload)
                  }
                >
                  Pelunasan
                </button>
              )}
            {data.status === "pending_manual_confirmation" && (
              <p className="muted">
                Pembayaran tunai menunggu konfirmasi Manager.
              </p>
            )}
            {positiveId(data.invoice?.id) && (
              <button
                onClick={() =>
                  navigate(`/client/invoice?id=${data.invoice.id}`)
                }
              >
                Buka Invoice
              </button>
            )}
            {positiveId(data.project?.id) && (
              <button
                onClick={() =>
                  navigate(`/client/delivery?project=${data.project.id}`)
                }
              >
                Lihat Hasil Project
              </button>
            )}
          </div>
        </section>
      ) : (
        !resource.error && (
          <Empty text="Buka pembayaran melalui invoice atau notifikasi." />
        )
      )}
    </>
  );
}
