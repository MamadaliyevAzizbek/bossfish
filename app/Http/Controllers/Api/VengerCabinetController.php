<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VengerClient;
use App\Models\VengerResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VengerCabinetController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'role']),
            'stats' => [
                'clients_count' => VengerClient::query()->count(),
                'results_count' => VengerResult::query()->count(),
            ],
        ]);
    }
}
