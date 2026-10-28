import Dashboard from "./Dashboard";
import Finance from "./Finance";
import ExternalManagement from "../../components/ExternalManagement";
import Notifications from "../shared/Notifications";
import ResourcePage from "../shared/ResourcePage";

export default function ManagerView({ page, openAction, onRead }) {
  switch (page) {
    case "dashboard":
      return <Dashboard openAction={openAction} />;
    case "project":
      return <Dashboard production openAction={openAction} />;
    case "reports":
      return <Finance openAction={openAction} />;
    case "management":
      return <ExternalManagement />;
    case "notifications":
      return <Notifications role="manager" onRead={onRead} />;
    default:
      return (
        <ResourcePage role="manager" page={page} openAction={openAction} />
      );
  }
}
