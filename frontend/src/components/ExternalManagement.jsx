import { useEffect, useState } from 'react';
import axios from 'axios';
import PageTitle from './PageTitle';
import Loading from './Loading';
import ErrorNotice from './ErrorNotice';
import DataView from './DataView';

const baseURL = import.meta.env.VITE_MANAGEMENT_API_URL?.replace(/\/$/, '');
const client = axios.create({ baseURL, timeout: 20000, withCredentials: import.meta.env.VITE_EXTERNAL_WITH_CREDENTIALS === 'true', headers: { Accept: 'application/json' } });
const names = { rooms: 'Room', users: 'User', projects: 'Project' };
export default function ExternalManagement() {
  const [kind, setKind] = useState('rooms'), [rows, setRows] = useState([]), [page, setPage] = useState(1), [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false), [busy, setBusy] = useState(false), [error, setError] = useState(null), [refresh, setRefresh] = useState(0);
  const [editing, setEditing] = useState(null), [name, setName] = useState(''), [email, setEmail] = useState(''), [active, setActive] = useState(true);
  const [deleting, setDeleting] = useState(null), [success, setSuccess] = useState('');
  useEffect(() => {
    if (!baseURL) return;
    const controller = new AbortController(); setLoading(true); setError(null); setRows([]);
    client.get(`/${kind}`, { params: { page }, signal: controller.signal }).then(({ data }) => {
      const list = Array.isArray(data) ? data : data.data;
      if (!Array.isArray(list)) throw new Error('Respons daftar harus berupa array atau { data: [] }.');
      setRows(list); setLastPage(data.meta?.last_page || 1);
    }).catch(error => { if (!controller.signal.aborted) setError(error); }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [kind, page, refresh]);
  function edit(row = {}) { setEditing(row); setName(row.name || ''); setEmail(row.email || ''); setActive(row.is_active ?? true); setSuccess(''); }
  async function mutate(event) {
    event.preventDefault(); setBusy(true); setError(null); setSuccess('');
    try {
      const path = `/${kind}${deleting ? `/${encodeURIComponent(deleting.id)}` : editing?.id != null ? `/${encodeURIComponent(editing.id)}` : ''}`;
      const data = { name: name.trim(), ...(kind !== 'projects' ? { is_active: active } : {}), ...(kind === 'users' ? { email } : {}) };
      await client.request({ url: path, method: deleting ? 'DELETE' : editing?.id != null ? 'PATCH' : 'POST', data: deleting ? undefined : data });
      setEditing(null); setDeleting(null); setSuccess('Perubahan disimpan oleh layanan eksternal.'); setRefresh(value => value + 1);
    } catch (error) { setError(new Error(error.response?.data?.message || error.message)); }
    finally { setBusy(false); }
  }
  return <><PageTitle title="Manajemen Room / User / Project" subtitle="Data dari layanan eksternal yang dikonfigurasi."/>
    {!baseURL ? <section className="panel"><p>Layanan pengelolaan belum terhubung. URL dan kontrak autentikasi diperlukan untuk mengaktifkan tambah, ubah, dan hapus data.</p></section> : <>
      <div className="action-strip"><label className="field"><span>Jenis data</span><select value={kind} disabled={busy} onChange={event => { setKind(event.target.value); setPage(1); setEditing(null); setDeleting(null); setSuccess(''); }}>{Object.entries(names).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><button disabled={busy || loading} onClick={() => edit()}>Tambah {names[kind]}</button><button disabled={busy || loading} onClick={() => setRefresh(value => value + 1)}>Muat Ulang</button></div>
      <ErrorNotice error={error}/>{success && <p role="status">{success}</p>}
      {(editing || deleting) && <form className="panel" onSubmit={mutate}>
        <h3>{deleting ? `Hapus ${deleting.name || deleting.id}?` : `${editing.id != null ? 'Ubah' : 'Tambah'} ${names[kind]}`}</h3>
        {!deleting && <><label className="field"><span>Nama</span><input required maxLength={255} value={name} disabled={busy} onChange={event => setName(event.target.value)}/></label>{kind === 'users' && <label className="field"><span>Email</span><input required type="email" value={email} disabled={busy} onChange={event => setEmail(event.target.value)}/></label>}{kind !== 'projects' && <label><input type="checkbox" checked={active} disabled={busy} onChange={event => setActive(event.target.checked)}/> Aktif</label>}</>}
        <div className="detail-actions"><button disabled={busy}>{busy ? 'Memproses...' : deleting ? 'Konfirmasi Hapus' : 'Simpan'}</button><button type="button" disabled={busy} onClick={() => { setEditing(null); setDeleting(null); }}>Batal</button></div>
      </form>}
      {loading ? <Loading/> : <section className="panel">{rows.map(row => <article className="data-item" key={row.id}><DataView data={row}/><div className="detail-actions"><button disabled={busy} onClick={() => { setDeleting(null); edit(row); }}>Ubah</button><button disabled={busy} onClick={() => { setEditing(null); setDeleting(row); }}>Hapus</button></div></article>)}{!rows.length && <p>Belum ada data.</p>}</section>}
      <div className="action-strip"><button disabled={page <= 1 || loading || busy} onClick={() => setPage(value => value - 1)}>Sebelumnya</button><span>Halaman {page} / {lastPage}</span><button disabled={page >= lastPage || loading || busy} onClick={() => setPage(value => value + 1)}>Berikutnya</button></div>
    </>}
  </>;
}
