export function positiveId(value) {
  const text=String(value ?? '');
  return /^[1-9]\d*$/.test(text) && Number.isSafeInteger(Number(text)) ? text : '';
}

export function workflowContext(search=window.location.search) {
  const params=new URLSearchParams(search);
  return Object.fromEntries(['project','stage','track','progress','submission','payment','invoice','id'].map(key=>[key,positiveId(params.get(key))]));
}

// Construct local destinations from known resource types. Never use a server URL.
export function notificationRoute(role,item) {
  if(!['manager','operator','client'].includes(role)) return null;
  const data=item?.data;
  if(!data || Array.isArray(data)) return null;
  const target=data.target || {}, metadata=data.metadata || {};
  let type=target.type, id=positiveId(target.id);
  if(!type) {
    if(positiveId(data.invoice_id)) {type='invoice';id=positiveId(data.invoice_id);}
    else if(positiveId(data.payment_id)) {type='payment';id=positiveId(data.payment_id);}
    else return null;
  }
  const context={};
  for(const [key,source] of Object.entries({project:'project_id',stage:'stage_id',track:'track_id',progress:'track_stage_progress_id',submission:'submission_id',payment:'payment_id',invoice:'invoice_id'})) {
    const value=positiveId(metadata[source]);if(value) context[key]=value;
  }
  // Alternative metadata names refer to the same documented resource IDs.
  context.track ||= positiveId(metadata.project_track_id);
  context.progress ||= positiveId(metadata.progress_id);
  let page;
  if(type==='project') {context.project=id;page=role==='client'?'delivery':role==='manager'?'project':null;}
  else if(type==='stage' || type==='project_stage') {context.stage=id;page=role==='operator'?'tasks':role==='manager'?'project':null;}
  else if(type==='track_progress') {context.progress=id;page=role==='operator'?'tasks':role==='manager'?'project':null;}
  else if(type==='submission') {context.submission=id;page=role==='operator'?'tasks':role==='manager'?'project':null;}
  else if(type==='payment') {context[role==='client'?'payment':'id']=id;page=role==='client'?'payment':role==='manager'?'payments':null;}
  else if(type==='invoice') {context[role==='client'?'id':'invoice']=id;page=role==='client'?'invoice':role==='manager' && context.payment?'payments':null;if(role==='manager' && context.payment)context.id=context.payment;}
  else if(type==='booking' || type==='recording_session') {context.id=id;page=role==='operator'?'sessions':'booking';}
  else if(type==='quotation') {context.id=id;page=role==='operator'?null:'quotation';}
  else return null;
  if(!page || !id) return null;
  const params=new URLSearchParams(Object.entries(context).filter(([,value])=>value));
  return `/${role}/${page}?${params}`;
}

export function projectChoices(invoices=[]) {
  const unique=new Map();
  for(const invoice of invoices) {
    const project=invoice.project;
    if(positiveId(project?.id)) unique.set(String(project.id),{id:String(project.id),name:project.name || `Project #${project.id}`});
  }
  return [...unique.values()];
}
