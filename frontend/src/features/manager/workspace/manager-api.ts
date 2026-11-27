export type Row = Record<string, unknown>
export type Collection = { data: Row[]; meta?: { current_page: number; last_page: number; total: number } }
const origin = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000'
export async function managerRequest<T>(path: string, method = 'GET', body?: unknown): Promise<T> {
  if (method !== 'GET') {
    const csrf = await fetch(`${origin}/sanctum/csrf-cookie`, { credentials: 'include', headers: { Accept: 'application/json' } })
    if (!csrf.ok) throw new Error('Sesi berakhir. Silakan masuk kembali.')
  }
  const token = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))?.slice(11)
  const response = await fetch(`${origin}/api/v1/manager/${path}`, {
    method, credentials: 'include', headers: { Accept: 'application/json', ...(method === 'GET' ? {} : { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '' }) },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  })
  const data = await response.json().catch(() => null)
  if (!response.ok) throw new Error(data?.message ?? 'Permintaan tidak berhasil. Silakan coba lagi.')
  return data as T
}
export const money = (amount: unknown) => `Rp ${new Intl.NumberFormat('id-ID').format(Number(amount ?? 0))}`
export const value = (row: Row, field: string): unknown => field.split('.').reduce<unknown>((current, key) => current && typeof current === 'object' ? (current as Row)[key] : undefined, row)
export function display(entry: unknown): string {
  if (entry === null || entry === undefined || entry === '') return '—'
  if (typeof entry === 'boolean') return entry ? 'Aktif' : 'Nonaktif'
  if (Array.isArray(entry)) return entry.map(display).join(', ')
  if (typeof entry === 'object') return display((entry as Row).name ?? (entry as Row).label)
  if (typeof entry === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(entry)) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(entry))
  }
  if (typeof entry === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(entry)) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${entry}T00:00:00Z`))
  }
  return String(entry)
}
