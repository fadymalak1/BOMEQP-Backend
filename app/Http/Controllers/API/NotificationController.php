<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    #[OA\Get(
        path: "/notifications",
        summary: "Get all notifications",
        description: "Get all notifications for the authenticated user with optional filtering and pagination.",
        tags: ["Notifications"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "is_read", in: "query", schema: new OA\Schema(type: "boolean"), example: false),
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string"), example: "info"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Notifications retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "notifications", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pagination", type: "object"),
                        new OA\Property(property: "unread_count", type: "integer", example: 5)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = $user->notifications()->orderBy('created_at', 'desc');

        // Filter by read/unread status
        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $notifications = $query->paginate($perPage);

        // Translate notifications based on user's current language preference
        $translatedNotifications = $this->translateNotifications($notifications->items(), $user);

        return response()->json([
            'success' => true,
            'notifications' => $translatedNotifications,
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    #[OA\Get(
        path: "/notifications/unread-count",
        summary: "Get unread notifications count",
        description: "Get the count of unread notifications for the authenticated user.",
        tags: ["Notifications"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Unread count retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "unread_count", type: "integer", example: 5)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    #[OA\Get(
        path: "/notifications/{id}",
        summary: "Get notification",
        description: "Get a specific notification by ID.",
        tags: ["Notifications"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Notification retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "notification", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Notification not found")
        ]
    )]
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);

        // Translate notification based on user's current language preference
        $translatedNotification = $this->translateNotifications([$notification], $user)[0];

        return response()->json([
            'success' => true,
            'notification' => $translatedNotification,
        ]);
    }

    #[OA\Put(
        path: "/notifications/{id}/read",
        summary: "Mark notification as read",
        description: "Mark a specific notification as read.",
        tags: ["Notifications"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Notification marked as read",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Notification marked as read"),
                        new OA\Property(property: "notification", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Notification not found")
        ]
    )]
    public function markAsRead(Request $request, int $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsRead();

        // Translate notification based on user's current language preference
        $translatedNotification = $this->translateNotifications([$notification->fresh()], $user)[0];

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'notification' => $translatedNotification,
        ]);
    }

    #[OA\Put(
        path: "/notifications/{id}/unread",
        summary: "Mark notification as unread",
        description: "Mark a specific notification as unread.",
        tags: ["Notifications"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Notification marked as unread",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Notification marked as unread"),
                        new OA\Property(property: "notification", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Notification not found")
        ]
    )]
    public function markAsUnread(Request $request, int $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsUnread();

        // Translate notification based on user's current language preference
        $translatedNotification = $this->translateNotifications([$notification->fresh()], $user)[0];

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as unread',
            'notification' => $translatedNotification,
        ]);
    }

    #[OA\Post(
        path: "/notifications/mark-all-read",
        summary: "Mark all notifications as read",
        description: "Mark all notifications for the authenticated user as read.",
        tags: ["Notifications"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "All notifications marked as read",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "5 notification(s) marked as read"),
                        new OA\Property(property: "updated_count", type: "integer", example: 5)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $updated = $user->notifications()->where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} notification(s) marked as read",
            'updated_count' => $updated,
        ]);
    }

    #[OA\Delete(
        path: "/notifications/{id}",
        summary: "Delete notification",
        description: "Delete a specific notification.",
        tags: ["Notifications"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Notification deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Notification deleted successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Notification not found")
        ]
    )]
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully',
        ]);
    }

    #[OA\Delete(
        path: "/notifications/read",
        summary: "Delete all read notifications",
        description: "Delete all read notifications for the authenticated user.",
        tags: ["Notifications"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Read notifications deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "10 notification(s) deleted"),
                        new OA\Property(property: "deleted_count", type: "integer", example: 10)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function deleteRead(Request $request)
    {
        $user = $request->user();
        $deleted = $user->notifications()->where('is_read', true)->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} notification(s) deleted",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Translate notifications based on user's language preference
     */
    private function translateNotifications(array $notifications, $user): array
    {
        $language = $user && $user->language ? $user->language : 'en';
        
        return array_map(function ($notification) use ($language) {
            // Get translation using the notification type and data
            $translated = $this->notificationService->getTranslatedNotification(
                $notification->type,
                $language,
                $notification->data ?? []
            );
            
            // Create a copy of the notification with translated title and message
            $notificationArray = $notification->toArray();
            $notificationArray['title'] = $translated['title'];
            $notificationArray['message'] = $translated['message'];
            
            return $notificationArray;
        }, $notifications);
    }
}

