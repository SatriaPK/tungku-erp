import {test,expect} from '@playwright/test';

const catalog={id:1,name:'Paket Single',description:'Produksi satu lagu',track_count:1,recording_hours:2,include_recording:true,include_editing:true,include_mixing:true,include_mastering:true,price:970000};
const dashboard={revenue:{current:1000000,percentage:null,percentage_reason:'Belum ada pembanding'},expense:{current:100000,percentage:null},monthly:[{month:10,revenue:1000000,expense:100000}],in_progress_projects:{count:1,track_count:1},awaiting_final_payment:{amount:500000,project_count:1},project_progress:[{id:7,name:'Album Pertama',client_name:'Klien A',status:'awaiting_assignment',status_label:'Menunggu Penugasan',current_stage_label:'Recording'}],recording_schedule:[],operator_activities:[],package_sales:{items:[]},quotation_progress:[]};
const pagination={current_page:1,last_page:1,total:1};
async function mockApi(page,{role='manager',loggedIn=true,custom}={}) {
  const calls=[];
  const user={id:1,name:'Pengguna Uji',email:'uji@example.test',role,role_label:role,crm_consent:false};
  await page.route('**/sanctum/csrf-cookie',async route=>{calls.push({path:'/sanctum/csrf-cookie',method:'GET'});await route.fulfill({status:204,headers:{'set-cookie':'XSRF-TOKEN=csrf%20test; Path=/','access-control-allow-origin':'http://localhost:4174','access-control-allow-credentials':'true'}});});
  await page.route('**/api/v1/**',async route=>{
    const req=route.request(),url=new URL(req.url()),path=url.pathname.replace('/api/v1','');
    const method=req.method(),body=req.postDataJSON();
    if(method==='OPTIONS')return route.fulfill({status:204,headers:{'access-control-allow-origin':'http://localhost:4174','access-control-allow-credentials':'true','access-control-allow-headers':'content-type,x-xsrf-token','access-control-allow-methods':'GET,POST,PUT,PATCH,DELETE'}});
    calls.push({path,method,body,headers:req.headers(),query:url.searchParams.toString()});
    let response=custom?.({path,method,body,calls});
    if(!response) {
      if(path==='/auth/me')response=loggedIn?{data:user}:{status:401,message:'Unauthenticated.'};
      else if(path==='/auth/login'||path==='/auth/register'){loggedIn=true;response={data:user};}
      else if(path==='/auth/logout'){loggedIn=false;response={status:204};}
      else if(path==='/notifications/unread-count')response={data:{unread_count:2}};
      else if(path==='/manager/dashboard')response={data:dashboard};
      else if(path.endsWith('/packages'))response={data:[catalog]};
      else if(path==='/manager/settings')response={data:[]};
      else if(path==='/manager/base-prices')response={data:[]};
      else response={data:[]};
    }
    const {status=200,headers={},...json}=response;
    await route.fulfill({status,headers:{'access-control-allow-origin':'http://localhost:4174','access-control-allow-credentials':'true',...headers},...(status===204?{}:{json})});
  });
  return calls;
}
test('Sanctum login fetches CSRF before login, uses XSRF and server role',async({page})=>{
  const calls=await mockApi(page,{role:'operator',loggedIn:false});
  await page.goto('/login');
  await page.getByLabel('Email').fill('uji@example.test');
  await page.getByLabel('Password *',{exact:true}).fill('password1');
  await page.getByRole('button',{name:'Masuk',exact:true}).click();
  await expect(page).toHaveURL(/\/operator\/sessions/);
  const csrf=calls.findIndex(c=>c.path==='/sanctum/csrf-cookie'), login=calls.findIndex(c=>c.path==='/auth/login');
  expect(csrf).toBeLessThan(login);expect(calls[login].headers['x-xsrf-token']).toBe('csrf test');
  expect(calls[login].headers.authorization).toBeUndefined();
  expect(calls[login].body).toEqual({email:'uji@example.test',password:'password1',remember:false});
  await expect(page.getByRole('link',{name:'Laporan & Payroll'})).toHaveCount(0);
});
test('registration is client-only with explicit false CRM consent',async({page})=>{
  const calls=await mockApi(page,{role:'client',loggedIn:false});
  await page.goto('/register');
  await page.getByLabel('Nama',{exact:false}).fill('Klien Baru');
  await page.getByRole('textbox',{name:'Email *',exact:true}).fill('baru@example.test');
  await page.getByLabel('Nomor Telepon').fill('081234567890');
  await page.getByLabel('Password *',{exact:true}).fill('password1');
  await page.getByLabel('Konfirmasi Password').fill('password1');
  await page.getByRole('button',{name:'Daftar sebagai Klien'}).click();
  await expect(page).toHaveURL(/\/client\/packages/);
  const body=calls.find(c=>c.path==='/auth/register').body;
  expect(body.phone).toBe('6281234567890');expect(body.crm_consent).toBe(false);expect(body.role).toBeUndefined();
});
test('dashboard handles null MoM and loads without Firebase',async({page})=>{
  const calls=await mockApi(page);const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('/manager/dashboard');
  await expect(page.getByText('Belum ada pembanding',{exact:true})).toBeVisible();
  await expect(page.getByText('Album Pertama')).toBeVisible();
  expect(errors).toEqual([]);expect(calls.some(c=>c.path==='/manager/dashboard')).toBe(true);
  await page.screenshot({path:'test-results/manager-dashboard.png',fullPage:true,animations:'disabled'});
});
test('operator cannot access manager finance even with forged localStorage',async({page})=>{
  const calls=await mockApi(page,{role:'operator'});
  await page.addInitScript(()=>localStorage.setItem('internalRole','manager'));
  await page.goto('/manager/reports');await expect(page).toHaveURL(/\/operator\/sessions/);
  expect(calls.some(c=>c.path.includes('/finance/'))).toBe(false);
});
test('booking rejects conflicting sources then sends WIB hold payload',async({page})=>{
  const calls=await mockApi(page,{role:'client'});
  await page.goto('/client/booking');await page.getByRole('button',{name:'Booking Baru'}).click();
  const dialog=page.getByRole('dialog');
  await dialog.getByLabel('ID Ruangan').fill('1');await dialog.getByLabel('Mulai').fill('2099-01-20T10:00');await dialog.getByLabel('Selesai').fill('2099-01-20T12:00');
  await dialog.getByRole('combobox',{name:'Paket',exact:true}).selectOption('1');await dialog.getByLabel('ID Penawaran').fill('3');
  await dialog.getByRole('button',{name:'Tahan Jadwal',exact:true}).click();
  await expect(dialog.getByRole('alert')).toContainText('Pilih tepat satu');
  expect(calls.filter(c=>c.path.endsWith('/hold'))).toHaveLength(0);
  await dialog.getByLabel('ID Penawaran').fill('');await dialog.getByRole('button',{name:'Tahan Jadwal',exact:true}).click();
  await expect(dialog.getByText('Berhasil.',{exact:false})).toBeVisible();
  const body=calls.find(c=>c.path.endsWith('/hold')).body;
  expect(body.start_at).toBe('2099-01-20T10:00:00+07:00');expect(body.package_id).toBe(1);expect(body.quotation_id).toBeUndefined();
});
test('quotation uses latest offer ID and counter price',async({page})=>{
  const quote={id:9,name:'Single',status:'awaiting_client',latest_offer:{id:42,price:900000}};
  const calls=await mockApi(page,{role:'client',custom:({path,method})=>path==='/client/quotations'?{data:[quote]}:path==='/client/quotations/9'?{data:quote}:path.endsWith('/respond')&&method==='POST'?{data:{...quote,status:'awaiting_studio'}}:null});
  await page.goto('/client/quotation');await page.getByRole('button',{name:/Single/}).click();
  await page.getByRole('button',{name:'Tanggapi Penawaran'}).click();
  const dialog=page.getByRole('dialog',{name:'Tanggapi Penawaran'});
  await dialog.getByLabel('Tanggapan').selectOption('counter');await dialog.getByLabel('Harga (Rp)').fill('800000');
  await dialog.getByRole('button',{name:'Simpan'}).click();
  await expect(dialog.getByText('Berhasil.',{exact:false})).toBeVisible();
  expect(calls.find(c=>c.path.endsWith('/respond')).body).toEqual({response_type:'counter',latest_offer_id:42,price:800000,note:null});
});
test('invoice payment excludes cash and redirect never marks it paid',async({page})=>{
  const invoice={id:5,invoice_number:'INV-5',status:'issued',total:1000000};
  const calls=await mockApi(page,{role:'client',custom:({path})=>path==='/client/invoices'?{data:[invoice]}:path==='/client/invoices/5'?{data:invoice}:path.endsWith('/initiate-payment')?{data:{id:6,status:'pending',payment_url:'https://example.test/pay'}}:null});
  await page.goto('/client/invoice');await page.getByRole('button',{name:/INV-5/}).click();await page.getByRole('button',{name:'Bayar Invoice'}).click();
  const dialog=page.getByRole('dialog',{name:'Bayar Invoice'});await expect(dialog.locator('option[value="cash"]')).toHaveCount(0);
  await dialog.getByLabel('Metode Pembayaran').selectOption('midtrans');await dialog.getByRole('button',{name:'Mulai Pembayaran'}).click();
  await expect(dialog.getByText('Menunggu',{exact:true})).toBeVisible();await expect(dialog.getByRole('link',{name:'Buka tautan'})).toHaveAttribute('href','https://example.test/pay');
  expect(calls.find(c=>c.path.endsWith('/initiate-payment')).body).toEqual({gateway:'midtrans'});
});
test('notification GET does not mark read and unknown payload has fallback',async({page})=>{
  const calls=await mockApi(page,{custom:({path})=>path==='/notifications'?{data:[{id:'uuid-1',type:'notification',data:[],read_at:null,created_at:'2026-10-24T10:00:00+07:00'}],meta:pagination}:null});
  await page.goto('/manager/notifications');await expect(page.getByText('Pembaruan Tungku Studio')).toBeVisible();
  expect(calls.some(c=>c.method==='PATCH')).toBe(false);
  await page.getByRole('button',{name:'Tandai Semua Dibaca'}).click();await expect.poll(()=>calls.some(c=>c.path==='/notifications/read-all'&&c.method==='PATCH')).toBe(true);
});
test('rate limit blocks retry and preserves validation error',async({page})=>{
  const calls=await mockApi(page,{loggedIn:false,custom:({path})=>path==='/auth/login'?{status:429,message:'Terlalu banyak login',headers:{'retry-after':'30','access-control-expose-headers':'Retry-After'}}:null});
  await page.goto('/login');await page.getByLabel('Email').fill('uji@example.test');await page.getByLabel('Password *',{exact:true}).fill('password1');await page.getByRole('button',{name:'Masuk',exact:true}).click();
  await expect(page.getByRole('alert')).toContainText('Terlalu banyak login');await expect(page.getByRole('button',{name:/Tunggu/})).toBeDisabled();
  expect(calls.filter(c=>c.path==='/auth/login')).toHaveLength(1);
});
test('mobile navigation and layout fit the viewport',async({page})=>{
  await page.setViewportSize({width:390,height:844});await mockApi(page,{role:'client'});await page.goto('/client/packages');
  await expect(page.getByText('Paket Single')).toBeVisible();
  await page.getByRole('button',{name:'Buka menu'}).click();await expect(page.getByRole('link',{name:'Booking Saya'})).toBeInViewport();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.getByRole('link',{name:'Booking Saya'}).click();
  await expect(page.getByRole('heading',{name:'Booking',exact:true})).toBeVisible();
  await page.screenshot({path:'test-results/client-mobile.png',fullPage:true,animations:'disabled'});
});

