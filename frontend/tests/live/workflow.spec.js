import {test,expect} from '@playwright/test';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

// These tests use actual Laravel cookie sessions and an explicitly isolated local DB.
const backend=fileURLToPath(new URL('../../../backend',import.meta.url));
const password='TungkuLocal2026!';
async function signIn(browser,email) {
  const context=await browser.newContext({baseURL:'http://localhost:5174'});
  const page=await context.newPage();
  await page.goto('/login');
  await page.getByLabel('Email *',{exact:true}).fill(email);
  await page.getByLabel('Password *',{exact:true}).fill(password);
  await page.getByRole('button',{name:'Masuk',exact:true}).click();
  await expect(page).not.toHaveURL(/\/login$/);
  return {page,context};
}
async function finish(page) {await page.getByRole('button',{name:'Selesai',exact:true}).click();}
async function submitStage(page,name,url) {
  await page.goto('/operator/tasks');
  const option=page.getByRole('combobox',{name:'Tahap Produksi'}).locator('option').filter({hasText:name});
  await page.getByRole('combobox',{name:'Tahap Produksi'}).selectOption(await option.first().getAttribute('value'));
  await page.getByRole('button',{name:'Tetapkan Tenggat'}).click();
  let dialog=page.getByRole('dialog');await dialog.getByLabel('Tenggat').fill('2099-01-20T17:00');await dialog.getByRole('button',{name:'Simpan'}).click();await expect(dialog.getByText('Berhasil.',{exact:false})).toBeVisible();await finish(page);
  await page.getByRole('button',{name:'Kirim Link Hasil'}).click();dialog=page.getByRole('dialog');await dialog.getByLabel('Tautan Hasil').fill(url);await dialog.getByRole('button',{name:'Kirim Hasil',exact:true}).click();await expect(dialog.getByText('Berhasil.',{exact:false})).toBeVisible();await finish(page);
}
async function review(page,name,stageName) {
  await page.goto('/manager/project');
  const select=page.getByRole('combobox',{name:'Tahap Produksi'});
  const option=select.locator('option').filter({hasText:`${name} · ${stageName}`});
  await select.selectOption(await option.first().getAttribute('value'));
  await page.getByRole('button',{name:'Review Hasil',exact:true}).click();
  const dialog=page.getByRole('dialog');await dialog.getByLabel('Keputusan').selectOption('approve');await dialog.getByRole('button',{name:'Simpan'}).click();await expect(dialog.getByText('Berhasil.',{exact:false})).toBeVisible();await finish(page);
}

test('real sessions complete assignment, production, final cash settlement and private delivery',async({browser})=>{
  const name=`Uji Browser ${Date.now()}`;
  execFileSync('php',['artisan','--env=local','db:seed','--class=LocalDemoSeeder','--force','--no-ansi'],{cwd:backend,env:{...process.env,LOCAL_DEMO_PROJECT_CODE:`LIVE-${Date.now()}`,LOCAL_DEMO_PROJECT_NAME:name},timeout:30000});
  const contexts=[];
  try {
    const manager=await signIn(browser,'manager@studio.test');contexts.push(manager.context);const m=manager.page;
    await m.goto('/manager/project');const row=m.getByRole('row').filter({hasText:name});await row.getByRole('button',{name:'Tugaskan'}).click();
    let dialog=m.getByRole('dialog');await dialog.getByLabel('Operator Editing').selectOption({label:'Operator Satu'});await dialog.getByLabel('Operator Mastering').selectOption({label:'Operator Dua'});await dialog.getByRole('button',{name:'Simpan'}).click();await expect(dialog.getByText('Berhasil.',{exact:false})).toBeVisible();await finish(m);
    const editing=await signIn(browser,'operator1@studio.test');contexts.push(editing.context);
    await submitStage(editing.page,name,'https://example.test/live-editing-result');await review(m,name,'Editing');
    const mastering=await signIn(browser,'operator2@studio.test');contexts.push(mastering.context);
    await submitStage(mastering.page,name,'https://example.test/live-final-result');await review(m,name,'Mastering');
    const client=await signIn(browser,'klien@studio.test');contexts.push(client.context);const c=client.page;
    await c.goto('/client/notifications');await c.getByRole('button').filter({hasText:'Tagihan pelunasan siap'}).filter({hasText:name}).click();
    await expect(c.locator('.panel')).toBeVisible();
    if(await c.getByRole('button',{name:'Pelunasan',exact:true}).count()) {
      await c.getByRole('button',{name:'Pelunasan',exact:true}).click();dialog=c.getByRole('dialog');await dialog.getByLabel('Penyedia Pembayaran').selectOption('cash');await dialog.getByRole('button',{name:'Mulai Pelunasan',exact:true}).click();await expect(dialog.getByText('Menunggu Konfirmasi Tunai',{exact:true})).toBeVisible();await finish(c);
    } else await expect(c.getByText('Pembayaran tunai menunggu konfirmasi Manager.')).toBeVisible();
    await m.goto('/manager/payments');await m.getByRole('button',{name:new RegExp(`FINAL-`)}).filter({hasText:name}).filter({hasText:'Menunggu Konfirmasi Manual'}).first().click();
    await m.getByRole('button',{name:'Konfirmasi Tunai',exact:true}).click();dialog=m.getByRole('dialog');await dialog.getByLabel('Catatan').fill('Tunai diterima untuk pengujian lokal; bukan transaksi uang nyata.');await dialog.getByRole('button',{name:'Konfirmasi Diterima'}).click();await expect(dialog.getByText('Lunas',{exact:true}).first()).toBeVisible();await finish(m);
    await c.getByRole('button',{name:'Periksa Status',exact:true}).click();await expect(c.getByText('Lunas',{exact:true}).first()).toBeVisible();await c.getByRole('button',{name:'Lihat Hasil Project'}).click();await expect(c.getByRole('link',{name:'Buka tautan'})).toHaveAttribute('href','https://example.test/live-final-result');
    await c.screenshot({path:'test-results-live/live-final-delivery.png',fullPage:true,animations:'disabled'});
  } finally {for(const context of contexts)await context.close();}
});

test('real client catalog supplies room and package choices for booking',async({browser})=>{
  const {page,context}=await signIn(browser,'klien@studio.test');
  try {
    await page.goto('/client/booking');await page.getByRole('button',{name:'Booking Baru'}).click();const dialog=page.getByRole('dialog');
    await expect(dialog.getByRole('combobox',{name:'Ruangan'})).toBeVisible();await expect(dialog.getByRole('combobox',{name:'Paket',exact:true})).toBeVisible();
    const day=Math.floor(Date.now()/1000)%27+1;
    await dialog.getByRole('combobox',{name:'Ruangan'}).selectOption({index:1});await dialog.getByRole('combobox',{name:'Paket',exact:true}).selectOption({label:'Paket Single Studio'});await dialog.getByLabel('Mulai').fill(`2099-05-${String(day).padStart(2,'0')}T10:00`);await dialog.getByLabel('Selesai').fill(`2099-05-${String(day).padStart(2,'0')}T12:00`);await dialog.getByRole('button',{name:'Tahan Jadwal',exact:true}).click();await expect(dialog.getByText('Berhasil.',{exact:false})).toBeVisible();await finish(page);
  } finally {await context.close();}
});
