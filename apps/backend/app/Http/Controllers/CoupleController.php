<?php

namespace App\Http\Controllers;

use App\Http\Requests\LinkPartnerRequest;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoupleController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $couple = Couple::query()
            ->with(['user1', 'user2'])
            ->where('user1_id', $authUser->id)
            ->orWhere('user2_id', $authUser->id)
            ->first();

        if (!$couple) {
            return response()->json(['error' => 'Casal não encontrado.'], 404);
        }

        return response()->json([
            'couple' => $this->serializeCouple($couple),
        ]);
    }

    public function store(LinkPartnerRequest $request): JsonResponse
    {
        $authUser = $request->user();

        $data = $request->validated();

        $partner = User::query()->where('email', $data['partnerEmail'])->first();

        if (!$partner) {
            return response()->json(['error' => 'Parceiro(a) não encontrado(a).'], 404);
        }

        if ((int) $partner->id === (int) $authUser->id) {
            return response()->json(['error' => 'Você não pode vincular sua própria conta.'], 422);
        }

        $alreadyLinked = Couple::query()
            ->where('user1_id', $authUser->id)
            ->orWhere('user2_id', $authUser->id)
            ->orWhere('user1_id', $partner->id)
            ->orWhere('user2_id', $partner->id)
            ->exists();

        if ($alreadyLinked) {
            return response()->json(['error' => 'Um dos usuários já pertence a um casal.'], 422);
        }

        $quota = (float) $data['user1Quota'];
        $couple = Couple::query()->create([
            'user1_id' => $authUser->id,
            'user2_id' => $partner->id,
            'user1_quota' => $quota,
            'user2_quota' => round(1 - $quota, 4),
        ]);

        $couple->load(['user1', 'user2']);

        return response()->json([
            'couple' => $this->serializeCouple($couple),
        ], 201);
    }

    private function serializeCouple(Couple $couple): array
    {
        return [
            'id' => (string) $couple->id,
            'user1Id' => (string) $couple->user1_id,
            'user2Id' => (string) $couple->user2_id,
            'user1Quota' => (float) $couple->user1_quota,
            'user2Quota' => (float) $couple->user2_quota,
            'createdAt' => optional($couple->created_at)?->toISOString(),
            'user1' => $couple->user1 ? [
                'id' => (string) $couple->user1->id,
                'name' => $couple->user1->name,
                'email' => $couple->user1->email,
                'createdAt' => optional($couple->user1->created_at)?->toISOString(),
            ] : null,
            'user2' => $couple->user2 ? [
                'id' => (string) $couple->user2->id,
                'name' => $couple->user2->name,
                'email' => $couple->user2->email,
                'createdAt' => optional($couple->user2->created_at)?->toISOString(),
            ] : null,
        ];
    }
}
