@extends('admin.layout')
@section('title', $invoice->number)

@section('content')
    @php
        $statusClass = ['draft' => '', 'sent' => 'info', 'paid' => 'ok', 'cancelled' => 'bad'];
        $labels = ['sent' => 'Mark as sent', 'paid' => 'Record payment', 'cancelled' => 'Cancel invoice', 'draft' => 'Back to draft'];
        $btnClass = ['sent' => 'primary', 'paid' => 'success', 'cancelled' => 'danger', 'draft' => ''];
    @endphp

    <div class="page-head">
        <div>
            <a class="small" href="{{ route('admin.invoices.index') }}">← Invoices</a>
            <h1 style="margin-top:4px">
                {{ $invoice->number }}
                <span class="badge {{ $statusClass[$invoice->status] ?? '' }}" style="vertical-align:middle">{{ ucfirst($invoice->status) }}</span>
                @if ($invoice->is_overdue)<span class="badge warn" style="vertical-align:middle">Overdue</span>@endif
            </h1>
            <p>{{ $invoice->client?->name }} · created {{ $invoice->created_at?->format('d M Y') }}{{ $invoice->creator ? ' by '.$invoice->creator->name : '' }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('admin.invoices.pdf', ['invoice' => $invoice, 'inline' => 1]) }}" target="_blank" rel="noopener">View PDF</a>            @unless ($invoice->isLocked())
                <a class="btn" href="{{ route('admin.invoices.edit', $invoice) }}">Edit</a>
            @endunless
            @foreach ($transitions as $to)
                @if ($to === 'paid')
                    <button type="button" class="btn {{ $btnClass[$to] }}" data-open-dialog="pay-dialog">{{ $labels[$to] }}</button>
                @else
                    <form class="inline" method="POST" action="{{ route('admin.invoices.status', $invoice) }}"
                          @if ($to === 'cancelled') data-confirm="Cancel invoice {{ $invoice->number }}? It stays on record but can't be paid." @endif>
                        @csrf
                        <input type="hidden" name="status" value="{{ $to }}">
                        <button class="btn {{ $btnClass[$to] }}">{{ $labels[$to] }}</button>
                    </form>
                @endif
            @endforeach
            @if ($invoice->status === 'draft')
                <form class="inline" method="POST" action="{{ route('admin.invoices.destroy', $invoice) }}" data-confirm="Delete draft {{ $invoice->number }}? This can't be undone.">
                    @csrf @method('DELETE')
                    <button class="btn danger">Delete</button>
                </form>
            @endif
        </div>
    </div>

    @if ($invoice->status === 'paid' && $invoice->payment_reference)
        <div class="alert success">Paid on {{ $invoice->paid_at?->format('d M Y') }} · reference {{ $invoice->payment_reference }}</div>
    @endif

    <div class="card" style="overflow:hidden">
        @include('admin.invoices._document')
    </div>

    @if (in_array('paid', $transitions, true))
        <dialog id="pay-dialog">
            <form method="POST" action="{{ route('admin.invoices.status', $invoice) }}" class="card-body">
                @csrf
                <input type="hidden" name="status" value="paid">
                <h2>Record payment</h2>
                <p class="muted" style="margin:6px 0 14px">{{ $invoice->number }} · {{ $invoice->client?->name }}</p>
                <div class="field">
                    <label for="paid_at">Payment date</label>
                    <input id="paid_at" type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="field">
                    <label for="payment_reference">Reference (UTR, cheque no.)</label>
                    <input id="payment_reference" type="text" name="payment_reference" maxlength="255">
                </div>
                <div class="actions" style="justify-content:flex-end">
                    <button type="button" class="btn" data-close-dialog>Cancel</button>
                    <button type="submit" class="btn success">Mark as paid</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
