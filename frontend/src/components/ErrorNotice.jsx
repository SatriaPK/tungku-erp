import { fieldLabel } from '../lib/contract';

export default function ErrorNotice({error}) {
  if (!error) return null;
  return <div role="alert" className="notice error"><strong>{error.message}</strong>
    {Object.entries(error.errors || {}).map(([key,value])=><span key={key}>{fieldLabel(key)}: {Array.isArray(value)?value.join(' '):String(value)}</span>)}
    {error.retryAfter>0 && <span>Coba lagi setelah {error.retryAfter} detik.</span>}
    {!error.status && <span>Jika tindakan sebelumnya terputus, periksa data terbaru sebelum mengulang.</span>}
  </div>;
}
