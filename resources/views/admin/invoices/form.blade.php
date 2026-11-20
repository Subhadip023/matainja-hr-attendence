@extends('admin.layout')
@section('title', $invoice->exists ? 'Edit '.$invoice->number : 'New invoice')

@section('content')
    @php
        $v = fn ($field) => old($field, $invoice->{$field} instanceof \DateTimeInterface ? $invoice->{$field}->format('Y-m-d') : $invoice->{$field});
        $items = old('items', $invoice->exists
            ? $invoice->items->map(fn ($i) => $i->only(['description', 'quantity', 'unit_price']))->all()
            : [['description' => '', 'quantity' => 1, 'unit_price' => '']]);
        $items = array_values($items);
    @endphp

    <div class="page-head">
        <div>
            <a class="small" href="{{ $invoice->exists ? route('admin.invoices.show', $invoice) : route('admin.invoices.index') }}">← {{ $invoice->exists ? $invoice->number : 'Invoices' }}</a>
            <h1 style="margin-top:4px">{{ $invoice->exists ? 'Edit invoice '.$invoice->number : 'New invoice' }}</h1>
            <p>Pick the client, add what you're billing for, and save. New invoices start as drafts.</p>
        </div>
    </div>

    @if ($clients->isEmpty())
        <div class="alert info">You have no active clients yet. <a href="{{ route('admin.org.index', 'clients') }}">Add a client</a> first.</div>
    @endif

    <form method="POST" action="{{ $invoice->exists ? route('admin.invoices.update', $invoice) : route('admin.invoices.store') }}" id="invoice-form">
        @csrf
        @if ($invoice->exists) @method('PUT') @endif

        <div class="card" style="margin-bottom:16px">
            <div class="card-head"><h2>Details</h2></div>
            <div class="card-body form-grid">
                <div class="field">
                    <label for="client_id">Client *</label>
                    <select id="client_id" name="client_id" required>
                        <option value="">Choose a client</option>
                        @foreach ($clients as $c)<option value="{{ $c->id }}" @selected($v('client_id') == $c->id)>{{ $c->name }} ({{ $c->code }})</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="number">Invoice number</label>
                    <input id="number" type="text" name="number" value="{{ $v('number') }}" placeholder="{{ $nextNumber ?? '' }}" maxlength="50">
                    @unless ($invoice->exists)<div class="hint">Leave blank to use {{ $nextNumber }}.</div>@endunless
                </div>
                <div class="field">
                    <label for="issue_date">Issue date *</label>
                    <input id="issue_date" type="date" name="issue_date" value="{{ $v('issue_date') }}" required>
                </div>
                <div class="field">
                    <label for="due_date">Due date</label>
                    <input id="due_date" type="date" name="due_date" value="{{ $v('due_date') }}">
                </div>
                <div class="field">
                    <label for="currency">Currency</label>
                    <input id="currency" type="text" name="currency" value="{{ $v('currency') ?: 'INR' }}" maxlength="3" style="text-transform:uppercase">
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px">
            <div class="card-head"><h2>Line items</h2></div>
            <div class="table-wrap">
                <table class="invoice-lines">
                    <thead>
                    <tr><th style="width:50%">Description</th><th class="right">Qty</th><th class="right">Unit price</th><th class="right">Amount</th><th></th></tr>
                    </thead>
                    <tbody id="lines">
                    @foreach ($items as $i => $item)
                        <tr class="line">
                            <td><input type="text" name="items[{{ $i }}][description]" value="{{ $item['description'] ?? '' }}" maxlength="500" placeholder="e.g. Website development – November"></td>
                            <td><input type="number" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" step="0.01" min="0.01" class="right" data-qty></td>
                            <td><input type="number" name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" step="0.01" min="0" class="right" data-price></td>
                            <td class="right nowrap" data-amount>0.00</td>
                            <td class="right"><button type="button" class="btn sm ghost" data-remove-line aria-label="Remove line">✕</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr><td colspan="5"><button type="button" class="btn sm" id="add-line">+ Add line</button></td></tr>
                    <tr><td colspan="3" class="right muted">Subtotal</td><td class="right nowrap" id="t-sub">0.00</td><td></td></tr>
                    @foreach ($taxes as $tax)
                        <tr data-tax-row="{{ $tax->id }}" @unless (in_array($tax->id, $selectedTaxIds, true)) hidden @endunless>
                            <td colspan="3" class="right muted">{{ $tax->label }}</td><td class="right nowrap" data-tax-amount>0.00</td><td></td>
                        </tr>
                    @endforeach
                    <tr><td colspan="3" class="right"><strong>Total</strong></td><td class="right nowrap"><strong id="t-total">0.00</strong></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px">
            <div class="card-head">
                <h2>Taxes</h2>
                <a class="small" href="{{ route('admin.org.index', 'taxes') }}" target="_blank" rel="noopener">Manage taxes</a>
            </div>
            <div class="card-body">
                @if ($taxes->isEmpty())
                    <p class="muted" style="margin:0">No taxes set up yet. <a href="{{ route('admin.org.index', 'taxes') }}">Add taxes</a> (e.g. CGST 9%, SGST 9%, IGST 18%), then pick the ones this invoice needs.</p>
                @else
                    <div class="field" style="margin:0">
                        <label for="tax_ids">Taxes to apply</label>
                        <select id="tax_ids" name="tax_ids[]" multiple data-placeholder="Search and pick taxes, e.g. CGST, IGST…" style="width:100%">
                            @foreach ($taxes->groupBy('name') as $name => $group)
                                <optgroup label="{{ $name }}">
                                    @foreach ($group as $tax)
                                        <option value="{{ $tax->id }}" data-rate="{{ (float) $tax->rate }}" @selected(in_array($tax->id, $selectedTaxIds, true))>{{ $tax->label }}{{ $tax->is_active ? '' : ' (turned off)' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <div class="hint">Each tax is worked out on the subtotal. Same state as you: CGST + SGST. Another state: IGST. Export: none.</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Notes & terms</h2></div>
            <div class="card-body form-grid">
                <div class="field wide">
                    <label for="notes">Notes to the client</label>
                    <textarea id="notes" name="notes" maxlength="5000" placeholder="Thank you for your business.">{{ $v('notes') }}</textarea>
                </div>
                <div class="field wide">
                    <label for="terms">Payment terms / bank details</label>
                    <textarea id="terms" name="terms" maxlength="5000" placeholder="Bank: … A/C: … IFSC: …">{{ $v('terms') }}</textarea>
                    <div class="hint">Set a default for new invoices under Settings → Invoicing.</div>
                </div>
            </div>
        </div>

        <div class="actions" style="justify-content:flex-end;margin-top:16px">
            <a class="btn" href="{{ $invoice->exists ? route('admin.invoices.show', $invoice) : route('admin.invoices.index') }}">Cancel</a>
            <button class="btn primary" type="submit">{{ $invoice->exists ? 'Save invoice' : 'Create draft' }}</button>
        </div>
    </form>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css">
<style>
    /* Match Select2 to the admin inputs. */
    .select2-container--default .select2-selection--multiple { border: 1px solid var(--border); border-radius: 8px; min-height: 38px; padding: 2px 6px; }
    .select2-container--default.select2-container--focus .select2-selection--multiple { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-50); }
    .select2-container--default .select2-selection--multiple .select2-selection__choice { background: var(--brand-50); border: 1px solid #c5e0db; color: var(--brand-600); border-radius: 999px; padding: 1px 10px 1px 22px; font-weight: 600; margin-top: 5px; }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove { color: var(--brand); border: 0; left: 4px; top: 1px; }
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background: var(--brand); }
    .select2-container--default .select2-results__group { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .06em; }
    /* Select2's search box is a <textarea>: undo the admin textarea/input styles (80px min-height, border, padding). */
    .select2-container .select2-search--inline .select2-search__field {
        min-height: 0; height: 22px; width: auto; padding: 0; border: 0; border-radius: 0; outline: 0;
        margin: 7px 0 0 4px; resize: none; background: transparent; font-family: inherit; line-height: 22px;
    }
    .select2-container .select2-search--dropdown .select2-search__field { min-height: 0; padding: 6px 8px; }
    .select2-container--default .select2-selection--multiple .select2-selection__clear { margin-top: 7px; margin-right: 6px; }
    .select2-dropdown { border-color: var(--border); border-radius: 8px; }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
<script>
(function () {
    var body = document.getElementById('lines');
    var fmt = function (n) { return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var num = function (el) { var n = parseFloat(el && el.value); return isNaN(n) ? 0 : n; };

    function renumber() {
        body.querySelectorAll('tr.line').forEach(function (tr, i) {
            tr.querySelectorAll('input').forEach(function (inp) {
                inp.name = inp.name.replace(/items\[\d+\]/, 'items[' + i + ']');
            });
        });
    }
    function recalc() {
        var sub = 0, tax = 0;
        body.querySelectorAll('tr.line').forEach(function (tr) {
            var amount = Math.round(num(tr.querySelector('[data-qty]')) * num(tr.querySelector('[data-price]')) * 100) / 100;
            sub += amount;
            tr.querySelector('[data-amount]').textContent = fmt(amount);
        });
        sub = Math.round(sub * 100) / 100;
        document.querySelectorAll('#tax_ids option').forEach(function (opt) {
            var row = document.querySelector('[data-tax-row="' + opt.value + '"]');
            var amount = Math.round(sub * parseFloat(opt.getAttribute('data-rate'))) / 100;
            row.hidden = !opt.selected;
            row.querySelector('[data-tax-amount]').textContent = fmt(amount);
            if (opt.selected) tax += amount;
        });
        document.getElementById('t-sub').textContent = fmt(sub);
        document.getElementById('t-total').textContent = fmt(sub + tax);
    }

    // Tax picker: Select2 when it loaded from the CDN, otherwise the plain multi-select still works.
    var taxSelect = document.getElementById('tax_ids');
    if (taxSelect) {
        taxSelect.addEventListener('change', recalc);
        if (window.jQuery && jQuery.fn.select2) {
            jQuery(taxSelect).select2({ closeOnSelect: false, allowClear: true, width: '100%' })
                .on('change', recalc); // Select2 fires jQuery events, not native ones
        }
    }

    document.getElementById('add-line').addEventListener('click', function () {
        var last = body.querySelector('tr.line:last-child');
        var row = last.cloneNode(true);
        row.querySelectorAll('input').forEach(function (inp) {
            inp.value = inp.hasAttribute('data-qty') ? 1 : '';
        });
        body.appendChild(row);
        renumber(); recalc();
        row.querySelector('input').focus();
    });
    body.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove-line]');
        if (!btn) return;
        if (body.querySelectorAll('tr.line').length === 1) {
            btn.closest('tr').querySelectorAll('input').forEach(function (inp) { if (!inp.hasAttribute('data-qty')) inp.value = ''; });
        } else {
            btn.closest('tr').remove();
        }
        renumber(); recalc();
    });
    body.addEventListener('input', recalc);
    recalc();
})();
</script>
@endpush
