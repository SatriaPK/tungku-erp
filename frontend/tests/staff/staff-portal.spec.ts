import { expect, test } from '@playwright/test'

const dashboard = { period: { year: 2026, month: 11 }, revenue: { current: 0, percentage: null, direction: 'up' }, expense: { current: 0, percentage: null, direction: 'up' }, awaiting_final_payment: { amount: 0 }, in_progress_projects: { count: 1, track_count: 1 }, quotations: { open_count: 0, awaiting_studio_count: 0 }, monthly: [], package_sales: { items: [], total_count: 0 }, expense_categories: { items: [], total_amount: 0 }, recording_schedule: [], project_progress: [], quotation_progress: [], operator_activities: [] }
const project = { id: 8, code: 'PRJ-008', name: 'Single Baru', client: { name: 'Nadine' }, status: 'awaiting_assignment', status_label: 'Menunggu Penugasan', price: 1250000, stages: [{ stage: 'recording', status: 'locked', operator: null }, { stage: 'mixing', status: 'locked', operator: null }] }

test('manager menus and operator assignment modal work', async ({ page }) => {
  let submitted: Record<string, unknown> | undefined
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1, name: 'Manager Studio', role: 'manager', email: 'manager@test.local' } }) }))
  await page.route('**/api/v1/manager/dashboard', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: dashboard }) }))
  await page.route('**/api/v1/manager/projects**', (route) => {
    if (route.request().method() === 'POST') submitted = route.request().postDataJSON()
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [project] }) })
  })
  await page.route('**/api/v1/manager/operators**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [{ id: 2, name: 'Raka', role: 'operator' }, { id: 3, name: 'Ayu', role: 'operator' }] }) }))
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.goto('/dashboard')
  await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Project' }).click()
  await expect(page.getByRole('heading', { name: 'Project', exact: true }).last()).toBeVisible()
  await page.getByRole('button', { name: 'Pilih Operator' }).click()
  await expect(page.getByRole('dialog', { name: 'Pilih operator' })).toBeVisible()
  await page.screenshot({ path: 'test-results/manager-operator-modal.png', fullPage: true })
  await page.getByRole('combobox', { name: 'Operator Recording' }).selectOption('2')
  await page.getByRole('combobox', { name: 'Operator Mixing' }).selectOption('3')
  await page.getByRole('button', { name: 'Simpan Penugasan' }).click()
  await expect(page.getByRole('dialog', { name: 'Pilih operator' })).toBeHidden()
  expect(submitted).toEqual({ assignments: { recording: 2, mixing: 3 } })
})

test('operator portal shows assigned stage and a short result modal', async ({ page }) => {
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 2, name: 'Raka', role: 'operator', email: 'raka@test.local' } }) }))
  await page.route('**/api/v1/operator/stages**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [{ id: 10, project_id: 8, project_name: 'Single Baru', client_name: 'Nadine', stage: 'recording', status: 'ready', deadline_at: null, tracks: [{ id: 12, title: 'Lagu Pertama', progress_id: 15, status: 'pending', review_notes: null }] }] }) }))
  await page.goto('/operator')
  await expect(page.getByRole('heading', { name: 'Pekerjaan produksi' })).toBeVisible()
  await page.getByRole('button', { name: 'Kirim Hasil' }).click()
  await expect(page.getByRole('dialog', { name: 'Kirim hasil Lagu Pertama' })).toBeVisible()
  await expect(page.getByRole('textbox', { name: 'Link hasil kerja' })).toBeVisible()
})


test('manager reviews an operator result from project stages', async ({ page }) => {
  let review: Record<string, unknown> | undefined
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1, name: 'Manager Studio', role: 'manager' } }) }))
  await page.route('**/api/v1/manager/dashboard', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: dashboard }) }))
  await page.route('**/api/v1/manager/projects**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [{ ...project, status: 'in_progress', stages: [{ stage: 'recording', status: 'ready', operator: { name: 'Raka' }, tracks: [{ progress_id: 15, title: 'Lagu Pertama', status: 'in_review', submission_id: 19, result_url: 'https://example.com/result' }] }] }] }) }))
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/manager/track-progress/**', (route) => { review = route.request().postDataJSON(); return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: {} }) }) })
  await page.goto('/dashboard/project')
  await page.getByRole('button', { name: 'Lihat Tahap' }).click()
  await page.getByRole('button', { name: 'Tinjau' }).click()
  await page.getByRole('combobox', { name: 'Keputusan' }).selectOption('request_revision')
  await page.getByRole('textbox', { name: 'Catatan revisi' }).fill('Mohon perbaiki bagian vokal utama.')
  await page.getByRole('button', { name: 'Simpan Review' }).click()
  await expect(page.getByRole('dialog')).toBeHidden()
  expect(review).toEqual({ decision: 'request_revision', review_notes: 'Mohon perbaiki bagian vokal utama.' })
})


test('manager can edit profile and log out', async ({ page }) => {
  let saved: Record<string, unknown> | undefined
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1, name: 'Manager Studio', email: 'manager@studio.test', phone: '6281234567890', role: 'manager', role_label: 'Manager' } }) }))
  await page.route('**/api/v1/manager/dashboard', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: dashboard }) }))
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/auth/profile', (route) => { saved = route.request().postDataJSON(); return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1, name: saved?.name, email: saved?.email, phone: saved?.phone, role: 'manager', role_label: 'Manager' } }) }) })
  await page.route('**/api/v1/auth/logout', (route) => route.fulfill({ status: 204 }))
  await page.goto('/dashboard')
  await page.getByRole('button', { name: 'Edit Profil' }).first().click()
  await page.getByRole('textbox', { name: 'Nama lengkap' }).fill('Manager Baru')
  await page.getByRole('button', { name: 'Simpan Perubahan' }).click()
  await expect(page.getByRole('dialog', { name: 'Edit profil' })).toBeHidden()
  await expect(page.locator('.manager-profile__details strong')).toHaveText('Manager Baru')
  expect(saved).toMatchObject({ name: 'Manager Baru', email: 'manager@studio.test', phone: '6281234567890' })
  await page.getByRole('button', { name: 'Logout' }).first().click()
  await expect(page).toHaveURL(/\/staff\/login$/)
})


