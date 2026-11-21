import { expect, test } from '@playwright/test'

const specifications = { track_count: 1, recording_hours: 6, include_recording: true, include_editing: true, include_mixing: true, include_mastering: true }
const packages = ['A', 'B', 'C'].map((letter, index) => ({
  ...specifications, id: index + 1, name: `Paket Lengkap ${letter}`, description: `Paket lengkap untuk ${index ? index === 1 ? 'dua' : 'tiga' : 'satu'} lagu, dari rekaman sampai siap dirilis.`,
  track_count: index + 1, recording_hours: (index + 1) * 6, price: 970000 + index * 870000,
}))
const offers = [970000, 1100000, 1400000, 1250000].map((price, index) => ({ id: index + 1, price, side: 'client', note: null, created_at: `2026-10-${String(1 + index * 7).padStart(2, '0')}T07:20:00Z` }))
const quotation = { id: 1, name: 'Single Pertama', specifications, status: 'awaiting_studio', status_label: 'Menunggu studio', auto_price: 1250000, agreed_price: null, created_at: offers[0].created_at, latest_offer: offers[3], offers }
const rates = ['recording', 'editing', 'mixing', 'mastering'].map((stage, index) => ({ stage, price: [150000, 200000, 350000, 250000][index], unit: stage === 'recording' ? 'hour' : 'track' }))

test.beforeEach(async ({ page }) => {
  await page.route('http://localhost:8000/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    let data: unknown = null
    if (path.endsWith('/auth/me')) data = { id: 4, name: 'Klien Contoh', email: 'klien@studio.test', role: 'client', role_label: 'Klien' }
    if (path.endsWith('/packages')) data = packages
    if (path.endsWith('/base-prices')) data = rates
    if (path.endsWith('/quotations')) data = [quotation]
    if (path.endsWith('/quotations/1')) data = quotation
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) })
  })
})

test('matches package layout, assets and local fonts at desktop and mobile sizes', async ({ page }) => {
  await page.goto('/client/packages')
  await expect(page.getByRole('heading', { name: 'Paket yang Kami Sediakan' })).toBeVisible()
  await expect(page.locator('.client-package')).toHaveCount(3)
  await page.evaluate(() => document.fonts.ready)
  const media = await page.locator('.client-content img, .client-navbar img').evaluateAll((elements) => elements.map((element) => {
    const img = element as HTMLImageElement
    return { src: img.currentSrc, loaded: img.complete && img.naturalWidth > 0, width: img.getBoundingClientRect().width, height: img.getBoundingClientRect().height }
  }))
  expect(media.every((img) => img.loaded)).toBeTruthy()
  expect(media.some((img) => img.src.includes('figma.com'))).toBeFalsy()
  await expect(page.locator('.client-phases img').first()).toHaveJSProperty('naturalWidth', 48)
  expect(await page.locator('.client-phases img').first().evaluate((img) => img.getBoundingClientRect().width)).toBe(48)
  await expect(page.locator('.client-package .ts-badge img').first()).toHaveJSProperty('naturalWidth', 16)
  expect(await page.evaluate(() => document.fonts.check('400 16px "Tungku Client DM Sans"'))).toBeTruthy()
  await page.screenshot({ path: 'test-results/client-desktop.png', fullPage: true })
  await page.setViewportSize({ width: 390, height: 844 })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy()
  await page.screenshot({ path: 'test-results/client-mobile.png', fullPage: true })
})

test('prices recalculate and invalid quotations cannot be sent', async ({ page }) => {
  await page.goto('/client/packages')
  await expect(page.getByRole('button', { name: 'Kirim Penawaran', exact: true })).toBeEnabled()
  await page.getByRole('switch', { name: 'Atur Harga Manual' }).click()
  await expect(page.locator('.client-summary-total strong')).toHaveText('Rp 1.250.000')
  await page.getByRole('button', { name: 'Tambah Jumlah Lagu', exact: true }).click()
  await expect(page.locator('.client-summary-total strong')).toHaveText('Rp 2.050.000')
  await page.getByRole('button', { name: /Recording Rp/ }).click()
  await expect(page.getByRole('spinbutton', { name: 'Durasi Rekaman (Total Jam)', exact: true })).toHaveValue('0')
  await expect(page.locator('.client-summary-total strong')).toHaveText('Rp 1.600.000')
  for (const stage of ['Editing', 'Mixing', 'Mastering']) await page.getByRole('button', { name: new RegExp(`${stage} Rp`) }).click()
  await expect(page.getByRole('button', { name: 'Kirim Penawaran', exact: true })).toBeDisabled()
  await expect(page.getByRole('alert')).toContainText('Pilih minimal satu tahap')
})

