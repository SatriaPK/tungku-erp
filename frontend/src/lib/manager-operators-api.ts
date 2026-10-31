import type { ApiError } from './auth-api'

interface RequestOptions {
  method?: string
  body?: string
  headers?: Record<string, string>
}

const apiOrigin = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000'

function getXsrfToken() {
  const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='))
  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : ''
}

async function refreshCsrfCookie() {
  const response = await fetch(`${apiOrigin}/sanctum/csrf-cookie`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) throw new Error('Sesi keamanan tidak dapat diperbarui. Muat ulang halaman lalu coba lagi.')
}

export interface ManagerOperator {
  id: number
  name: string
  email: string
  phone: string | null
  role: string
  role_label: string
  is_active: boolean
  created_at: string | null
  assignments_count: number
  assigned_projects?: Array<{
    id: number
    name: string
    status: string
    status_label: string
    client: { id: number; name: string; avatar_url: string | null } | null
    current_stage: { stage: string; stage_label: string; operator: { id: number; name: string; avatar_url: string | null } | null } | null
    progress_percent: number
    started_at: string | null
    created_at: string | null
    stages: string[]
  }>
}

export interface ManagerOperatorPage {
  data: ManagerOperator[]
  active_count: number
  current_page: number
  last_page: number
  per_page: number
  total: number
}

async function request<T>(path: string, options: RequestOptions = {}, unwrap = true): Promise<T> {
  let response: Response
  try {
    const method = options.method ?? 'GET'
    const isMutation = !['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())
    if (isMutation && !getXsrfToken()) await refreshCsrfCookie()

    response = await fetch(`${apiOrigin}${path}`, {
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(isMutation ? { 'X-XSRF-TOKEN': getXsrfToken() } : {}),
        ...options.headers,
      },
      credentials: 'include',
    })
  } catch {
    throw new Error('Tidak dapat terhubung ke layanan. Pastikan server Tungku sedang aktif.')
  }

  const payload = await response.json().catch(() => null)
  if (response.status === 419 && options.method && options.method.toUpperCase() !== 'GET') {
    const error: ApiError = new Error('Sesi keamanan telah kedaluwarsa. Muat ulang halaman lalu coba lagi.')
    error.status = response.status
    throw error
  }
  if (!response.ok) {
    const error: ApiError = new Error(payload?.message ?? 'Terjadi kendala. Silakan coba lagi.')
    error.status = response.status
    error.errors = payload?.errors
    throw error
  }

  return (unwrap ? payload?.data : payload) as T
}

export async function getManagerOperators(search = '', page = 1) {
  const params = new URLSearchParams({ page: String(page), per_page: '10' })
  if (search.trim()) params.set('search', search.trim())
  const payload = await request<unknown>(`/api/v1/manager/operators?${params.toString()}`, {}, false)
  if (!payload || typeof payload !== 'object') {
    throw new Error('Format respons daftar operator tidak valid.')
  }

  const root = payload as Record<string, unknown>
  // Keep the complete Laravel collection response: data is the array and
  // meta is its sibling, including pagination and active_count.
  const wrappedPaginator = root.data
  const paginator = typeof wrappedPaginator === 'object' && wrappedPaginator !== null && !Array.isArray(wrappedPaginator)
    ? wrappedPaginator as Record<string, unknown>
    : root
  const pageData = Array.isArray(paginator.data)
    ? paginator.data
    : Array.isArray(wrappedPaginator)
      ? wrappedPaginator
      : null
  const paginatorMeta = paginator.meta
  const meta = typeof paginatorMeta === 'object' && paginatorMeta !== null
    ? paginatorMeta as Record<string, unknown>
    : {}
  const extraMeta = typeof root.meta === 'object' && root.meta !== null
    ? root.meta as Record<string, unknown>
    : typeof paginatorMeta === 'object' && paginatorMeta !== null
      ? paginatorMeta as Record<string, unknown>
      : {}

  if (!pageData) {
    throw new Error('Format respons daftar operator tidak valid.')
  }

  return {
    data: pageData as ManagerOperator[],
    active_count: typeof extraMeta.active_count === 'number' ? extraMeta.active_count : 0,
    current_page: typeof meta.current_page === 'number' ? meta.current_page : page,
    last_page: typeof meta.last_page === 'number' ? meta.last_page : 1,
    per_page: typeof meta.per_page === 'number' ? meta.per_page : 10,
    total: typeof meta.total === 'number' ? meta.total : pageData.length,
  }
}

export function createManagerOperator(details: { name: string; email: string; phone: string; password: string; password_confirmation: string }) {
  return request<ManagerOperator>('/api/v1/manager/operators', { method: 'POST', body: JSON.stringify(details) })
}

export function updateManagerOperatorStatus(id: number, isActive: boolean) {
  return request<ManagerOperator>(`/api/v1/manager/operators/${id}/status`, { method: 'PATCH', body: JSON.stringify({ is_active: isActive }) })
}

export function updateManagerOperator(id: number, details: { name: string; email: string; phone: string }) {
  return request<ManagerOperator>(`/api/v1/manager/operators/${id}`, { method: 'PUT', body: JSON.stringify(details) })
}

export async function deleteManagerOperator(id: number) {
  await request<unknown>(`/api/v1/manager/operators/${id}`, { method: 'DELETE' })
}