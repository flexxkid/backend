<?php

namespace App\Http\Controllers;

use App\Models\Notifications;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Notifications::query()
                ->where('RecipientUserID', $request->user()->UserID)
                ->orderByDesc('CreatedAt')
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notifications::query()
            ->where('RecipientUserID', $request->user()->UserID)
            ->findOrFail($id);
        $notification->update([
            'IsRead' => true,
            'ReadAt' => Carbon::now(),
        ]);

        return response()->json($notification);
    }
}
