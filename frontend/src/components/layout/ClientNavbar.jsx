import { useState } from "react";
import { sections, home } from "../../config/navigation";
import navigate from "../../lib/navigation";
import DesignIcon from "../DesignIcon";
import logo from "../../../assets/tungku-icon.svg";
export default function ClientNavbar({
  user,
  page,
  unreadCount,
  onLogout,
  logoutBusy,
}) {
  const [search, setSearch] = useState("");
  return (
    <header className="client-navbar">
      <a
        className="brand"
        href={home("client")}
        onClick={(e) => {
          e.preventDefault();
          navigate(home("client"));
        }}
      >
        <img src={logo} alt="" />
        <span>Tungku Studio</span>
      </a>
      <nav aria-label="Navigasi client">
        {sections.client
          .filter(([key]) => !["notifications", "profile"].includes(key))
          .map(([key, label]) => (
            <a
              className={page === key ? "active" : ""}
              aria-current={page === key ? "page" : undefined}
              key={key}
              href={`/client/${key}`}
              onClick={(e) => {
                e.preventDefault();
                navigate(`/client/${key}`);
              }}
            >
              {label}
            </a>
          ))}
      </nav>
      <form
        className="search client-navbar-search"
        onSubmit={(event) => {
          event.preventDefault();
          window.dispatchEvent(
            new CustomEvent("workspace-search", { detail: search }),
          );
          if (search && window.find) window.find(search);
        }}
      >
        <DesignIcon name="search" />
        <input
          aria-label="Cari pada halaman aktif"
          placeholder="Cari project..."
          value={search}
          onChange={(event) => setSearch(event.target.value)}
        />
      </form>
      <div className="client-account">
        <button
          className="icon-button bell"
          aria-label="Buka notifikasi"
          onClick={() => navigate("/client/notifications")}
        >
          <DesignIcon name="notifications" />
          {unreadCount > 0 && <em>{unreadCount}</em>}
        </button>
        <button
          className="avatar"
          aria-label="Profil saya"
          onClick={() => navigate("/client/profile")}
        >
          {user.name?.slice(0, 1)?.toUpperCase()}
        </button>
        <button
          className="icon-button"
          aria-label="Keluar"
          disabled={logoutBusy}
          onClick={onLogout}
        >
          <DesignIcon name="logout" />
        </button>
      </div>
    </header>
  );
}
