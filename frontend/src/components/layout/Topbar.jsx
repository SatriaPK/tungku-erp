import { Menu, Search } from "lucide-react";
import DesignIcon from "../DesignIcon";
import navigate from "../../lib/navigation";
import { useState } from "react";
const groups = {
  dashboard: "Utama",
  booking: "Operasional",
  quotation: "Operasional",
  project: "Operasional",
  inventaris: "Operasional",
  packages: "Penjualan",
  crm: "Penjualan",
  payments: "Keuangan",
  reports: "Keuangan",
  expenses: "Keuangan",
  settings: "Administrasi",
  management: "Administrasi",
  notifications: "Workspace",
};
export default function Topbar({
  user,
  title,
  page,
  unreadCount,
  socketStatus,
  onToggleMenu,
}) {
  const [search, setSearch] = useState("");
  return (
    <header className="topbar">
      <button
        className="mobile-menu icon-button"
        aria-label="Buka menu"
        onClick={onToggleMenu}
      >
        <Menu />
      </button>
      <div className="breadcrumb">
        <small>
          {groups[page] || "Workspace"} / {title}
        </small>
        <strong>{title}</strong>
      </div>
      <div className="top-right">
        <form
          className="topbar-search search"
          onSubmit={(e) => {
            e.preventDefault();
            window.dispatchEvent(
              new CustomEvent("workspace-search", { detail: search }),
            );
            if (search && window.find) window.find(search);
          }}
        >
          <Search size={20} />
          <input
            aria-label="Cari pada halaman aktif"
            placeholder="Cari pada halaman ini..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </form>
        {user.role === "manager" && (
          <button
            className="icon-button topbar-settings"
            aria-label="Pengaturan studio"
            onClick={() => navigate("/manager/settings")}
          >
            <DesignIcon name="settings" />
          </button>
        )}
        <button
          className="icon-button bell"
          aria-label="Buka notifikasi"
          title={
            socketStatus === "connected"
              ? "Notifikasi terhubung"
              : "Notifikasi diperbarui berkala"
          }
          onClick={() => navigate(`/${user.role}/notifications`)}
        >
          <DesignIcon name="notifications" />
          {unreadCount > 0 && <em>{unreadCount}</em>}
        </button>
      </div>
    </header>
  );
}
