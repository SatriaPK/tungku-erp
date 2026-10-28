import { useState } from "react";
import DesignIcon from "../DesignIcon";
import Field from "../Field";
import ErrorNotice from "../ErrorNotice";
import { NumberStepper } from "./PackageFields";
import { stages } from "../../config/resources";
import { buildBody, money, formatDate } from "../../lib/contract";
import schemas from "../../lib/schemas.json";
import { request } from "../../lib/api";
import { rowsOf, unwrap } from "../../lib/resource";
import useResource from "../../hooks/useResource";
import navigate from "../../lib/navigation";

const icons = {
  recording: "modal-mic",
  editing: "modal-cut",
  mixing: "modal-mix",
  mastering: "modal-graphic-eq",
};
const defaults = () => ({
  name: "",
  track_count: 1,
  recording_hours: 3,
  include_recording: true,
  include_editing: true,
  include_mixing: true,
  include_mastering: true,
  offer_price: "",
  notes: "",
});
export default function ClientQuoteComposer() {
  const [values, setValues] = useState(defaults),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(null),
    [created, setCreated] = useState(null);
  const history = useResource("/client/quotations");
  const change = (name, value) =>
    setValues((previous) => ({ ...previous, [name]: value }));
  const field = (name) =>
    schemas.SubmitQuotationRequest.find((field) => field.name === name);
  const uncertain =
    error && (!error.status || error.status >= 500 || error.status === 419);
  async function submit(event) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const body = buildBody(schemas.SubmitQuotationRequest, {
        ...values,
        recording_hours: values.include_recording ? values.recording_hours : 0,
      });
      if (!stages.some((stage) => body[`include_${stage}`]))
        throw Object.assign(new Error("Pilih minimal satu tahap produksi."), {
          status: 422,
        });
      setCreated(
        unwrap(await request("/client/quotations", { method: "POST", body })),
      );
      history.reload();
    } catch (error) {
      setError(error);
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="client-quote-section">
      <header>
        <div>
          <h2>Penawaran Kustom</h2>
          <p>
            Buat penawaran sesuai kebutuhan proyek Anda. Tim akan meninjau harga
            dan mengirimkan tanggapan.
          </p>
        </div>
        <span className="badge">Kustom</span>
      </header>
      <div className="client-quote-layout panel">
        <form id="client-quote-form" onSubmit={submit}>
          <label className="quote-name-field">
            <span className="sr-only">Nama Penawaran *</span>
            <input
              type="text"
              name="name"
              value={values.name}
              required
              maxLength={100}
              placeholder="Buat Penawaran Kustom"
              aria-invalid={Boolean(error?.errors?.name)}
              onChange={(event) => change("name", event.target.value)}
            />
          </label>
          <p>
            Pilih tahap yang dibutuhkan, tentukan durasi rekaman dan jumlah
            lagu, lalu masukkan harga tawaran Anda.
          </p>
          <ErrorNotice error={error} />
          {created && (
            <div className="notice success">
              Penawaran berhasil dikirim. Tunggu tanggapan studio.
            </div>
          )}
          <fieldset className="package-stages">
            <legend className="sr-only">Tahap Produksi</legend>
            <div className="phase-grid">
              {stages.map((stage) => (
                <button
                  className={`phase-card ${values[`include_${stage}`] ? "selected" : ""}`}
                  key={stage}
                  type="button"
                  aria-pressed={Boolean(values[`include_${stage}`])}
                  onClick={() =>
                    change(`include_${stage}`, !values[`include_${stage}`])
                  }
                >
                  <DesignIcon name={icons[stage]} />
                  <div className="phase-information">
                    <strong>{stage[0].toUpperCase() + stage.slice(1)}</strong>
                    <small>
                      {stage === "recording" ? "Durasi rekaman" : "Per lagu"}
                    </small>
                  </div>
                </button>
              ))}
            </div>
          </fieldset>
          <div className="package-quantities">
            <NumberStepper
              field={{
                ...field("recording_hours"),
                label: "Durasi Rekaman (Jam)",
              }}
              value={values.recording_hours}
              onChange={(value) => change("recording_hours", value)}
              disabled={!values.include_recording}
              error={error?.errors?.recording_hours}
            />
            <NumberStepper
              field={{ ...field("track_count"), label: "Jumlah Lagu" }}
              value={values.track_count}
              onChange={(value) => change("track_count", value)}
              error={error?.errors?.track_count}
            />
          </div>
          <Field
            field={{
              ...field("offer_price"),
              label: "Input Harga Tawaran (Rp)",
            }}
            value={values.offer_price}
            onChange={(value) => change("offer_price", value)}
            error={error?.errors?.offer_price}
          />
          <details className="quote-optional-notes">
            <summary>Catatan (Opsional)</summary>{" "}
            <Field
              field={{ ...field("notes"), label: "Catatan (Opsional)" }}
              value={values.notes}
              onChange={(value) => change("notes", value)}
              error={error?.errors?.notes}
            />
          </details>
          {created && (
            <button
              type="button"
              onClick={() => {
                setCreated(null);
                setValues(defaults());
              }}
            >
              Buat Penawaran Lain
            </button>
          )}
          {uncertain && (
            <button type="button" onClick={() => navigate("/client/quotation")}>
              Periksa Penawaran Terbaru
            </button>
          )}
        </form>
        <aside className="quote-summary">
          <h3>Ringkasan Penawaran</h3>
          <p>Harga akhir dikonfirmasi melalui negosiasi dengan studio.</p>
          <dl>
            <div>
              <dt>Tahap Produksi</dt>
              <dd>
                {stages.filter((stage) => values[`include_${stage}`]).length}{" "}
                Tahap
              </dd>
            </div>
            <div>
              <dt>Durasi Rekaman</dt>
              <dd>
                {values.include_recording ? values.recording_hours : 0} Jam
              </dd>
            </div>
            <div>
              <dt>Jumlah Lagu</dt>
              <dd>{values.track_count} Lagu</dd>
            </div>
            <div>
              <dt>Status</dt>
              <dd>{created ? "Menunggu Tinjauan" : "Belum Dikirim"}</dd>
            </div>
          </dl>
          <span>Harga Tawaran</span>
          <strong className="quote-price">
            {money(Number(values.offer_price) || 0)}
          </strong>
          <div className="quote-submit-actions">
            {" "}
            <button
              form="client-quote-form"
              className="primary"
              disabled={busy || Boolean(created) || uncertain}
            >
              {busy ? "Mengirim..." : "Kirim Penawaran"}
            </button>
            <button
              type="button"
              disabled={busy}
              className="text-link"
              onClick={() => {
                if (uncertain || created) navigate("/client/quotation");
                else setValues(defaults());
              }}
            >
              Batal
            </button>
          </div>
        </aside>
        <aside className="quote-history">
          <h3>Riwayat Penawaran</h3>
          <ErrorNotice error={history.error} />
          {rowsOf(history.data)
            .slice(0, 4)
            .map((item) => (
              <button
                key={item.id}
                className="quote-history-item"
                onClick={() => navigate(`/client/quotation?id=${item.id}`)}
              >
                <strong>{item.name || item.package_display_name}</strong>
                <span>{item.status_label}</span>
                <small>{formatDate(item.updated_at || item.created_at)}</small>
                <b>
                  {money(
                    item.latest_offer?.price ??
                      item.agreed_price ??
                      item.auto_price,
                  )}
                </b>
              </button>
            ))}
          {!history.loading && !rowsOf(history.data).length && (
            <p>
              Belum ada penawaran. Kirim penawaran pertama Anda melalui formulir
              ini.
            </p>
          )}
          <button
            className="text-link"
            onClick={() => navigate("/client/quotation")}
          >
            Lihat Semua Penawaran
          </button>
        </aside>
      </div>
    </section>
  );
}
