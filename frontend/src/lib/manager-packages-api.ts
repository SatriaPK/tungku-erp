import type { ApiError } from './auth-api'

const apiOrigin = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000'

export interface ManagerPackage {
  id: number
  name: string
  description: string | null
  track_count: number
  recording_hours: number
  stages: string[]
  include_recording: boolean
  include_editing: boolean
  include_mixing: boolean
  include_mastering: boolean
  auto_price: number
  manual_price: number | null
  is_price_manual: boolean
  price: number
  is_active: boolean
  created_at: string | null
}

export interface ManagerPackagePage {
  data: ManagerPackage[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface ManagerPackageSummary {
  total: number
  by_stage: Record<'recording' | 'editing' | 'mixing' | 'mastering', number>
}

export interface ManagerBasePrice {
  stage: 'recording' | 'editing' | 'mixing' | 'mastering'
  stage_label: string
  unit: 'hour' | 'track'
  unit_label: string
  price: number
  updated_at: string | null
}

export interface CreateManagerPackageInput {
  name: string
  description: string | null
  track_count: number
  recording_hours: number | null
  include_recording: boolean
  include_editing: boolean
  include_mixing: boolean
  include_mastering: boolean
  manual_price: number | null
}

export async function getManagerBasePrices(): Promise<ManagerBasePrice[]> {
  return getManagerPackageData<ManagerBasePrice[]>('/api/v1/manager/base-prices')
}

export async function calculateManagerPackagePrice(input: Omit<CreateManagerPackageInput, 'name' | 'description' | 'manual_price'>): Promise<number> {
  const payload = await managerPackageRequest<{ data: { auto_price: number } }>('/api/v1/manager/packages/calculate-price', {
    method: 'POST',
    body: input,
  })
  return payload.data.auto_price
}

export async function createManagerPackage(input: CreateManagerPackageInput): Promise<ManagerPackage> {
  const payload = await managerPackageRequest<{ data: ManagerPackage }>('/api/v1/manager/packages', {
    method: 'POST',
    body: {
      ...input,
      recording_hours: input.include_recording ? input.recording_hours : null,
    },
  })
  return payload.data
}

export async function getManagerPackages(page = 1, active?: boolean): Promise<ManagerPackagePage> {
  const params = new URLSearchParams({ page: String(page) })
  if (active !== undefined) params.set('active', active ? '1' : '0')
  let response: Response
  try {
    response = await fetch(`${apiOrigin}/api/v1/manager/packages?${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
  } catch {
    throw new Error('Tidak dapat terhubung ke layanan. Pastikan server Tungku sedang aktif.')
  }

  const payload = await response.json().catch(() => null)
  if (!response.ok) {
    const error: ApiError = new Error(payload?.message ?? 'Paket tidak dapat dimuat.')
    error.status = response.status
    error.errors = payload?.errors
    throw error
  }

  return payload as ManagerPackagePage
}

export async function getManagerPackageSummary(): Promise<ManagerPackageSummary> {
  let response: Response
  try {
    response = await fetch(`${apiOrigin}/api/v1/manager/packages/summary`, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
  } catch {
    throw new Error('Tidak dapat terhubung ke layanan. Pastikan server Tungku sedang aktif.')
  }

  const payload = await response.json().catch(() => null)
  if (!response.ok) {
    const error: ApiError = new Error(payload?.message ?? 'Ringkasan paket tidak dapat dimuat.')
    error.status = response.status
    error.errors = payload?.errors
    throw error
  }

  return payload.data as ManagerPackageSummary
}

async function getManagerPackageData<T>(path: string): Promise<T> {
  const payload = await managerPackageRequest<{ data: T }>(path)
  return payload.data
}

async function managerPackageRequest<T>(path: string, options: { method?: string; body?: unknown } = {}): Promise<T> {
  const method = options.method ?? 'GET'
  let response: Response
  try {
    response = await fetch(`${apiOrigin}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(options.body === undefined ? {} : { 'Content-Type': 'application/json' }),
        ...(method === 'GET' ? {} : { 'X-XSRF-TOKEN': getXsrfToken() }),
      },
      credentials: 'include',
      ...(options.body === undefined ? {} : { body: JSON.stringify(options.body) }),
    })
  } catch {
    throw new Error('Tidak dapat terhubung ke layanan. Pastikan server Tungku sedang aktif.')
  }

  const payload = await response.json().catch(() => null)
  if (!response.ok) {
    const error: ApiError = new Error(payload?.message ?? 'Paket tidak dapat disimpan.')
    error.status = response.status
    error.errors = payload?.errors
    throw error
  }
  return payload as T
}

export async function deleteManagerPackage(id: number): Promise<void> {
  let response: Response
  try {
    response = await fetch(`${apiOrigin}/api/v1/manager/packages/${encodeURIComponent(id)}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getXsrfToken() },
      credentials: 'include',
    })
  } catch {
    throw new Error('Tidak dapat terhubung ke layanan. Pastikan server Tungku sedang aktif.')
  }

  if (!response.ok) {
    const payload = await response.json().catch(() => null)
    const error: ApiError = new Error(payload?.message ?? 'Paket tidak dapat dihapus.')
    error.status = response.status
    error.errors = payload?.errors
    throw error
  }
}

export async function updateManagerPackageActive(item: ManagerPackage, isActive: boolean): Promise<ManagerPackage> {
  let response: Response
  try {
    response = await fetch(`${apiOrigin}/api/v1/manager/packages/${encodeURIComponent(item.id)}`, {
      method: 'PUT',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getXsrfToken() },
      credentials: 'include',
      body: JSON.stringify({
        name: item.name,
        description: item.description,
        track_count: item.track_count,
        recording_hours: item.include_recording ? Math.max(1, item.recording_hours) : null,
        include_recording: item.include_recording,
        include_editing: item.include_editing,
        include_mixing: item.include_mixing,
        include_mastering: item.include_mastering,
        manual_price: item.manual_price,
        is_active: isActive,
      }),
    })
  } catch {
    throw new Error('Tidak dapat terhubung ke layanan. Pastikan server Tungku sedang aktif.')
  }

  const payload = await response.json().catch(() => null)
  if (!response.ok) {
    const error: ApiError = new Error(payload?.message ?? 'Status paket tidak dapat diubah.')
    error.status = response.status
    error.errors = payload?.errors
    throw error
  }

  return payload.data as ManagerPackage
}

function getXsrfToken() {
  const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='))
  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : ''
}