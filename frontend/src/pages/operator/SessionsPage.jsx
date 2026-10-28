import ResourcePage from "../shared/ResourcePage";

export default function SessionsPage({ openAction }) {
  return (
    <ResourcePage role="operator" page="sessions" openAction={openAction} />
  );
}
