<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
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

        return response()->json([
            'success' => true,
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Get unread notifications count
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * Get a specific notification
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);

        return response()->json([
            'success' => true,
            'notification' => $notification,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, int $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'notification' => $notification->fresh(),
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(Request $request, int $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsUnread();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as unread',
            'notification' => $notification->fresh(),
        ]);
    }

    /**
     * Mark all notifications as read
     */
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

    /**
     * Delete a notification
     */
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

    /**
     * Delete all read notifications
     */
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
     * Stream notifications using Server-Sent Events (SSE)
     * This endpoint keeps the connection open and sends notifications in real-time
     */
    public function stream(Request $request)
    {
        $user = $request->user();
        
        // Set headers for SSE
        $response = new StreamedResponse(function () use ($user, $request) {
            // Send initial connection message
            echo "data: " . json_encode([
                'type' => 'connected',
                'message' => 'Connected to notification stream',
                'timestamp' => now()->toIso8601String(),
            ]) . "\n\n";
            
            // Flush output immediately
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();

            // Get last notification ID to send only new notifications
            $lastNotificationId = $request->get('last_id', 0);
            
            // Track connection time
            $startTime = time();
            $maxConnectionTime = 300; // 5 minutes
            
            // Poll for new notifications
            while (true) {
                // Check connection time limit
                if (time() - $startTime > $maxConnectionTime) {
                    echo "data: " . json_encode([
                        'type' => 'timeout',
                        'message' => 'Connection timeout. Please reconnect.',
                        'timestamp' => now()->toIso8601String(),
                    ]) . "\n\n";
                    flush();
                    break;
                }

                // Check if connection is still alive (for client disconnection detection)
                if (connection_aborted()) {
                    break;
                }

                // Get new notifications
                $newNotifications = $user->notifications()
                    ->where('id', '>', $lastNotificationId)
                    ->orderBy('id', 'asc')
                    ->get();

                // Send new notifications
                foreach ($newNotifications as $notification) {
                    echo "data: " . json_encode([
                        'type' => 'notification',
                        'notification' => [
                            'id' => $notification->id,
                            'type' => $notification->type,
                            'title' => $notification->title,
                            'message' => $notification->message,
                            'data' => $notification->data,
                            'is_read' => $notification->is_read,
                            'created_at' => $notification->created_at->toIso8601String(),
                        ],
                        'timestamp' => now()->toIso8601String(),
                    ]) . "\n\n";
                    flush();

                    // Update last notification ID
                    $lastNotificationId = $notification->id;
                }

                // Send heartbeat every 30 seconds to keep connection alive
                echo ": heartbeat\n\n";
                flush();

                // Sleep for 2 seconds before checking again
                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no'); // Disable nginx buffering
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    /**
     * Stream unread count updates using Server-Sent Events
     * 
     * Query Parameters:
     * - token: Authentication token (optional, if not using Authorization header)
     */
    public function streamUnreadCount(Request $request)
    {
        // Support token from query parameter for SSE
        if ($request->has('token')) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->token);
            $user = $token?->tokenable;
            if (!$user) {
                abort(401, 'Invalid token');
            }
        } else {
            $user = $request->user();
        }
        
        $response = new StreamedResponse(function () use ($user) {
            // Send initial count
            $count = $user->unreadNotifications()->count();
            echo "data: " . json_encode([
                'type' => 'count',
                'unread_count' => $count,
                'timestamp' => now()->toIso8601String(),
            ]) . "\n\n";
            flush();

            $lastCount = $count;
            $startTime = time();
            $maxConnectionTime = 300; // 5 minutes

            while (true) {
                if (time() - $startTime > $maxConnectionTime) {
                    echo "data: " . json_encode([
                        'type' => 'timeout',
                        'message' => 'Connection timeout. Please reconnect.',
                        'timestamp' => now()->toIso8601String(),
                    ]) . "\n\n";
                    flush();
                    break;
                }

                if (connection_aborted()) {
                    break;
                }

                // Check for count changes
                $currentCount = $user->unreadNotifications()->count();
                
                if ($currentCount !== $lastCount) {
                    echo "data: " . json_encode([
                        'type' => 'count',
                        'unread_count' => $currentCount,
                        'timestamp' => now()->toIso8601String(),
                    ]) . "\n\n";
                    flush();
                    $lastCount = $currentCount;
                }

                // Send heartbeat
                echo ": heartbeat\n\n";
                flush();

                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}

