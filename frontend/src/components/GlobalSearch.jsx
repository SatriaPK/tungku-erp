import { useEffect, useId, useRef, useState } from 'react';
import DesignIcon from './DesignIcon';
import { request } from '../lib/api';
import navigate from '../lib/navigation';

function searchLocation(role, query, selection = {}) {
  return `/${role}/search?${new URLSearchParams({ q: query, ...selection })}`;
}

export default function GlobalSearch({ role, className = '' }) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [response, setResponse] = useState({ key: '', data: null, error: null });
  const form = useRef(null);
  const resultsId = useId();
  const term = query.trim();
  const key = `${role}:${term}`;
  const result = response.key === key ? response.data : null;
  const error = response.key === key ? response.error : null;
  const loading = term.length >= 2 && response.key !== key;
  const label = role === 'client' ? 'Cari nama proyek' : 'Cari nama proyek atau klien';

  useEffect(() => {
    if (term.length < 2) return;
    const controller = new AbortController();
    const timer = setTimeout(() => {
      request('/search', { params: { q: term, per_page: 5 }, signal: controller.signal })
        .then(value => { if (!controller.signal.aborted) setResponse({ key, data: value, error: null }); })
        .catch(reason => { if (!controller.signal.aborted) setResponse({ key, data: null, error: reason }); });
    }, 300);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [term, key]);

  function go(selection = {}) {
    if (term.length < 2) return;
    setOpen(false);
    navigate(searchLocation(role, term, selection));
  }
  return (
    <form ref={form} className={`global-search search ${className}`}
      role="search" onSubmit={event => { event.preventDefault(); go(); }}
      onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false); }}
      onKeyDown={event => {
        if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
        if (event.key === 'ArrowDown' && event.target.tagName === 'INPUT') {
          event.preventDefault(); form.current.querySelector('.global-search-result')?.focus();
        }
      }}>
      <DesignIcon name="search" />
      <input type="search" aria-label={label} placeholder={role === 'client' ? 'Cari nama proyek...' : 'Cari proyek atau klien...'}
        value={query} autoComplete="off" onChange={event => { setQuery(event.target.value); setOpen(true); }}
        onFocus={() => setOpen(true)} aria-expanded={open && term.length >= 2} aria-controls={resultsId} />
      {open && term.length >= 2 && (
        <section className="global-search-popover" id={resultsId} aria-label="Hasil pencarian cepat">
          {loading && <p role="status">Mencari proyek dan klien...</p>}
          {error && <p role="alert">{error.message}</p>}
          {!loading && !error && result && <>
            {(result.data?.projects || []).map(project => <button type="button" className="global-search-result" key={`project-${project.id}`}
              onClick={() => go({ project: project.id })}>
              <span><strong>{project.name}</strong><small>{project.code} · {project.client?.name}</small></span><small>Proyek</small>
            </button>)}
            {(result.data?.clients || []).map(client => <button type="button" className="global-search-result" key={`client-${client.id}`}
              onClick={() => go({ client: client.id })}>
              <span><strong>{client.name}</strong><small>Lihat proyek milik klien</small></span><small>Klien</small>
            </button>)}
            {!result.data?.projects?.length && !result.data?.clients?.length && <p>Tidak ada proyek atau klien yang cocok.</p>}
            <button type="submit" className="global-search-all">Lihat semua hasil</button>
          </>}
        </section>
      )}
    </form>
  );
}
