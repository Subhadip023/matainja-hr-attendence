{{-- Server-rendered invoice PDF (dompdf). Tables only: dompdf has no flexbox/grid. --}}
@php
    $cur = $invoice->currency ?: 'INR';
    $money = fn ($v) => ($cur === 'INR' ? '₹' : $cur.' ').number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $taxes = $invoice->taxes->filter(fn ($t) => (float) $t->rate > 0);
    $client = $invoice->client;
    $stampColor = ['paid' => '#1e8e5a', 'cancelled' => '#c0392b', 'draft' => '#64707d'][$invoice->status] ?? null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { margin: 14mm 14mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #17202a; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .muted { color: #64707d; }
        .label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1px; color: #64707d; margin-bottom: 3px; }
        .company { font-size: 14pt; font-weight: bold; margin-bottom: 3px; }
        .doc-title { font-size: 18pt; font-weight: bold; letter-spacing: 2px; color: #1f6f66; text-align: right; margin-bottom: 6px; }
        .meta td { padding: 1px 0 1px 10px; text-align: right; }
        .stamp { display: inline-block; border: 1.5pt solid; padding: 2px 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; font-size: 8.5pt; }
        .lines { margin-top: 22px; }
        .lines th { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .5px; color: #64707d; text-align: left; border-bottom: 1.5pt solid #17202a; padding: 6px 5px; }
        .lines td { border-bottom: .5pt solid #d6dbe0; padding: 7px 5px; }
        .r, .lines th.r, .lines td.r { text-align: right; white-space: nowrap; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; }
        .totals td { padding: 3px 5px; }
        .totals .grand td { border-top: 1.5pt solid #17202a; font-size: 12pt; font-weight: bold; padding-top: 6px; }
        .foot { margin-top: 26px; }
        .foot .block { margin-bottom: 14px; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #8a949e; }
    </style>
</head>
<body>
    <div class="footer">{{ $company['name'] }} · {{ $invoice->number }}</div>

    <table>
        <tr>
            <td style="width:55%">
                @if (! empty($company['logo']))
                    <img src="{{ $company['logo'] }}" alt="" style="max-height:60px;max-width:220px;margin-bottom:8px">
                @endif
                <div class="company">{{ $company['name'] }}</div>
                @if ($company['address'])<div>{!! nl2br(e($company['address'])) !!}</div>@endif
                @if ($company['tax_id'])<div>GSTIN: {{ $company['tax_id'] }}</div>@endif
                @if ($company['email'])<div>{{ $company['email'] }}</div>@endif
                @if ($company['phone'])<div>{{ $company['phone'] }}</div>@endif
            </td>
            <td style="width:45%">
                <div class="doc-title">{{ $taxes->isNotEmpty() ? 'TAX INVOICE' : 'INVOICE' }}</div>
                <table class="meta">
                    <tr><td class="muted">Invoice no.</td><td style="width:1%;white-space:nowrap"><strong>{{ $invoice->number }}</strong></td></tr>
                    <tr><td class="muted">Issue date</td><td style="white-space:nowrap">{{ $invoice->issue_date?->format('d M Y') }}</td></tr>
                    @if ($invoice->due_date)<tr><td class="muted">Due date</td><td style="white-space:nowrap">{{ $invoice->due_date->format('d M Y') }}</td></tr>@endif
                </table>
                @if ($stampColor)
                    <div style="text-align:right;margin-top:8px">
                        <span class="stamp" style="color:{{ $stampColor }};border-color:{{ $stampColor }}">{{ $invoice->status }}{{ $invoice->status === 'paid' && $invoice->paid_at ? ' · '.$invoice->paid_at->format('d M Y') : '' }}</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div style="margin-top:22px">
        <div class="label">Bill to</div>
        <strong>{{ $client?->name }}</strong>
        @if ($client?->contact_person)<div>Attn: {{ $client->contact_person }}</div>@endif
        @if ($client?->address)<div>{{ $client->address }}</div>@endif
        @if ($client?->city || $client?->country)<div>{{ collect([$client->city, $client->country])->filter()->implode(', ') }}</div>@endif
        @if ($client?->tax_id)<div>GSTIN: {{ $client->tax_id }}</div>@endif
        @if ($client?->email)<div>{{ $client->email }}</div>@endif
    </div>

    <table class="lines">
        <thead>
        <tr><th style="width:5%">#</th><th>Description</th><th class="r" style="width:8%">Qty</th><th class="r" style="width:18%">Unit price</th><th class="r" style="width:18%">Amount</th></tr>
        </thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->description }}</td>
                <td class="r">{{ $qty($item->quantity) }}</td>
                <td class="r">{{ $money($item->unit_price) }}</td>
                <td class="r">{{ $money($item->amount) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="r">{{ $money($invoice->subtotal) }}</td></tr>
        @foreach ($taxes as $tax)
            <tr><td>{{ $tax->label }}</td><td class="r">{{ $money($tax->amount) }}</td></tr>
        @endforeach
        <tr class="grand"><td>Total</td><td class="r">{{ $money($invoice->total) }}</td></tr>
        @if ($invoice->status === 'paid')
            <tr><td>Paid</td><td class="r">{{ $money($invoice->total) }}</td></tr>
            <tr><td><strong>Balance due</strong></td><td class="r"><strong>{{ $money(0) }}</strong></td></tr>
        @endif
    </table>

    <div class="foot">
        @if ($invoice->notes)<div class="block"><div class="label">Notes</div>{!! nl2br(e($invoice->notes)) !!}</div>@endif
        @if ($invoice->terms)<div class="block"><div class="label">Payment terms</div>{!! nl2br(e($invoice->terms)) !!}</div>@endif
    </div>
</body>
</html>
