import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {buildBody,validateBusiness,currentPeriod,formatDate} from '../src/lib/contract.js';
import {notificationRoute,workflowContext,projectChoices,positiveId} from '../src/lib/workflow.js';
const schemas=JSON.parse(readFileSync(new URL('../src/lib/schemas.json',import.meta.url)));
test('all documented request schemas are present',()=>{
  assert.equal(Object.keys(schemas).length,25);
  assert.equal(schemas.RegisterRequest.length,6);
  assert.equal(schemas.PackageRequest.length,10);
});
test('register always includes explicit false marketing consent',()=>{
  const body=buildBody(schemas.RegisterRequest,{name:'Klien',email:'klien@example.test',phone:'081234567890',password:'password1',password_confirmation:'password1',crm_consent:false});
  assert.equal(body.crm_consent,false);
});
test('WIB input is independent of the browser timezone',()=>{
  const body=buildBody(schemas.CheckAvailabilityRequest,{room_id:'1',start_at:'2027-01-20T10:00',end_at:'2027-01-20T12:00'});
  assert.equal(body.start_at,'2027-01-20T10:00:00+07:00');
  assert.equal(body.room_id,1);
  assert.equal(new Date(body.start_at).toISOString(),'2027-01-20T03:00:00.000Z');
});
test('hold rejects both and neither package and quotation',()=>{
  assert.throws(()=>validateBusiness('/client/bookings/hold',{}),/tepat satu/);
  assert.throws(()=>validateBusiness('/client/bookings/hold',{package_id:1,quotation_id:2}),/tepat satu/);
  assert.doesNotThrow(()=>validateBusiness('/client/bookings/hold',{package_id:1}));
});
test('booking rejects past starts and duration outside 1–12 hours',()=>{
  assert.throws(()=>validateBusiness('/client/bookings/hold',{start_at:'2020-01-01T10:00:00+07:00',end_at:'2020-01-01T12:00:00+07:00',package_id:1}),/masa depan/);
  assert.throws(()=>validateBusiness('/client/bookings/check-availability',{start_at:'2099-01-01T10:00:00+07:00',end_at:'2099-01-02T12:00:00+07:00'}),/1–12/);
});
test('quotation counter needs price; accept omits stale counter price',()=>{
  assert.throws(()=>validateBusiness('/client/quotations/1/respond',{response_type:'counter'}),/wajib/);
  const body=buildBody(schemas.RespondToQuotationRequest,{response_type:'accept',latest_offer_id:4,price:999});
  assert.deepEqual(body,{response_type:'accept',latest_offer_id:4,note:null});
});
test('revision needs meaningful notes and approval omits revision text',()=>{
  assert.throws(()=>validateBusiness('/manager/track-progress/1/submissions/2/review',{decision:'request_revision',review_notes:'abc'}),/10–5000/);
  assert.deepEqual(buildBody(schemas.ReviewTrackResultRequest,{decision:'approve',review_notes:'Old revision'}),{decision:'approve'});
});
test('campaign accepts email only and integer recipient IDs',()=>{
  const body=buildBody(schemas.CreateCRMCampaignRequest,{name:'Campaign',message:'Hello',channels:['whatsapp'],client_ids:'1,2,2'});
  assert.deepEqual(body.channels,['email']);assert.deepEqual(body.client_ids,[1,2]);
  assert.throws(()=>buildBody(schemas.CreateCRMCampaignRequest,{name:'Campaign',message:'Hello',client_ids:'a'}),/angka positif/);
});
test('assignments reject shared operators',()=>{
  assert.throws(()=>validateBusiness('/manager/projects/1/assignments',{assignments:{recording:1,mixing:1}}),/berbeda/);
});
test('payroll cannot finalize a current or future period',()=>{
  assert.throws(()=>validateBusiness('/manager/finance/payroll-finalization',currentPeriod()),/sudah berakhir/);
  assert.throws(()=>validateBusiness('/manager/finance/payroll-finalization',{year:9999,month:1}),/sudah berakhir/);
});
test('decimal payroll values stay decimals, not rupiah',()=>{
  const body=buildBody([{name:'studio_profit_percentage',rules:'number; minimum=0; maximum=100'}],{studio_profit_percentage:'25.50'});
  assert.equal(body.studio_profit_percentage,'25.50');
  assert.throws(()=>validateBusiness('/manager/finance/payroll-allocations',{studio_profit_percentage:'10',allocations:[{operator_id:1,percentage:'100.01'}]}),/0–100/);
});
test('calendar dates do not shift day and UTC timestamps are parsed once',()=>{
  assert.match(formatDate('2027-01-20'),/20/);
  assert.match(formatDate('2027-01-20T03:00:00Z'),/10[.:]00/);
});
test('notification targets retain resource context without accepting server URLs',()=>{
  const path=notificationRoute('operator',{data:{target:{type:'stage',id:12},metadata:{project_id:7,track_id:4,track_stage_progress_id:9}}});
  assert.equal(path.split('?')[0],'/operator/tasks');
  assert.deepEqual(workflowContext(path.split('?')[1]),{project:'7',stage:'12',track:'4',progress:'9',submission:'',payment:'',invoice:'',id:''});
  assert.equal(notificationRoute('client',{data:{target:{type:'https://evil.example',id:1}}}),null);
  assert.equal(notificationRoute('operator',{data:{target:{type:'payment',id:1}}}),null);
  assert.equal(notificationRoute('client',{data:[]}),null);
});
test('legacy payment notifications open the actual payment',()=>{
  assert.equal(notificationRoute('client',{data:{payment_id:55}}),'/client/payment?payment=55');
  assert.equal(notificationRoute('manager',{data:{payment_id:55}}),'/manager/payments?id=55');
});
test('only positive safe integer references enter local routes',()=>{
  for(const value of ['https://evil.example',0,-1,'1/../../users','1e3',Number.MAX_SAFE_INTEGER+1])assert.equal(positiveId(value),'');
  assert.equal(positiveId(17),'17');
});
test('project selection uses only present invoice relationships',()=>{
  assert.deepEqual(projectChoices([{id:1},{id:2,project:null},{project:{id:7,name:'Album'}},{project:{id:7,name:'Album'}}]),[{id:'7',name:'Album'}]);
});
test('studio percentage may be omitted from payroll requests',()=>{
  assert.doesNotThrow(()=>validateBusiness('/manager/finance/payroll-allocations',{allocations:[{operator_id:10,percentage:'50.00'}]}));
});
