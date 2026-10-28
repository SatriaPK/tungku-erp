import { test, expect } from '@playwright/test';

const project = { id: 42, name: 'Album Senja', code: 'PRJ-SENJA', client: { id: 8, name: 'Rani Musik' }, status: 'in_progress', status_label: 'In Progress', stage_id: 7 };
async function setup(page, role = 'manager') {
  const searches = [];
  await page.route('**/api/v1/**', async route => {
    const url = new URL(route.request().url());
    const endpoint = url.pathname.replace('/api/v1', '');
    let data = { data: [] };
    if (endpoint === '/auth/me') data = { data: { id: 1, name: 'Pengguna Uji', role, role_label: role } };
    else if (endpoint === '/notifications/unread-count') data = { data: { unread_count: 0 } };
    else if (endpoint === '/manager/dashboard') data = { data: { revenue: { current: 0, percentage: null, percentage_reason: 'previous_not_positive' }, expense: { current: 0, percentage: null, percentage_reason: 'previous_not_positive' } } };
    else if (endpoint === '/search') {
      searches.push(url.searchParams);
      data = { data: { projects: url.searchParams.get('q') === 'kosong' ? [] : [project], clients: role === 'manager' && url.searchParams.get('q') !== 'kosong' ? [{ id: 8, name: 'Rani Musik' }] : [] }, meta: { current_page: 1, last_page: 1, total: 1 } };
    }
    await route.fulfill({ json: data, headers: { 'access-control-allow-origin': 'http://localhost:4174', 'access-control-allow-credentials': 'true' } });
  });
  return searches;
}

test('all five dashboard metric cards navigate and technical reasons are hidden', async ({ page }) => {
  await setup(page);
  for (const [name, destination] of [['Pendapatan Bulan Ini','reports'],['Pengeluaran Bulan Ini','expenses'],['Pembayaran Belum Lunas','payments'],['Project Aktif','project'],['Dalam Penawaran','quotation']]) {
    await page.goto('/manager/dashboard');
    await expect(page.getByText('previous_not_positive')).toHaveCount(0);
    await expect(page.getByText('Belum ada pembanding', { exact: true })).toHaveCount(2);
    await page.getByRole('link', { name: `Buka ${name}`, exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/manager/${destination}$`));
  }
});

test('header finds a project outside the current page and opens its search result', async ({ page }) => {
  const searches = await setup(page);
  await page.goto('/manager/expenses');
  const input = page.getByRole('searchbox', { name: 'Cari nama proyek atau klien' });
  await input.fill('Senja');
  const results = page.getByRole('region', { name: 'Hasil pencarian cepat' });
  await expect(results.getByRole('button', { name: /Album Senja/ })).toBeVisible();
  await results.getByRole('button', { name: /Album Senja/ }).click();
  await expect(page).toHaveURL(/\/manager\/search\?q=Senja&project=42/);
  await expect(page.getByRole('heading', { name: 'Album Senja' })).toBeVisible();
  expect(searches.some(params => params.get('project_id') === '42')).toBe(true);
  await page.getByRole('button', { name: 'Buka produksi' }).click();
  await expect(page).toHaveURL(/\/manager\/project\?project=42&stage=7/);
});

test('search supports client results, keyboard submission and an empty state', async ({ page }) => {
  await setup(page);
  await page.goto('/manager/dashboard');
  const input = page.getByRole('searchbox', { name: 'Cari nama proyek atau klien' });
  await input.fill('Rani');
  await page.getByRole('region', { name: 'Hasil pencarian cepat' }).getByRole('button', { name: /^Rani Musik/ }).click();
  await expect(page).toHaveURL(/client=8/);
  await expect(page.getByRole('heading', { name: 'Album Senja' })).toBeVisible();
  await input.fill('kosong');
  await expect(page.getByText('Tidak ada proyek atau klien yang cocok.', { exact: true })).toBeVisible();
  await input.press('Escape');
  await expect(page.getByRole('region', { name: 'Hasil pencarian cepat' })).not.toBeVisible();
  await input.press('Enter');
  await expect(page.getByText('Tidak ada proyek yang cocok dengan pencarian ini.', { exact: true })).toBeVisible();
});

test('client header search opens the clients own project results', async ({ page }) => {
  await setup(page, 'client');
  await page.goto('/client/invoice');
  const input = page.getByRole('searchbox', { name: 'Cari nama proyek' });
  await expect(input).toHaveCount(1);
  await input.fill('Senja');
  await input.press('Enter');
  await expect(page).toHaveURL(/\/client\/search\?q=Senja/);
  await expect(page.getByRole('heading', { name: 'Album Senja' })).toBeVisible();
});

test('mobile header search stays accessible and the result menu fits the screen', async ({ page }) => {
  await setup(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/manager/dashboard');
  await page.getByRole('searchbox', { name: 'Cari nama proyek atau klien' }).fill('Senja');
  const results = page.getByRole('region', { name: 'Hasil pencarian cepat' });
  await expect(results.getByRole('button', { name: /Album Senja/ })).toBeVisible();
  const bounds = await results.boundingBox();
  expect(bounds.x).toBeGreaterThanOrEqual(0);
  expect(bounds.x + bounds.width).toBeLessThanOrEqual(390);
  await expect(page.locator('html')).toHaveJSProperty('scrollWidth', 390);
});
