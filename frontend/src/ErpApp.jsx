import ManagerView from "./pages/manager/ManagerView";
import ClientView from "./pages/client/ClientView";
import OperatorView from "./pages/operator/OperatorView";
import ClientNavbar from "./components/layout/ClientNavbar";
import { connectNotifications } from "./lib/notificationSocket";
import ErrorNotice from "./components/ErrorNotice";
import PageTitle from "./components/PageTitle";
import Loading from "./components/Loading";
import { useCallback, useEffect, useState } from "react";
import { request } from "./lib/api";
import { sections, aliases, home } from "./config/navigation.js";
import { resourceConfigs } from "./config/resources.js";
import navigate from "./lib/navigation.js";
import { unwrap } from "./lib/resource.js";
import useResource from "./hooks/useResource.js";
import ActionModal from "./components/ActionModal.jsx";
import AuthPage from "./pages/auth/AuthPage.jsx";
import Sidebar from "./components/layout/Sidebar.jsx";
import Topbar from "./components/layout/Topbar.jsx";
import SearchResults from "./pages/shared/SearchResults.jsx";

const roleViews = {
  manager: ManagerView,
  client: ClientView,
  operator: OperatorView,
};

export default function App() {
  const [path, setPath] = useState(
      window.location.pathname + window.location.search,
    ),
    [user, setUser] = useState(null),
    [loading, setLoading] = useState(true),
    [error, setError] = useState(null),
    [action, setAction] = useState(null),
    [logoutBusy, setLogoutBusy] = useState(false),
    [menuOpen, setMenuOpen] = useState(false);
  const unread = useResource(user ? "/notifications/unread-count" : null);
  const [socketStatus, setSocketStatus] = useState("polling");
  useEffect(() => {
    if (!user) return;
    let last = 0;
    return connectNotifications({
      url: import.meta.env.VITE_NOTIFICATION_WS_URL,
      onStatus: setSocketStatus,
      onChange: () => {
        if (Date.now() - last < 2000) return;
        last = Date.now();
        unread.reload();
        window.dispatchEvent(new Event("inbox-updated"));
      },
    });
  }, [user?.id, unread.reload]);
  const checkSession = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setUser((await request("/auth/me")).data);
    } catch (error) {
      setUser(null);
      if (![401, 419].includes(error.status)) setError(error);
    } finally {
      setLoading(false);
    }
  }, []);
  useEffect(() => {
    checkSession();
  }, [checkSession]);
  useEffect(() => {
    const restricted = (event) => {
      setUser(null);
      setAction(null);
      setError(event.detail);
      navigate("/login");
    };
    window.addEventListener("session-restricted", restricted);
    return () => window.removeEventListener("session-restricted", restricted);
  }, []);
  useEffect(() => {
    const onPath = () => {
      setPath(window.location.pathname + window.location.search);
      setMenuOpen(false);
    };
    const expired = () => {
      setUser(null);
      setAction(null);
      navigate("/login");
    };
    const restored = (event) => setUser(event.detail);
    window.addEventListener("popstate", onPath);
    window.addEventListener("session-expired", expired);
    window.addEventListener("session-restored", restored);
    return () => {
      window.removeEventListener("popstate", onPath);
      window.removeEventListener("session-expired", expired);
      window.removeEventListener("session-restored", restored);
    };
  }, []);
  useEffect(() => {
    if (!user) return;
    const id = setInterval(() => {
      if (!document.hidden) unread.reload();
    }, 30000);
    return () => clearInterval(id);
  }, [user, unread.reload]);
  const unreadCount = unwrap(unread.data)?.unread_count || 0;
  const role = user?.role;
  const rawPage = path.split("?")[0].split("/").slice(2).join("/");
  const page =
    role === "manager" && rawPage === "invoice"
      ? "payments"
      : aliases[rawPage] || rawPage || sections[role]?.[0][0];
  useEffect(() => {
    if (
      user &&
      sections[user.role] &&
      (path === "/" ||
        path === "/login" ||
        path === "/register" ||
        path.split("/")[1] !== user.role)
    )
      navigate(home(user.role));
  }, [user, path]);
  function onLogin(profile) {
    setUser(profile);
    setError(null);
    navigate(home(profile.role));
  }
  function openAction(config, done = () => {}) {
    setAction({ ...config, done });
  }
  async function logout() {
    setLogoutBusy(true);
    setError(null);
    try {
      await request("/auth/logout", { method: "POST" });
      setUser(null);
      setAction(null);
      navigate("/login");
    } catch (error) {
      setError(error);
    } finally {
      setLogoutBusy(false);
    }
  }
  if (loading)
    return (
      <main className="startup">
        <Loading />
      </main>
    );
  if (!user)
    return (
      <>
        <AuthPage
          key={path === "/register" ? "register" : "login"}
          onLogin={onLogin}
          mode={path === "/register" ? "register" : "login"}
        />
        {error && (
          <div className="connection-error">
            <ErrorNotice error={error} />
            <button onClick={checkSession}>Coba Hubungkan Lagi</button>
            {error.status === 403 && (
              <button disabled={logoutBusy} onClick={logout}>
                Keluar dari Session
              </button>
            )}
          </div>
        )}
      </>
    );
  if (!sections[role])
    return (
      <main className="startup">
        <p>Role akun ini tidak dikenali.</p>
        <button onClick={logout}>Keluar</button>
      </main>
    );
  const RoleView = roleViews[role];
  const allowed =
    page === 'search' || sections[role].some(([key]) => key === page) ||
    (role === "manager" &&
      ["expense-categories", "inventory-categories"].includes(page)) ||
    (role === "client" && page === "payment");
  const title =
    sections[role].find(([key]) => key === page)?.[1] ||
    resourceConfigs[page]?.title ||
    (page === 'search' ? 'Hasil Pencarian' : page === "payment" ? "Pembayaran Saya" : "Halaman");
  return (
    <div
      className={`app-shell role-${role} page-${page} ${menuOpen ? "menu-open" : ""}`}
    >
      <Sidebar
        user={user}
        page={page}
        unreadCount={unreadCount}
        logoutBusy={logoutBusy}
        onLogout={logout}
      />
      <div className="workspace">
        {role === "client" && (
          <ClientNavbar
            user={user}
            page={page}
            unreadCount={unreadCount}
            onLogout={logout}
            logoutBusy={logoutBusy}
          />
        )}
        <Topbar
          user={user}
          title={title}
          page={page}
          unreadCount={unreadCount}
          socketStatus={socketStatus}
          onToggleMenu={() => setMenuOpen((v) => !v)}
        />
        <main className="page-content">
          <ErrorNotice error={error} />
          {!allowed ? (
            <>
              <PageTitle
                title="Halaman tidak tersedia"
                subtitle="Halaman ini tidak tersedia untuk role akun kamu."
              />
              <button onClick={() => navigate(home(role))}>
                Kembali ke Workspace
              </button>
            </>
          ) : page === 'search' ? <SearchResults key={path} role={role} /> : (
            <RoleView
              key={path}
              page={page}
              user={user}
              openAction={openAction}
              onRead={unread.reload}
              reloadSession={checkSession}
            />
          )}
        </main>
        <footer className="workspace-footer">
          TUNGKU STUDIO <span>Ruang untuk berkarya.</span>
        </footer>
      </div>
      {action && (
        <ActionModal
          action={action}
          onClose={() => setAction(null)}
          onDone={() => {
            action.done();
            unread.reload();
          }}
        />
      )}
    </div>
  );
}
