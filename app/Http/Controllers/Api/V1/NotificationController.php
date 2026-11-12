<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->appNotifications()->latest()->paginate(20);

        return ApiResponder::success([
            'unread_count' => $request->user()->appNotifications()->whereNull('read_at')->count(),
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ], 'Notifications retrieved');
    }

    public function markRead(Request $request, InAppNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return ApiResponder::error('Not found.', 404);
        }

        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return ApiResponder::success(null, 'Notification marked as read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return ApiResponder::success(null, 'All notifications marked as read');
    }

    public function getPreferences(Request $request): JsonResponse
    {
        $preferences = $request->user()->notificationPreference?->preferences
            ?? ['push' => true, 'email' => true, 'in_app' => true];

        return ApiResponder::success($preferences, 'Preferences retrieved');
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push' => ['boolean'],
            'email' => ['boolean'],
            'in_app' => ['boolean'],
        ]);

        $preference = $request->user()->notificationPreference()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['preferences' => $validated]
        );

        return ApiResponder::success($preference->preferences, 'Preferences updated');
    }
}
