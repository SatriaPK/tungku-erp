import PackageFields from "./packages/PackageFields";
import DesignIcon from "./DesignIcon";
import SubmissionUpload from "./SubmissionUpload";
import ErrorNotice from "./ErrorNotice";
import DataView from "./DataView";
import Field from "./Field";
import { useEffect, useState } from "react";
import { X, Check } from "lucide-react";
import { request } from "../lib/api";
import { buildBody, validateBusiness } from "../lib/contract";
import { unwrap, rowsOf } from "../lib/resource.js";

export default function ActionModal({ action, onClose, onDone }) {
  const packageAction =
    /^\/manager\/packages(?:\/\d+)?$/.test(action.path) &&
    ["POST", "PUT"].includes(action.method);
  const [lookup, setLookup] = useState({}),
    [lookupError, setLookupError] = useState(null);
  const [values, setValues] = useState(() =>
    Object.fromEntries(
      action.fields.map((field) => [
        field.name,
        action.initial?.[field.name] ??
          (packageAction && field.name === "track_count"
            ? 1
            : packageAction && field.name === "recording_hours"
              ? 3
              : packageAction && field.name.startsWith("include_")
                ? true
                : field.name === "is_active"
                  ? true
                  : field.rules === "boolean"
                    ? false
                    : field.name === "channels"
                      ? ["email"]
                      : field.options?.length === 1
                        ? field.options[0]
                        : ""),
      ]),
    ),
  );
  const [error, setError] = useState(null),
    [busy, setBusy] = useState(false),
    [until, setUntil] = useState(0),
    [now, setNow] = useState(Date.now());
  const [result, setResult] = useState(null),
    [completed, setCompleted] = useState(false);
  const [changedOffer, setChangedOffer] = useState(null);
  const [uploadBusy, setUploadBusy] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    const names = action.fields.map((field) => field.name),
      role = window.location.pathname.split("/")[1];
    const sources = [];
    if (
      role === "client" &&
      names.some((name) => ["package_id", "room_id"].includes(name))
    )
      sources.push(["/client/packages", "catalog"]);
    if (role === "client" && names.includes("quotation_id"))
      sources.push(["/client/quotations", "quotations"]);
    if (role === "manager" && names.includes("room_id"))
      sources.push(["/manager/dashboard", "dashboard"]);
    for (const [field, path] of [
      ["expense_category_id", "expense-categories"],
      ["inventory_category_id", "inventory-categories"],
      ["package_id", "packages"],
      ["client_ids", "crm/recommendations"],
    ])
      if (role === "manager" && names.includes(field))
        sources.push([`/manager/${path}`, field]);
    async function readLookup(path) {
      const first = await request(path, { signal: controller.signal });
      if (!Array.isArray(first?.data) || !first.meta?.last_page) return first;
      const all = [...first.data];
      for (let page = 2; page <= first.meta.last_page; page++) {
        const next = await request(path, {
          params: { page },
          signal: controller.signal,
        });
        all.push(...rowsOf(next));
      }
      return { ...first, data: all };
    }
    Promise.all(
      sources.map(async ([path, key]) => [key, await readLookup(path)]),
    )
      .then((entries) => {
        if (!controller.signal.aborted) setLookup(Object.fromEntries(entries));
      })
      .catch((error) => {
        if (!controller.signal.aborted) setLookupError(error);
      });
    return () => controller.abort();
  }, [action]);
  function resolveField(field) {
    let rows, label;
    if (field.name === "room_id") {
      rows =
        lookup.catalog?.meta?.rooms ||
        (lookup.dashboard?.meta?.workflow || unwrap(lookup.dashboard)?.workflow)
          ?.rooms;
      label = "Ruangan";
    } else if (field.name === "package_id") {
      rows = rowsOf(lookup.catalog || lookup.package_id);
      label = "Paket";
    } else if (field.name === "quotation_id") {
      rows = rowsOf(lookup.quotations).filter(
        (row) => row.status === "accepted",
      );
      label = "Penawaran Disetujui";
    } else if (
      ["expense_category_id", "inventory_category_id", "client_ids"].includes(
        field.name,
      )
    ) {
      rows = rowsOf(lookup[field.name]);
      label = field.name === "client_ids" ? "Penerima Email" : "Kategori";
    }
    return rows?.length
      ? {
          ...field,
          label,
          options: rows.map((row) => ({
            value: row.id,
            label: row.name || row.package_display_name || `#${row.id}`,
          })),
        }
      : field;
  }
  useEffect(() => {
    if (until <= Date.now()) return;
    const id = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(id);
  }, [until]);
  useEffect(() => {
    const handler = (e) => {
      if (e.key === "Escape" && !busy && !uploadBusy) onClose();
    };
    window.addEventListener("keydown", handler);
    return () => window.removeEventListener("keydown", handler);
  }, [onClose, busy, uploadBusy]);
  async function submit(event) {
    event.preventDefault();
    setError(null);
    setBusy(true);
    try {
      let body = buildBody(action.fields, values);
      if (action.transform) body = action.transform(body);
      validateBusiness(action.path, body);
      const response = await request(action.path, {
        method: action.method,
        body: action.method === "GET" ? undefined : body,
        params: action.method === "GET" ? body : undefined,
      });
      setResult(unwrap(response));
      setCompleted(true);
      onDone();
    } catch (error) {
      setError(error);
      if (error.retryAfter) {
        setUntil(Date.now() + error.retryAfter * 1000);
        setNow(Date.now());
      }
      if (
        error.status === 422 &&
        /\/quotations\/\d+\/respond$/.test(action.path)
      ) {
        try {
          const latest = unwrap(
            await request(action.path.replace(/\/respond$/, "")),
          )?.latest_offer;
          if (latest && String(latest.id) !== String(values.latest_offer_id))
            setChangedOffer(latest);
        } catch (readError) {
          setError(readError);
        }
      }
    } finally {
      setBusy(false);
    }
  }
  const wait = Math.max(0, Math.ceil((until - now) / 1000));
  const uncertain =
    error &&
    action.method !== "GET" &&
    (!error.status || error.status >= 500 || error.status === 419);
  return (
    <div className="modal-backdrop">
      <section
        className={`modal ${packageAction ? "package-modal" : ""}`}
        role="dialog"
        aria-modal="true"
        aria-labelledby="action-title"
      >
        <header>
          <div>
            {!packageAction && <span className="eyebrow">TUNGKU STUDIO</span>}
            <h2 id="action-title">{action.title}</h2>
          </div>
          <button
            className="icon-button"
            autoFocus
            onClick={onClose}
            disabled={busy || uploadBusy}
            aria-label="Tutup"
          >
            {packageAction ? <DesignIcon name="modal-close" /> : <X />}
          </button>
        </header>
        {completed ? (
          <>
            <div className="notice success">
              <Check size={18} />{" "}
              {action.success || "Berhasil. Data terbaru sudah dimuat."}
            </div>
            {result != null && <DataView data={result} />}
            <footer>
              <button className="primary" onClick={onClose}>
                Selesai
              </button>
            </footer>
          </>
        ) : (
          <form onSubmit={submit}>
            {action.fields.some((field) => field.name === "result_url") && (
              <SubmissionUpload
                busy={busy}
                onBusy={setUploadBusy}
                onUploaded={(url) =>
                  setValues((values) => ({ ...values, result_url: url }))
                }
              />
            )}
            {action.description && (
              <p className="muted">{action.description}</p>
            )}
            <ErrorNotice error={error || lookupError} />
            {changedOffer && (
              <section className="panel">
                <h3>Penawaran sudah berubah</h3>
                <DataView data={changedOffer} />
                <button
                  type="button"
                  onClick={() => {
                    setValues((v) => ({
                      ...v,
                      latest_offer_id: changedOffer.id,
                    }));
                    setChangedOffer(null);
                    setError(null);
                  }}
                >
                  Gunakan Penawaran Terbaru
                </button>
              </section>
            )}
            {packageAction ? (
              <PackageFields
                fields={action.fields}
                values={values}
                setValues={setValues}
                errors={error?.errors}
                editing={action.method === "PUT"}
              />
            ) : (
              <div className="form-grid">
                {action.fields
                  .filter(
                    (field) =>
                      field.name !== "latest_offer_id" &&
                      field.name !== "project_id" &&
                      !(
                        field.name === "price" &&
                        values.response_type !== "counter"
                      ) &&
                      !(
                        field.name === "review_notes" &&
                        values.decision !== "request_revision"
                      ),
                  )
                  .map((field) => (
                    <Field
                      key={field.name}
                      field={{
                        ...resolveField(field),
                        required:
                          field.required ||
                          (field.name === "price" &&
                            values.response_type === "counter") ||
                          (field.name === "review_notes" &&
                            values.decision === "request_revision"),
                      }}
                      value={values[field.name]}
                      onChange={(value) =>
                        setValues((v) => ({ ...v, [field.name]: value }))
                      }
                      error={error?.errors?.[field.name]}
                    />
                  ))}
              </div>
            )}
            {!action.fields.length && (
              <p>{action.confirmation || "Lanjutkan tindakan ini?"}</p>
            )}
            <footer>
              {packageAction && (
                <span className="package-footer-divider">
                  <DesignIcon name="modal-separator2" />
                </span>
              )}
              <button
                type="button"
                onClick={onClose}
                disabled={busy || uploadBusy}
              >
                Batal
              </button>
              {uncertain && (
                <button
                  type="button"
                  onClick={() => {
                    onDone();
                    onClose();
                  }}
                >
                  Periksa Data Terbaru
                </button>
              )}
              <button
                className={action.danger ? "danger" : "primary"}
                disabled={
                  busy ||
                  uploadBusy ||
                  wait > 0 ||
                  uncertain ||
                  Boolean(changedOffer)
                }
              >
                {busy
                  ? "Memproses..."
                  : wait > 0
                    ? `Tunggu ${wait} detik`
                    : action.submit || "Simpan"}
              </button>
            </footer>
          </form>
        )}
      </section>
    </div>
  );
}
