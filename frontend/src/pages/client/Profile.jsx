import DataView from "../../components/DataView";
import PageTitle from "../../components/PageTitle";
import { make } from "../../lib/actions.js";

export default function Profile({ user, openAction, reload }) {
  return (
    <>
      <PageTitle
        title="Profil Saya"
        subtitle="Informasi akun dan persetujuan komunikasi."
      />
      <section className="panel">
        <DataView data={user} />
        {user.crm_consent && (
          <button
            onClick={() =>
              openAction(
                make("Cabut Persetujuan CRM", "/auth/crm-consent", [], {
                  method: "DELETE",
                  submit: "Cabut Persetujuan",
                  confirmation:
                    "Hentikan persetujuan menerima kampanye pemasaran?",
                }),
                reload,
              )
            }
          >
            Cabut Persetujuan CRM
          </button>
        )}
      </section>
    </>
  );
}
