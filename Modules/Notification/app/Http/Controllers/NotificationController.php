<?php

namespace Modules\Notification\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Notification;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Notification\app\Http\Resources\NotificationResource;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *   path="/api/notifications",
     *   tags={"Notifications"},
     *   summary="Get all notifications (filter by read/unread)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number to retrieve.",
     *     required=false,
     *     @OA\Schema(type="integer", default=1)
     *   ),
     *   @OA\Parameter(
     *     name="pageSize",
     *     in="query",
     *     description="Number of notifications per page.",
     *     required=false,
     *     @OA\Schema(type="integer", default=15)
     *   ),
     *   @OA\Parameter(
     *     name="status",
     *     in="query",
     *     description="Filter notifications by status (read|unread)",
     *     required=false,
     *     @OA\Schema(type="string", enum={"read", "unread"})
     *   ),
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $borrowerId = auth()->guard('borrower')->user()->id;
        $perPage = $request->query('pageSize', 15);
        $status = $request->query('status');

        $query = Notification::where('borrower_id', $borrowerId)
        ->orderBy('created_at', 'desc');

        if ($status === 'read') {
            $query->where('read_at', "!=", null);
        } elseif ($status === 'unread') {
            $query->where('read_at', null);
        }

        $notifications = $query->paginate($perPage);
        $paginatedResource = NotificationResource::collection($notifications);

        $meta = [
            'current_page' => $notifications->currentPage(),
            'total_pages'  => $notifications->lastPage(),
            'total_items'  => $notifications->total(),
            'per_page'     => $notifications->perPage(),
        ];
        return $this->success([
            'items' => $paginatedResource,
            'meta' => $meta,
        ], 'Notifications retrieved successfully', 200);
    }


    /**
     * @OA\Post(
     *   path="/api/notifications/{id}/read",
     *   tags={"Notifications"},
     *   summary="Mark a single notification as read",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Notification ID",
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(response=200, description="Notification marked as read"),
     *   @OA\Response(response=404, description="Notification not found")
     * )
     */
    public function markAsRead($id)
    {
        $borrowerId = auth()->guard('borrower')->user()->id;
        $notification = Notification::where('borrower_id', $borrowerId)->find($id);

        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        $notification->update(['read_at' => now()]);

        return $this->success(new NotificationResource($notification), 'Notification marked as read', 200);
    }


    /**
     * @OA\Post(
     *   path="/api/notifications/read-all",
     *   tags={"Notifications"},
     *   summary="Mark all notifications as read",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="All notifications marked as read")
     * )
     */
    public function markAllAsRead()
    {
        $borrowerId = auth()->guard('borrower')->user()->id;

        Notification::where('borrower_id', $borrowerId)
            ->where('read_at', null)
            ->update(['read_at' => now()]);

        return $this->success([], 'All notifications marked as read', 200);
    }



    /**
     * @OA\Post(
     *     path="/api/notifications/send-push",
     *     tags={"Notifications"},
     *     summary="Send push notification to a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_email", "title", "message"},
     *             @OA\Property(property="user_email", type="string", example="someone@example.com"),
     *             @OA\Property(property="title", type="string", example="Hello"),
     *             @OA\Property(property="message", type="string", example="Greetings")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Message sent successfully"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     * )
     */
    public function SendPushNotificationTouser(Request $request)
    {
        
        $borrower = Borrower::where("email", $request->user_email)->first();
        if(!$borrower){
            return $this->error('Borrower not found', 404);
        }

        if(!$borrower->fcm_token){
            return $this->error('FCM token not found for borrower', 404);
        }

        $firebaseService = app()->make(\Modules\Notification\Services\FirebaseService::class);
        $send = $firebaseService->sendPush(
            $borrower->fcm_token,
            $request->title,
            $request->message,
            []
        );

        return $this->success($send, 'Notification sent', 200);
    }



    public function createNotification(array $newNotification): void
    {
        Notification::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => $newNotification['type'],
            'notifiable_type' => $newNotification['notifiable_type'],
            'notifiable_id' => 0,
            'data' => json_encode($newNotification['data']),
            'borrower_id' => $newNotification['borrower_id'] ?? null,
            'company_id' => $newNotification['company_id'] ?? null,
            'branch_id' => $newNotification['branch_id'] ?? null,
        ]);
    }

}
