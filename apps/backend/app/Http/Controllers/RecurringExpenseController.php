<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringExpenseRequest;
use App\Http\Resources\RecurringExpenseResource;
use App\Models\Couple;
use App\Models\RecurringExpense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $couple = $this->getCouple($request);

        $recurring = $this->expenseService->listRecurringExpenses($couple);

        return response()->json([
            'recurring' => $recurring->map(
                fn (RecurringExpense $r) => (new RecurringExpenseResource($r))->toArray($request)
            )->values()->all(),
        ]);
    }

    public function store(StoreRecurringExpenseRequest $request): JsonResponse
    {
        $couple = $this->getCouple($request);

        $recurring = $this->expenseService->createRecurringExpense(
            $couple,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'recurring' => (new RecurringExpenseResource($recurring))->toArray($request),
        ], 201);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $couple = $this->getCouple($request);

        $data = $request->validate([
            'isActive' => ['required', 'boolean'],
        ]);

        $recurring = $this->expenseService->updateRecurringExpenseStatus(
            $couple,
            (int) $id,
            (bool) $data['isActive']
        );

        return response()->json([
            'recurring' => (new RecurringExpenseResource($recurring))->toArray($request),
        ]);
    }

    protected function getCouple(Request $request): Couple
    {
        $couple = $request->attributes->get('couple') ?? $request->user()->currentCouple();

        if (!$couple) {
            abort(403, 'User is not part of a couple');
        }

        return $couple;
    }
}
