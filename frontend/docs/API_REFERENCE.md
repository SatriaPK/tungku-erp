# Referensi endpoint ERP Tungku

Referensi endpoint, request, dan response API Laravel. Panduan instalasi dan deployment tersedia di [README](../../README.md).

Prefix semua path di bawah: `/api`. `PUT` dan `PATCH` resource memakai validasi update yang sama; PATCH belum tentu menerima body parsial. Field optional bisa tidak hadir; nullable berarti nilainya boleh null. Semua contoh dan schema bersifat struktural, bukan data akun nyata.

| Method | Path | Operasi |
| --- | --- | --- |
| GET | `/user` | [get.user](#getuser) |
| POST | `/v1/auth/register` | [auth.register](#authregister) |
| POST | `/v1/auth/login` | [auth.login](#authlogin) |
| POST | `/v1/auth/logout` | [auth.logout](#authlogout) |
| GET | `/v1/auth/me` | [auth.me](#authme) |
| DELETE | `/v1/auth/crm-consent` | [auth.crm-consent.destroy](#authcrm-consentdestroy) |
| GET | `/v1/manager/base-prices` | [basePrice.index](#basepriceindex) |
| PUT | `/v1/manager/base-prices` | [basePrice.update](#basepriceupdate) |
| GET | `/v1/manager/bookings` | [v1.manager.booking.index](#v1managerbookingindex) |
| GET | `/v1/manager/bookings/{id}` | [v1.manager.booking.show](#v1managerbookingshow) |
| DELETE | `/v1/manager/bookings/{id}` | [booking.destroy](#bookingdestroy) |
| GET | `/v1/client/bookings` | [v1.client.booking.index](#v1clientbookingindex) |
| GET | `/v1/client/bookings/{id}` | [v1.client.booking.show](#v1clientbookingshow) |
| DELETE | `/v1/client/bookings/{id}` | [booking.cancelBooking](#bookingcancelbooking) |
| POST | `/v1/client/bookings/check-availability` | [booking.checkAvailability](#bookingcheckavailability) |
| POST | `/v1/client/bookings/hold` | [booking.holdSlot](#bookingholdslot) |
| POST | `/v1/client/bookings/confirm` | [booking.confirmSlot](#bookingconfirmslot) |
| GET | `/v1/manager/crm/campaigns` | [cRMCampaign.index](#crmcampaignindex) |
| POST | `/v1/manager/crm/campaigns` | [cRMCampaign.store](#crmcampaignstore) |
| GET | `/v1/manager/crm/campaigns/{campaign}` | [cRMCampaign.show](#crmcampaignshow) |
| POST | `/v1/manager/crm/campaigns/{campaign}/dispatch` | [cRMCampaign.dispatch](#crmcampaigndispatch) |
| GET | `/v1/manager/crm/recommendations` | [clientRecommendation.index](#clientrecommendationindex) |
| GET | `/v1/manager/dashboard` | [dashboard.show](#dashboardshow) |
| GET | `/v1/manager/dashboard/operator-activities` | [dashboard.activities](#dashboardactivities) |
| GET | `/v1/manager/expenses` | [expenses.index](#expensesindex) |
| POST | `/v1/manager/expenses` | [expenses.store](#expensesstore) |
| GET | `/v1/manager/expenses/{expense}` | [expenses.show](#expensesshow) |
| PUT | `/v1/manager/expenses/{expense}` | [expenses.update](#expensesupdate) |
| PATCH | `/v1/manager/expenses/{expense}` | [expenses.update.patch](#expensesupdatepatch) |
| DELETE | `/v1/manager/expenses/{expense}` | [expenses.destroy](#expensesdestroy) |
| GET | `/v1/manager/expense-categories` | [expense-categories.index](#expense-categoriesindex) |
| POST | `/v1/manager/expense-categories` | [expense-categories.store](#expense-categoriesstore) |
| GET | `/v1/manager/expense-categories/{expense_category}` | [expense-categories.show](#expense-categoriesshow) |
| PUT | `/v1/manager/expense-categories/{expense_category}` | [expense-categories.update](#expense-categoriesupdate) |
| PATCH | `/v1/manager/expense-categories/{expense_category}` | [expense-categories.update.patch](#expense-categoriesupdatepatch) |
| DELETE | `/v1/manager/expense-categories/{expense_category}` | [expense-categories.destroy](#expense-categoriesdestroy) |
| GET | `/v1/client/projects/{project}/final-delivery` | [finalDelivery.show](#finaldeliveryshow) |
| GET | `/v1/manager/inventories` | [inventories.index](#inventoriesindex) |
| POST | `/v1/manager/inventories` | [inventories.store](#inventoriesstore) |
| GET | `/v1/manager/inventories/{inventory}` | [inventories.show](#inventoriesshow) |
| PUT | `/v1/manager/inventories/{inventory}` | [inventories.update](#inventoriesupdate) |
| PATCH | `/v1/manager/inventories/{inventory}` | [inventories.update.patch](#inventoriesupdatepatch) |
| DELETE | `/v1/manager/inventories/{inventory}` | [inventories.destroy](#inventoriesdestroy) |
| POST | `/v1/manager/inventories/{inventory}/dispose` | [inventory.dispose](#inventorydispose) |
| GET | `/v1/manager/inventory-categories` | [inventory-categories.index](#inventory-categoriesindex) |
| POST | `/v1/manager/inventory-categories` | [inventory-categories.store](#inventory-categoriesstore) |
| GET | `/v1/manager/inventory-categories/{inventory_category}` | [inventory-categories.show](#inventory-categoriesshow) |
| PUT | `/v1/manager/inventory-categories/{inventory_category}` | [inventory-categories.update](#inventory-categoriesupdate) |
| PATCH | `/v1/manager/inventory-categories/{inventory_category}` | [inventory-categories.update.patch](#inventory-categoriesupdatepatch) |
| DELETE | `/v1/manager/inventory-categories/{inventory_category}` | [inventory-categories.destroy](#inventory-categoriesdestroy) |
| GET | `/v1/client/invoices` | [invoice.index](#invoiceindex) |
| GET | `/v1/client/invoices/{invoice}` | [invoice.show](#invoiceshow) |
| GET | `/v1/manager/finance/monthly-summary` | [monthlyFinanceSummary.show](#monthlyfinancesummaryshow) |
| POST | `/v1/manager/finance/payroll-finalization` | [monthlyPayrollFinalization.store](#monthlypayrollfinalizationstore) |
| GET | `/v1/notifications` | [notifications.index](#notificationsindex) |
| GET | `/v1/notifications/unread-count` | [notifications.unread-count](#notificationsunread-count) |
| PATCH | `/v1/notifications/read-all` | [notifications.read-all](#notificationsread-all) |
| PATCH | `/v1/notifications/{notification}/read` | [notifications.read](#notificationsread) |
| POST | `/v1/manager/packages/calculate-price` | [package.calculatePrice](#packagecalculateprice) |
| GET | `/v1/manager/packages` | [packages.index](#packagesindex) |
| POST | `/v1/manager/packages` | [packages.store](#packagesstore) |
| GET | `/v1/manager/packages/{package}` | [packages.show](#packagesshow) |
| PUT | `/v1/manager/packages/{package}` | [packages.update](#packagesupdate) |
| PATCH | `/v1/manager/packages/{package}` | [packages.update.patch](#packagesupdatepatch) |
| DELETE | `/v1/manager/packages/{package}` | [packages.destroy](#packagesdestroy) |
| GET | `/v1/client/packages` | [packageCatalog.index](#packagecatalogindex) |
| GET | `/v1/manager/payments` | [payment.index](#paymentindex) |
| GET | `/v1/manager/payments/{payment}` | [v1.manager.payment.show](#v1managerpaymentshow) |
| POST | `/v1/manager/payments/{payment}/confirm` | [payment.confirm](#paymentconfirm) |
| POST | `/v1/manager/payments/{payment}/refund` | [payment.refund](#paymentrefund) |
| POST | `/v1/manager/payments/{payment}/cancel` | [payment.cancel](#paymentcancel) |
| POST | `/v1/client/invoices/{invoice}/initiate-payment` | [payment.initiatePayment](#paymentinitiatepayment) |
| GET | `/v1/client/payments/{payment}` | [v1.client.payment.show](#v1clientpaymentshow) |
| POST | `/v1/client/payments/{payment}/final/initiate` | [payment.initiateFinalPayment](#paymentinitiatefinalpayment) |
| GET | `/v1/manager/finance/payroll-allocations` | [payrollAllocation.show](#payrollallocationshow) |
| PUT | `/v1/manager/finance/payroll-allocations` | [payrollAllocation.update](#payrollallocationupdate) |
| POST | `/v1/manager/projects/{project}/assignments` | [projectAssignment.store](#projectassignmentstore) |
| GET | `/v1/manager/quotations` | [v1.manager.quotation.index](#v1managerquotationindex) |
| GET | `/v1/manager/quotations/{quotation}` | [v1.manager.quotation.show](#v1managerquotationshow) |
| POST | `/v1/manager/quotations/{quotation}/respond` | [v1.manager.quotation.respond](#v1managerquotationrespond) |
| GET | `/v1/client/quotations` | [v1.client.quotation.index](#v1clientquotationindex) |
| POST | `/v1/client/quotations` | [quotation.store](#quotationstore) |
| GET | `/v1/client/quotations/{quotation}` | [v1.client.quotation.show](#v1clientquotationshow) |
| POST | `/v1/client/quotations/{quotation}/respond` | [v1.client.quotation.respond](#v1clientquotationrespond) |
| POST | `/v1/operator/stages/{stage}/recording-extensions` | [recordingExtension.store](#recordingextensionstore) |
| POST | `/v1/webhooks/resend` | [v1.resendWebhook](#v1resendwebhook) |
| POST | `/v1/operator/stages/{stage}/confirm-deadline` | [session.confirmDeadline](#sessionconfirmdeadline) |
| POST | `/v1/operator/track-progress/{progress}/tracks/{track}/submissions` | [session.submitTrackResult](#sessionsubmittrackresult) |
| GET | `/v1/operator/sessions` | [session.index](#sessionindex) |
| GET | `/v1/operator/sessions/{id}` | [session.show](#sessionshow) |
| GET | `/v1/manager/settings` | [studioSetting.index](#studiosettingindex) |
| PUT | `/v1/manager/settings/{key}` | [studioSetting.update](#studiosettingupdate) |
| POST | `/v1/manager/track-progress/{progress}/submissions/{submission}/review` | [trackStageReview.store](#trackstagereviewstore) |
| GET | `/v1/manager/projects/{project}/stages/{stage}/tracks/{track}/history` | [v1.manager.trackStageSubmissionHistory.index](#v1managertrackstagesubmissionhistoryindex) |
| GET | `/v1/operator/track-progress/{progress}/submissions` | [v1.operator.trackStageSubmissionHistory.index](#v1operatortrackstagesubmissionhistoryindex) |
| POST | `/v1/webhooks/payment/{gateway}` | [webhook.handlePaymentWebhook](#webhookhandlepaymentwebhook) |

<a id="getuser"></a>

## get.user

`GET /api/user`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | [User](#user) `User` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="authregister"></a>

## auth.register

`POST /api/v1/auth/register`



Autentikasi: tanpa session pengguna; webhook tetap memerlukan verifikasi provider.

Body `application/json`: [RegisterRequest](#registerrequest)

| Status | Response |
| --- | --- |
| 201 | { data: [UserResource](#userresource) } `UserResource` |
| 422 | { message: string; errors: object } Validation error |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="authlogin"></a>

## auth.login

`POST /api/v1/auth/login`



Autentikasi: tanpa session pengguna; webhook tetap memerlukan verifikasi provider.

Body `application/json`: [LoginRequest](#loginrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [UserResource](#userresource) } `UserResource` |
| 422 | { message: string; errors: object } Validation error |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="authlogout"></a>

## auth.logout

`POST /api/v1/auth/logout`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 204 | any No content |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="authme"></a>

## auth.me

`GET /api/v1/auth/me`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: [UserResource](#userresource) } `UserResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="authcrm-consentdestroy"></a>

## auth.crm-consent.destroy

`DELETE /api/v1/auth/crm-consent`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 204 | any No content |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="basepriceindex"></a>

## basePrice.index

`GET /api/v1/manager/base-prices`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[BasePriceResource](#basepriceresource)&gt; } Array of `BasePriceResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="basepriceupdate"></a>

## basePrice.update

`PUT /api/v1/manager/base-prices`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [UpdateBasePricesRequest](#updatebasepricesrequest)

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[BasePriceResource](#basepriceresource)&gt; } Array of `BasePriceResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="v1managerbookingindex"></a>

## v1.manager.booking.index

`GET /api/v1/manager/bookings`

List all bookings (manager view)

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[RecordingSessionListResource](#recordingsessionlistresource)&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `RecordingSessionListResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="v1managerbookingshow"></a>

## v1.manager.booking.show

`GET /api/v1/manager/bookings/{id}`

View a single booking detail

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `id` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [RecordingSessionResource](#recordingsessionresource) } `RecordingSessionResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="bookingdestroy"></a>

## booking.destroy

`DELETE /api/v1/manager/bookings/{id}`

Cancel any booking (manager privilege)

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `id` | path | ya | integer |

| Status | Response |
| --- | --- |
| 422 | { message: string; errors: { id: array; minItems=1; maxItems=1 } }  |
| 200 | { data: [RecordingSessionResource](#recordingsessionresource) } `RecordingSessionResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="v1clientbookingindex"></a>

## v1.client.booking.index

`GET /api/v1/client/bookings`

List client's own bookings

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[RecordingSessionListResource](#recordingsessionlistresource) / object&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `RecordingSessionListResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="v1clientbookingshow"></a>

## v1.client.booking.show

`GET /api/v1/client/bookings/{id}`

View a single booking detail

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `id` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [RecordingSessionResource](#recordingsessionresource) } `RecordingSessionResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="bookingcancelbooking"></a>

## booking.cancelBooking

`DELETE /api/v1/client/bookings/{id}`

Cancel a client's held or confirmed booking

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `id` | path | ya | integer |
| `reason` | query | tidak | string / null; maxLength=1000 |

| Status | Response |
| --- | --- |
| 422 | { message: string; errors: object } Validation error |
| 200 | { data: [RecordingSessionResource](#recordingsessionresource) } `RecordingSessionResource` |
| 404 | { message: string }  |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="bookingcheckavailability"></a>

## booking.checkAvailability

`POST /api/v1/client/bookings/check-availability`

Check if a time slot is available for booking

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [CheckAvailabilityRequest](#checkavailabilityrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [AvailabilityResource](#availabilityresource) } `AvailabilityResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="bookingholdslot"></a>

## booking.holdSlot

`POST /api/v1/client/bookings/hold`

Hold a time slot for checkout (temporary reservation)

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [HoldSlotRequest](#holdslotrequest)

| Status | Response |
| --- | --- |
| 422 | { message: string; errors: object } Validation error |
| 201 | { data: [RecordingSessionResource](#recordingsessionresource) } `RecordingSessionResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="bookingconfirmslot"></a>

## booking.confirmSlot

`POST /api/v1/client/bookings/confirm`

Confirm a held slot after payment

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [ConfirmSlotRequest](#confirmslotrequest)

| Status | Response |
| --- | --- |
| 422 | { message: string; errors: object } Validation error |
| 200 | { data: [RecordingSessionResource](#recordingsessionresource) } `RecordingSessionResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="crmcampaignindex"></a>

## cRMCampaign.index

`GET /api/v1/manager/crm/campaigns`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `per_page` | query | tidak | integer; minimum=1; maximum=100 |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[CRMCampaignResource](#crmcampaignresource)&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `CRMCampaignResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="crmcampaignstore"></a>

## cRMCampaign.store

`POST /api/v1/manager/crm/campaigns`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [CreateCRMCampaignRequest](#createcrmcampaignrequest)

| Status | Response |
| --- | --- |
| 201 | { data: [CRMCampaignResource](#crmcampaignresource) } `CRMCampaignResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="crmcampaignshow"></a>

## cRMCampaign.show

`GET /api/v1/manager/crm/campaigns/{campaign}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `campaign` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [CRMCampaignResource](#crmcampaignresource) } `CRMCampaignResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="crmcampaigndispatch"></a>

## cRMCampaign.dispatch

`POST /api/v1/manager/crm/campaigns/{campaign}/dispatch`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `campaign` | path | ya | integer |

| Status | Response |
| --- | --- |
| 202 | { data: { id: integer; status: string } }  |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="clientrecommendationindex"></a>

## clientRecommendation.index

`GET /api/v1/manager/crm/recommendations`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `per_page` | query | tidak | integer; minimum=1; maximum=100 |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[ClientRecommendationResource](#clientrecommendationresource)&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `ClientRecommendationResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="dashboardshow"></a>

## dashboard.show

`GET /api/v1/manager/dashboard`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: [DashboardResource](#dashboardresource) } `DashboardResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="dashboardactivities"></a>

## dashboard.activities

`GET /api/v1/manager/dashboard/operator-activities`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: [OperatorActivityFeedResource](#operatoractivityfeedresource) } `OperatorActivityFeedResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="expensesindex"></a>

## expenses.index

`GET /api/v1/manager/expenses`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `expense_category_id` | query | tidak | integer |
| `date_from` | query | tidak | string; format="date" |
| `date_to` | query | tidak | string; format="date" |
| `per_page` | query | tidak | integer; minimum=1; maximum=100 |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[ExpenseResource](#expenseresource) / object&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `ExpenseResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expensesstore"></a>

## expenses.store

`POST /api/v1/manager/expenses`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [ExpenseRequest](#expenserequest)

| Status | Response |
| --- | --- |
| 201 | { data: [ExpenseResource](#expenseresource) / object } `ExpenseResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expensesshow"></a>

## expenses.show

`GET /api/v1/manager/expenses/{expense}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `expense` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [ExpenseResource](#expenseresource) / object } `ExpenseResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expensesupdate"></a>

## expenses.update

`PUT /api/v1/manager/expenses/{expense}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `expense` | path | ya | integer |

Body `application/json`: [ExpenseRequest](#expenserequest)

| Status | Response |
| --- | --- |
| 200 | { data: [ExpenseResource](#expenseresource) / object } `ExpenseResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expensesupdatepatch"></a>

## expenses.update.patch

`PATCH /api/v1/manager/expenses/{expense}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `expense` | path | ya | integer |

Body `application/json`: [ExpenseRequest](#expenserequest)

| Status | Response |
| --- | --- |
| 200 | { data: [ExpenseResource](#expenseresource) / object } `ExpenseResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expensesdestroy"></a>

## expenses.destroy

`DELETE /api/v1/manager/expenses/{expense}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `expense` | path | ya | integer |

| Status | Response |
| --- | --- |
| 204 | any No content |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expense-categoriesindex"></a>

## expense-categories.index

`GET /api/v1/manager/expense-categories`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `active` | query | tidak | boolean |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[ExpenseCategoryResource](#expensecategoryresource)&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `ExpenseCategoryResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expense-categoriesstore"></a>

## expense-categories.store

`POST /api/v1/manager/expense-categories`

Store a newly created resource in storage

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [ExpenseCategoryRequest](#expensecategoryrequest)

| Status | Response |
| --- | --- |
| 201 | { data: [ExpenseCategoryResource](#expensecategoryresource) } `ExpenseCategoryResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expense-categoriesshow"></a>

## expense-categories.show

`GET /api/v1/manager/expense-categories/{expense_category}`

Display the specified resource

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `expense_category` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [ExpenseCategoryResource](#expensecategoryresource) } `ExpenseCategoryResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expense-categoriesupdate"></a>

## expense-categories.update

`PUT /api/v1/manager/expense-categories/{expense_category}`

Update the specified resource in storage

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `expense_category` | path | ya | integer |

Body `application/json`: [ExpenseCategoryRequest](#expensecategoryrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [ExpenseCategoryResource](#expensecategoryresource) } `ExpenseCategoryResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expense-categoriesupdatepatch"></a>

## expense-categories.update.patch

`PATCH /api/v1/manager/expense-categories/{expense_category}`

Update the specified resource in storage

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `expense_category` | path | ya | integer |

Body `application/json`: [ExpenseCategoryRequest](#expensecategoryrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [ExpenseCategoryResource](#expensecategoryresource) } `ExpenseCategoryResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="expense-categoriesdestroy"></a>

## expense-categories.destroy

`DELETE /api/v1/manager/expense-categories/{expense_category}`

Remove the specified resource from storage

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `expense_category` | path | ya | integer |

| Status | Response |
| --- | --- |
| 204 | any No content |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="finaldeliveryshow"></a>

## finalDelivery.show

`GET /api/v1/client/projects/{project}/final-delivery`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `project` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[FinalDeliveryResource](#finaldeliveryresource)&gt; } Array of `FinalDeliveryResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventoriesindex"></a>

## inventories.index

`GET /api/v1/manager/inventories`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `status` | query | tidak | string; enum=["active","in_repair","sold","disposed"] |
| `inventory_category_id` | query | tidak | integer |
| `room_id` | query | tidak | integer |
| `search` | query | tidak | string; maxLength=100 |
| `per_page` | query | tidak | integer; minimum=1; maximum=100 |
| `include_deleted` | query | tidak | boolean |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[InventoryResource](#inventoryresource) / object&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `InventoryResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventoriesstore"></a>

## inventories.store

`POST /api/v1/manager/inventories`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [InventoryRequest](#inventoryrequest)

| Status | Response |
| --- | --- |
| 201 | { data: [InventoryResource](#inventoryresource) / object } `InventoryResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventoriesshow"></a>

## inventories.show

`GET /api/v1/manager/inventories/{inventory}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `inventory` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [InventoryResource](#inventoryresource) / object } `InventoryResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventoriesupdate"></a>

## inventories.update

`PUT /api/v1/manager/inventories/{inventory}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `inventory` | path | ya | integer |

Body `application/json`: [InventoryRequest](#inventoryrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [InventoryResource](#inventoryresource) / object } `InventoryResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventoriesupdatepatch"></a>

## inventories.update.patch

`PATCH /api/v1/manager/inventories/{inventory}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `inventory` | path | ya | integer |

Body `application/json`: [InventoryRequest](#inventoryrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [InventoryResource](#inventoryresource) / object } `InventoryResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventoriesdestroy"></a>

## inventories.destroy

`DELETE /api/v1/manager/inventories/{inventory}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `inventory` | path | ya | integer |

| Status | Response |
| --- | --- |
| 204 | any No content |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventorydispose"></a>

## inventory.dispose

`POST /api/v1/manager/inventories/{inventory}/dispose`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `inventory` | path | ya | integer |

Body `application/json`: [DisposeInventoryRequest](#disposeinventoryrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [InventoryResource](#inventoryresource) / object } `InventoryResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventory-categoriesindex"></a>

## inventory-categories.index

`GET /api/v1/manager/inventory-categories`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `active` | query | tidak | boolean |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[InventoryCategoryResource](#inventorycategoryresource)&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `InventoryCategoryResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventory-categoriesstore"></a>

## inventory-categories.store

`POST /api/v1/manager/inventory-categories`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [InventoryCategoryRequest](#inventorycategoryrequest)

| Status | Response |
| --- | --- |
| 201 | { data: [InventoryCategoryResource](#inventorycategoryresource) } `InventoryCategoryResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventory-categoriesshow"></a>

## inventory-categories.show

`GET /api/v1/manager/inventory-categories/{inventory_category}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `inventory_category` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [InventoryCategoryResource](#inventorycategoryresource) } `InventoryCategoryResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventory-categoriesupdate"></a>

## inventory-categories.update

`PUT /api/v1/manager/inventory-categories/{inventory_category}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `inventory_category` | path | ya | integer |

Body `application/json`: [InventoryCategoryRequest](#inventorycategoryrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [InventoryCategoryResource](#inventorycategoryresource) } `InventoryCategoryResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventory-categoriesupdatepatch"></a>

## inventory-categories.update.patch

`PATCH /api/v1/manager/inventory-categories/{inventory_category}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `inventory_category` | path | ya | integer |

Body `application/json`: [InventoryCategoryRequest](#inventorycategoryrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [InventoryCategoryResource](#inventorycategoryresource) } `InventoryCategoryResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="inventory-categoriesdestroy"></a>

## inventory-categories.destroy

`DELETE /api/v1/manager/inventory-categories/{inventory_category}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `inventory_category` | path | ya | integer |

| Status | Response |
| --- | --- |
| 204 | any No content |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="invoiceindex"></a>

## invoice.index

`GET /api/v1/client/invoices`

List client's own invoices

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[InvoiceListResource](#invoicelistresource) / object&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `InvoiceListResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="invoiceshow"></a>

## invoice.show

`GET /api/v1/client/invoices/{invoice}`

Show a single invoice detail

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `invoice` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [InvoiceResource](#invoiceresource) / object } `InvoiceResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="monthlyfinancesummaryshow"></a>

## monthlyFinanceSummary.show

`GET /api/v1/manager/finance/monthly-summary`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `year` | query | ya | integer; minimum=1; maximum=9999 |
| `month` | query | ya | integer; minimum=1; maximum=12 |

| Status | Response |
| --- | --- |
| 200 | { data: [MonthlyFinanceSummaryResource](#monthlyfinancesummaryresource) } `MonthlyFinanceSummaryResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="monthlypayrollfinalizationstore"></a>

## monthlyPayrollFinalization.store

`POST /api/v1/manager/finance/payroll-finalization`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [FinalizeMonthlyPayrollRequest](#finalizemonthlypayrollrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [PayrollAllocationResource](#payrollallocationresource) } `PayrollAllocationResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="notificationsindex"></a>

## notifications.index

`GET /api/v1/notifications`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `unread` | query | tidak | boolean |
| `per_page` | query | tidak | integer; minimum=1; maximum=100 |
| `page` | query | tidak | integer; minimum=1 |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[NotificationResource](#notificationresource)&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `NotificationResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="notificationsunread-count"></a>

## notifications.unread-count

`GET /api/v1/notifications/unread-count`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: { unread_count: integer; minimum=0 } }  |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="notificationsread-all"></a>

## notifications.read-all

`PATCH /api/v1/notifications/read-all`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: { updated_count: integer; minimum=0 } }  |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="notificationsread"></a>

## notifications.read

`PATCH /api/v1/notifications/{notification}/read`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `notification` | path | ya | string |

| Status | Response |
| --- | --- |
| 200 | { data: [NotificationResource](#notificationresource) }  |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="packagecalculateprice"></a>

## package.calculatePrice

`POST /api/v1/manager/packages/calculate-price`

Pratinjau harga otomatis untuk formulir, tanpa menyimpan apa pun

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [CalculatePackagePriceRequest](#calculatepackagepricerequest)

| Status | Response |
| --- | --- |
| 200 | { data: { auto_price: integer } }  |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="packagesindex"></a>

## packages.index

`GET /api/v1/manager/packages`

Daftar paket. Tambahkan ?active=1 atau ?active=0 untuk menyaring

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `active` | query | tidak | boolean |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[PackageResource](#packageresource)&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `PackageResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="packagesstore"></a>

## packages.store

`POST /api/v1/manager/packages`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [PackageRequest](#packagerequest)

| Status | Response |
| --- | --- |
| 201 | { data: [PackageResource](#packageresource) } `PackageResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="packagesshow"></a>

## packages.show

`GET /api/v1/manager/packages/{package}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `package` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [PackageResource](#packageresource) } `PackageResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="packagesupdate"></a>

## packages.update

`PUT /api/v1/manager/packages/{package}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `package` | path | ya | integer |

Body `application/json`: [PackageRequest](#packagerequest)

| Status | Response |
| --- | --- |
| 200 | { data: [PackageResource](#packageresource) } `PackageResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="packagesupdatepatch"></a>

## packages.update.patch

`PATCH /api/v1/manager/packages/{package}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `package` | path | ya | integer |

Body `application/json`: [PackageRequest](#packagerequest)

| Status | Response |
| --- | --- |
| 200 | { data: [PackageResource](#packageresource) } `PackageResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="packagesdestroy"></a>

## packages.destroy

`DELETE /api/v1/manager/packages/{package}`

Soft delete. Project yang sudah berjalan memakai snapshot, jadi tidak terpengaruh

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `package` | path | ya | integer |

| Status | Response |
| --- | --- |
| 204 | any No content |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="packagecatalogindex"></a>

## packageCatalog.index

`GET /api/v1/client/packages`

Get all active packages for the catalog.
Available to all authenticated users

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[PackageCatalogResource](#packagecatalogresource)&gt; } Array of `PackageCatalogResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="paymentindex"></a>

## payment.index

`GET /api/v1/manager/payments`

List all payments with filtering

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `status` | query | tidak | string / null; enum=["pending","paid","failed","expired","cancelled","refunded",null] |
| `provider` | query | tidak | string / null; enum=["midtrans","xendit","manual","cash",null] |
| `client_id` | query | tidak | integer / null |
| `project_id` | query | tidak | integer / null |
| `date_from` | query | tidak | string / null; format="date-time" |
| `date_to` | query | tidak | string / null; format="date-time" |
| `sort_by` | query | tidak | string / null; enum=["created_at","amount","paid_at",null] |
| `sort_order` | query | tidak | string / null; enum=["asc","desc",null] |
| `per_page` | query | tidak | integer / null; minimum=1; maximum=100 |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[PaymentListResource](#paymentlistresource)&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `PaymentListResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="v1managerpaymentshow"></a>

## v1.manager.payment.show

`GET /api/v1/manager/payments/{payment}`

Show payment detail

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `payment` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [PaymentResource](#paymentresource) / object } `PaymentResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="paymentconfirm"></a>

## payment.confirm

`POST /api/v1/manager/payments/{payment}/confirm`

Confirm manual payment

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `payment` | path | ya | integer |

Body `application/json`: { note: string; maxLength=500; paid_at?: string / null; format="date-time" }
| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `note` | ya | string; maxLength=500 |
| `paid_at` | tidak | string / null; format="date-time" |

| Status | Response |
| --- | --- |
| 422 | { message: string; errors: object } Validation error |
| 200 | { data: [PaymentResource](#paymentresource) / object } `PaymentResource` |
| 403 | { message: string } Inactive account or operation not permitted. |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="paymentrefund"></a>

## payment.refund

`POST /api/v1/manager/payments/{payment}/refund`

Refund dilakukan di luar sistem (ADR Q13). Endpoint kompatibilitas ini menolak refund baru.

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `payment` | path | ya | integer |

Body `application/json`: { amount: integer; minimum=1; reason: string; maxLength=500 }
| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `amount` | ya | integer; minimum=1 |
| `reason` | ya | string; maxLength=500 |

| Status | Response |
| --- | --- |
| 422 | { message: string } Refunds are handled outside this system. |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="paymentcancel"></a>

## payment.cancel

`POST /api/v1/manager/payments/{payment}/cancel`

Cancel payment

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `payment` | path | ya | integer |

Body `application/json`: { reason: string; maxLength=500 }
| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `reason` | ya | string; maxLength=500 |

| Status | Response |
| --- | --- |
| 422 | { message: string; errors: object } Validation error |
| 200 | { data: [PaymentResource](#paymentresource) / object } `PaymentResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="paymentinitiatepayment"></a>

## payment.initiatePayment

`POST /api/v1/client/invoices/{invoice}/initiate-payment`

Initiate payment for an invoice

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `invoice` | path | ya | integer |

Body `application/json`: { gateway: string; enum=["midtrans","xendit"] }
| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `gateway` | ya | string; enum=["midtrans","xendit"] |

| Status | Response |
| --- | --- |
| 422 | { message: string; errors: object } Validation error |
| 201 | { data: [App.Http.Resources.PaymentResource](#apphttpresourcespaymentresource) } `App.Http.Resources.PaymentResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="v1clientpaymentshow"></a>

## v1.client.payment.show

`GET /api/v1/client/payments/{payment}`

Show payment status

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `payment` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [App.Http.Resources.PaymentResource](#apphttpresourcespaymentresource) / object } `App.Http.Resources.PaymentResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="paymentinitiatefinalpayment"></a>

## payment.initiateFinalPayment

`POST /api/v1/client/payments/{payment}/final/initiate`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `payment` | path | ya | integer |

Body `application/json`: { provider: string; enum=["midtrans","xendit","cash"] }
| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `provider` | ya | string; enum=["midtrans","xendit","cash"] |

| Status | Response |
| --- | --- |
| 422 | { message: string; errors: object } Validation error |
| 200 | { data: [App.Http.Resources.PaymentResource](#apphttpresourcespaymentresource) } `App.Http.Resources.PaymentResource` |
| 403 | { message: string } Inactive account or operation not permitted. |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="payrollallocationshow"></a>

## payrollAllocation.show

`GET /api/v1/manager/finance/payroll-allocations`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `year` | query | ya | integer; minimum=1; maximum=9999 |
| `month` | query | ya | integer; minimum=1; maximum=12 |

| Status | Response |
| --- | --- |
| 200 | { data: [PayrollAllocationResource](#payrollallocationresource) } `PayrollAllocationResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="payrollallocationupdate"></a>

## payrollAllocation.update

`PUT /api/v1/manager/finance/payroll-allocations`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [PayrollAllocationRequest](#payrollallocationrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [PayrollAllocationResource](#payrollallocationresource) } `PayrollAllocationResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="projectassignmentstore"></a>

## projectAssignment.store

`POST /api/v1/manager/projects/{project}/assignments`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `project` | path | ya | integer |

Body `application/json`: [AssignOperatorsRequest](#assignoperatorsrequest)

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[ProjectStageAssignmentResource](#projectstageassignmentresource) / object&gt;; meta: { project_id: integer; project_status: string } } Array of `ProjectStageAssignmentResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="v1managerquotationindex"></a>

## v1.manager.quotation.index

`GET /api/v1/manager/quotations`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[QuotationResource](#quotationresource) / object&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `QuotationResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="v1managerquotationshow"></a>

## v1.manager.quotation.show

`GET /api/v1/manager/quotations/{quotation}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `quotation` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [QuotationResource](#quotationresource) / object } `QuotationResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="v1managerquotationrespond"></a>

## v1.manager.quotation.respond

`POST /api/v1/manager/quotations/{quotation}/respond`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `quotation` | path | ya | integer |

Body `application/json`: [RespondToQuotationRequest](#respondtoquotationrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [QuotationResource](#quotationresource) } `QuotationResource` |
| 400 | { message: string } An error |
| 422 | { message: string; errors: object } Validation error |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="v1clientquotationindex"></a>

## v1.client.quotation.index

`GET /api/v1/client/quotations`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[QuotationResource](#quotationresource) / object&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `QuotationResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="quotationstore"></a>

## quotation.store

`POST /api/v1/client/quotations`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

Body `application/json`: [SubmitQuotationRequest](#submitquotationrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [QuotationResource](#quotationresource) / object } `QuotationResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="v1clientquotationshow"></a>

## v1.client.quotation.show

`GET /api/v1/client/quotations/{quotation}`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `quotation` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [QuotationResource](#quotationresource) / object } `QuotationResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="v1clientquotationrespond"></a>

## v1.client.quotation.respond

`POST /api/v1/client/quotations/{quotation}/respond`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `quotation` | path | ya | integer |

Body `application/json`: [App.Http.Requests.Client.RespondToQuotationRequest](#apphttprequestsclientrespondtoquotationrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [QuotationResource](#quotationresource) } `QuotationResource` |
| 400 | { message: string } An error |
| 422 | { message: string; errors: object } Validation error |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="recordingextensionstore"></a>

## recordingExtension.store

`POST /api/v1/operator/stages/{stage}/recording-extensions`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `stage` | path | ya | integer |

Body `application/json`: [RequestRecordingExtensionRequest](#requestrecordingextensionrequest)

| Status | Response |
| --- | --- |
| 201 | { data: [RecordingExtensionResource](#recordingextensionresource) / object } `RecordingExtensionResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="v1resendwebhook"></a>

## v1.resendWebhook

`POST /api/v1/webhooks/resend`



Autentikasi: tanpa session pengguna; webhook tetap memerlukan verifikasi provider.

| Status | Response |
| --- | --- |
| 200 | { status: string } / { status: string; message: string }  |
| 400 | { message: string }  |
| 401 | { message: string }  |

<a id="sessionconfirmdeadline"></a>

## session.confirmDeadline

`POST /api/v1/operator/stages/{stage}/confirm-deadline`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `stage` | path | ya | integer |

Body `application/json`: [ConfirmStageDeadlineRequest](#confirmstagedeadlinerequest)

| Status | Response |
| --- | --- |
| 200 | { data: [OperatorProjectStageResource](#operatorprojectstageresource) } `OperatorProjectStageResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="sessionsubmittrackresult"></a>

## session.submitTrackResult

`POST /api/v1/operator/track-progress/{progress}/tracks/{track}/submissions`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `progress` | path | ya | integer |
| `track` | path | ya | integer |

Body `application/json`: [SubmitTrackResultRequest](#submittrackresultrequest)

| Status | Response |
| --- | --- |
| 201 | { data: [TrackStageSubmissionResource](#trackstagesubmissionresource) } `TrackStageSubmissionResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="sessionindex"></a>

## session.index

`GET /api/v1/operator/sessions`

List sessions assigned to the authenticated operator

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[RecordingSessionListResource](#recordingsessionlistresource)&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `RecordingSessionListResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="sessionshow"></a>

## session.show

`GET /api/v1/operator/sessions/{id}`

View a single session detail (must be assigned to operator)

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `id` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: [RecordingSessionResource](#recordingsessionresource) } `RecordingSessionResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="studiosettingindex"></a>

## studioSetting.index

`GET /api/v1/manager/settings`

Get all studio settings

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[StudioSettingResource](#studiosettingresource)&gt; } Array of `StudioSettingResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="studiosettingupdate"></a>

## studioSetting.update

`PUT /api/v1/manager/settings/{key}`

Update a specific studio setting

Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `key` | path | ya | string |

Body `application/json`: [UpdateStudioSettingRequest](#updatestudiosettingrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [StudioSettingResource](#studiosettingresource) } `StudioSettingResource` |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="trackstagereviewstore"></a>

## trackStageReview.store

`POST /api/v1/manager/track-progress/{progress}/submissions/{submission}/review`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `progress` | path | ya | integer |
| `submission` | path | ya | integer |

Body `application/json`: [ReviewTrackResultRequest](#reviewtrackresultrequest)

| Status | Response |
| --- | --- |
| 200 | { data: [TrackStageReviewResource](#trackstagereviewresource) } `TrackStageReviewResource` |
| 404 | { message: string } Not found |
| 422 | { message: string; errors: object } Validation error |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |
| 429 | { message: string } Too many requests. Wait the Retry-After number of seconds before retrying. |

<a id="v1managertrackstagesubmissionhistoryindex"></a>

## v1.manager.trackStageSubmissionHistory.index

`GET /api/v1/manager/projects/{project}/stages/{stage}/tracks/{track}/history`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `project` | path | ya | integer |
| `stage` | path | ya | integer |
| `track` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[TrackStageSubmissionResource](#trackstagesubmissionresource) / object&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `TrackStageSubmissionResource` |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="v1operatortrackstagesubmissionhistoryindex"></a>

## v1.operator.trackStageSubmissionHistory.index

`GET /api/v1/operator/track-progress/{progress}/submissions`



Autentikasi: session Sanctum, dengan policy ownership/role endpoint.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `progress` | path | ya | integer |

| Status | Response |
| --- | --- |
| 200 | { data: array&lt;[TrackStageSubmissionResource](#trackstagesubmissionresource) / object&gt;; links: { first: string / null; last: string / null; prev: string / null; next: string / null }; meta: { current_page: integer; minimum=1; from: integer / null; minimum=1; last_page: integer; minimum=1; links: array&lt;{ url: string / null; label: string; active: boolean }&gt;; path: string / null; per_page: integer; minimum=0; to: integer / null; minimum=1; total: integer; minimum=0 } } Paginated set of `TrackStageSubmissionResource` |
| 404 | { message: string } Not found |
| 401 | { message: string } Unauthenticated. |
| 403 | { message: string } Inactive account or operation not permitted. |

<a id="webhookhandlepaymentwebhook"></a>

## webhook.handlePaymentWebhook

`POST /api/v1/webhooks/payment/{gateway}`

Handle payment gateway webhook

Autentikasi: tanpa session pengguna; webhook tetap memerlukan verifikasi provider.

| Parameter | Lokasi | Wajib | Tipe / aturan |
| --- | --- | --- | --- |
| `gateway` | path | ya | string |

| Status | Response |
| --- | --- |
| 500 | { status: string; message: string }  |
| 401 | { status: string; message: string }  |
| 200 | { status: string; message: string } / { status: string; message: string }  |
| 400 | { message: string }  |

# Schema request dan response


## App.Http.Requests.Client.RespondToQuotationRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `response_type` | ya | [QuotationResponseType](#quotationresponsetype) |
| `latest_offer_id` | ya | integer |
| `price` | tidak | integer / null; minimum=0 |
| `note` | tidak | string / null; maxLength=1000 |

## App.Http.Resources.PaymentResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `order_id` | ya | string |
| `type` | ya | string |
| `type_label` | ya | string; enum=["PO","Pelunasan"] |
| `amount` | ya | integer |
| `status` | ya | string |
| `status_label` | ya | string; enum=["Pending","Menunggu Konfirmasi Manual","Lunas","Gagal","Kedaluwarsa","Dibatalkan","Dikembalikan"] |
| `provider` | ya | string |
| `provider_label` | ya | string; enum=["Midtrans","Xendit","Tunai"] |
| `payment_method` | ya | string / null |
| `payment_url` | ya | string / null |
| `snap_token` | tidak | string / null |
| `expires_at` | ya | string / null |
| `paid_at` | ya | string / null |
| `created_at` | ya | string |
| `project` | tidak | { id: integer; name: string } |
| `invoice` | tidak | [InvoiceResource](#invoiceresource) / null |

## AssignOperatorsRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `assignments` | ya | array&lt;integer&gt;; minItems=1 |

## AvailabilityResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `available` | ya | boolean |
| `reason` | ya | string / null |
| `requested_start` | ya | string |
| `requested_end` | ya | string |
| `room_id` | ya | integer |

## BasePriceResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `stage` | ya | string |
| `stage_label` | ya | string; enum=["Recording","Editing","Mixing","Mastering"] |
| `unit` | ya | string |
| `unit_label` | ya | string; enum=["per jam","per track"] |
| `price` | ya | integer |
| `updated_at` | ya | string / null |

## CRMCampaignRecipientResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `client_id` | ya | integer |
| `client_name` | ya | string / null |
| `channel` | ya | string |
| `status` | ya | string |
| `sent_at` | ya | string / null; format="date-time" |
| `delivered_at` | ya | string / null; format="date-time" |
| `error` | ya | string / null |

## CRMCampaignResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string |
| `message` | ya | string |
| `package_id` | ya | integer / null |
| `channels` | ya | array&lt;string&gt; |
| `status` | ya | string |
| `sent_at` | ya | string / null; format="date-time" |
| `recipient_counts` | ya | { total: integer / null; pending: integer / null; sent: integer / null; failed: integer / null } |
| `recipients` | ya | array&lt;[CRMCampaignRecipientResource](#crmcampaignrecipientresource)&gt; |
| `created_at` | ya | string / null; format="date-time" |

## CalculatePackagePriceRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `track_count` | ya | integer; minimum=1; maximum=50 |
| `recording_hours` | tidak | integer / null; minimum=1; maximum=100 |
| `include_recording` | ya | boolean |
| `include_editing` | ya | boolean |
| `include_mixing` | ya | boolean |
| `include_mastering` | ya | boolean |

## CheckAvailabilityRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `room_id` | ya | integer |
| `start_at` | ya | string; format="date-time" |
| `end_at` | ya | string; format="date-time" |

## ClientRecommendationResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string |
| `email` | ya | string |
| `last_paid_at` | ya | any |
| `has_paid_order_history` | ya | boolean |

## ConfirmSlotRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `recording_session_id` | ya | integer |

## ConfirmStageDeadlineRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `deadline_at` | ya | string; format="date-time" |

## CreateCRMCampaignRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `name` | ya | string; maxLength=150 |
| `message` | ya | string; maxLength=5000 |
| `channels` | ya | array&lt;string; enum=["email"]&gt;; minItems=1; maxItems=1 |
| `client_ids` | ya | array&lt;integer&gt;; minItems=1; maxItems=500 |
| `package_id` | tidak | integer / null |

## DashboardResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `as_of` | ya | string |
| `period` | ya | { year: integer; month: integer; timezone: string; today: string; month_start: string; next_month_start: string } |
| `revenue` | ya | { current: integer; previous: integer; delta: integer; percentage: number / null; percentage_reason: string / null; direction: string } |
| `expense` | ya | { current: integer; previous: integer; delta: integer; percentage: number / null; percentage_reason: string / null; direction: string } |
| `monthly` | ya | array&lt;{ month: integer; revenue: integer; expense: integer }&gt; |
| `annual_totals` | ya | { revenue: integer; expense: integer } |
| `awaiting_final_payment` | ya | { amount: integer; project_count: integer; positive_balance_project_count: integer } |
| `in_progress_projects` | ya | { count: integer; track_count: integer } |
| `tracks_by_stage` | ya | array&lt;{ stage: string; stage_label: string; track_count: integer }&gt; |
| `unclassified_track_count` | ya | integer |
| `quotations` | ya | { open_count: integer; awaiting_studio_count: integer } |
| `package_sales` | ya | { total_count: integer; items: array&lt;{ key: string; package_id: integer / null; quotation_id: integer / null; name: string; project_count: integer; percentage: number }&gt; } |
| `expense_categories` | ya | { total_amount: integer; transaction_count: integer; items: array&lt;{ category_id: integer / null; category_name: string; transaction_count: integer; amount: integer; percentage: number }&gt; } |
| `recording_schedule` | ya | array&lt;{ id: integer; project_id: integer / null; project_name: string / null; client_id: integer / null; client_name: string / null; start_at: string; end_at: string; status: string; status_label: string }&gt; |
| `project_progress` | ya | array&lt;{ id: integer; name: string; client_id: integer / null; client_name: string / null; status: string; status_label: string; current_stage: string / null; current_stage_label: string / null; stage_status: string / null; stage_status_label: string / null }&gt; |
| `quotation_progress` | ya | array&lt;{ id: integer; name: string / null; package_display_name: string; client_id: integer / null; client_name: string / null; status: string; status_label: string }&gt; |
| `operator_activities` | ya | array&lt;{ id: integer; type: string; actor: { id: integer / null; name: string / null; is_system: boolean }; operator: { id: integer / null; name: string / null }; project: { id: integer / null; name: string / null }; stage: { id: integer / null; type: string; label: string }; track: { id: integer / null; title: string / null }; occurred_at: string; recorded_at: string; deadline_at: string / null; age_seconds: integer; description: string }&gt; |

## DisposeInventoryRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `type` | ya | string; enum=["sold","disposed"] |
| `disposed_on` | ya | string; format="date" |
| `disposal_value` | tidak | integer / null; minimum=0 |

## ExpenseCategoryRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `name` | ya | string; maxLength=50 |
| `is_active` | tidak | boolean |

## ExpenseCategoryResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string |
| `is_active` | ya | boolean |
| `created_at` | ya | string / null |

## ExpenseRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `expense_category_id` | ya | integer |
| `description` | ya | string; maxLength=255 |
| `amount` | ya | integer; minimum=1 |
| `spent_on` | ya | string; format="date" |

## ExpenseResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `expense_category_id` | ya | integer |
| `category` | tidak | [ExpenseCategoryResource](#expensecategoryresource) |
| `recorded_by` | ya | integer |
| `recorder` | tidak | { id: integer; name: string } |
| `description` | ya | string |
| `amount` | ya | integer |
| `spent_on` | ya | string |
| `created_at` | ya | string / null |
| `updated_at` | ya | string / null |

## FinalDeliveryResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `track_id` | ya | integer |
| `track_title` | ya | string |
| `stage` | ya | string |
| `version` | ya | integer |
| `result_url` | ya | string |
| `submitted_at` | ya | string / null |

## FinalizeMonthlyPayrollRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `year` | ya | integer; minimum=1; maximum=9999 |
| `month` | ya | integer; minimum=1; maximum=12 |

## HoldSlotRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `room_id` | ya | integer |
| `start_at` | ya | string; format="date-time" |
| `end_at` | ya | string; format="date-time" |
| `package_id` | tidak | integer |
| `quotation_id` | tidak | integer |
| `project_id` | tidak | integer / null |

## InventoryCategoryRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `name` | ya | string; maxLength=50 |
| `is_active` | tidak | boolean |

## InventoryCategoryResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string |
| `is_active` | ya | boolean |
| `created_at` | ya | string / null |

## InventoryRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `code` | ya | string; maxLength=30 |
| `name` | ya | string; maxLength=100 |
| `inventory_category_id` | ya | integer |
| `brand` | tidak | string / null; maxLength=50 |
| `model` | tidak | string / null; maxLength=50 |
| `serial_number` | tidak | string / null; maxLength=100 |
| `purchase_date` | ya | string; format="date" |
| `purchase_price` | ya | integer; minimum=0 |
| `residual_value` | tidak | integer; minimum=0 |
| `useful_life_months` | ya | integer; minimum=1; maximum=65535 |
| `room_id` | tidak | integer / null |
| `expense_id` | tidak | integer / null |
| `notes` | tidak | string / null |

## InventoryResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `code` | ya | string |
| `name` | ya | string |
| `inventory_category_id` | ya | integer |
| `category` | tidak | [InventoryCategoryResource](#inventorycategoryresource) |
| `brand` | ya | string / null |
| `model` | ya | string / null |
| `serial_number` | ya | string / null |
| `purchase_date` | ya | string |
| `purchase_price` | ya | integer |
| `residual_value` | ya | integer |
| `useful_life_months` | ya | integer |
| `monthly_depreciation` | ya | integer |
| `book_value` | ya | integer |
| `book_value_as_of` | ya | string |
| `status` | ya | string |
| `disposed_on` | ya | string / null |
| `disposal_value` | ya | integer / null |
| `room_id` | ya | integer / null |
| `room` | tidak | { id: integer; name: string } / null |
| `expense_id` | ya | integer / null |
| `expense` | tidak | { id: integer; description: string } / null |
| `notes` | ya | string / null |
| `created_at` | ya | string / null |
| `updated_at` | ya | string / null |

## InvoiceItemResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `line_number` | ya | integer |
| `description` | ya | string |
| `details` | ya | string / null |
| `unit_price` | ya | integer |
| `quantity` | ya | integer |
| `subtotal` | ya | integer |
| `tax_amount` | ya | integer |
| `discount_amount` | ya | integer |
| `total` | ya | integer |
| `package_id` | ya | integer / null |
| `recording_session_id` | ya | integer / null |

## InvoiceListResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `invoice_number` | ya | string |
| `status` | ya | string |
| `status_label` | ya | string; enum=["Draft","Terbit","Lunas","Dibatalkan","Dikembalikan"] |
| `total` | ya | integer |
| `issue_date` | ya | string / null |
| `due_date` | ya | string / null |
| `is_overdue` | ya | boolean |
| `created_at` | ya | string |
| `project` | tidak | { id: integer; name: string } / null |

## InvoiceResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `invoice_number` | ya | string |
| `status` | ya | string |
| `status_label` | ya | string; enum=["Draft","Terbit","Lunas","Dibatalkan","Dikembalikan"] |
| `subtotal` | ya | integer |
| `tax_amount` | ya | integer |
| `discount_amount` | ya | integer |
| `total` | ya | integer |
| `notes` | ya | string / null |
| `issue_date` | ya | string / null |
| `due_date` | ya | string / null |
| `paid_at` | ya | string / null |
| `is_overdue` | ya | boolean |
| `created_at` | ya | string |
| `client` | tidak | { id: integer; name: string; email: string } |
| `project` | tidak | { id: integer; name: string } / null |
| `recording_session` | tidak | { id: integer } / null |
| `items` | tidak | array&lt;[InvoiceItemResource](#invoiceitemresource)&gt; |
| `payment` | tidak | [App.Http.Resources.PaymentResource](#apphttpresourcespaymentresource) / null |

## LoginRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `email` | ya | string; format="email" |
| `password` | ya | string |
| `remember` | tidak | boolean |

## MonthlyFinanceSummaryResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `period` | ya | { year: integer; month: integer; timezone: string } |
| `totals` | ya | { revenue: integer; expense: integer; net_profit: integer } |
| `expenses_by_category` | ya | array&lt;{ category_id: integer; category_name: string; amount: integer }&gt; |
| `status` | ya | string |

## NotificationResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | string |
| `type` | ya | string |
| `data` | ya | array&lt;string&gt;; minItems=0; maxItems=0 / { type: string; payment_id?: integer; order_id?: string; amount?: integer; invoice_id?: integer; invoice_number?: string; schema_version?: integer; event_key?: string; title?: string; body?: string; target?: { type: string; id: integer } / null; metadata?: object } |
| `read_at` | ya | string / null |
| `created_at` | ya | string / null |

## OperatorActivityFeedResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `as_of` | ya | string |
| `timezone` | ya | string |
| `items` | ya | array&lt;{ id: integer; type: string; actor: { id: integer / null; name: string / null; is_system: boolean }; operator: { id: integer / null; name: string / null }; project: { id: integer / null; name: string / null }; stage: { id: integer / null; type: string; label: string }; track: { id: integer / null; title: string / null }; occurred_at: string; recorded_at: string; deadline_at: string / null; age_seconds: integer; description: string }&gt; |

## OperatorProjectStageResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `project_id` | ya | integer |
| `stage` | ya | string |
| `sequence` | ya | integer |
| `status` | ya | string |
| `deadline_at` | ya | string / null |
| `started_at` | ya | string / null |

## PackageCatalogResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string |
| `description` | ya | string / null |
| `track_count` | ya | integer |
| `recording_hours` | ya | integer |
| `include_recording` | ya | boolean |
| `include_editing` | ya | boolean |
| `include_mixing` | ya | boolean |
| `include_mastering` | ya | boolean |
| `price` | ya | integer |

## PackageRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `name` | ya | string; maxLength=100 |
| `description` | tidak | string / null; maxLength=255 |
| `track_count` | ya | integer; minimum=1; maximum=50 |
| `recording_hours` | tidak | integer / null; minimum=1; maximum=100 |
| `include_recording` | ya | boolean |
| `include_editing` | ya | boolean |
| `include_mixing` | ya | boolean |
| `include_mastering` | ya | boolean |
| `manual_price` | tidak | integer / null; minimum=0 |
| `is_active` | tidak | boolean |

## PackageResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string |
| `description` | ya | string / null |
| `track_count` | ya | integer |
| `recording_hours` | ya | integer |
| `stages` | ya | array&lt;any&gt; |
| `include_recording` | ya | boolean |
| `include_editing` | ya | boolean |
| `include_mixing` | ya | boolean |
| `include_mastering` | ya | boolean |
| `auto_price` | ya | integer |
| `manual_price` | ya | integer / null |
| `is_price_manual` | ya | boolean |
| `price` | ya | integer |
| `is_active` | ya | boolean |
| `created_at` | ya | string / null |

## PaymentListResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `order_id` | ya | string |
| `type` | ya | string |
| `type_label` | ya | string; enum=["PO","Pelunasan"] |
| `amount` | ya | integer |
| `status` | ya | string |
| `status_label` | ya | string; enum=["Pending","Menunggu Konfirmasi Manual","Lunas","Gagal","Kedaluwarsa","Dibatalkan","Dikembalikan"] |
| `provider` | ya | string |
| `provider_label` | ya | string; enum=["Midtrans","Xendit","Tunai"] |
| `payment_method` | ya | string / null |
| `client` | tidak | { id: integer; name: string } |
| `project` | tidak | { id: integer; code: string; name: string } |
| `paid_at` | ya | string / null |
| `created_at` | ya | string |

## PaymentResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `order_id` | ya | string |
| `type` | ya | string |
| `type_label` | ya | string; enum=["PO","Pelunasan"] |
| `amount` | ya | integer |
| `status` | ya | string |
| `status_label` | ya | string; enum=["Pending","Menunggu Konfirmasi Manual","Lunas","Gagal","Kedaluwarsa","Dibatalkan","Dikembalikan"] |
| `provider` | ya | string |
| `provider_label` | ya | string; enum=["Midtrans","Xendit","Tunai"] |
| `payment_method` | ya | string / null |
| `payment_url` | ya | string / null |
| `snap_token` | ya | string / null |
| `gateway_charge_id` | ya | string / null |
| `notes` | ya | string / null |
| `expires_at` | ya | string / null |
| `paid_at` | ya | string / null |
| `created_at` | ya | string |
| `updated_at` | ya | string |
| `client` | tidak | { id: integer; name: string; email: string; phone: string } |
| `project` | tidak | { id: integer; code: string; name: string } |
| `invoice` | tidak | [InvoiceResource](#invoiceresource) / object |
| `gateway_response` | tidak | array / null&lt;any&gt; |

## PayrollAllocationRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `year` | ya | integer; minimum=1; maximum=9999 |
| `month` | ya | integer; minimum=1; maximum=12 |
| `studio_profit_percentage` | tidak | number; minimum=0; maximum=100; pattern="^\\d{1,3}(?:\\.\\d{1,2})?$" |
| `allocations` | ya | array&lt;{ operator_id: integer; percentage: number; minimum=0; maximum=100; pattern="^\\d{1,3}(?:\\.\\d{1,2})?$" }&gt;; minItems=1 |

## PayrollAllocationResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `period` | ya | { year: integer; month: integer; timezone: string } |
| `status` | ya | string |
| `studio_profit_percentage` | ya | string |
| `studio_profit_amount` | ya | integer |
| `allocations` | ya | array&lt;{ operator_id: integer; operator_name: string; jobs_count: integer; recommended_percentage: string; percentage: string / null; amount: integer }&gt; |

## ProjectStageAssignmentResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `project_id` | ya | integer |
| `stage` | ya | string |
| `sequence` | ya | integer |
| `operator` | tidak | [UserResource](#userresource) / null |
| `status` | ya | string |
| `progress_percent` | ya | integer |
| `assigned_at` | ya | string / null |

## QuotationOfferResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `quotation_id` | ya | integer |
| `offered_by` | ya | integer |
| `side` | ya | string |
| `side_label` | ya | string |
| `price` | ya | integer |
| `note` | ya | string / null |
| `created_at` | ya | string / null; format="date-time" |

## QuotationResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string / null |
| `package_display_name` | ya | string |
| `client_id` | ya | integer |
| `specifications` | ya | { track_count: integer; recording_hours: integer; include_recording: boolean; include_editing: boolean; include_mixing: boolean; include_mastering: boolean } |
| `notes` | ya | string / null |
| `status` | ya | [QuotationStatus](#quotationstatus) |
| `status_label` | ya | string; enum=["Menunggu studio","Menunggu klien","Disetujui","Ditolak"] |
| `auto_price` | ya | integer / null |
| `agreed_price` | ya | integer / null |
| `closed_by` | ya | integer / null |
| `closed_at` | ya | string / null; format="date-time" |
| `created_at` | ya | string / null; format="date-time" |
| `updated_at` | ya | string / null; format="date-time" |
| `latest_offer` | tidak | [QuotationOfferResource](#quotationofferresource) |
| `offers` | tidak | array&lt;[QuotationOfferResource](#quotationofferresource)&gt; |
| `client` | tidak | [UserResource](#userresource) |

## QuotationResponseType

string; enum=["accept","decline","counter"]

## QuotationStatus

string; enum=["awaiting_studio","awaiting_client","accepted","declined"]

## RecordingExtensionResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `room` | tidak | [RoomResource](#roomresource) |
| `start_at` | ya | string |
| `end_at` | ya | string |
| `status` | ya | string |
| `is_extension` | ya | boolean |
| `extra_fee` | ya | integer |
| `project_stage_id` | ya | integer / null |
| `created_at` | ya | string |

## RecordingSessionListResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `room_id` | ya | integer / null |
| `room_name` | tidak | string |
| `client_id` | ya | integer / null |
| `client_name` | tidak | string |
| `start_at` | ya | string |
| `end_at` | ya | string |
| `status` | ya | string |
| `status_label` | ya | string; enum=["On Hold","Terkonfirmasi","Selesai","Dibatalkan","Kedaluwarsa"] |

## RecordingSessionResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `room` | tidak | [RoomResource](#roomresource) |
| `client` | tidak | [UserResource](#userresource) / null |
| `package_id` | ya | integer / null |
| `quotation_id` | tidak | integer / null |
| `start_at` | ya | string |
| `end_at` | ya | string |
| `status` | ya | string |
| `status_label` | ya | string; enum=["On Hold","Terkonfirmasi","Selesai","Dibatalkan","Kedaluwarsa"] |
| `held_until` | ya | string / null |
| `project_id` | ya | integer / null |
| `project_stage_id` | ya | integer / null |
| `created_at` | ya | string |
| `updated_at` | ya | string |

## RegisterRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `name` | ya | string; maxLength=255 |
| `email` | ya | string; format="email"; maxLength=255 |
| `phone` | ya | string; pattern="^628\\d{8,11}$" |
| `password` | ya | string |
| `crm_consent` | ya | boolean |
| `password_confirmation` | ya | string |

## RequestRecordingExtensionRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `room_id` | ya | integer |
| `start_at` | ya | string; format="date-time" |
| `end_at` | ya | string; format="date-time" |

## RespondToQuotationRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `response_type` | ya | [QuotationResponseType](#quotationresponsetype) |
| `latest_offer_id` | ya | integer |
| `price` | tidak | integer / null; minimum=0 |
| `note` | tidak | string / null; maxLength=1000 |

## ReviewTrackResultRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `decision` | ya | [TrackResultReviewDecision](#trackresultreviewdecision) |
| `review_notes` | tidak | string / null; minLength=10; maxLength=5000 |

## Role

string; enum=["manager","operator","client"]

## RoomResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string |
| `is_active` | ya | boolean |

## StudioSettingResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `key` | ya | string |
| `value` | ya | string |
| `type` | ya | string |
| `description` | ya | string / null |
| `updated_by` | tidak | [UserResource](#userresource) / null |
| `updated_at` | ya | string / null; format="date-time" |

## SubmitQuotationRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `name` | ya | string; maxLength=100 |
| `track_count` | ya | integer; minimum=1; maximum=255 |
| `recording_hours` | ya | integer; minimum=0; maximum=255 |
| `include_recording` | ya | boolean |
| `include_editing` | ya | boolean |
| `include_mixing` | ya | boolean |
| `include_mastering` | ya | boolean |
| `notes` | tidak | string / null; maxLength=1000 |
| `offer_price` | ya | integer; minimum=0 |

## SubmitTrackResultRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `result_url` | ya | string; format="uri"; maxLength=2048 |

## TrackResultReviewDecision

string; enum=["approve","request_revision"]

## TrackStageReviewResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `project_stage_id` | ya | integer |
| `project_track_id` | ya | integer |
| `status` | ya | string |
| `stage_status` | ya | string / null |
| `result_url` | ya | string / null |
| `current_version` | ya | integer |
| `review_notes` | ya | string / null |
| `revision_count` | ya | integer |
| `approved_at` | ya | string / null |
| `approved_by` | ya | integer / null |
| `submission` | ya | [TrackStageSubmissionResource](#trackstagesubmissionresource) / null |

## TrackStageSubmissionResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `track_stage_progress_id` | ya | integer |
| `version` | ya | integer |
| `result_url` | ya | string |
| `submitted_by` | tidak | [UserResource](#userresource) |
| `submitted_at` | ya | string / null |
| `review_notes` | ya | string / null |
| `reviewed_at` | ya | string / null |
| `review_decision` | ya | string / null |
| `reviewed_by` | tidak | [UserResource](#userresource) / null |

## UpdateBasePricesRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `prices` | ya | { recording: integer; minimum=0; maximum=1000000000; editing: integer; minimum=0; maximum=1000000000; mixing: integer; minimum=0; maximum=1000000000; mastering: integer; minimum=0; maximum=1000000000 } |

## UpdateStudioSettingRequest

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `value` | ya | string |

## User

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string |
| `email` | ya | string |
| `phone` | ya | string |
| `email_verified_at` | ya | string / null; format="date-time" |
| `role` | ya | [Role](#role) |
| `crm_consent_at` | ya | string / null; format="date-time" |
| `is_active` | ya | boolean |
| `created_at` | ya | string / null; format="date-time" |
| `updated_at` | ya | string / null; format="date-time" |

## UserResource

| Field | Wajib | Tipe / aturan |
| --- | --- | --- |
| `id` | ya | integer |
| `name` | ya | string |
| `email` | ya | string |
| `phone` | ya | string |
| `role` | ya | string |
| `role_label` | ya | string; enum=["Manager","Operator","Klien"] |
| `crm_consent` | ya | boolean |
| `created_at` | ya | string / null |
