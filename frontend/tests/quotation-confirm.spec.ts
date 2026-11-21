import { expect, test } from '@playwright/test'

for (const missing of [false, true]) {
  test(`confirmation submits acceptance when initial offer metadata is ${missing ? 'missing' : 'present'}`, async ({ page }) => {
    let reads = 0
    let accepted = false
    const offer = { id: 7, side: 'studio', price: 1250000, created_at: '2026-11-13T10:00:00+07:00' }
    const quote = { id: 1, name: 'Produksi mini album', status: 'awaiting_client', status_label: 'Menunggu klien', auto_price: 1000000, agreed_price: null, created_at: '2026-11-13T10:00:00+07:00' }
    await page.route('**/api/v1/auth/me', route => route.fulfill({ json: { data: { id: 1, name: 'Client', role: 'client' } } }))
    await page.route('**/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }))
    await page.route('**/api/v1/client/**', route => {
      const path = new URL(route.request().url()).pathname
      if (path.endsWith('/respond')) {
        expect(route.request().postDataJSON()).toMatchObject({ response_type: 'accept', latest_offer_id: 7 })
        accepted = true
        return route.fulfill({ json: { data: { ...quote, status: 'accepted', status_label: 'Disetujui', latest_offer: offer } } })
      }
      if (path.endsWith('/quotations/1')) {
        reads += 1
        return route.fulfill({ json: { data: { ...quote, ...(!missing || reads > 1 ? { offers: [offer] } : {}) } } })
      }
      if (path.endsWith('/quotations')) return route.fulfill({ json: { data: [quote] } })
      return route.fulfill({ json: { data: [] } })
    })
    await page.goto('/client/packages')
    await page.getByRole('button', { name: 'Terima', exact: true }).click()
    const dialog = page.getByRole('dialog', { name: 'Terima Penawaran Studio' })
    await dialog.getByRole('button', { name: 'Konfirmasi' }).click()
    await expect(dialog).not.toBeVisible()
    await expect(page.getByText('Tanggapan penawaran berhasil dikirim.')).toBeVisible()
    expect(accepted).toBe(true)
  })
}
