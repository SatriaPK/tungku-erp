import type { ApiError } from './auth-api'

const apiOrigin = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000'

export interface DashboardTrend {
  current: number
  previous: number
  delta: number
  percentage: number | null
  percentage_reason: string | null
  direction: string
}

export interface ManagerDashboardData {
  as_of: string
  period: { year: number; month: number; timezone: string; today: string; month_start: string; next_month_start: string }
  revenue: DashboardTrend
  expense: DashboardTrend
  monthly: Array<{ month: number; revenue: number; expense: number }>
  annual_totals: { revenue: number; expense: number }
  awaiting_final_payment: { amount: number; project_count: number; positive_balance_project_count: number }
  in_progress_projects: { count: number; track_count: number }
  tracks_by_stage: Array<{ stage: string; stage_label: string; track_count: number }>
  unclassified_track_count: number
  quotations: { open_count: number; awaiting_studio_count: number }
  package_sales: { total_count: number; items: Array<{ key: string; package_id: number | null; quotation_id: number | null; name: string; project_count: number; percentage: number }> }
  expense_categories: { total_amount: number; transaction_count: number; items: Array<{ category_id: number | null; category_name: string; transaction_count: number; amount: number; percentage: number }> }
  recording_schedule: Array<{ id: number; project_id: number | null; project_name: string | null; client_id: number | null; client_name: string | null; start_at: string; end_at: string; status: string; status_label: string }>
  pending_project_assignments: number
  project_progress: Array<{ id: number; name: string; client_id: number | null; client_name: string | null; status: string; status_label: string; current_stage: string | null; current_stage_label: string | null; stage_status: string | null; stage_status_label: string | null }>
  quotation_progress: Array<{ id: number; name: string | null; package_display_name: string; client_id: number | null; client_name: string | null; status: string; status_label: string }>
  operator_activities: Array<{ id: number; type: string; actor: { id: number | null; name: string | null; is_system: boolean }; operator: { id: number | null; name: string | null }; project: { id: number | null; name: string | null }; stage: { id: number | null; type: string; label: string }; track: { id: number | null; title: string | null }; occurred_at: string; recorded_at: string; deadline_at: string | null; age_seconds: number; description: string }>
}

export interface ManagerUser {
  id: number
  name: string
  email: string
  role: string
  role_label: string
}

export interface ManagerProject {
  id: number
  code: string
  name: string
  client: { id: number; name: string; avatar_url: string | null } | null
  status: string
  status_label: string
  started_at: string | null
  created_at: string
  current_stage: {
    stage: string
    stage_label: string
    status: string
    status_label: string
    operator: { id: number; name: string; avatar_url: string | null } | null
  } | null
  progress_percent: number
  tracks_count: number
  tracks_approved_count: number
}

export interface ManagerProjectFilters {
  search?: string
  status?: string
  stage?: string
  sort?: 'newest' | 'oldest' | 'name_asc' | 'name_desc'
  page?: number
  per_page?: number
}

export interface ManagerProjectPage {
  data: ManagerProject[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: { current_page: number; from: number | null; last_page: number; per_page: number; to: number | null; total: number }
}

async function getData<T>(path: string): Promise<T> {
  let response: Response
  try {
    response = await fetch(`${apiOrigin}${path}`, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
  } catch {
    throw new Error('Tidak dapat terhubung ke layanan. Pastikan server Tungku sedang aktif.')
  }

  const data = await response.json().catch(() => null)
  if (!response.ok) {
    const error: ApiError = new Error(data?.message ?? 'Terjadi kendala. Silakan coba lagi.')
    error.status = response.status
    throw error
  }

  return data.data as T
}

export function getManagerDashboard(year?: number) {
  const query = year === undefined ? '' : `?year=${encodeURIComponent(year)}`
  return getData<ManagerDashboardData>(`/api/v1/manager/dashboard${query}`)
}

export function getCurrentUser() {
  return getData<ManagerUser>('/api/v1/auth/me')
}

export async function getManagerProjects(filters: ManagerProjectFilters = {}): Promise<ManagerProjectPage> {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== '') params.set(key, String(value))
  })
  const query = params.size ? `?${params.toString()}` : ''
  const result: unknown = await getData<unknown>(`/api/v1/manager/projects${query}`)

  // Laravel keeps paginator metadata beside `data`; the resource collection
  // endpoint returns pagination inside `data` instead.
  if (Array.isArray(result)) {
    const projects = result as ManagerProject[]

    return {
      data: projects,
      links: { first: null, last: null, prev: null, next: null },
      meta: {
        current_page: 1,
        from: projects.length ? 1 : null,
        last_page: 1,
        per_page: projects.length,
        to: projects.length || null,
        total: projects.length,
      },
    }
  }

  if (typeof result !== 'object' || result === null) {
    throw new Error('Format respons daftar project tidak valid.')
  }

  const response = result as Record<string, unknown>
  const nestedPage = response.data
  const page = typeof nestedPage === 'object' && nestedPage !== null
    ? nestedPage as Record<string, unknown>
    : response
  const projects = page.data

  if (!Array.isArray(projects)) {
    throw new Error('Data project tidak ditemukan pada respons server.')
  }

  const fallbackMeta = {
    current_page: 1,
    from: projects.length ? 1 : null,
    last_page: 1,
    per_page: projects.length,
    to: projects.length || null,
    total: projects.length,
  }

  return {
    data: projects as ManagerProject[],
    links: (page.links ?? response.links ?? { first: null, last: null, prev: null, next: null }) as ManagerProjectPage['links'],
    meta: (page.meta ?? response.meta ?? fallbackMeta) as ManagerProjectPage['meta'],
  }
}