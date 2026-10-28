const labels = {
  name:'Nama',email:'Email',phone:'Nomor Telepon',password:'Password',password_confirmation:'Konfirmasi Password',
  remember:'Ingat Saya',crm_consent:'Saya setuju menerima promosi melalui email',
  description:'Deskripsi',track_count:'Jumlah Lagu',recording_hours:'Jam Rekaman',include_recording:'Recording',include_editing:'Editing',include_mixing:'Mixing',include_mastering:'Mastering',
  manual_price:'Harga Manual (Rp)',is_active:'Aktif',room_id:'ID Ruangan',start_at:'Mulai',end_at:'Selesai',package_id:'ID Paket',quotation_id:'ID Penawaran',project_id:'ID Project',recording_session_id:'ID Sesi Rekaman',
  held_until:'Batas Hold',status:'Status',status_label:'Keterangan Status',created_at:'Dibuat',updated_at:'Diperbarui',
  response_type:'Tanggapan',latest_offer_id:'ID Penawaran Terakhir',price:'Harga (Rp)',offer_price:'Harga Penawaran Awal (Rp)',agreed_price:'Harga Disepakati (Rp)',auto_price:'Harga Otomatis (Rp)',note:'Catatan',notes:'Catatan',
  expense_category_id:'ID Kategori Pengeluaran',inventory_category_id:'ID Kategori Inventaris',amount:'Nominal (Rp)',spent_on:'Tanggal Pengeluaran',code:'Kode Aset',brand:'Merek',model:'Model',serial_number:'Nomor Seri',
  purchase_date:'Tanggal Pembelian',purchase_price:'Harga Beli (Rp)',residual_value:'Nilai Residu (Rp)',useful_life_months:'Masa Manfaat (Bulan)',expense_id:'ID Pengeluaran',book_value:'Nilai Buku (Rp)',book_value_as_of:'Tanggal Nilai Buku',monthly_depreciation:'Penyusutan Bulanan',
  type:'Jenis',disposed_on:'Tanggal Pelepasan',disposal_value:'Nilai Pelepasan (Rp)',
  gateway:'Metode Pembayaran',provider:'Penyedia Pembayaran',paid_at:'Diterima Pada',reason:'Alasan',payment_url:'Tautan Pembayaran',expires_at:'Kedaluwarsa',
  message:'Pesan Email',channels:'Saluran',client_ids:'ID Klien Penerima',client_id:'ID Klien',client_name:'Nama Klien',client:'Klien',
  result_url:'Tautan Hasil',deadline_at:'Tenggat',decision:'Keputusan',review_notes:'Catatan Revisi',
  year:'Tahun',month:'Bulan',studio_profit_percentage:'Porsi Studio (%)',allocations:'Alokasi Payroll',percentage:'Persentase (%)',
  key:'Pengaturan',value:'Nilai',recording:'Recording',editing:'Editing',mixing:'Mixing',mastering:'Mastering',
  project:'ID Project',stage:'ID Tahap',track:'ID Track',progress:'ID Progress Track',submission:'ID Submission',
  project_name:'Nama Project',invoice_number:'Nomor Invoice',issue_date:'Tanggal Terbit',due_date:'Jatuh Tempo',total:'Total (Rp)',
  accept:'Terima',decline:'Tolak',counter:'Tawarkan Harga Lain',approve:'Setujui',request_revision:'Minta Revisi',cash:'Tunai',
  pending_payment:'Menunggu Pembayaran',awaiting_assignment:'Menunggu Penugasan',in_progress:'Dalam Proses',awaiting_final_payment:'Menunggu Pelunasan',completed:'Selesai',cancelled:'Dibatalkan',pending:'Menunggu',pending_manual_confirmation:'Menunggu Konfirmasi Tunai',paid:'Lunas',failed:'Gagal',expired:'Kedaluwarsa',refunded:'Dikembalikan (Histori)',
  awaiting_studio:'Menunggu Studio',awaiting_client:'Menunggu Klien',accepted:'Disetujui',declined:'Ditolak',locked:'Terkunci',ready:'Siap',in_review:'Dalam Review',revision:'Revisi',approved:'Disetujui',finalized:'Difinalisasi',draft:'Draft',sold:'Dijual',disposed:'Dihapus',
};
export function humanize(value) {return labels[value] || String(value ?? '').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());}
export function fieldLabel(value) {return labels[value] || humanize(value);}
export function money(value) {return value==null?'—':new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(value));}
export function formatDate(value) {
  if(!value)return '—';
  const date=new Date(value); if(Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat('id-ID', /^\d{4}-\d{2}-\d{2}$/.test(value)?{dateStyle:'medium',timeZone:'UTC'}:{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Jakarta'}).format(date);
}
export function currentPeriod() {
  const parts=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Jakarta',year:'numeric',month:'numeric'}).formatToParts(new Date());
  return {year:Number(parts.find(p=>p.type==='year').value),month:Number(parts.find(p=>p.type==='month').value)};
}
function invalid(message,field) {throw Object.assign(new Error(message),{status:422,errors:field?{[field]:[message]}:{}});}
export function buildBody(fields,values) {
  const body={};
  for(const {name,rules,required} of fields) {
    if(name==='price' && values.response_type && values.response_type!=='counter') continue;
    if(name==='review_notes' && values.decision && values.decision!=='request_revision') continue;
    let value=values[name];
    if(name==='channels')value=['email'];
    if(rules==='boolean') {body[name]=Boolean(value);continue;}
    if(value==='' || value==null) {
      if(required) invalid(`${fieldLabel(name)} wajib diisi.`,name);
      // Nullable fields must be explicit to clear an existing value on update.
      if(rules.includes('null'))body[name]=null;
      continue;
    }
    if(rules.startsWith('integer') || rules.startsWith('number')) {
      const number=Number(value);
      if(!Number.isFinite(number) || rules.startsWith('integer') && !Number.isInteger(number)) invalid(`${fieldLabel(name)} harus berupa angka yang valid.`,name);
      const min=rules.match(/minimum=([\d.]+)/)?.[1],max=rules.match(/maximum=([\d.]+)/)?.[1];
      if(min!=null && number<Number(min) || max!=null && number>Number(max)) invalid(`${fieldLabel(name)} di luar batas yang diizinkan.`,name);
      // Payroll percentages are decimal values, not currency.
      value=name.includes('percentage')?String(value):number;
    } else if(rules.includes('date-time')) {
      value=/[zZ]$|[+-]\d{2}:\d{2}$/.test(value)?value:`${value.length===16?value+':00':value}+07:00`;
      if(Number.isNaN(new Date(value).getTime()))invalid('Tanggal dan waktu tidak valid.',name);
    } else if(rules.startsWith('array')) {
      value=Array.isArray(value)?value:String(value).split(',').map(v=>v.trim()).filter(Boolean);
      if(rules.includes('integer')) {
        if(value.some(v=>!/^\d+$/.test(String(v)) || Number(v)<1)) invalid('ID penerima harus berupa angka positif.',name);
        value=[...new Set(value.map(Number))];
      }
      const min=rules.match(/minItems=(\d+)/)?.[1],max=rules.match(/maxItems=(\d+)/)?.[1];
      if(min && value.length<Number(min) || max && value.length>Number(max))invalid('Jumlah pilihan di luar batas yang diizinkan.',name);
    }
    body[name]=value;
  }
  return body;
}
export function validateBusiness(path,body) {
  if(body.start_at || body.end_at) {
    const start=new Date(body.start_at),end=new Date(body.end_at),hours=(end-start)/3600000;
    if(!Number.isFinite(hours) || hours<1 || hours>12)invalid('Durasi booking harus 1–12 jam.','end_at');
    if(start<=new Date())invalid('Waktu mulai harus berada di masa depan.','start_at');
  }
  if(path.endsWith('/hold') && Boolean(body.package_id)===Boolean(body.quotation_id))invalid('Pilih tepat satu paket atau penawaran.','package_id');
  if(body.response_type==='counter' && (body.price==null || body.price<0))invalid('Harga penawaran balik wajib diisi.','price');
  if(body.decision==='request_revision' && (String(body.review_notes || '').trim().length<10 || body.review_notes.length>5000))invalid('Catatan revisi wajib 10–5000 karakter.','review_notes');
  if(body.deadline_at && new Date(body.deadline_at)<=new Date())invalid('Tenggat harus berada di masa depan.','deadline_at');
  if(body.paid_at && new Date(body.paid_at)>new Date())invalid('Waktu penerimaan tidak boleh di masa depan.','paid_at');
  if(body.assignments) {
    const ids=Object.values(body.assignments);
    if(!ids.length)invalid('Isi operator untuk seluruh tahap aktif project.');
    if(ids.some(v=>!Number.isInteger(v)||v<1))invalid('ID operator harus berupa angka positif.');
    if(new Set(ids).size!==ids.length)invalid('Operator setiap tahap harus berbeda.');
  }
  if(path.endsWith('/payroll-finalization')) {
    const current=currentPeriod();
    if(body.year>current.year || body.year===current.year && body.month>=current.month)invalid('Finalisasi hanya untuk periode yang sudah berakhir.');
  }
  if(path.endsWith('/payroll-allocations') && body.allocations) {
    const values=[...(Object.hasOwn(body,'studio_profit_percentage')?[body.studio_profit_percentage]:[]),...body.allocations.map(row=>row.percentage)];
    if(values.some(value=>value==='' || !/^\d{1,3}(\.\d{1,2})?$/.test(String(value)) || Number(value)>100))invalid('Isi persentase 0–100 dengan maksimal dua angka desimal.');
  }
}
