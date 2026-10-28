import { useState } from "react";
import Field from "../Field";
import DesignIcon from "../DesignIcon";
import ErrorNotice from "../ErrorNotice";
import useResource from "../../hooks/useResource";
import { rowsOf } from "../../lib/resource";
import { money } from "../../lib/contract";
import { stages } from "../../config/resources";

const icons = {
  recording: "modal-mic",
  editing: "modal-cut",
  mixing: "modal-mix",
  mastering: "modal-graphic-eq",
};

export function NumberStepper({ field, value, onChange, error, disabled }) {
  const min = Number(field.rules?.match(/minimum=(\d+)/)?.[1] ?? 1),
    max = Number(field.rules?.match(/maximum=(\d+)/)?.[1] ?? 100);
  const change = (delta) =>
    onChange(Math.min(max, Math.max(min, Number(value || min) + delta)));
  return (
    <label className="field stepper-field">
      <span>
        {field.label}
        <b className="required">*</b>
      </span>
      <div className="number-stepper">
        <button
          type="button"
          aria-label={`Kurangi ${field.label}`}
          onClick={() => change(-1)}
          disabled={disabled || Number(value) <= min}
        >
          <DesignIcon name="modal-minus" />
        </button>
        <input
          name={field.name}
          aria-label={field.label}
          type="number"
          min={min}
          max={max}
          required={!disabled}
          disabled={disabled}
          value={value ?? ""}
          onChange={(e) => onChange(e.target.value)}
          aria-invalid={Boolean(error)}
        />
        <button
          type="button"
          aria-label={`Tambah ${field.label}`}
          onClick={() => change(1)}
          disabled={disabled || Number(value) >= max}
        >
          <DesignIcon name="modal-add" />
        </button>
      </div>
      {error && <small className="field-error">{error.join(" ")}</small>}
    </label>
  );
}

export default function PackageFields({
  fields,
  values,
  setValues,
  errors = {},
  editing,
}) {
  const prices = useResource("/manager/base-prices");
  const rates = Object.fromEntries(
    rowsOf(prices.data).map((row) => [row.stage, Number(row.price)]),
  );
  const [manual, setManual] = useState(
    values.manual_price != null && values.manual_price !== "",
  );
  const change = (name, value) =>
    setValues((previous) => ({ ...previous, [name]: value }));
  const field = (name) => fields.find((field) => field.name === name);
  const selected = stages.filter((stage) => values[`include_${stage}`]);
  const ready =
    !prices.loading &&
    !prices.error &&
    selected.every((stage) => Number.isFinite(rates[stage]));
  const quantity = (stage) =>
    Number(
      stage === "recording" ? values.recording_hours : values.track_count,
    ) || 0;
  const total = selected.reduce(
    (sum, stage) => sum + (rates[stage] || 0) * quantity(stage),
    0,
  );
  return (
    <div className="package-fields">
      <div className="package-settings">
        <div className="package-information">
          <Field
            field={{ ...field("name"), label: "Nama Paket" }}
            value={values.name}
            onChange={(value) => change("name", value)}
            error={errors.name}
          />
          <Field
            field={{
              ...field("description"),
              label: "Deskripsi Paket (Opsional)",
            }}
            value={values.description}
            onChange={(value) => change("description", value)}
            error={errors.description}
          />
        </div>
        <div className="package-divider">
          <DesignIcon name="modal-separator" />
        </div>
        <fieldset className="package-stages">
          <legend>
            Tahap Produksi<b className="required">*</b>
          </legend>
          <div className="phase-grid">
            {stages.map((stage) => (
              <button
                type="button"
                key={stage}
                className={`phase-card ${values[`include_${stage}`] ? "selected" : ""}`}
                aria-pressed={Boolean(values[`include_${stage}`])}
                onClick={() =>
                  change(`include_${stage}`, !values[`include_${stage}`])
                }
              >
                <DesignIcon name={icons[stage]} />
                <div className="phase-information">
                  <strong>{stage[0].toUpperCase() + stage.slice(1)}</strong>
                  <small>
                    {rates[stage] != null ? money(rates[stage]) : "—"} /{" "}
                    {stage === "recording" ? "Jam" : "Lagu"}
                  </small>
                </div>
              </button>
            ))}
          </div>
          {stages.map(
            (stage) =>
              errors[`include_${stage}`] && (
                <small className="field-error" key={stage}>
                  {errors[`include_${stage}`].join(" ")}
                </small>
              ),
          )}
        </fieldset>
        <div className="package-divider">
          <DesignIcon name="modal-separator" />
        </div>
        <div className="package-quantities">
          <NumberStepper
            field={{
              ...field("recording_hours"),
              label: "Durasi Recording (Jam)",
            }}
            value={values.recording_hours}
            onChange={(value) => change("recording_hours", value)}
            error={errors.recording_hours}
            disabled={!values.include_recording}
          />
          <NumberStepper
            field={{ ...field("track_count"), label: "Jumlah Lagu" }}
            value={values.track_count}
            onChange={(value) => change("track_count", value)}
            error={errors.track_count}
          />
        </div>
        {editing && (
          <Field
            field={field("is_active")}
            value={values.is_active}
            onChange={(value) => change("is_active", value)}
            error={errors.is_active}
          />
        )}
      </div>
      <div className="package-pricing">
        <h3>Rincian Harga</h3>
        <ErrorNotice error={prices.error} />
        <div className="price-breakdown" aria-live="polite">
          {prices.loading ? (
            <p>Memuat harga studio...</p>
          ) : (
            selected.map((stage) => (
              <div className="price-line" key={stage}>
                <div>
                  <span>{stage[0].toUpperCase() + stage.slice(1)}</span>
                  <small>
                    {quantity(stage)} {stage === "recording" ? "Jam" : "Lagu"} x{" "}
                    {rates[stage] != null ? money(rates[stage]) : "—"}
                  </small>
                </div>
                <strong>
                  {rates[stage] != null
                    ? money(quantity(stage) * rates[stage])
                    : "—"}
                </strong>
              </div>
            ))
          )}
          {!selected.length && (
            <p>Pilih tahap produksi untuk menghitung harga.</p>
          )}
          <div className="package-divider">
            <DesignIcon name="modal-separator1" />
          </div>
          <div className="price-total">
            <strong>Harga Paket</strong>
            <output>
              {manual
                ? money(Number(values.manual_price) || 0)
                : ready
                  ? money(total)
                  : "—"}
            </output>
          </div>
        </div>
        <label className="manual-pricing">
          <span>Atur Harga Manual</span>
          <input
            type="checkbox"
            role="switch"
            checked={manual}
            onChange={(e) => {
              setManual(e.target.checked);
              if (!e.target.checked) change("manual_price", "");
            }}
          />
          <DesignIcon
            name={manual ? "modal-state-active" : "modal-state-nonactive"}
          />
        </label>
        {manual && (
          <Field
            field={{
              ...field("manual_price"),
              label: "Harga Paket",
              required: true,
            }}
            value={values.manual_price}
            onChange={(value) => change("manual_price", value)}
            error={errors.manual_price}
          />
        )}
      </div>
    </div>
  );
}
