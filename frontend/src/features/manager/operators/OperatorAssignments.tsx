import { useState } from 'react'
import { Badge, Icon } from '../../../components/ui'
import type { ManagerOperator } from '../../../lib/manager-operators-api'

export default function OperatorAssignments({ projects }: { projects: ManagerOperator['assigned_projects'] }) {
  const [index, setIndex] = useState(0)
  const uniqueProjects = projects === undefined ? undefined : [...new Map(projects.map((project) => [project.id, project])).values()]
  const currentIndex = Math.min(index, Math.max(0, (uniqueProjects?.length ?? 0) - 1))
  const project = uniqueProjects?.[currentIndex]

  return <>
    <div className="manager-operator-detail__section-heading"><h3>Penugasan Operator</h3><span>{uniqueProjects === undefined ? 'Jumlah project belum tersedia' : `${uniqueProjects.length.toLocaleString('id-ID')} project`}</span></div>
    {project ? <article className="manager-operator-project-card">
      <div className="manager-operator-project-card__heading"><span className="manager-operator-project-avatar"><Icon name="folder" size={22} /></span><div><h4>{project.name}</h4><p>{project.status_label}</p></div></div>
      <div>{project.stages.length ? project.stages.map((stage, stageIndex) => <Badge key={`${stage}-${stageIndex}`} tone="neutral">{stage}</Badge>) : <p>Tahap penugasan belum tersedia.</p>}</div>
    </article> : <div className="manager-operator-project-empty"><Icon name="folder" size={22} /><span>{uniqueProjects === undefined ? 'Data penugasan project belum tersedia.' : 'Belum ada project yang ditugaskan.'}</span></div>}
    {uniqueProjects && uniqueProjects.length > 1 && <div aria-label="Halaman penugasan" className="manager-operator-dots">
      {uniqueProjects.map((item, itemIndex) => <button aria-label={`Tampilkan project ${item.name}`} aria-current={itemIndex === currentIndex ? 'true' : undefined} className={itemIndex === currentIndex ? 'is-active' : undefined} key={item.id} onClick={() => setIndex(itemIndex)} type="button"><span aria-hidden="true">•</span></button>)}
    </div>}
  </>
}
