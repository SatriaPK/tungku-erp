import { humanize } from '../lib/contract';

export default function Badge({value,label}) { return <span className={`badge ${value || ''}`}>{label || humanize(value) || '—'}</span>; }
