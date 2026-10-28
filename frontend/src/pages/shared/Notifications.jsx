import ErrorNotice from "../../components/ErrorNotice";
import PageTitle from "../../components/PageTitle";
import Loading from "../../components/Loading";
import Empty from "../../components/Empty";
import Pagination from "../../components/Pagination";
import { useState } from "react";
import { ChevronRight, Check } from "lucide-react";
import { request } from "../../lib/api";
import { formatDate } from "../../lib/contract";
import { notificationRoute } from "../../lib/workflow";
import navigate from "../../lib/navigation.js";
import { rowsOf } from "../../lib/resource.js";
import useResource from "../../hooks/useResource.js";

export default function Notifications({ role, onRead }) {
  const [page, setPage] = useState(1),
    [filter, setFilter] = useState("");
  const resource = useResource("/notifications", {
    page,
    per_page: 15,
    ...(filter !== "" ? { unread: filter } : {}),
  });
  const [error, setError] = useState(null),
    [busy, setBusy] = useState(false);
  async function mark(item) {
    setBusy(true);
    setError(null);
    try {
      await request(
        item ? `/notifications/${item.id}/read` : "/notifications/read-all",
        { method: "PATCH" },
      );
      resource.reload();
      onRead();
      if (item) {
        const destination = notificationRoute(role, item);
        if (destination) navigate(destination);
      }
    } catch (error) {
      setError(error);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <PageTitle
        title="Notifikasi"
        subtitle="Pembaruan pekerjaan, jadwal, dan pembayaran."
      >
        <button disabled={busy} onClick={() => mark(null)}>
          <Check size={16} />
          Tandai Semua Dibaca
        </button>
      </PageTitle>
      <div className="action-strip">
        <select
          aria-label="Filter notifikasi"
          value={filter}
          onChange={(e) => {
            setFilter(e.target.value);
            setPage(1);
          }}
        >
          <option value="">Semua</option>
          <option value="1">Belum Dibaca</option>
          <option value="0">Sudah Dibaca</option>
        </select>
      </div>
      <ErrorNotice error={error || resource.error} />
      {resource.loading ? (
        <Loading />
      ) : (
        <section className="panel inbox">
          {rowsOf(resource.data).map((item) => (
            <button
              disabled={busy}
              className={`notification ${item.read_at ? "" : "unread"}`}
              onClick={() => mark(item)}
              key={item.id}
            >
              <span className="notification-dot" />
              <div>
                <strong>{item.data?.title || "Pembaruan Tungku Studio"}</strong>
                <p>
                  {item.data?.body ||
                    (item.data?.invoice_number
                      ? `Invoice ${item.data.invoice_number}`
                      : item.data?.order_id
                        ? `Pembayaran ${item.data.order_id}`
                        : "Ada pembaruan pada akun kamu.")}
                </p>
                <small>{formatDate(item.created_at)}</small>
              </div>
              <ChevronRight size={18} />
            </button>
          ))}
          {!rowsOf(resource.data).length && (
            <Empty text="Belum ada notifikasi." />
          )}
        </section>
      )}
      {resource.data?.meta && (
        <Pagination meta={resource.data.meta} onChange={setPage} />
      )}
    </>
  );
}
