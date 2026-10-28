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

export function getManagerDashboard() {
  return getData<ManagerDashboardData>('/api/v1/manager/dashboard')
}

export function getCurrentUser() {
  return getData<ManagerUser>('/api/v1/auth/me')
}