import { expect, test } from '@playwright/test'

test('every Manager menu opens a working page', async ({ page }) => {
  const requested: string[] = []
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1, name: 'Manager Studio', email: 'manager@studio.test', phone: '6281234567890', role: 'manager' } }) }))
  await page.route('**/api/v1/manager/**', (route) => {
    requested.push(new URL(route.request().url()).pathname)
    const data = route.request().url().includes('monthly-summary')
      ? { period: {}, totals: { revenue: 100000 }, expenses_by_category: [], status: 'open' }
      : route.request().url().includes('/bookings')
        ? [{ id: 1, room_name: 'Studio A', client_name: 'Nadine', start_at: '2026-11-03T10:00:00+07:00', status_label: 'Terjadwal' }]
        : []
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data }) })
  })
  const menus = [
    ['/booking', 'Booking', '/bookings'],
    ['/quotation', 'Quotation', '/quotations'],
    ['/inventaris', 'Inventaris', '/inventories'],
    ['/crm', 'CRM', '/crm/campaigns'],
    ['/invoice', 'Invoice', '/invoices'],
    ['/laporan', 'Laporan Keuangan', '/finance/monthly-summary'],
    ['/pengeluaran', 'Pengeluaran', '/expenses'],
    ['/pengaturan', 'Pengaturan', '/settings'],
  ] as const
  for (const [path, title, api] of menus) {
    await page.goto(path)
    await expect(page.getByRole('heading', { name: title, exact: true }).last()).toBeVisible()
    await expect.poll(() => requested.some((entry) => entry.endsWith(api))).toBe(true)
  }
  await page.goto('/booking')
  await expect(page.getByRole('table')).toContainText('Studio A')
  await page.screenshot({ path: 'test-results/manager-booking.png', fullPage: true })
  await page.setViewportSize({ width: 390, height: 844 })
  await expect(page.getByRole('table')).toContainText('Studio A')
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390)
})

test('Operator login opens the task portal, separate from Manager Operator page', async ({ page }) => {
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 2, name: 'Raka', email: 'raka@studio.test', role: 'operator' } }) }))
  await page.route('**/api/v1/operator/**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [] }) }))
  await page.goto('/operator/workspace')
  await expect(page.getByRole('heading', { name: 'Pekerjaan produksi' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Jadwal rekaman' })).toBeVisible()
})

test('Manager can edit profile and log out from the sidebar', async ({ page }) => {
  let profileSaved = false
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1, name: 'Manager Studio', email: 'manager@studio.test', phone: '6281234567890', role: 'manager' } }) }))
  await page.route('**/api/v1/manager/**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [] }) }))
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/auth/profile', (route) => {
    profileSaved = true
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1, name: 'Manager Baru', email: 'manager@studio.test', phone: '6281234567890', role: 'manager' } }) })
  })
  await page.route('**/api/v1/auth/logout', (route) => route.fulfill({ status: 204 }))
  await page.goto('/booking')
  await page.getByRole('button', { name: 'Edit Profil' }).click()
  const dialog = page.getByRole('dialog', { name: 'Edit profil' })
  await dialog.getByRole('textbox', { name: 'Nama lengkap' }).fill('Manager Baru')
  await dialog.getByRole('button', { name: 'Simpan Perubahan' }).click()
  await expect.poll(() => profileSaved).toBe(true)
  await page.getByRole('button', { name: 'Keluar' }).click()
  await expect(page).toHaveURL(/\/staff\/login$/)
})
