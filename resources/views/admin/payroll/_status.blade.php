{{-- Payroll run status badge. Needs $status. --}}
<span class="badge {{ ['draft' => 'warn', 'locked' => 'info', 'paid' => 'ok'][$status] ?? '' }}">{{ \App\Models\PayrollRun::STATUSES[$status] ?? ucfirst($status) }}</span>
