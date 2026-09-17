<?php

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($data);

        return response()->json([
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $token = $user->createToken('duofinance-web')->plainTextToken;

        $couple = Couple::query()
            ->where('user1_id', $user->id)
            ->orWhere('user2_id', $user->id)
            ->first();

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
            'coupleId' => $couple ? (string) $couple->id : null,
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'createdAt' => optional($user->created_at)?->toISOString(),
        ];
    }
}