test('search, package selection and quote submission use the selected specification', async ({ page }) => {
  let submitted: Record<string, unknown> | undefined
  await page.route('**/api/v1/client/quotations', async (route) => {
    if (route.request().method() === 'POST') {
      submitted = route.request().postDataJSON()
      await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: { ...quotation, id: 7, name: submitted?.name, specifications: submitted } }) })
    } else await route.fallback()
  })
  await page.goto('/client/packages')
  await page.getByRole('searchbox', { name: 'Cari paket' }).fill('Lengkap B')
  await expect(page.locator('.client-package')).toHaveCount(1)
  await page.getByRole('button', { name: 'Aksi untuk Paket Lengkap B' }).click()
  await page.getByRole('button', { name: 'Sesuaikan Penawaran' }).click()
  await expect(page.getByRole('spinbutton', { name: 'Jumlah Lagu', exact: true })).toHaveValue('2')
  await page.getByRole('button', { name: 'Kirim Penawaran', exact: true }).click()
  await page.getByLabel('Nama Proyek', { exact: true }).fill('Album Klien')
  await page.getByRole('button', { name: 'Kirim ke Studio' }).click()
  await expect(page.getByRole('status')).toContainText('berhasil dikirim')
  expect(submitted).toMatchObject({ name: 'Album Klien', track_count: 2, recording_hours: 12, offer_price: 1840000 })
})

test('does not show client data to a staff account or anonymous visitor', async ({ page }) => {
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { role: 'manager' } }) }))
  await page.goto('/client/packages')
  await expect(page.getByText('Halaman ini untuk akun Klien.', { exact: false })).toBeVisible()
  await expect(page.locator('.client-package')).toHaveCount(0)
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ status: 401, contentType: 'application/json', body: '{}' }))
  await page.reload()
  await expect(page).toHaveURL(/\/login$/)
})

test('load failures show a retry action', async ({ page }) => {
  let failed = true
  await page.route('**/api/v1/client/packages', async (route) => {
    if (failed) await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Studio sementara tidak tersedia.' }) })
    else await route.fallback()
  })
  await page.goto('/client/packages')
  await expect(page.getByRole('alert')).toHaveText('Studio sementara tidak tersedia.')
  failed = false
  await page.getByRole('button', { name: 'Coba lagi' }).click()
  await expect(page.locator('.client-package')).toHaveCount(3)
})


test('client navigation opens workspace pages and displays live records', async ({ page }) => {
  await page.route('**/api/v1/client/projects**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [] }) }))
  await page.route('**/api/v1/client/bookings**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [] }) }))
  await page.route('**/api/v1/client/rooms**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [{ id: 1, name: 'Studio A', is_active: true }] }) }))
  await page.route('**/api/v1/client/invoices**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [] }) }))
  await page.goto('/client')
  await expect(page.getByRole('heading', { name: 'Selamat Datang di Tungku Studio' })).toBeVisible()
  await page.getByRole('navigation', { name: 'Navigasi klien' }).getByRole('link', { name: 'Jadwal Booking' }).click()
  await expect(page.getByRole('heading', { name: 'Buat Booking' })).toBeVisible()
  await page.getByRole('navigation', { name: 'Navigasi klien' }).getByRole('link', { name: 'Penawaran' }).click()
  await expect(page.getByRole('heading', { name: 'Penawaran Saya' })).toBeVisible()
  await page.getByRole('navigation', { name: 'Navigasi klien' }).getByRole('link', { name: 'Project' }).click()
  await expect(page.getByRole('heading', { name: 'Project Saya' })).toBeVisible()
  await page.getByRole('navigation', { name: 'Navigasi klien' }).getByRole('link', { name: 'Transaksi' }).click()
  await expect(page.getByRole('heading', { name: 'Transaksi Saya' })).toBeVisible()
})


test('client can open profile editor and log out from account menu', async ({ page }) => {
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/auth/profile', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 4, name: 'Klien Baru', email: 'klien@studio.test', phone: '6281234567890', role: 'client', role_label: 'Klien' } }) }))
  await page.route('**/api/v1/auth/logout', (route) => route.fulfill({ status: 204 }))
  await page.goto('/client/packages')
  await page.getByRole('button', { name: /Akun Klien Contoh/ }).click()
  await page.getByRole('button', { name: 'Edit Profil' }).click()
  await expect(page.getByRole('dialog', { name: 'Edit profil' })).toBeVisible()
  await page.getByRole('textbox', { name: 'Nama lengkap' }).fill('Klien Baru')
  await page.getByRole('textbox', { name: 'Nomor telepon' }).fill('081234567890')
  await page.getByRole('button', { name: 'Simpan Perubahan' }).click()
  await expect(page.getByRole('button', { name: /Akun Klien Baru/ })).toBeVisible()
  await page.getByRole('button', { name: /Akun Klien Baru/ }).click()
  await page.getByRole('button', { name: 'Logout' }).click()
  await expect(page).toHaveURL(/\/login$/)
})
