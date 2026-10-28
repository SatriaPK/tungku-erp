const apiOrigin = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000'

export interface ApiError extends Error {
  errors?: Record<string, string[]>
  status?: number
}

export type UserRole = 'manager' | 'operator' | 'client'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  role_label: string
}

function xsrfToken() {
  const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='))
  return cookie ? decodeURIComponent(cookie.substring('XSRF-TOKEN='.length)) : ''
}

async function apiRequest(path: string, body: Record<string, unknown>) {
  const response = await fetch(`${apiOrigin}${path}`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-XSRF-TOKEN': xsrfToken(),
    },
    credentials: 'include',
    body: JSON.stringify(body),
  })

  const data = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    const error: ApiError = new Error(data?.message ?? 'Terjadi kendala. Silakan coba lagi.')
    error.status = response.status
    error.errors = data?.errors
    throw error
  }

  return data
}

async function prepareSession() {
  try {
    const response = await fetch(`${apiOrigin}/sanctum/csrf-cookie`, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) throw new Error('Tidak dapat terhubung ke layanan. Silakan coba lagi.')
  } catch {
    throw new Error('Tidak dapat terhubung ke layanan. Pastikan server Tungku sedang aktif.')
  }
}

export async function login(email: string, password: string) {
  await prepareSession()
  return apiRequest('/api/v1/auth/login', { email, password, remember: false })
}

export async function getCurrentUser(): Promise<AuthUser> {
  let response: Response
  try {
    response = await fetch(`${apiOrigin}/api/v1/auth/me`, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
  } catch {
    throw new Error('Tidak dapat terhubung ke layanan. Pastikan server Tungku sedang aktif.')
  }

  const data = await response.json().catch(() => null)

  if (!response.ok || !data?.data) {
    const error: ApiError = new Error(data?.message ?? 'Tidak dapat memuat profil akun.')
    error.status = response.status
    throw error
  }

  const user = data.data as Partial<AuthUser>
  if (user.role !== 'manager' && user.role !== 'operator' && user.role !== 'client') {
    throw new Error('Peran akun tidak dikenali. Hubungi tim Tungku untuk bantuan.')
  }

  return user as AuthUser
}

export async function register(details: {
  name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
  crm_consent: boolean
}) {
  await prepareSession()
  return apiRequest('/api/v1/auth/register', details)
}

export function getApiErrorMessage(error: unknown) {
  if (error instanceof Error) return error.message
  return 'Terjadi kendala. Silakan coba lagi.'
}