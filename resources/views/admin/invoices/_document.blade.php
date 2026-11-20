{{-- The invoice itself, shared by the admin detail page and the print page. --}}
@php
    $cur = $invoice->currency ?: 'INR';
    $money = fn ($v) => ($cur === 'INR' ? '₹' : $cur.' ').number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $taxes = $invoice->taxes->filter(fn ($t) => (float) $t->rate > 0);
    $client = $invoice->client;
@endphp
<style>
    .invoice-doc { background:#fff; color:#17202a; padding:40px; font-size:13px; line-height:1.5; }
    .invoice-doc .top { display:flex; justify-content:space-between; gap:24px; flex-wrap:wrap; margin-bottom:28px; }
    .invoice-doc .from strong { font-size:18px; display:block; margin-bottom:4px; }
    .invoice-doc .title { text-align:right; }
    .invoice-doc .title h2 { font-size:26px; letter-spacing:.08em; color:#1f6f66; margin:0 0 6px; }
    .invoice-doc .meta { border-collapse:collapse; margin-left:auto; }
    .invoice-doc .meta td { padding:1px 0 1px 14px; text-align:right; }
    .invoice-doc .meta td:first-child { color:#64707d; }
    .invoice-doc .bill { margin-bottom:24px; }
    .invoice-doc .label { font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#64707d; margin-bottom:4px; }
    .invoice-doc table.lines { width:100%; border-collapse:collapse; margin-bottom:16px; }
    .invoice-doc table.lines th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64707d; border-bottom:2px solid #17202a; padding:8px 6px; }
    .invoice-doc table.lines td { border-bottom:1px solid #e3e7eb; padding:9px 6px; vertical-align:top; }
    .invoice-doc .r, .invoice-doc table.lines th.r { text-align:right; white-space:nowrap; }
    .invoice-doc .totals { margin-left:auto; border-collapse:collapse; min-width:260px; }
    .invoice-doc .totals td { padding:4px 6px; }
    .invoice-doc .totals tr.grand td { border-top:2px solid #17202a; font-size:16px; font-weight:700; padding-top:8px; }
    .invoice-doc .foot { margin-top:28px; display:grid; gap:16px; }
    .invoice-doc .pre { white-space:pre-line; }
    .invoice-doc .stamp { display:inline-block; border:2px solid; border-radius:6px; padding:2px 10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; font-size:12px; }
    .invoice-doc .stamp.paid { color:#1e8e5a; } .invoice-doc .stamp.cancelled { color:#c0392b; } .invoice-doc .stamp.draft { color:#64707d; }
    @media (max-width: 600px) { .invoice-doc { padding:20px; } .invoice-doc .title { text-align:left; } .invoice-doc .meta { margin-left:0; } .invoice-doc .meta td { text-align:left; padding:1px 14px 1px 0; } }
</style>
<div class="invoice-doc">
    <div class="top">
        <div class="from">
            @if (! empty($company['logo']))
                <img src="{{ $company['logo'] }}" alt="{{ $company['name'] }} logo" style="display:block;max-height:60px;max-width:220px;margin-bottom:8px">
            @endif
            <strong>{{ $company['name'] }}</strong>
            @if ($company['address'])<div class="pre">{{ $company['address'] }}</div>@endif
            @if ($company['tax_id'])<div>GSTIN: {{ $company['tax_id'] }}</div>@endif
            @if ($company['email'])<div>{{ $company['email'] }}</div>@endif
            @if ($company['phone'])<div>{{ $company['phone'] }}</div>@endif
        </div>
        <div class="title">
            <h2>{{ $taxes->isNotEmpty() ? 'TAX INVOICE' : 'INVOICE' }}</h2>
            <table class="meta">
                <tr><td>Invoice no.</td><td><strong>{{ $invoice->number }}</strong></td></tr>
                <tr><td>Issue date</td><td>{{ $invoice->issue_date?->format('d M Y') }}</td></tr>
                @if ($invoice->due_date)<tr><td>Due date</td><td>{{ $invoice->due_date->format('d M Y') }}</td></tr>@endif
            </table>
            @if (in_array($invoice->status, ['paid', 'cancelled', 'draft'], true))
                <div style="margin-top:8px"><span class="stamp {{ $invoice->status }}">{{ $invoice->status }}{{ $invoice->status === 'paid' && $invoice->paid_at ? ' · '.$invoice->paid_at->format('d M Y') : '' }}</span></div>
            @endif
        </div>
    </div>

    <div class="bill">
        <div class="label">Bill to</div>
        <strong>{{ $client?->name }}</strong>
        @if ($client?->contact_person)<div>Attn: {{ $client->contact_person }}</div>@endif
        @if ($client?->address)<div>{{ $client->address }}</div>@endif
        @if ($client?->city || $client?->country)<div>{{ collect([$client->city, $client->country])->filter()->implode(', ') }}</div>@endif
        @if ($client?->tax_id)<div>GSTIN: {{ $client->tax_id }}</div>@endif
        @if ($client?->email)<div>{{ $client->email }}</div>@endif
    </div>

    <table class="lines">
        <thead><tr><th style="width:36px">#</th><th>Description</th><th class="r" style="width:8%">Qty</th><th class="r" style="width:18%">Unit price</th><th class="r" style="width:18%">Amount</th></tr></thead>
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
        @if ($invoice->notes)<div><div class="label">Notes</div><div class="pre">{{ $invoice->notes }}</div></div>@endif
        @if ($invoice->terms)<div><div class="label">Payment terms</div><div class="pre">{{ $invoice->terms }}</div></div>@endif
    </div>
</div>
