import schemas from "./schemas.json";
import { positiveId } from "./workflow";

export const f = (name, rules = "integer", required = true, extra = {}) => ({
  name,
  rules,
  required,
  ...extra,
});

export const make = (title, path, schema, options = {}) => ({
  title,
  path,
  fields: typeof schema === "string" ? schemas[schema] : schema || [],
  method: "POST",
  ...options,
});

export function finalPaymentAction(id) {
  return make(
    "Mulai Pelunasan",
    `/client/payments/${positiveId(id)}/final/initiate`,
    [
      f("provider", "string", true, {
        options: ["midtrans", "xendit", "cash"],
      }),
    ],
    {
      description:
        "Pembayaran ini khusus tagihan pelunasan. Tunai dikonfirmasi oleh Manager; status lunas ditentukan studio.",
      submit: "Mulai Pelunasan",
      success:
        "Permintaan pelunasan diproses. Periksa status pembayaran terbaru sebelum melanjutkan.",
    },
  );
}
