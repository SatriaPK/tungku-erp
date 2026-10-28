import QuotationToolbar from "../../components/QuotationToolbar";
import ClientQuoteComposer from "../../components/packages/ClientQuoteComposer";
import BookingCalendar from "../../components/BookingCalendar";
import PackageToolbar from "../../components/packages/PackageToolbar";
import { money } from "../../lib/contract";
import ErrorNotice from "../../components/ErrorNotice";
import DataView from "../../components/DataView";
import PageTitle from "../../components/PageTitle";
import Loading from "../../components/Loading";
import Empty from "../../components/Empty";
import Pagination from "../../components/Pagination";
import { useCallback, useEffect, useState } from "react";
import { Search, Plus, RefreshCw, X } from "lucide-react";
import { request } from "../../lib/api";
import { workflowContext, positiveId } from "../../lib/workflow";
import { resourceConfigs } from "../../config/resources.js";
import navigate from "../../lib/navigation.js";
import { unwrap, rowsOf, itemTitle } from "../../lib/resource.js";
import { f, make, finalPaymentAction } from "../../lib/actions.js";
import useResource from "../../hooks/useResource.js";
import ItemCard from "../../components/ItemCard.jsx";
import Recommendations from "../../components/Recommendations.jsx";
import BasePrices from "../../components/BasePrices.jsx";

