<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(
        path: '/api/notifications',
        summary: 'List Latest In-App Notifications',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Notification List Fetched'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = $user->notifications()->latest()->limit(50)->get();

        return response()->json([
            'data' => $items->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? class_basename($notification->type),
                'message' => $notification->data['message'] ?? '',
                'month' => $notification->data['month'] ?? '',
                'amount' => (int) ($notification->data['amount'] ?? 0),
                'contract_id' => (int) ($notification->data['contract_id'] ?? 0),
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
            ]),
            'unread' => $user->unreadNotifications()->count(),
        ], 200);
    }

    #[OA\Post(
        path: '/api/notifications/{id}/read',
        summary: 'Mark One Notification as Read',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Notification Marked as Read'),
            new OA\Response(response: 404, description: 'Notification Not Found')
        ]
    )]
    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/notifications/read-all',
        summary: 'Mark All Notifications as Read',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 204, description: 'All Notifications Marked as Read')
        ]
    )]
    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(null, 204);
    }
}
