export const stages = ["recording", "editing", "mixing", "mastering"];

export const resourceConfigs = {
  booking: {
    path: "bookings",
    title: "Booking",
    subtitle: "Jadwal rekaman dan reservasi ruang studio.",
  },
  quotation: {
    path: "quotations",
    title: "Penawaran",
    subtitle: "Spesifikasi karya dan negosiasi harga.",
  },
  packages: {
    path: "packages",
    title: "Paket Produksi",
    subtitle: "Pilih rangkaian proses yang tepat untuk setiap karya.",
    schema: "PackageRequest",
  },
  inventaris: {
    path: "inventories",
    title: "Inventaris",
    subtitle: "Aset, nilai buku, dan perlengkapan studio.",
    schema: "InventoryRequest",
  },
  expenses: {
    path: "expenses",
    title: "Pengeluaran",
    subtitle: "Catat biaya operasional studio.",
    schema: "ExpenseRequest",
  },
  payments: {
    path: "payments",
    title: "Pembayaran",
    subtitle: "Pantau pembayaran dan konfirmasi penerimaan tunai.",
  },
  invoice: {
    path: "invoices",
    title: "Invoice",
    subtitle: "Tagihan dan pembayaran untuk karya kamu.",
  },
  sessions: {
    path: "sessions",
    title: "Sesi Saya",
    subtitle: "Sesi rekaman yang ditugaskan kepada kamu.",
  },
  crm: {
    path: "crm/campaigns",
    title: "CRM & Kampanye",
    subtitle: "Jaga hubungan dengan klien melalui kampanye email.",
    schema: "CreateCRMCampaignRequest",
  },
  settings: {
    path: "settings",
    title: "Pengaturan Studio",
    subtitle: "Kelola konfigurasi dan harga dasar studio.",
  },
  "expense-categories": {
    path: "expense-categories",
    title: "Kategori Pengeluaran",
    schema: "ExpenseCategoryRequest",
  },
  "inventory-categories": {
    path: "inventory-categories",
    title: "Kategori Inventaris",
    schema: "InventoryCategoryRequest",
  },
};
