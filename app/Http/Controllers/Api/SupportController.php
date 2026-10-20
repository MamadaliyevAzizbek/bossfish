<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function info(): JsonResponse
    {
        return response()->json([
            'phone' => config('support.phone'),
            'phone_tel' => config('support.phone_tel'),
            'hours' => config('support.hours'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $data = $request->validate([
                'message' => 'required|string|min:5|max:4000',
            ]);

            $message = SupportMessage::create([
                'user_id' => $user->id,
                'user_role' => $user->role,
                'user_name' => $user->name,
                'user_phone' => $user->phone,
                'message' => $data['message'],
            ]);
        } else {
            $data = $request->validate([
                'name' => 'required|string|max:120',
                'phone' => 'required|string|max:32',
                'message' => 'required|string|min:5|max:4000',
            ]);

            $message = SupportMessage::create([
                'user_id' => null,
                'user_role' => 'guest',
                'user_name' => $data['name'],
                'user_phone' => $data['phone'],
                'message' => $data['message'],
            ]);
        }

        return response()->json([
            'message' => 'Murojaatingiz qabul qilindi',
            'item' => [
                'id' => $message->id,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ],
        ], 201);
    }
}