test('expired CSRF refreshes the cookie without replaying a payment mutation',async({page})=>{
  const payment={id:4,order_id:'PAY-4',status:'pending_manual_confirmation',provider:'cash',amount:500000};
  let expired=false;
  const calls=await mockApi(page,{custom:({path})=>{if(path.endsWith('/confirm')){expired=true;return {status:419,message:'CSRF token mismatch.'};}return path==='/auth/me'&&expired?{status:401,message:'Unauthenticated.'}:path==='/manager/payments'?{data:[payment]}:path==='/manager/payments/4'?{data:payment}:null;}});
  await page.goto('/manager/payments');await page.getByRole('button',{name:/PAY-4/}).click();await page.getByRole('button',{name:'Konfirmasi Tunai',exact:true}).click();
  const dialog=page.getByRole('dialog',{name:'Konfirmasi Tunai'});await dialog.getByLabel('Catatan').fill('Tunai diterima');await dialog.getByRole('button',{name:'Konfirmasi Diterima'}).click();
  await expect(page).toHaveURL(/\/login/);expect(calls.filter(c=>c.path.endsWith('/confirm'))).toHaveLength(1);expect(calls.some(c=>c.path==='/sanctum/csrf-cookie')).toBe(true);
});
test('package update sends complete fields and displays server field errors',async({page})=>{
  const data={...catalog,is_active:true,manual_price:null};
  const calls=await mockApi(page,{custom:({path,method})=>path==='/manager/packages/1'?method==='PUT'?{status:422,message:'Validasi gagal.',errors:{name:['Nama paket sudah digunakan.']}}:{data}:null});
  await page.goto('/manager/packages');await page.getByRole('button',{name:/Paket Single/}).click();await page.getByRole('button',{name:'Ubah',exact:true}).click();
  const dialog=page.getByRole('dialog',{name:'Ubah Paket Produksi'});await dialog.getByRole('button',{name:'Simpan',exact:true}).click();
  await expect(dialog.getByLabel('Nama Paket *')).toHaveAttribute('aria-invalid','true');await expect(dialog.getByRole('alert')).toContainText('Nama paket sudah digunakan.');
  const body=calls.find(c=>c.method==='PUT').body;expect(body).toMatchObject({name:catalog.name,track_count:1,recording_hours:2,include_recording:true,include_editing:true,include_mixing:true,include_mastering:true,is_active:true});
});
test('finalized payroll is displayed as an immutable snapshot',async({page})=>{
  await mockApi(page,{custom:({path})=>path.endsWith('/monthly-summary')?{data:{totals:{revenue:1000000,expense:100000,net_profit:900000},expenses_by_category:[]}}:path.endsWith('/payroll-allocations')?{data:{status:'finalized',studio_profit_percentage:'50.00',allocations:[{operator_id:10,operator_name:'Operator A',jobs_count:1,recommended_percentage:'50.00',percentage:'50.00',amount:450000}]}}:null});
  await page.goto('/manager/reports');await expect(page.getByText('Difinalisasi',{exact:true})).toBeVisible();await expect(page.getByRole('button',{name:'Simpan Alokasi'})).toHaveCount(0);await expect(page.getByLabel('Persentase Operator A')).toBeDisabled();await expect(page.getByText('50.00%',{exact:true})).toBeVisible();
});
test('logout accepts 204 and returns to login',async({page})=>{
  const calls=await mockApi(page);await page.goto('/manager/dashboard');await page.getByRole('button',{name:'Keluar',exact:true}).click();await expect(page).toHaveURL(/\/login/);expect(calls.find(c=>c.path==='/auth/logout').method).toBe('POST');
});
test('419 keeps an active session after rechecking it and never retries the mutation',async({page})=>{
  const payment={id:4,order_id:'PAY-4',status:'pending_manual_confirmation',provider:'cash',amount:500000};
  const calls=await mockApi(page,{custom:({path})=>path==='/manager/payments'?{data:[payment]}:path==='/manager/payments/4'?{data:payment}:path.endsWith('/confirm')?{status:419,message:'CSRF token mismatch.'}:null});
  await page.goto('/manager/payments?id=4');await page.getByRole('button',{name:'Konfirmasi Tunai',exact:true}).click();
  const dialog=page.getByRole('dialog',{name:'Konfirmasi Tunai'});await dialog.getByLabel('Catatan').fill('Tunai diterima');await dialog.getByRole('button',{name:'Konfirmasi Diterima'}).click();
  await expect(dialog.getByRole('alert')).toContainText('Cookie keamanan sudah diperbarui');await expect(dialog.getByRole('button',{name:'Konfirmasi Diterima'})).toBeDisabled();
  expect(calls.filter(c=>c.path.endsWith('/confirm'))).toHaveLength(1);expect(calls.filter(c=>c.path==='/auth/me').length).toBeGreaterThan(1);await expect(page).toHaveURL(/manager\/payments/);
});
test('final settlement uses the unpaid final payment instead of the paid PO',async({page})=>{
  const invoices=[{id:1,invoice_number:'INV-PO',status:'paid'},{id:2,invoice_number:'INV-FINAL',status:'issued'}];
  const po={...invoices[0],payment:{id:11,type:'po',status:'paid'}};
  const final={...invoices[1],payment:{id:22,type:'final',status:'pending'}};
  const calls=await mockApi(page,{role:'client',custom:({path,body})=>path==='/client/invoices'?{data:invoices}:path==='/client/invoices/1'?{data:po}:path==='/client/invoices/2'?{data:final}:path==='/client/payments/22/final/initiate'?{data:{id:22,type:'final',provider:body.provider,status:'pending_manual_confirmation'}}:null});
  await page.goto('/client/invoice?id=1');await expect(page.getByRole('dialog',{name:'Detail'})).toBeVisible();await expect(page.getByRole('button',{name:'Pelunasan',exact:true})).toHaveCount(0);
  await page.goto('/client/invoice?id=2');await page.getByRole('button',{name:'Pelunasan',exact:true}).click();
  const dialog=page.getByRole('dialog',{name:'Mulai Pelunasan'});await dialog.getByLabel('Penyedia Pembayaran').selectOption('cash');await dialog.getByRole('button',{name:'Mulai Pelunasan'}).click();
  await expect(dialog.getByText('Menunggu Konfirmasi Tunai',{exact:true})).toBeVisible();expect(calls.find(c=>c.path.endsWith('/final/initiate')).path).toBe('/client/payments/22/final/initiate');
});
test('stage notification fills the stage reference and submits its deadline',async({page})=>{
  const notification={id:'stage-uuid',data:{title:'Tahap Editing',body:'Kerjakan lagu',target:{type:'stage',id:12},metadata:{project_id:7}},read_at:null};
  const calls=await mockApi(page,{role:'operator',custom:({path})=>path==='/notifications'?{data:[notification]}:path.endsWith('/confirm-deadline')?{data:{id:12,status:'in_progress'}}:null});
  await page.goto('/operator/notifications');await page.getByRole('button',{name:/Tahap Editing/}).click();await expect(page).toHaveURL(/operator\/tasks\?.*stage=12/);
  await page.getByRole('button',{name:'Tetapkan Tenggat'}).click();const dialog=page.getByRole('dialog',{name:'Konfirmasi Tenggat'});await dialog.getByLabel('Tenggat').fill('2099-01-20T17:00');await dialog.getByRole('button',{name:'Simpan'}).click();
  await expect(dialog.getByText('Berhasil.',{exact:false})).toBeVisible();expect(calls.find(c=>c.path.endsWith('/confirm-deadline')).path).toBe('/operator/stages/12/confirm-deadline');
});
test('final delivery selects a project from invoices without typing an ID',async({page})=>{
  const calls=await mockApi(page,{role:'client',custom:({path})=>path==='/client/invoices'?{data:[{id:1,project:{id:7,name:'Album Kita'}}]}:path==='/client/projects/7/final-delivery'?{data:[{track_id:3,track_title:'Lagu Pertama',version:2,result_url:'https://example.test/private-result'}]}:null});
  await page.goto('/client/delivery');await page.getByRole('combobox',{name:'Project',exact:true}).selectOption('7');await expect(page.getByText('Lagu Pertama')).toBeVisible();await expect(page.getByRole('link',{name:'Buka tautan'})).toHaveAttribute('href','https://example.test/private-result');expect(calls.some(c=>c.path==='/client/projects/7/final-delivery')).toBe(true);
});
test('a legacy payment notification opens the exact payment status',async({page})=>{
  const calls=await mockApi(page,{role:'client',custom:({path})=>path==='/notifications'?{data:[{id:'legacy',data:{payment_id:22,order_id:'FINAL-22'},read_at:null}]}:path==='/client/payments/22'?{data:{id:22,type:'final',order_id:'FINAL-22',status:'pending'}}:null});
  await page.goto('/client/notifications');await page.getByRole('button',{name:/FINAL-22/}).click();await expect(page).toHaveURL('/client/payment?payment=22');await expect(page.getByRole('button',{name:'Pelunasan',exact:true})).toBeVisible();expect(calls.some(c=>c.path==='/client/payments/22')).toBe(true);
});
test('a stale offer is reread and requires accepting the new offer context',async({page})=>{
  let changed=false;
  const quote=()=>({id:9,name:'Single',status:'awaiting_client',latest_offer:{id:changed?43:42,price:changed?800000:900000}});
  const calls=await mockApi(page,{role:'client',custom:({path,method})=>{
    if(path==='/client/quotations')return {data:[quote()]};
    if(path==='/client/quotations/9')return {data:quote()};
    if(path.endsWith('/respond')&&method==='POST'){if(!changed){changed=true;return {status:422,message:'Penawaran sudah berubah.',errors:{latest_offer_id:['Penawaran usang.']}};}return {data:{status:'accepted'}};}
    return null;
  }});
  await page.goto('/client/quotation?id=9');await page.getByRole('button',{name:'Tanggapi Penawaran'}).click();const dialog=page.getByRole('dialog',{name:'Tanggapi Penawaran'});await dialog.getByLabel('Tanggapan').selectOption('accept');await dialog.getByRole('button',{name:'Simpan'}).click();
  await expect(dialog.getByRole('heading',{name:'Penawaran sudah berubah'})).toBeVisible();await expect(dialog.getByRole('button',{name:'Simpan'})).toBeDisabled();expect(calls.filter(c=>c.path.endsWith('/respond'))).toHaveLength(1);
  await dialog.getByRole('button',{name:'Gunakan Penawaran Terbaru'}).click();await dialog.getByRole('button',{name:'Simpan'}).click();await expect(dialog.getByText('Berhasil.',{exact:false})).toBeVisible();expect(calls.filter(c=>c.path.endsWith('/respond'))[1].body.latest_offer_id).toBe(43);
});
test('accepted quotation opens booking with its quotation reference',async({page})=>{
  const quote={id:9,name:'Single',status:'accepted',latest_offer:{id:42,price:900000}};
  await mockApi(page,{role:'client',custom:({path})=>path==='/client/quotations'?{data:[quote]}:path==='/client/quotations/9'?{data:quote}:null});
  await page.goto('/client/quotation?id=9');await page.getByRole('button',{name:'Booking Penawaran Ini'}).click();const dialog=page.getByRole('dialog',{name:'Booking Penawaran'});await expect(dialog.getByRole('combobox',{name:'Penawaran Disetujui'})).toHaveValue('9');await expect(dialog.getByRole('combobox',{name:'Paket',exact:true})).toHaveValue('');
});
test('inactive account can still end its session',async({page})=>{
  const calls=await mockApi(page,{custom:({path})=>path==='/auth/me'?{status:403,message:'Akun nonaktif.'}:null});
  await page.goto('/login');await expect(page.getByRole('alert')).toContainText('Akun nonaktif.');await page.getByRole('button',{name:'Keluar dari Session'}).click();await expect.poll(()=>calls.some(c=>c.path==='/auth/logout'&&c.method==='POST')).toBe(true);await expect(page.getByRole('button',{name:'Keluar dari Session'})).toHaveCount(0);
});


test('external management stays disconnected without a configured provider',async({page})=>{
  const calls=await mockApi(page);
  await page.goto('/manager/management');
  await expect(page.getByRole('heading',{name:'Manajemen Room / User / Project'})).toBeVisible();
  await expect(page.getByText('Layanan pengelolaan belum terhubung.',{exact:false})).toBeVisible();
  expect(calls.some(call=>/^\/manager\/(rooms|users|projects)$/.test(call.path))).toBe(false);
  await page.screenshot({path:'test-results/external-management.png',fullPage:true});
});

test('operator file picker does not pretend to upload without a provider',async({page})=>{
  await mockApi(page,{role:'operator'});
  await page.goto('/operator/tasks?progress=3&track=4');
  await page.getByRole('button',{name:'Kirim Hasil / Upload'}).click();
  await expect(page.getByRole('heading',{name:'Upload File Hasil'})).toBeVisible();
  await page.locator('input[type=file]').setInputFiles({name:'hasil.wav',mimeType:'audio/wav',buffer:Buffer.from('test audio')});
  await expect(page.getByText('hasil.wav',{exact:false})).toBeVisible();
  await expect(page.getByRole('button',{name:'Upload & Gunakan Tautan'})).toBeDisabled();
});
