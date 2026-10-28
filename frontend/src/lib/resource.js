export const unwrap = (response) =>
  response && Object.hasOwn(response, "data") ? response.data : response;

export const rowsOf = (response) => {
  const value = unwrap(response);
  return Array.isArray(value) ? value : [];
};

export const isFinal = (data) =>
  ["finalized", "finalised"].includes(data?.status);

export function itemTitle(row) {
  return (
    row.name ||
    row.invoice_number ||
    row.order_id ||
    row.description ||
    row.package_display_name ||
    row.room_name ||
    row.key ||
    row.track_title ||
    `#${row.id}`
  );
}
