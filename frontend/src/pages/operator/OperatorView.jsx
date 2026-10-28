import TasksPage from "./TasksPage";
import SessionsPage from "./SessionsPage";
import Notifications from "../shared/Notifications";

export default function OperatorView({ page, openAction, onRead }) {
  switch (page) {
    case "tasks":
      return <TasksPage openAction={openAction} />;
    case "notifications":
      return <Notifications role="operator" onRead={onRead} />;
    default:
      return <SessionsPage openAction={openAction} />;
  }
}
