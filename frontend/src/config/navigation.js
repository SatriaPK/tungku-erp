import {
  LayoutDashboard,
  CalendarDays,
  FileText,
  AudioLines,
  Package,
  Boxes,
  Users,
  Wallet,
  Settings,
  Bell,
  Headphones,
} from "lucide-react";

export const sections = {
  manager: [
    ["dashboard", "Dashboard", LayoutDashboard],
    ["booking", "Booking", CalendarDays],
    ["quotation", "Penawaran", FileText],
    ["project", "Produksi", AudioLines],
    ["packages", "Manajemen Paket", Package],
    ["inventaris", "Inventaris", Boxes],
    ["crm", "CRM & Kampanye", Users],
    ["payments", "Pembayaran", Wallet],
    ["reports", "Laporan & Payroll", FileText],
    ["management", "Room / User / Project", Users],
    ["expenses", "Pengeluaran", Wallet],
    ["settings", "Pengaturan", Settings],
    ["notifications", "Notifikasi", Bell],
  ],
  operator: [
    ["sessions", "Sesi Saya", Headphones],
    ["tasks", "Pekerjaan Produksi", AudioLines],
    ["notifications", "Notifikasi", Bell],
  ],
  client: [
    ["packages", "Katalog Paket", Package],
    ["booking", "Booking Saya", CalendarDays],
    ["quotation", "Penawaran Saya", FileText],
    ["invoice", "Invoice Saya", Wallet],
    ["delivery", "Hasil Akhir", Headphones],
    ["notifications", "Notifikasi", Bell],
    ["profile", "Profil Saya", Users],
  ],
};

export const aliases = {
  bookings: "booking",
  quotations: "quotation",
  projects: "project",
  inventories: "inventaris",
  finance: "reports",
  operator: "project",
  assignments: "project",
  "project/create": "project",
};

export const home = (role) => `/${role}/${sections[role]?.[0][0] || ""}`;
