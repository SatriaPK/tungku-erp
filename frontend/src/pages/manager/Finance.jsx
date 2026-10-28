import ErrorNotice from "../../components/ErrorNotice";
import Badge from "../../components/Badge";
import DataView from "../../components/DataView";
import Field from "../../components/Field";
import PageTitle from "../../components/PageTitle";
import Loading from "../../components/Loading";
import Summary from "../../components/Summary";
import { useEffect, useState } from "react";
import { RefreshCw } from "lucide-react";
import { money, currentPeriod } from "../../lib/contract";
import { unwrap, isFinal } from "../../lib/resource.js";
import { f, make } from "../../lib/actions.js";
import useResource from "../../hooks/useResource.js";

export default function Finance({ openAction }) {
  const [period, setPeriod] = useState(currentPeriod());
  const summary = useResource("/manager/finance/monthly-summary", period),
    payroll = useResource("/manager/finance/payroll-allocations", period);
  const data = unwrap(summary.data),
    allocation = unwrap(payroll.data);
  const [percentages, setPercentages] = useState({}),
    [studio, setStudio] = useState("");
  useEffect(() => {
    const d = unwrap(payroll.data);
    setPercentages(
      Object.fromEntries(
        (d?.allocations || []).map((r) => [
          r.operator_id,
          r.percentage ?? r.recommended_percentage,
        ]),
      ),
    );
    setStudio(d?.studio_profit_percentage ?? "");
  }, [payroll.data]);
  const reload = () => {
    summary.reload();
    payroll.reload();
  };
  return (
    <>
      <PageTitle
        title="Laporan & Payroll"
        subtitle="Ringkasan keuangan dan pembagian pendapatan bulanan."
      />
      <div className="action-strip">
        <Field
          field={f("year", "integer; minimum=1; maximum=9999")}
          value={period.year}
          onChange={(year) => setPeriod((p) => ({ ...p, year }))}
        />
        <Field
          field={f("month", "integer; minimum=1; maximum=12")}
          value={period.month}
          onChange={(month) => setPeriod((p) => ({ ...p, month }))}
        />
        <button onClick={reload}>
          <RefreshCw size={16} />
          Muat Ulang
        </button>
      </div>
      <ErrorNotice error={summary.error} />
      {summary.loading ? (
        <Loading />
      ) : (
        data && (
          <>
            <div className="summary-grid">
              <Summary label="Pendapatan" value={money(data.totals?.revenue)} />
              <Summary
                label="Pengeluaran"
                value={money(data.totals?.expense)}
              />
              <Summary
                label="Laba Bersih"
                value={money(data.totals?.net_profit)}
              />
            </div>
            <section className="panel">
              <h2>Pengeluaran per Kategori</h2>
              <DataView data={data.expenses_by_category} />
            </section>
          </>
        )
      )}
      <section className="panel">
        <h2>Alokasi Payroll</h2>
        <ErrorNotice error={payroll.error} />
        {payroll.loading ? (
          <Loading />
        ) : (
          allocation && (
            <>
              <Badge value={allocation.status} />
              <div className="form-grid">
                {isFinal(allocation) ? (
                  <p>Porsi studio: {studio}%</p>
                ) : (
                  <Field
                    field={f(
                      "studio_profit_percentage",
                      "number; minimum=0; maximum=100",
                      false,
                    )}
                    value={studio}
                    onChange={setStudio}
                  />
                )}
              </div>
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Operator</th>
                      <th>Pekerjaan</th>
                      <th>Rekomendasi</th>
                      <th>Persentase (%)</th>
                      <th>Nominal</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(allocation.allocations || []).map((row) => (
                      <tr key={row.operator_id}>
                        <td>{row.operator_name}</td>
                        <td>{row.jobs_count}</td>
                        <td>{row.recommended_percentage}%</td>
                        <td>
                          <input
                            aria-label={`Persentase ${row.operator_name}`}
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            disabled={isFinal(allocation)}
                            value={percentages[row.operator_id] ?? ""}
                            onChange={(e) =>
                              setPercentages((p) => ({
                                ...p,
                                [row.operator_id]: e.target.value,
                              }))
                            }
                          />
                        </td>
                        <td>{money(row.amount)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {!isFinal(allocation) && (
                <div className="detail-actions">
                  <button
                    disabled={!allocation.allocations?.length}
                    onClick={() =>
                      openAction(
                        make(
                          "Simpan Alokasi Payroll",
                          "/manager/finance/payroll-allocations",
                          [],
                          {
                            method: "PUT",
                            transform: () => ({
                              year: Number(period.year),
                              month: Number(period.month),
                              ...(studio === ""
                                ? {}
                                : { studio_profit_percentage: studio }),
                              allocations: (allocation.allocations || []).map(
                                (r) => ({
                                  operator_id: r.operator_id,
                                  percentage: percentages[r.operator_id],
                                }),
                              ),
                            }),
                            confirmation:
                              "Simpan persentase alokasi untuk periode ini?",
                          },
                        ),
                        reload,
                      )
                    }
                  >
                    Simpan Alokasi
                  </button>
                  <button
                    className="primary"
                    onClick={() =>
                      openAction(
                        make(
                          "Finalisasi Payroll",
                          "/manager/finance/payroll-finalization",
                          [],
                          {
                            transform: () => ({
                              year: Number(period.year),
                              month: Number(period.month),
                            }),
                            description:
                              "Hanya periode yang sudah berakhir dapat difinalisasi. Snapshot final tidak dapat diubah.",
                            submit: "Finalisasi",
                          },
                        ),
                        reload,
                      )
                    }
                  >
                    Finalisasi Periode
                  </button>
                </div>
              )}
            </>
          )
        )}
      </section>
    </>
  );
}
