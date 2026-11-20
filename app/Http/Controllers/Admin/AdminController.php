<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

abstract class AdminController extends Controller
{
    /** Office timezone used for "today" and for showing punch times. */
    protected function tz(): string
    {
        return once(fn () => Branch::query()->whereNotNull('timezone')->where('timezone', '!=', '')
            ->orderBy('id')->value('timezone') ?: config('app.timezone'));
    }

    protected function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->tz())->startOfDay();
    }

    /**
     * Turn a JSON response from one of the API controllers (reused so the
     * panel follows exactly the same business rules) into a flash message.
     */
    protected function backFromApi(JsonResponse $response, string $fallback = 'Done.'): RedirectResponse
    {
        $data = $response->getData(true);
        $ok = $response->isSuccessful() && ($data['success'] ?? true);
        $message = $data['message'] ?? $fallback;

        return back()->with($ok ? 'success' : 'error', $message);
    }
}
