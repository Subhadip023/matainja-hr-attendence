<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends AdminController
{
    public function index(Request $request): View
    {
        $logs = AuditLog::with('actor:id,name,email')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
            ->when($request->query('actor'), fn ($q, $u) => $q->where('actor_user_id', $u))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->pluck('action')
                ->map(fn ($a) => explode('.', (string) $a)[0])->filter()->unique()->sort()->values(),
            'actors' => User::whereIn('id', AuditLog::query()->select('actor_user_id')->distinct())->orderBy('name')->get(['id', 'name']),
            'tz' => $this->tz(),
        ]);
    }
}
