import { fieldLabel, humanize } from "../lib/contract";

export default function Field({ field, value, onChange, error }) {
  const { name, rules, required } = field;
  const enumMatch = rules.match(/enum=(\[[^\]]*\])/);
  let choices = field.options || (enumMatch ? JSON.parse(enumMatch[1]) : null);
  if (name === "response_type") choices = ["accept", "decline", "counter"];
  if (name === "decision") choices = ["approve", "request_revision"];
  if (name === "channels")
    return (
      <label className="field">
        <span>Saluran</span>
        <input value="Email" readOnly />
      </label>
    );
  const array = rules.startsWith("array");
  const type =
    rules === "boolean"
      ? "checkbox"
      : rules.includes("date-time")
        ? "datetime-local"
        : rules.includes('format="date"')
          ? "date"
          : name.includes("password")
            ? "password"
            : name === "email"
              ? "email"
              : rules.startsWith("integer") || rules.startsWith("number")
                ? "number"
                : rules.includes('format="uri"')
                  ? "url"
                  : "text";
  const min = rules.match(/minimum=([\d.]+)/)?.[1],
    max = rules.match(/maximum=([\d.]+)/)?.[1];
  const maxLength = rules.match(/maxLength=(\d+)/)?.[1],
    minLength = rules.match(/minLength=(\d+)/)?.[1];
  const props = {
    className: "type-body-large",
    id: `field-${name}`,
    name,
    value: value ?? "",
    onChange: (e) => onChange(e.target.value),
    readOnly: name === "latest_offer_id",
    required: required && type !== "checkbox",
    min,
    max,
    maxLength,
    minLength,
    "aria-invalid": Boolean(error),
    "aria-describedby": error ? `error-${name}` : undefined,
  };
  return (
    <label
      className={`field ${type === "checkbox" ? "check-field" : ""}`}
      htmlFor={props.id}
    >
      <span className="type-label-large">
        {field.label || fieldLabel(name)}
        {required && type !== "checkbox" ? " *" : ""}
        {type === "datetime-local" ? " (WIB)" : ""}
      </span>
      {choices ? (
        <select
          {...props}
          multiple={array}
          value={
            array
              ? Array.isArray(value)
                ? value
                : String(value || "")
                    .split(",")
                    .filter(Boolean)
              : props.value
          }
          onChange={
            array
              ? (e) =>
                  onChange(
                    Array.from(
                      e.target.selectedOptions,
                      (option) => option.value,
                    ),
                  )
              : props.onChange
          }
        >
          {!array && <option value="">Pilih...</option>}
          {choices.map((choice) => {
            const item =
              typeof choice === "object"
                ? choice
                : { value: choice, label: humanize(choice) };
            return (
              <option key={item.value} value={item.value}>
                {item.label}
              </option>
            );
          })}
        </select>
      ) : type === "checkbox" ? (
        <input
          {...props}
          type="checkbox"
          checked={Boolean(value)}
          onChange={(e) => onChange(e.target.checked)}
          value={undefined}
        />
      ) : [
          "message",
          "notes",
          "note",
          "review_notes",
          "description",
          "reason",
        ].includes(name) ? (
        <textarea {...props} rows={3} />
      ) : (
        <input
          {...props}
          type={type}
          step={
            type === "number"
              ? rules.startsWith("integer")
                ? "1"
                : "0.01"
              : undefined
          }
          placeholder={
            field.placeholder || (array ? "Pisahkan dengan koma" : "")
          }
        />
      )}
      {error && (
        <small id={`error-${name}`} className="field-error">
          {Array.isArray(error) ? error.join(" ") : error}
        </small>
      )}
    </label>
  );
}
