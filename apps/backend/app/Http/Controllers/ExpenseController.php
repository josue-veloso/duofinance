<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\InstallmentResource;
use App\Models\Couple;
use App\Models\ExpenseAudit;
use App\Models\Installment;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    public function indexByMonth(Request $request, string $month): JsonResponse
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json(['error' => 'Invalid month format. Use YYYY-MM'], 400);
        }

        $couple = $this->getCouple($request);

        $filters = [];
        if ($request->filled('search')) {
            $filters['search'] = $request->input('search');
        }
        if ($request->filled('categoryId')) {
            $filters['categoryId'] = $request->input('categoryId');
        }
        if ($request->filled('paidByUserId')) {
            $filters['paidByUserId'] = $request->input('paidByUserId');
        }
        if ($request->has('isShared')) {
            $val = $request->input('isShared');
            if ($val === 'true' || $val === true || $val === '1' || $val === 1) {
                $filters['isShared'] = true;
            } elseif ($val === 'false' || $val === false || $val === '0' || $val === 0) {
                $filters['isShared'] = false;
            }
        }

        $installments = $this->expenseService->listExpensesByMonth(
            $couple,
            $request->user(),
            $month,
            $filters
        );

        return response()->json([
            'installments' => $installments->map(
                fn (Installment $i) => (new InstallmentResource($i))->toArray($request)
            )->values()->all(),
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $couple = $this->getCouple($request);

        $expense = $this->expenseService->createExpense(
            $couple,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'expense' => (new ExpenseResource($expense))->toArray($request),
        ], 201);
    }

    public function update(UpdateExpenseRequest $request, string $id): JsonResponse
    {
        $couple = $this->getCouple($request);

        $expense = $this->expenseService->updateExpense(
            $couple,
            $request->user(),
            (int) $id,
            $request->validated()
        );

        return response()->json([
            'expense' => (new ExpenseResource($expense))->toArray($request),
        ]);
    }

    public function undo(Request $request, string $id): JsonResponse
    {
        $couple = $this->getCouple($request);

        $expense = $this->expenseService->undoLastExpenseEdit(
            $couple,
            $request->user(),
            (int) $id
        );

        return response()->json([
            'expense' => (new ExpenseResource($expense))->toArray($request),
        ]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $couple = $this->getCouple($request);

        $expense = $this->expenseService->restoreExpense(
            $couple,
            $request->user(),
            (int) $id
        );

        return response()->json([
            'expense' => (new ExpenseResource($expense))->toArray($request),
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $couple = $this->getCouple($request);

        $expense = $this->expenseService->approveExpense(
            $couple,
            $request->user(),
            (int) $id
        );

        return response()->json([
            'expense' => (new ExpenseResource($expense))->toArray($request),
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $couple = $this->getCouple($request);

        $expense = $this->expenseService->rejectExpense(
            $couple,
            $request->user(),
            (int) $id
        );

        return response()->json([
            'expense' => (new ExpenseResource($expense))->toArray($request),
        ]);
    }

    public function audits(Request $request, string $id): JsonResponse
    {
        $couple = $this->getCouple($request);

        $audits = $this->expenseService->listExpenseAudits($couple, (int) $id);

        return response()->json([
            'audits' => $audits->map(fn (ExpenseAudit $a) => $a->toFrontendArray())->values()->all(),
        ]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $couple = $this->getCouple($request);

        $this->expenseService->deleteExpense(
            $couple,
            $request->user(),
            (int) $id
        );

        return response()->noContent();
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
