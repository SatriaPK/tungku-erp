import ErrorNotice from "./ErrorNotice";
import Summary from "./Summary";
import { money } from "../lib/contract";
import { stages } from "../config/resources.js";
import { rowsOf } from "../lib/resource.js";
import { f, make } from "../lib/actions.js";
import useResource from "../hooks/useResource.js";

export default function BasePrices({ openAction }) {
  const resource = useResource("/manager/base-prices");
  const rows = rowsOf(resource.data);
  return (
    <section className="panel">
      <div className="panel-heading">
        <h2>Harga Dasar Produksi</h2>
        <button
          disabled={resource.loading || !!resource.error}
          onClick={() =>
            openAction(
              make(
                "Ubah Harga Dasar",
                "/manager/base-prices",
                stages.map((s) =>
                  f(s, "integer; minimum=0; maximum=1000000000"),
                ),
                {
                  method: "PUT",
                  initial: Object.fromEntries(
                    rows.map((row) => [row.stage, row.price]),
                  ),
                  transform: (body) => ({ prices: body }),
                },
              ),
              resource.reload,
            )
          }
        >
          Ubah Harga
        </button>
      </div>
      <ErrorNotice error={resource.error} />
      <div className="summary-grid">
        {rows.map((row) => (
          <Summary
            key={row.stage}
            label={row.stage_label}
            value={money(row.price)}
            detail={row.unit_label}
          />
        ))}
      </div>
    </section>
  );
}
