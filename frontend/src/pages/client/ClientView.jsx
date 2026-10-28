import Delivery from "./Delivery";
import Payment from "./Payment";
import Profile from "./Profile";
import Notifications from "../shared/Notifications";
import ResourcePage from "../shared/ResourcePage";

export default function ClientView({
  page,
  user,
  openAction,
  onRead,
  reloadSession,
}) {
  switch (page) {
    case "delivery":
      return <Delivery />;
    case "payment":
      return <Payment openAction={openAction} />;
    case "profile":
      return (
        <Profile user={user} openAction={openAction} reload={reloadSession} />
      );
    case "notifications":
      return <Notifications role="client" onRead={onRead} />;
    default:
      return <ResourcePage role="client" page={page} openAction={openAction} />;
  }
}
