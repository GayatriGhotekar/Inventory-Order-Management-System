<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Notifications retrieved successfully', 'data' => $request->user()->notifications()->latest()->paginate(20)]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return response()->json(['success' => true, 'message' => 'Notification marked as read', 'data' => $item->fresh()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true, 'message' => 'All notifications marked as read']);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate(['in_app_notifications' => ['required', 'boolean'], 'email_notifications' => ['required', 'boolean']]);
        $request->user()->update($data);

        return response()->json(['success' => true, 'message' => 'Notification preferences updated', 'data' => $request->user()->fresh()]);
    }
}
