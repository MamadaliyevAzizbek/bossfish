<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use App\Support\WorkerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'phone' => Phone::normalize((string) $request->input('phone', '')),
        ]);

        $credentials = $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
            'role' => 'nullable|in:director,worker',
        ]);

        $phone = Phone::normalize($credentials['phone']);
        $user = User::where('phone', $phone)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Telefon yoki parol noto\'g\'ri.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['Hisobingiz faol emas.'],
            ]);
        }

        if ($user->isOwner() || $user->isVenger()) {
            throw ValidationException::withMessages([
                'phone' => ['Bu hisob bu sahifadan kira olmaydi.'],
            ]);
        }

        if (! empty($credentials['role']) && $user->role !== $credentials['role']) {
            throw ValidationException::withMessages([
                'phone' => ['Bu hisob tanlangan rolga mos emas.'],
            ]);
        }

        if ($user->isDirector() && $user->access_expired) {
            throw ValidationException::withMessages([
                'phone' => ['Kirish muddati tugagan. BOSS bilan bog‘laning.'],
            ]);
        }

        if ($user->isWorker() && ($blockMessage = WorkerAccess::loginBlockMessage($user))) {
            throw ValidationException::withMessages([
                'phone' => [$blockMessage],
            ]);
        }

        $tokenPayload = $this->issueSpaToken($user, 'spa');

        return response()->json($tokenPayload);
    }

    public function ownerLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', mb_strtolower(trim($data['login'])))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Login yoki parol noto\'g\'ri.'],
            ]);
        }

        if (! $user->isOwner() || ! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Bu hisob egasi emas yoki faol emas.'],
            ]);
        }

        $tokenPayload = $this->issueSpaToken($user, 'owner-spa');

        return response()->json($tokenPayload);
    }

    public function vengerLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', mb_strtolower(trim($data['login'])))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Login yoki parol noto\'g\'ri.'],
            ]);
        }

        if (! $user->isVenger() || ! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Bu hisob Venger Carp hodimiga tegishli emas yoki faol emas.'],
            ]);
        }

        $tokenPayload = $this->issueSpaToken($user, 'venger-spa');

        return response()->json($tokenPayload);
    }

    /** @return array{user: User, token: string, expires_at: string} */
    private function issueSpaToken(User $user, string $name): array
    {
        $days = max(1, (int) config('auth.spa_remember_days', 30));
        $expiresAt = now()->addDays($days);
        $token = $user->createToken($name, ['*'], $expiresAt)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = $user->toArray();

        if ($user->isWorker()) {
            $director = WorkerAccess::linkedDirector($user);
            $payload['linked_director'] = $director ? [
                'id' => $director->id,
                'name' => $director->name,
                'phone' => $director->phone,
                'access_expires_at' => optional($director->access_expires_at)->toIso8601String(),
                'access_days_left' => $director->access_days_left,
                'access_expired' => $director->access_expired,
            ] : null;
            $payload['director_access_blocked'] = WorkerAccess::isBlockedByDirectorAccess($user);
        }

        return response()->json($payload);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Chiqildi']);
    }
}
