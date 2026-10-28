import { useState } from 'react';
import useResource from '../../hooks/useResource';
import PageTitle from '../../components/PageTitle';
import ErrorNotice from '../../components/ErrorNotice';
import Loading from '../../components/Loading';
import Empty from '../../components/Empty';
import Badge from '../../components/Badge';
import Pagination from '../../components/Pagination';
import navigate from '../../lib/navigation';
import { positiveId } from '../../lib/workflow';

export default function SearchResults({ role }) {
  const query = new URLSearchParams(window.location.search);
  const term = query.get('q') || '';
  const [page, setPage] = useState(1);
  const params = { q: term, page };
  if (positiveId(query.get('client'))) params.client_id = query.get('client');
  if (positiveId(query.get('project'))) params.project_id = query.get('project');
  const resource = useResource('/search', params);
  const projects = resource.data?.data?.projects || [];
  const clients = resource.data?.data?.clients || [];
  function workspace(project) {
    const context = new URLSearchParams({ project: project.id });
    if (project.stage_id) context.set('stage', project.stage_id);
    navigate(`/${role}/${role === 'manager' ? 'project' : role === 'operator' ? 'tasks' : 'delivery'}?${context}`);
  }
  return <>
    <PageTitle title="Hasil Pencarian" subtitle={term ? `Pencarian proyek dan klien untuk “${term}”.` : 'Proyek dan klien dalam akses akun kamu.'} />
    <ErrorNotice error={resource.error} />
    {resource.loading ? <Loading /> : !resource.error && <>
      {clients.length > 0 && <section className="panel search-client-results"><h2>Klien</h2>{clients.map(client => <article key={client.id}>
        <strong>{client.name}</strong>
        <button onClick={() => navigate(`/${role}/search?${new URLSearchParams({ q: client.name, client: client.id })}`)}>Lihat proyek klien</button>
      </article>)}</section>}
      <section className="panel search-project-results"><h2>Proyek ({resource.data?.meta?.total || 0})</h2>
        {projects.length ? projects.map(project => <article key={project.id}>
          <div><h3>{project.name}</h3><p>{project.code} · {project.client?.name}</p><Badge value={project.status} label={project.status_label} /></div>
          <button onClick={() => workspace(project)}>{role === 'client' ? 'Buka hasil proyek' : 'Buka produksi'}</button>
        </article>) : <Empty text="Tidak ada proyek yang cocok dengan pencarian ini." />}
      </section>
      {resource.data?.meta && <Pagination meta={resource.data.meta} onChange={setPage} />}
    </>}
  </>;
}
