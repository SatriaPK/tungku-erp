@php
    $rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
    $date = fn ($d) => $d ? $d->copy()->setTimezone(config('app.timezone'))->translatedFormat('d F Y') : '-';
    $detailText = function ($details) {
        if (is_array($details)) {
            return implode(', ', array_filter(array_map(fn ($v) => is_scalar($v) ? (string) $v : null, $details)));
        }

        return (string) $details;
    };
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $invoice->invoice_number }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; }
    .wrap { padding: 32px 36px; }
    .head { width: 100%; border-bottom: 2px solid #c8463d; padding-bottom: 12px; margin-bottom: 18px; }
    .brand { font-size: 20px; font-weight: bold; color: #c8463d; }
    .muted { color: #666; }
    .right { text-align: right; }
    h1 { font-size: 18px; margin: 0; }
    table.meta, table.items, table.totals { width: 100%; border-collapse: collapse; }
    table.meta td { vertical-align: top; padding: 2px 0; }
    table.items { margin-top: 18px; }
    table.items th { background: #f3f3f3; text-align: left; padding: 7px 8px; border-bottom: 1px solid #ddd; }
    table.items td { padding: 7px 8px; border-bottom: 1px solid #eee; }
    table.totals { margin-top: 10px; }
    table.totals td { padding: 4px 8px; }
    .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid #222; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; background: #eee; }
    .paid { background: #dff3e4; color: #1d6b34; }
    .foot { margin-top: 28px; font-size: 10px; color: #777; }
</style>
</head>
<body>
<div class="wrap">
    <table class="head"><tr>
        <td><div class="brand">{{ $studioName }}</div><div class="muted">Recording Studio</div></td>
        <td class="right"><h1>INVOICE</h1><div>{{ $invoice->invoice_number }}</div>
            <span class="badge {{ $invoice->status->value === 'paid' ? 'paid' : '' }}">{{ $invoice->status->label() }}</span></td>
    </tr></table>

    <table class="meta"><tr>
        <td style="width:55%">
            <div class="muted">Ditagihkan kepada</div>
            <strong>{{ $invoice->client?->name ?? '-' }}</strong><br>
            {{ $invoice->client?->email }}<br>
            {{ $invoice->client?->phone }}
        </td>
        <td>
            <table class="meta">
                <tr><td class="muted">Tanggal terbit</td><td class="right">{{ $date($invoice->issue_date) }}</td></tr>
                <tr><td class="muted">Jatuh tempo</td><td class="right">{{ $date($invoice->due_date) }}</td></tr>
                @if ($invoice->paid_at)
                <tr><td class="muted">Tanggal lunas</td><td class="right">{{ $date($invoice->paid_at) }}</td></tr>
                @endif
                @if ($invoice->project)
                <tr><td class="muted">Proyek</td><td class="right">{{ $invoice->project->name }}</td></tr>
                @endif
            </table>
        </td>
    </tr></table>

    <table class="items">
        <thead><tr><th style="width:5%">#</th><th>Deskripsi</th><th class="right" style="width:8%">Qty</th><th class="right" style="width:18%">Harga</th><th class="right" style="width:18%">Jumlah</th></tr></thead>
        <tbody>
        @foreach ($invoice->items->sortBy('line_number') as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->description }}@if ($item->details)<br><span class="muted">{{ $detailText($item->details) }}</span>@endif</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ $rp($item->unit_price) }}</td>
                <td class="right">{{ $rp($item->total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td style="width:64%"></td><td class="muted">Subtotal</td><td class="right">{{ $rp($invoice->subtotal) }}</td></tr>
        @if ((int) $invoice->discount_amount > 0)
        <tr><td></td><td class="muted">Diskon{{ isset($invoice->metadata['coupon']['code']) ? ' ('.$invoice->metadata['coupon']['code'].')' : '' }}</td><td class="right">-{{ $rp($invoice->discount_amount) }}</td></tr>
        @endif
        @if ((int) $invoice->tax_amount > 0)
        <tr><td></td><td class="muted">Pajak</td><td class="right">{{ $rp($invoice->tax_amount) }}</td></tr>
        @endif
        <tr class="grand"><td></td><td>Total</td><td class="right">{{ $rp($invoice->total) }}</td></tr>
    </table>

    @if ($invoice->notes)
        <p style="margin-top:18px"><strong>Catatan:</strong> {{ $invoice->notes }}</p>
    @endif

    <div class="foot">Dokumen ini dibuat otomatis oleh sistem {{ $studioName }} pada {{ now()->setTimezone(config('app.timezone'))->translatedFormat('d F Y H:i') }} WIB.</div>
</div>
</body>
</html>
