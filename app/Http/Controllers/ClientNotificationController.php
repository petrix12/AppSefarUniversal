<?php

namespace App\Http\Controllers;

use App\Support\NotificationViewData;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ClientNotificationController extends Controller
{
    public function index()
    {
        $notificationsReady = $this->notificationsTableExists();

        $notifications = $notificationsReady
            ? request()->user()
                ->notifications()
                ->latest()
                ->paginate(20)
            : new LengthAwarePaginator(
                [],
                0,
                20,
                (int) request('page', 1),
                ['path' => request()->url(), 'query' => request()->query()]
            );

        return view('notifications.index', compact('notifications', 'notificationsReady'));
    }

    public function markAsRead(Request $request, string $notification)
    {
        if (! $this->notificationsTableExists()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Las notificaciones internas aun no estan activas.'], 422)
                : back()->with('success', 'Las notificaciones internas aun no estan activas.');
        }

        $notificationModel = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $notificationModel->markAsRead();

        $actionUrl = NotificationViewData::normalize($notificationModel)['action_url'] ?? null;

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return $actionUrl
            ? redirect()->to($actionUrl)
            : back();
    }

    public function markAllAsRead(Request $request)
    {
        if (! $this->notificationsTableExists()) {
            return back()->with('success', 'Las notificaciones internas aun no estan activas.');
        }

        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Notificaciones marcadas como leidas.');
    }

    private function notificationsTableExists(): bool
    {
        try {
            return Schema::hasTable('notifications');
        } catch (Throwable) {
            return false;
        }
    }
}
