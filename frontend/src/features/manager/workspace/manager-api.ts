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
export const display = (entry: unknown): string => entry === null || entry === undefined || entry === '' ? '?' : typeof entry === 'boolean' ? entry ? 'Aktif' : 'Nonaktif' : typeof entry === 'object' ? Array.isArray(entry) ? entry.map(display).join(', ') : display((entry as Row).name ?? (entry as Row).label) : String(entry)