test('animated loading screen appears while staff login waits for server', async ({ page }) => {
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/auth/login', async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 700))
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1 } }) })
  })
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1, name: 'Manager Studio', email: 'manager@studio.test', role: 'manager' } }) }))
  await page.route('**/api/v1/manager/dashboard', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: dashboard }) }))
  await page.goto('/staff/login')
  await page.getByRole('textbox', { name: 'Email' }).fill('manager@studio.test')
  await page.getByLabel('Password', { exact: true }).fill('password')
  await page.getByRole('button', { name: 'Masuk', exact: true }).click()
  await expect(page.getByRole('status', { name: 'Masuk ke Tungku Studio' })).toBeVisible()
  await expect(page).toHaveURL(/\/dashboard$/)
})

test('manager can add an operator account', async ({ page }) => {
  let created: Record<string, unknown> | undefined
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 1, name: 'Manager Studio', role: 'manager' } }) }))
  await page.route('**/api/v1/manager/dashboard', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: dashboard }) }))
  await page.route('**/api/v1/manager/operator-accounts**', (route) => {
    if (route.request().method() === 'POST') created = route.request().postDataJSON()
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [] }) })
  })
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.goto('/dashboard/operator')
  await page.getByRole('button', { name: 'Tambah Operator' }).click()
  const dialog = page.getByRole('dialog', { name: 'Tambah operator' })
  await dialog.getByRole('textbox', { name: 'Nama' }).fill('Raka Studio')
  await dialog.getByRole('textbox', { name: 'Email' }).fill('raka@studio.test')
  await dialog.getByRole('textbox', { name: 'Nomor telepon' }).fill('081234567890')
  await dialog.getByLabel('Password', { exact: true }).fill('password123')
  await dialog.getByLabel('Ulangi password').fill('password123')
  await dialog.getByRole('button', { name: 'Simpan Operator' }).click()
  await expect(dialog).toBeHidden()
  expect(created).toMatchObject({ name: 'Raka Studio', email: 'raka@studio.test', password: 'password123' })
})

test('operator can view sessions and submission history', async ({ page }) => {
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: { id: 2, name: 'Raka', role: 'operator' } }) }))
  await page.route('**/api/v1/operator/stages**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [{ id: 10, project_id: 8, project_name: 'Single Baru', client_name: 'Nadine', stage: 'recording', status: 'in_progress', deadline_at: null, tracks: [{ id: 12, title: 'Lagu Pertama', progress_id: 15, status: 'in_review', review_notes: null }] }] }) }))
  await page.route('**/api/v1/operator/sessions**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [{ id: 7, room_id: 3, room_name: 'Studio A', client_name: 'Nadine', start_at: '2026-11-03T10:00:00+07:00', end_at: '2026-11-03T12:00:00+07:00', status_label: 'Terjadwal' }] }) }))
  await page.route('**/api/v1/operator/rooms**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [{ id: 3, name: 'Studio A' }] }) }))
  await page.route('**/api/v1/operator/track-progress/15/submissions', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: [{ id: 19, version: 1, result_url: 'https://example.com/result', submitted_at: '2026-11-03T12:00:00+07:00', review_decision: null, review_notes: null }] }) }))
  await page.goto('/operator')
  await expect(page.getByRole('heading', { name: 'Jadwal rekaman' })).toBeVisible()
  await expect(page.locator('.operator-session-list')).toContainText('Studio A')
  await page.getByRole('button', { name: 'Riwayat' }).click()
  await expect(page.getByRole('dialog', { name: 'Lagu Pertama' })).toContainText('Versi 1')
})
