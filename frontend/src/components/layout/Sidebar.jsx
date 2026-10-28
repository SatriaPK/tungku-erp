import DesignIcon from "../DesignIcon";
import { ChevronDown } from "lucide-react";
import logo from "../../../assets/tungku-icon.svg";
import { sections, home } from "../../config/navigation";
import navigate from "../../lib/navigation";

const icons = {
  dashboard: "dashboard",
  booking: "calendar",
  quotation: "handshake",
  project: "folder",
  packages: "package",
  inventaris: "inventory",
  crm: "group",
  payments: "receipt",
  reports: "document",
  management: "account-box",
  expenses: "finance",
  settings: "settings",
  notifications: "notifications",
  sessions: "calendar",
  tasks: "folder",
  invoice: "receipt",
  delivery: "folder",
  profile: "account-box",
};
const managerGroups = [
  ["UTAMA", ["dashboard"]],
  ["OPERASIONAL", ["booking", "quotation", "project", "inventaris"]],
  ["PENJUALAN", ["packages", "crm"]],
  ["KEUANGAN", ["payments", "reports", "expenses"]],
  ["ADMINISTRASI", ["management", "settings", "notifications"]],
];
export default function Sidebar({
  user,
  page,
  unreadCount,
  logoutBusy,
  onLogout,
}) {
  const role = user.role;
  const groups =
    role === "manager"
      ? managerGroups
      : [
          [
            role === "operator" ? "WORKSPACE OPERATOR" : "WORKSPACE CLIENT",
            sections[role].map(([key]) => key),
          ],
        ];
  return (
    <aside className="sidebar">
      <a
        className="brand"
        href={home(role)}
        onClick={(e) => {
          e.preventDefault();
          navigate(home(role));
        }}
      >
        <img src={logo} alt="" />
        <div>
          Tungku Studio<small>Enterprise Resource Planning</small>
        </div>
      </a>
      <nav aria-label={`Navigasi ${role}`}>
        {groups.map(([title, keys]) => (
          <div className="nav-group" key={title}>
            <div className="nav-group-label">
              {title}
              <ChevronDown size={14} />
            </div>
            {sections[role]
              .filter(([key]) => keys.includes(key))
              .map(([key, label]) => (
                <a
                  key={key}
                  className={page === key ? "active" : ""}
                  aria-current={page === key ? "page" : undefined}
                  href={`/${role}/${key}`}
                  onClick={(e) => {
                    e.preventDefault();
                    navigate(`/${role}/${key}`);
                  }}
                >
                  <DesignIcon name={icons[key]} />
                  <span>{label}</span>
                  {key === "notifications" && unreadCount > 0 && (
                    <em>{unreadCount}</em>
                  )}
                </a>
              ))}
          </div>
        ))}
      </nav>
      <div className="account">
        <div className="avatar">{user.name?.slice(0, 1)?.toUpperCase()}</div>
        <div>
          <strong>{user.name}</strong>
          <small>{user.role_label || role}</small>
        </div>
        <button
          className="icon-button"
          disabled={logoutBusy}
          onClick={onLogout}
          aria-label="Keluar"
        >
          <DesignIcon name="logout" />
        </button>
      </div>
    </aside>
  );
}