export default function ResourcePage({ role, page, openAction }) {
  const config = resourceConfigs[page];
  const path = `/${role}/${config.path}`;
  const [number, setNumber] = useState(1),
    [query, setQuery] = useState("");
  const resource = useResource(
    path,
    page === "settings" || (role === "client" && page === "packages")
      ? undefined
      : { page: number },
  );
  useEffect(() => {
    const search = (event) => setQuery(event.detail);
    window.addEventListener("workspace-search", search);
    return () => window.removeEventListener("workspace-search", search);
  }, []);
  const [detail, setDetail] = useState(null),
    [detailError, setDetailError] = useState(null),
    [detailBusy, setDetailBusy] = useState(false);
  const [packageStage, setPackageStage] = useState("");
  const [packageView, setPackageView] = useState("cards");
  const [quotationStatus, setQuotationStatus] = useState("");
  const [quotationSort, setQuotationSort] = useState("newest");
  const allRows = rowsOf(resource.data);
  const rows = allRows
    .filter((row) => !packageStage || row[`include_${packageStage}`])
    .filter((row) => !quotationStatus || row.status === quotationStatus)
    .filter((row) =>
      `${itemTitle(row)} ${row.status_label || ""} ${row.client_name || ""} ${row.client?.name || ""}`
        .toLowerCase()
        .includes(query.toLowerCase()),
    );
  if (page === "quotation")
    rows.sort((a, b) =>
      quotationSort === "name"
        ? itemTitle(a).localeCompare(itemTitle(b), "id")
        : (Date.parse(b.updated_at || b.created_at) || 0) -
          (Date.parse(a.updated_at || a.created_at) || 0),
    );
  const meta = resource.data?.meta;
  const refresh = () => {
    resource.reload();
    setDetail(null);
  };
  const loadDetail = useCallback(
    async (row) => {
      setDetailError(null);
      setDetailBusy(true);
      setDetail(null);
      try {
        if (
          page === "settings" ||
          (role === "client" && page === "packages") ||
          page.endsWith("-categories")
        )
          setDetail(row);
        else setDetail(unwrap(await request(`${path}/${row.id}`)));
      } catch (error) {
        setDetailError(error);
      } finally {
        setDetailBusy(false);
      }
    },
    [page, role, path],
  );
  useEffect(() => {
    const id = workflowContext().id;
    if (id) loadDetail({ id });
  }, [loadDetail]);
  function edit() {
    openAction(
      make(`Ubah ${config.title}`, `${path}/${detail.id}`, config.schema, {
        method: "PUT",
        initial: { ...detail },
      }),
      refresh,
    );
  }
  function act(action) {
    openAction(action, refresh);
  }
  const quotationTurn =
    detail &&
    (role === "manager"
      ? detail.status === "awaiting_studio"
      : detail.status === "awaiting_client");
  return (
    <>
      {page === "packages" && role === "manager" && (
        <PackageToolbar
          rows={allRows}
          total={resource.data?.meta?.total}
          query={query}
          onQuery={setQuery}
          stage={packageStage}
          onStage={setPackageStage}
          view={packageView}
          onView={setPackageView}
          onCalculate={() =>
            act(
              make(
                "Hitung Harga Paket",
                `${path}/calculate-price`,
                "CalculatePackagePriceRequest",
                { submit: "Hitung", success: "Estimasi harga dari studio." },
              ),
            )
          }
          onCreate={() =>
            act(
              make("Buat Paket Baru", path, config.schema, {
                submit: "Buat Paket",
              }),
            )
          }
        />
      )}
      {page === "quotation" && role === "manager" && (
        <QuotationToolbar
          rows={allRows}
          total={meta?.total}
          query={query}
          onQuery={setQuery}
          view={packageView}
          onView={setPackageView}
          status={quotationStatus}
          onStatus={setQuotationStatus}
          sort={quotationSort}
          onSort={setQuotationSort}
        />
      )}
      {page === "packages" && role === "client" && (
        <div className="catalog-intro">
          <h1>Paket yang Kami Sediakan</h1>
          <p>
            Pilih paket siap pakai atau buat penawaran kustom untuk proyek Anda.
          </p>
          <h2>Paket Siap Pakai</h2>
          <p>
            Pilih paket yang sudah jadi, lalu lanjutkan ke pemilihan slot waktu
            dan booking.
          </p>
        </div>
      )}
      <PageTitle title={config.title} subtitle={config.subtitle}>
        <button
          className="icon-button"
          onClick={resource.reload}
          aria-label="Muat ulang"
        >
          <RefreshCw size={18} />
        </button>
        {config.schema && role === "manager" && (
          <button
            className="primary"
            onClick={() =>
              act(
                make(
                  page === "packages"
                    ? "Buat Paket Baru"
                    : `Tambah ${config.title}`,
                  path,
                  config.schema,
                  page === "packages" ? { submit: "Buat Paket" } : {},
                ),
              )
            }
          >
            <Plus size={17} />
            Tambah
          </button>
        )}
        {page === "quotation" && role === "client" && (
          <button
            className="primary"
            onClick={() =>
              act(make("Ajukan Penawaran", path, "SubmitQuotationRequest"))
            }
          >
            <Plus size={17} />
            Ajukan Penawaran
          </button>
        )}
      </PageTitle>
      {page === "booking" && role === "client" && (
        <div className="action-strip">
          <button
            onClick={() =>
              act(
                make(
                  "Cek Ketersediaan",
                  `${path}/check-availability`,
                  "CheckAvailabilityRequest",
                  {
                    submit: "Periksa",
                    success:
                      "Hasil pengecekan ketersediaan. Jadwal belum dipesan.",
                  },
                ),
              )
            }
          >
            Cek Ketersediaan
          </button>
          <button
            className="primary"
            onClick={() =>
              act(
                make("Tahan Jadwal", `${path}/hold`, "HoldSlotRequest", {
                  description:
                    "Pilih tepat satu paket atau penawaran yang disetujui. Durasi 1–12 jam. Jadwal harus berada di masa depan.",
                  submit: "Tahan Jadwal",
                }),
              )
            }
          >
            Booking Baru
          </button>
        </div>
      )}
      {["expenses", "inventaris"].includes(page) && (
        <div className="action-strip">
          <button
            onClick={() =>
              navigate(
                `/${role}/${page === "expenses" ? "expense" : "inventory"}-categories`,
              )
            }
          >
            Kelola Kategori
          </button>
        </div>
      )}
      {page === "crm" && <Recommendations />}
      {page === "settings" && <BasePrices openAction={openAction} />}
      <div className="list-toolbar">
        <label className="search">
          <Search size={17} />
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Cari di halaman ini..."
          />
        </label>
        <span className="muted">{meta?.total ?? rows.length} data</span>
      </div>
      <ErrorNotice error={resource.error} />
      <ErrorNotice error={detailError} />
      {resource.loading ? (
        <Loading />
      ) : page === "booking" && role === "manager" && !resource.error ? (
        <BookingCalendar rows={rows} onOpen={loadDetail} />
      ) : !resource.error && !rows.length ? (
        <Empty text="Belum ada data untuk ditampilkan." />
      ) : ["packages", "quotation"].includes(page) &&
        packageView === "table" ? (
        <div className="table-scroll package-table">
          <table>
            <thead>
              <tr>
                <th>
                  {page === "quotation" ? "Nama Penawaran" : "Nama Paket"}
                </th>
                <th>Jumlah Lagu</th>
                <th>Jam Rekaman</th>
                <th>Harga</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    <button onClick={() => loadDetail(row)}>{row.name}</button>
                  </td>
                  <td>{row.track_count ?? row.specifications?.track_count}</td>
                  <td>
                    {row.recording_hours ??
                      row.specifications?.recording_hours ??
                      0}
                  </td>
                  <td>
                    {money(
                      row.price ??
                        row.agreed_price ??
                        row.latest_offer?.price ??
                        row.auto_price,
                    )}
                  </td>
                  <td>
                    {page === "quotation"
                      ? row.status_label
                      : row.is_active
                        ? "Aktif"
                        : "Nonaktif"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="card-grid">
          {rows.map((row, i) => (
            <ItemCard
              key={row.id || row.key || i}
              row={row}
              config={config}
              onOpen={loadDetail}
            />
          ))}
        </div>
      )}
      {page === "packages" && role === "client" && <ClientQuoteComposer />}
      {meta && <Pagination meta={meta} onChange={setNumber} />}
      {detailBusy && <Loading />}
      {detail && (
        <div className="modal-backdrop">
          <section
            className="modal detail-modal"
            role="dialog"
            aria-modal="true"
            aria-label="Detail"
          >
            <header>
              <div>
                <span className="eyebrow">{config.title}</span>
                <h2>{itemTitle(detail)}</h2>
              </div>
              <button
                autoFocus
                className="icon-button"
                onClick={() => setDetail(null)}
                aria-label="Tutup"
              >
                <X />
              </button>
            </header>
            <DataView data={detail} />
            <div className="detail-actions">
              {config.schema && role === "manager" && page !== "crm" && (
                <>
                  <button onClick={edit}>Ubah</button>
                  <button
                    className="danger"
                    onClick={() =>
                      act(
                        make(
                          `Hapus ${config.title}`,
                          `${path}/${detail.id}`,
                          [],
                          {
                            method: "DELETE",
                            danger: true,
                            submit: "Hapus",
                            confirmation: "Hapus data ini?",
                          },
                        ),
                      )
                    }
                  >
                    Hapus
                  </button>
                </>
              )}
              {page === "settings" && (
                <button
                  onClick={() =>
                    act(
                      make(
                        "Ubah Pengaturan",
                        `${path}/${encodeURIComponent(detail.key)}`,
                        "UpdateStudioSettingRequest",
                        { method: "PUT", initial: detail },
                      ),
                    )
                  }
                >
                  Ubah Nilai
                </button>
              )}
              {page === "inventaris" && detail.status === "active" && (
                <button
                  onClick={() =>
                    act(
                      make(
                        "Lepas Aset",
                        `${path}/${detail.id}/dispose`,
                        "DisposeInventoryRequest",
                      ),
                    )
                  }
                >
                  Jual / Hapus Aset
                </button>
              )}
              {page === "booking" &&
                !["completed", "cancelled", "expired"].includes(
                  detail.status,
                ) && (
                  <button
                    className="danger"
                    onClick={() =>
                      act(
                        make("Batalkan Booking", `${path}/${detail.id}`, [], {
                          method: "DELETE",
                          danger: true,
                          submit: "Batalkan Booking",
                        }),
                      )
                    }
                  >
                    Batalkan Booking
                  </button>
                )}
              {page === "booking" &&
                role === "client" &&
                detail.held_until &&
                new Date(detail.held_until) > new Date() &&
                !["confirmed", "cancelled", "expired", "completed"].includes(
                  detail.status,
                ) && (
                  <button
                    className="primary"
                    onClick={() =>
                      act(
                        make("Konfirmasi Booking", `${path}/confirm`, [], {
                          transform: () => ({
                            recording_session_id: detail.id,
                          }),
                          description:
                            "Konfirmasi berhasil hanya jika pembayaran sudah lunas dan hold masih berlaku.",
                          submit: "Konfirmasi",
                        }),
                      )
                    }
                  >
                    Konfirmasi setelah Lunas
                  </button>
                )}
              {page === "quotation" &&
                quotationTurn &&
                detail.latest_offer?.id && (
                  <button
                    className="primary"
                    onClick={() =>
                      act(
                        make(
                          "Tanggapi Penawaran",
                          `${path}/${detail.id}/respond`,
                          "RespondToQuotationRequest",
                          {
                            initial: {
                              latest_offer_id: detail.latest_offer.id,
                            },
                            description:
                              "Jika penawaran telah berubah, muat ulang detail sebelum menanggapi.",
                          },
                        ),
                      )
                    }
                  >
                    Tanggapi Penawaran
                  </button>
                )}
              {page === "quotation" &&
                role === "client" &&
                detail.status === "accepted" && (
                  <button
                    className="primary"
                    onClick={() =>
                      act(
                        make(
                          "Booking Penawaran",
                          "/client/bookings/hold",
                          "HoldSlotRequest",
                          {
                            initial: { quotation_id: detail.id },
                            description:
                              "Penawaran yang disetujui hanya dapat digunakan jika belum dipakai project. Pilih jadwal untuk menahan ruang studio.",
                          },
                        ),
                      )
                    }
                  >
                    Booking Penawaran Ini
                  </button>
                )}
              {page === "invoice" &&
                detail.payment?.type !== "final" &&
                !["paid", "cancelled", "refunded"].includes(detail.status) && (
                  <button
                    className="primary"
                    onClick={() =>
                      act(
                        make(
                          "Bayar Invoice",
                          `${path}/${detail.id}/initiate-payment`,
                          [
                            f("gateway", "string", true, {
                              options: ["midtrans", "xendit"],
                            }),
                          ],
                          {
                            submit: "Mulai Pembayaran",
                            success:
                              "Pembayaran dibuat. Buka tautan pembayaran, lalu periksa kembali status dari studio.",
                          },
                        ),
                      )
                    }
                  >
                    Bayar Invoice
                  </button>
                )}
              {role === "client" &&
                page === "invoice" &&
                detail.payment?.id && (
                  <button
                    onClick={() =>
                      act(
                        make(
                          "Periksa Status Pembayaran",
                          `/client/payments/${detail.payment.id}`,
                          [],
                          {
                            method: "GET",
                            submit: "Periksa",
                            success: "Status pembayaran terbaru dari studio.",
                          },
                        ),
                      )
                    }
                  >
                    Periksa Pembayaran
                  </button>
                )}
              {role === "client" &&
                page === "invoice" &&
                detail.payment?.id && (
                  <button
                    onClick={() =>
                      navigate(`/client/payment?payment=${detail.payment.id}`)
                    }
                  >
                    Buka Pembayaran
                  </button>
                )}
              {role === "client" &&
                page === "invoice" &&
                detail.payment?.type === "final" &&
                ["pending", "failed", "expired"].includes(
                  detail.payment.status,
                ) && (
                  <button
                    className="primary"
                    onClick={() => act(finalPaymentAction(detail.payment.id))}
                  >
                    Pelunasan
                  </button>
                )}
              {role === "client" &&
                page === "invoice" &&
                positiveId(detail.project?.id) && (
                  <button
                    onClick={() =>
                      navigate(`/client/delivery?project=${detail.project.id}`)
                    }
                  >
                    Lihat Hasil Project
                  </button>
                )}
              {role === "client" &&
                page === "invoice" &&
                positiveId(detail.recording_session?.id) && (
                  <button
                    onClick={() =>
                      navigate(
                        `/client/booking?id=${detail.recording_session.id}`,
                      )
                    }
                  >
                    Buka Booking Terkait
                  </button>
                )}
              {role === "operator" &&
                page === "sessions" &&
                positiveId(detail.project_stage_id) && (
                  <button
                    className="primary"
                    onClick={() =>
                      navigate(
                        `/operator/tasks?stage=${detail.project_stage_id}${positiveId(detail.project_id) ? `&project=${detail.project_id}` : ""}`,
                      )
                    }
                  >
                    Kerjakan Tahap Ini
                  </button>
                )}
              {role === "client" && page === "packages" && (
                <button
                  className="primary"
                  onClick={() =>
                    act(
                      make(
                        "Booking Paket",
                        "/client/bookings/hold",
                        "HoldSlotRequest",
                        {
                          initial: { package_id: detail.id },
                          description:
                            "Jadwal ditahan sementara. Selesaikan pembayaran sebelum batas hold.",
                        },
                      ),
                    )
                  }
                >
                  Booking Paket Ini
                </button>
              )}
              {page === "payments" &&
                detail.status === "pending_manual_confirmation" &&
                detail.provider === "cash" && (
                  <button
                    className="primary"
                    onClick={() =>
                      act(
                        make(
                          "Konfirmasi Tunai",
                          `${path}/${detail.id}/confirm`,
                          [
                            f("note", "string; maxLength=500"),
                            f("paid_at", 'string; format="date-time"', false),
                          ],
                          { submit: "Konfirmasi Diterima" },
                        ),
                      )
                    }
                  >
                    Konfirmasi Tunai
                  </button>
                )}
              {page === "payments" &&
                ["pending", "pending_manual_confirmation"].includes(
                  detail.status,
                ) && (
                  <button
                    className="danger"
                    onClick={() =>
                      act(
                        make(
                          "Batalkan Pembayaran",
                          `${path}/${detail.id}/cancel`,
                          [f("reason", "string; maxLength=500")],
                          { danger: true, submit: "Batalkan" },
                        ),
                      )
                    }
                  >
                    Batalkan Pembayaran
                  </button>
                )}
              {page === "crm" &&
                ["draft", "pending"].includes(detail.status) && (
                  <button
                    className="primary"
                    onClick={() =>
                      act(
                        make(
                          "Kirim Kampanye",
                          `${path}/${detail.id}/dispatch`,
                          [],
                          {
                            submit: "Kirim Email",
                            description:
                              "Penerima harus aktif dan menyetujui komunikasi pemasaran.",
                            success: "Kampanye masuk antrean pengiriman email.",
                          },
                        ),
                      )
                    }
                  >
                    Kirim Kampanye
                  </button>
                )}
            </div>
          </section>
        </div>
      )}
    </>
  );
}
