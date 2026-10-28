import { ArrowUpRight } from 'lucide-react';

export default function SafeLink({value,children}) {
  let safe=false;
  try { safe=['https:','http:'].includes(new URL(value).protocol); } catch { /* Invalid URL. */ }
  return safe ? <a className="text-link" href={value} target="_blank" rel="noopener noreferrer">{children || 'Buka tautan'} <ArrowUpRight size={14}/></a> : <span>—</span>;
}
