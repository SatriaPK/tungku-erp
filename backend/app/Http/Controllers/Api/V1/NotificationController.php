<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\IndexNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(IndexNotificationsRequest $request): AnonymousResourceCollection
    {
        $query = $request->user()->notifications();
        if ($request->has('unread')) {
            if ($request->boolean('unread')) {
                $query->whereNull('read_at');
            } else {
                $query->whereNotNull('read_at');
            }
        }

        return NotificationResource::collection($query->reorder()->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))->withQueryString());
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread_count' => $request->user()->unreadNotifications()->count()]]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $row = DB::transaction(function () use ($request, $notification): DatabaseNotification {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $row = $user->notifications()->whereKey($notification)->lockForUpdate()->firstOrFail();
            if ($row->read_at === null) {
                $row->markAsRead();
            }

            return $row;
        });

        return response()->json(['data' => new NotificationResource($row)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = DB::transaction(function () use ($request): int {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            return $user->unreadNotifications()->update(['read_at' => now()]);
        });

        return response()->json(['data' => ['updated_count' => $count]]);
    }
}
