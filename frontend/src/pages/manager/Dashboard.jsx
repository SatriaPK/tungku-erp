import ManagerOverview from "../../components/dashboard/ManagerOverview";
import ErrorNotice from "../../components/ErrorNotice";
import Badge from "../../components/Badge";

import PageTitle from "../../components/PageTitle";
import Loading from "../../components/Loading";
import Empty from "../../components/Empty";

import { RefreshCw } from "lucide-react";
import { humanize } from "../../lib/contract";
import { stages } from "../../config/resources.js";
import { unwrap } from "../../lib/resource.js";
import { f, make } from "../../lib/actions.js";
import useResource from "../../hooks/useResource.js";
import ProductionTools from "../../components/production/ProductionTools.jsx";

export default function Dashboard({ openAction, production = false }) {
  const resource = useResource("/manager/dashboard");
  const raw = unwrap(resource.data);
  const data = raw
    ? { ...raw, workflow: resource.data?.meta?.workflow || raw.workflow }
    : null;
  if (resource.loading) return <Loading />;
  if (!production)
    return (
      <>
        <ErrorNotice error={resource.error} />
        {data && <ManagerOverview data={data} reload={resource.reload} />}
      </>
    );
  return (
    <>
      <PageTitle
        title={production ? "Produksi Studio" : "Dashboard"}
        subtitle={
          production
            ? "Penugasan dan review setiap tahap produksi."
            : "Seluruh aktivitas studio, dalam satu pandangan."
        }
      >
        <button onClick={resource.reload}>
          <RefreshCw size={16} />
          Muat Ulang
        </button>
      </PageTitle>
      <ErrorNotice error={resource.error} />
      {data && (
        <>
          <section className="panel">
            <h2>Progress Project</h2>
            <div className="table-scroll">
              <table>
                <thead>
                  <tr>
                    <th>Project</th>
                    <th>Klien</th>
                    <th>Tahap</th>
                    <th>Status</th>
                    {production && <th>Tindakan</th>}
                  </tr>
                </thead>
                <tbody>
                  {(production && data.workflow
                    ? data.workflow.projects
                    : data.project_progress || []
                  ).map((row) => (
                    <tr key={row.id}>
                      <td>
                        <strong>{row.name}</strong>
                        <small>#{row.id}</small>
                      </td>
                      <td>{row.client_name || "—"}</td>
                      <td>{row.current_stage_label || "—"}</td>
                      <td>
                        <Badge value={row.status} label={row.status_label} />
                      </td>
                      {production && (
                        <td>
                          {row.status === "awaiting_assignment" && (
                            <button
                              onClick={() =>
                                openAction(
                                  make(
                                    "Tugaskan Operator",
                                    `/manager/projects/${row.id}/assignments`,
                                    row.stages
                                      ? row.stages.map((stage) =>
                                          f(stage.stage, "integer", true, {
                                            label: `Operator ${stage.stage_label}`,
                                            options:
                                              data.workflow.operators.map(
                                                (operator) => ({
                                                  value: operator.id,
                                                  label: operator.name,
                                                }),
                                              ),
                                          }),
                                        )
                                      : stages.map((stage) =>
                                          f(stage, "integer", false, {
                                            label: `Operator ${humanize(stage)}`,
                                          }),
                                        ),
                                    {
                                      description:
                                        "Isi seluruh tahap aktif dalam snapshot project. Operator harus aktif dan berbeda untuk setiap tahap.",
                                      transform: (body) => ({
                                        assignments: body,
                                      }),
                                    },
                                  ),
                                  resource.reload,
                                )
                              }
                            >
                              Tugaskan
                            </button>
                          )}
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {!(
              production && data.workflow
                ? data.workflow.projects
                : data.project_progress
            )?.length && <Empty />}
          </section>
          {production && (
            <ProductionTools openAction={openAction} reload={resource.reload} />
          )}
        </>
      )}
    </>
  );
}
