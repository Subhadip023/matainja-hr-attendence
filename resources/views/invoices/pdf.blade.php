{{-- Server-rendered invoice PDF (dompdf). Tables only: dompdf has no flexbox/grid. --}}
@php
    $cur = $invoice->currency ?: 'INR';
    $amount = fn ($v) => number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $taxes = $invoice->taxes->filter(fn ($t) => (float) $t->rate > 0);
    $client = $invoice->client;
    $stampColor = ['paid' => '#1e8e5a', 'cancelled' => '#c0392b', 'draft' => '#64707d'][$invoice->status] ?? null;
    $inWords = strtoupper(app(\App\Services\InvoiceService::class)->amountInWords((float) $invoice->total, $cur));
    $declaration = collect([$invoice->terms, $invoice->notes])->filter()->implode("\n");
    // Pad the item grid like a printed invoice book, but give the space back to a
    // long declaration (about 90 characters per printed line) so it stays on one page.
    $declarationLines = collect(preg_split('/\R/', (string) $declaration))
        ->sum(fn ($line) => max(1, (int) ceil(mb_strlen($line) / 90)));
    $fillerHeight = max(0, (10 - $invoice->items->count()) * 20 - max(0, $declarationLines - 3) * 12);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { margin: 10mm 12mm 10mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #222; line-height: 1.3; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .r { text-align: right; white-space: nowrap; }
        .c { text-align: center; }
        .b { font-weight: bold; }

        .head td { vertical-align: middle; }
        .company-title { font-size: 16pt; font-weight: bold; text-align: center; letter-spacing: .5px; }
        .doc-type { text-align: center; text-decoration: underline; margin-top: 4px; }
        .meta { text-align: right; margin-top: 8px; line-height: 1.5; }
        .stamp { display: inline-block; border: 1.5pt solid; padding: 1px 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; font-size: 8pt; }

        .parties { margin-top: 12px; border: .75pt solid #555; }
        .parties td { width: 50%; padding: 12px 14px; }
        .parties td.from { border-right: .75pt solid #555; }
        .party-name { font-size: 11pt; font-weight: bold; margin-bottom: 5px; }
        .gap { margin-top: 5px; }

        .grid { margin-top: 14px; }
        .grid th, .grid td { border-left: .75pt solid #555; border-right: .75pt solid #555; padding: 4px 6px; }
        .grid thead th { border: .75pt solid #555; font-weight: bold; text-align: center; padding: 5px 6px; }
        .grid tbody tr.item td { border-top: 0; border-bottom: 0; }
        .grid tr.sum td { border: .75pt solid #555; padding: 5px 6px; }

        .footer-box { margin-top: 12px; border: .75pt solid #555; page-break-inside: avoid; }
        .footer-box td { padding: 6px 8px; }
        .footer-box td.sign { width: 30%; border-left: .75pt solid #555; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width:22%">
                @if (! empty($company['logo']))
                    <img src="{{ $company['logo'] }}" alt="" style="max-height:55px;max-width:140px">
                @endif
            </td>
            <td style="width:56%">
                <div class="company-title">{{ strtoupper($company['name']) }}</div>
                <div class="doc-type">{{ $taxes->isNotEmpty() ? 'TAX INVOICE' : 'INVOICE' }}</div>
            </td>
            <td style="width:22%"></td>
        </tr>
    </table>

    <div class="meta">
        <div>Invoice Date: {{ $invoice->issue_date?->format('d/m/Y') }}</div>
        <div>Invoice No: {{ $invoice->number }}</div>
        @if ($invoice->due_date)<div>Due Date: {{ $invoice->due_date->format('d/m/Y') }}</div>@endif
        @if ($invoice->payment_mode)<div>Payment Mode: {{ $invoice->payment_mode }}</div>@endif
        @if ($invoice->status === 'paid' && $invoice->payment_reference)<div>Payment Ref: {{ $invoice->payment_reference }}</div>@endif
        @if ($stampColor)
            <div style="margin-top:4px"><span class="stamp" style="color:{{ $stampColor }};border-color:{{ $stampColor }}">{{ $invoice->status }}{{ $invoice->status === 'paid' && $invoice->paid_at ? ' · '.$invoice->paid_at->format('d/m/Y') : '' }}</span></div>
        @endif
    </div>

    <table class="parties">
        <tr>
            <td class="from">
                <div class="party-name">{{ $company['name'] }}</div>
                @if ($company['address'])<div>{!! nl2br(e($company['address'])) !!}</div>@endif
                @if ($company['tax_id'])<div>GSTN Number: {{ $company['tax_id'] }}</div>@endif
                <div class="gap">
                    @if ($company['phone'])<div>Tel: {{ $company['phone'] }}</div>@endif
                    @if ($company['email'])<div>Email: {{ $company['email'] }}</div>@endif
                </div>
            </td>
            <td>
                <div class="party-name">{{ $client?->name }}</div>
                @if ($client?->contact_person)<div>Attn: {{ $client->contact_person }}</div>@endif
                @if ($client?->address)<div>{!! nl2br(e($client->address)) !!}</div>@endif
                @if ($client?->city || $client?->country)<div>{{ collect([$client->city, $client->country])->filter()->implode(', ') }}</div>@endif
                @if ($client?->tax_id)<div>GSTN Number: {{ $client->tax_id }}</div>@endif
                <div class="gap">
                    @if ($client?->phone)<div>Tel: {{ $client->phone }}</div>@endif
                    @if ($client?->email)<div>Email: {{ $client->email }}</div>@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
        <tr>
            <th style="width:6%">No</th>
            <th>Description</th>
            <th style="width:13%">Hour</th>
            <th style="width:15%">Amount({{ $cur }})</th>
            <th style="width:16%">Total({{ $cur }})</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr class="item">
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->description }}</td>
                <td class="r">{{ $qty($item->quantity) }}</td>
                <td class="r">{{ $amount($item->unit_price) }}</td>
                <td class="r">{{ $amount($item->amount) }}</td>
            </tr>
        @endforeach
        @if ($fillerHeight > 0)
            <tr class="item"><td style="height:{{ $fillerHeight }}px"></td><td></td><td></td><td></td><td></td></tr>
        @endif
        <tr class="sum">
            <td colspan="4" class="r">Sub-Total</td>
            <td class="r">({{ $cur }}) {{ $amount($invoice->subtotal) }}</td>
        </tr>
        <tr class="sum">
            <td colspan="4" class="r">Discount</td>
            <td class="r">{{ (float) $invoice->discount > 0 ? '- ' : '' }}{{ $amount($invoice->discount) }}</td>
        </tr>
        @foreach ($taxes as $tax)
            <tr class="sum">
                <td colspan="4" class="r">{{ $tax->label }}</td>
                <td class="r">{{ $amount($tax->amount) }}</td>
            </tr>
        @endforeach
        <tr class="sum">
            <td colspan="4" class="r">Commission Fee</td>
            <td class="r">{{ $amount($invoice->commission_fee) }}</td>
        </tr>
        <tr class="sum">
            <td colspan="2">Amount in Words: {{ $inWords }}</td>
            <td colspan="2" class="r b" style="vertical-align:middle">Total</td>
            <td class="r b" style="vertical-align:middle">({{ $cur }}) {{ $amount($invoice->total) }}</td>
        </tr>
        </tbody>
    </table>

    <table class="footer-box">
        <tr>
            <td>
                <div>Declaration:</div>
                @if ($declaration)<div>{!! nl2br(e($declaration)) !!}</div>@endif
                <div>Thank you for your business! {{ $company['name'] }}</div>
            </td>
            <td class="sign">
                <div>{{ $company['name'] }}</div>
                <div class="c" style="margin-top:40px">Stamp &amp; Signature</div>
            </td>
        </tr>
    </table>
</body>
</html>
