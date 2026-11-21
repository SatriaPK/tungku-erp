import { expect, test } from '@playwright/test'

const item = { id: 1, name: 'Paket Single', description: 'Termasuk ruang rekaman dan dua revisi mixing.', price: 900000, track_count: 1, recording_hours: 3, include_recording: true, include_editing: true, include_mixing: true, include_mastering: false }

test.beforeEach(async ({ page }) => {
  await page.route('**/api/v1/**', async (route) => {
    const url = new URL(route.request().url())
    let data: unknown = []
    if (url.pathname.endsWith('/auth/me')) data = { id: 4, name: 'Klien Uji', email: 'client@example.test', role: 'client' }
    if (url.pathname.endsWith('/packages')) data = [item]
    if (url.pathname.endsWith('/base-prices')) data = ['recording', 'editing', 'mixing', 'mastering'].map((stage) => ({ stage, price: 100000, unit: 'track' }))
    if (url.pathname.endsWith('/package-recommendations')) {
      expect(url.searchParams.get('budget')).toBe('1000000')
      expect(url.searchParams.get('track_count')).toBe('1')
      expect(url.searchParams.getAll('stages[]')).toContain('mixing')
      return route.fulfill({ json: { data: [{ package: item, score: 95, within_budget: true, reasons: ['Harga sesuai anggaran.'] }], meta: { message: 'Paket sesuai kebutuhan.' } } })
    }
    await route.fulfill({ json: { data } })
  })
})

test('package detail exposes price, duration, facilities and excluded services', async ({ page }) => {
  await page.goto('/client/packages')
  await page.getByRole('button', { name: 'Aksi untuk Paket Single' }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByText(item.description)).toBeVisible()
  await expect(dialog.getByText('3 jam total untuk paket ini')).toBeVisible()
  await expect(dialog.getByText('Mastering', { exact: true })).toBeVisible()
  await expect(dialog.getByText('Rp 900.000', { exact: true })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Booking Paket' })).toBeVisible()
})

test('recommendations send needs and budget, explain matches and invalidate changed inputs', async ({ page }) => {
  await page.goto('/client/packages')
  await page.getByLabel('Anggaran Maksimal (Rp, opsional)').fill('1000000')
  await page.getByRole('button', { name: 'Cari Rekomendasi' }).click()
  const section = page.getByRole('region', { name: 'Rekomendasi Paket' })
  await expect(section.getByText('Harga sesuai anggaran.')).toBeVisible()
  await section.getByRole('button', { name: 'Detail Paket Single' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.getByRole('button', { name: 'Tutup', exact: true }).click()
  await page.getByLabel('Anggaran Maksimal (Rp, opsional)').fill('800000')
  await expect(section.getByText('Kebutuhan berubah. Cari ulang untuk memperbarui rekomendasi.')).toBeVisible()
  await expect(section.getByRole('button', { name: 'Detail Paket Single' })).toHaveCount(0)
})

test('empty recommendations and API errors allow retry', async ({ page }) => {
  await page.route('**/package-recommendations?**', (route) => route.fulfill({ json: { data: [], meta: { message: 'Belum ada paket yang memenuhi kebutuhan. Buat penawaran kustom.' } } }))
  await page.goto('/client/packages')
  await page.getByRole('button', { name: 'Cari Rekomendasi' }).click()
  await expect(page.getByText('Belum ada paket yang memenuhi kebutuhan. Buat penawaran kustom.')).toBeVisible()
  await page.route('**/package-recommendations?**', (route) => route.fulfill({ status: 500, json: { message: 'Rekomendasi belum tersedia.' } }))
  await page.getByRole('button', { name: 'Cari Rekomendasi' }).click()
  await expect(page.getByText('Rekomendasi belum tersedia.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Cari Rekomendasi' })).toBeEnabled()
})
