import { expect, test } from '@playwright/test'

test('client applies coupon before booking and sends code to server', async ({ page }) => {
  let heldCode = ''
  await page.route('**/api/v1/auth/me', route => route.fulfill({ json: { data: { id: 1, name: 'Client', role: 'client' } } }))
  await page.route('**/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/client/**', route => {
    const url = route.request().url()
    if (url.endsWith('/coupons/preview')) return route.fulfill({ json: { data: { code: 'STUDIO10', subtotal: 1000000, discount: 50000, total: 950000 } } })
    if (url.endsWith('/check-availability')) return route.fulfill({ json: { data: { available: true } } })
    if (url.endsWith('/hold')) { heldCode = route.request().postDataJSON().coupon_code; return route.fulfill({ status: 201, json: { data: { id: 1 } } }) }
    if (url.endsWith('/packages')) return route.fulfill({ json: { data: [{ id: 1, name: 'Paket Studio', price: 1000000 }] } })
    if (url.endsWith('/rooms')) return route.fulfill({ json: { data: [{ id: 1, name: 'Studio A' }] } })
    return route.fulfill({ json: { data: [] } })
  })
  await page.goto('/client/booking')
  await page.getByLabel('Paket atau penawaran').selectOption('package:1')
  await page.getByLabel('Ruang rekaman').selectOption('1')
  await page.getByLabel('Mulai', { exact: true }).fill('2099-12-01T10:00')
  await page.getByLabel('Selesai', { exact: true }).fill('2099-12-01T12:00')
  await page.getByLabel('Kode kupon (opsional)').fill('STUDIO10')
  await page.getByRole('button', { name: 'Terapkan Kupon' }).click()
  await expect(page.getByText('Total setelah diskon')).toBeVisible()
  await expect(page.getByText('Rp 950.000', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Periksa Ketersediaan' }).click()
  await expect(page.getByRole('button', { name: 'Tahan Jadwal' })).toBeEnabled()
  await page.setViewportSize({ width: 390, height: 844 })
  await page.screenshot({ path: 'test-results/client-coupon-mobile.png', fullPage: true })
  await page.getByRole('button', { name: 'Tahan Jadwal' }).click()
  await expect.poll(() => heldCode).toBe('STUDIO10')
})
