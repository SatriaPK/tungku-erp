import type { ApiError } from '../../lib/auth-api'

export type Stage = 'recording' | 'editing' | 'mixing' | 'mastering'
export interface Specifications {
  track_count: number
  recording_hours: number
  include_recording: boolean
  include_editing: boolean
  include_mixing: boolean
  include_mastering: boolean
}
export interface ClientPackage extends Specifications {
  id: number
  name: string
  description: string | null
  price: number
}
export interface BasePrice { stage: Stage; price: number; unit: string }
export interface Offer { id: number; side: 'client' | 'studio'; price: number; note: string | null; created_at: string }
export interface Quotation {
  id: number
  name: string
  specifications: Specifications
  status: 'awaiting_studio' | 'awaiting_client' | 'accepted' | 'declined'
  status_label: string
  auto_price: number
  agreed_price: number | null
  created_at: string
  latest_offer?: Offer
  offers?: Offer[]
}
export interface Collection<T> { data: T[]; meta?: { current_page: number; last_page: number; total: number } }
export interface QuoteDraft extends Specifications { name: string; offer_price: number; notes?: string }
export interface ClientRoom { id: number; name: string; is_active: boolean }
export interface ClientBooking { id: number; room_id: number; room_name: string; start_at: string; end_at: string; status: string; status_label: string }
export interface ClientProject { id: number; code: string; name: string; status: string; status_label: string; price: number; package_name: string | null; stages: { stage: Stage; status: string }[]; completed_at: string | null; created_at: string }
export interface ClientInvoice { subtotal?: number; discount_amount?: number; coupon?: { code: string; discount: number } | null; id: number; invoice_number: string; status: string; status_label: string; total: number; issue_date: string | null; due_date: string | null; is_overdue: boolean; project: { id: number; name: string } | null }
export interface ClientPayment { id: number; status: string; status_label: string; amount: number; payment_url: string | null; provider_label: string; type_label: string }
export interface Delivery { track_id: number; track_title: string; stage: Stage; version: number; result_url: string | null; submitted_at: string | null }
export interface BookingAvailability { available: boolean; reason: string | null; requested_start: string; requested_end: string; room_id: number }
export interface BookingHold { id: number; held_until: string | null; status: string; room: ClientRoom; start_at: string; end_at: string }

const origin = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000'

export async function request<T>(path: string, body?: unknown): Promise<T> {
  if (body !== undefined) {
    const session = await fetch(`${origin}/sanctum/csrf-cookie`, { credentials: 'include', headers: { Accept: 'application/json' } })
    if (!session.ok) throw new Error('Sesi tidak dapat diperbarui. Silakan masuk kembali.')
  }
  const token = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length)
  const response = await fetch(`${origin}/api/v1/client/${path}`, {
    method: body === undefined ? 'GET' : 'POST',
    credentials: 'include',
    headers: { Accept: 'application/json', ...(body === undefined ? {} : { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '' }) },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  })
  const data = await response.json().catch(() => null)
  if (!response.ok) {
    const error: ApiError = new Error(data?.message ?? 'Data belum dapat dimuat. Silakan coba lagi.')
    error.status = response.status
    error.errors = data?.errors
    throw error
  }
  return data as T
}

export const getPackages = () => request<Collection<ClientPackage>>('packages')
export const getPrices = () => request<Collection<BasePrice>>('base-prices')
export const getQuotations = (page = 1) => request<Collection<Quotation>>(`quotations?page=${page}`)
export const getRooms = () => request<Collection<ClientRoom>>('rooms')
export const getBookings = (page = 1) => request<Collection<ClientBooking>>(`bookings?page=${page}`)
export const getProjects = (page = 1) => request<Collection<ClientProject>>(`projects?page=${page}`)
export const getInvoices = (page = 1) => request<Collection<ClientInvoice>>(`invoices?page=${page}`)
export const getInvoice = (id: number) => request<{ data: ClientInvoice & { payment?: ClientPayment; items?: { id: number; description: string; total: number }[] } }>(`invoices/${id}`)
export const initiateInvoicePayment = (id: number, gateway: 'midtrans' | 'xendit') => request<{ data: ClientPayment }>(`invoices/${id}/initiate-payment`, { gateway })
export const getDelivery = (projectId: number) => request<Collection<Delivery>>(`projects/${projectId}/final-delivery`)
export const checkAvailability = (room_id: number, start_at: string, end_at: string) => request<{ data: BookingAvailability }>('bookings/check-availability', { room_id, start_at, end_at })
export const holdBooking = (room_id: number, start_at: string, end_at: string, target: ({ package_id: number } | { quotation_id: number }) & { coupon_code?: string }) => request<{ data: BookingHold }>('bookings/hold', { room_id, start_at, end_at, ...target })
export const getQuotation = (id: number) => request<{ data: Quotation }>(`quotations/${id}`)
export const submitQuotation = (draft: QuoteDraft) => request<{ data: Quotation }>('quotations', draft)
export const respondToQuotation = (id: number, response_type: 'accept' | 'decline' | 'counter', latest_offer_id: number, price?: number) =>
  request<{ data: Quotation }>(`quotations/${id}/respond`, { response_type, latest_offer_id, ...(price === undefined ? {} : { price }) })

export function estimatePrice(spec: Specifications, prices: BasePrice[]): number {
  return prices.reduce((sum, rate) => sum + (spec[`include_${rate.stage}`] ? Number(rate.price) * (rate.stage === 'recording' ? spec.recording_hours : spec.track_count) : 0), 0)
}

export const rupiah = (value: number) => `Rp ${new Intl.NumberFormat('id-ID').format(value)}`
export const offerDate = (value: string) => new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Jakarta' }).format(new Date(value))

export interface CouponPreview { code: string; subtotal: number; discount: number; total: number }
export const previewCoupon = (coupon_code: string, target: { package_id: number } | { quotation_id: number }) => request<{ data: CouponPreview }>('coupons/preview', { coupon_code, ...target })
