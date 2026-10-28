import { fieldLabel, money, formatDate } from '../lib/contract';
import Badge from './Badge';
import SafeLink from './SafeLink';

export default function DataView({data}) {
  if (data == null) return <span className="muted">—</span>;
  if (Array.isArray(data)) return data.length ? <div className="data-list">{data.map((item,i)=><section className="data-item" key={item?.id ?? i}><DataView data={item}/></section>)}</div> : <p className="muted">Belum ada data.</p>;
  if (typeof data !== 'object') return <span>{String(data)}</span>;
  return <dl className="detail-grid">{Object.entries(data).filter(([key])=>!['gateway_response','snap_token'].includes(key)).map(([key,value])=><div key={key} className={typeof value==='object' && value!==null ? 'wide' : ''}><dt>{fieldLabel(key)}</dt><dd>{
    key.endsWith('_url') ? <SafeLink value={value}/> : key==='status' ? <Badge value={value} label={data.status_label}/> :
    value === null ? '—' : typeof value==='boolean' ? value ? 'Ya':'Tidak' : typeof value==='object' ? <DataView data={value}/> :
    /^(amount|price|auto_price|manual_price|total|subtotal|revenue|expense|net_profit|current|previous|delta|book_value|purchase_price|extra_fee|.*_amount|.*_value)$/.test(key) ? money(value) :
    /(_at|_on|_date)$/.test(key) ? formatDate(value) : String(value)
  }</dd></div>)}</dl>;
}
