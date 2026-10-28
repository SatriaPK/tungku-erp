import ErrorNotice from "./ErrorNotice";
import Loading from "./Loading";
import Pagination from "./Pagination";
import { useState } from "react";
import { formatDate } from "../lib/contract";
import { rowsOf } from "../lib/resource.js";
import useResource from "../hooks/useResource.js";

export default function Recommendations() {
  const [page, setPage] = useState(1);
  const resource = useResource("/manager/crm/recommendations", {
    page,
    per_page: 15,
  });
  return (
    <details className="panel">
      <summary>Rekomendasi Klien untuk Kampanye</summary>
      <p className="muted">
        Pilih ID penerima untuk kampanye. Pengiriman memerlukan akun aktif dan
        persetujuan CRM.
      </p>
      <ErrorNotice error={resource.error} />
      {resource.loading ? (
        <Loading />
      ) : (
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>ID Klien</th>
                <th>Nama</th>
                <th>Email</th>
                <th>Pembayaran Terakhir</th>
              </tr>
            </thead>
            <tbody>
              {rowsOf(resource.data).map((row) => (
                <tr key={row.id}>
                  <td>{row.id}</td>
                  <td>{row.name}</td>
                  <td>{row.email}</td>
                  <td>{formatDate(row.last_paid_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {resource.data?.meta && (
        <Pagination meta={resource.data.meta} onChange={setPage} />
      )}
    </details>
  );
}
