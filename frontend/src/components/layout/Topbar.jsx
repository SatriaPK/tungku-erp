import { Menu } from "lucide-react";
import GlobalSearch from "../GlobalSearch";
import DesignIcon from "../DesignIcon";
import navigate from "../../lib/navigation";
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
        <GlobalSearch role={user.role} className={`topbar-search ${user.role === 'client' ? 'client-mobile-search' : ''}`} />
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
