<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterDebtPaymentRequest;
use App\Http\Resources\DebtPaymentResource;
use App\Http\Resources\MonthlyCloseResource;
use App\Models\Couple;
use App\Models\MonthlyClose;
use App\Services\ClearingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClearingController extends Controller
{
    public function __construct(
        protected ClearingService $clearingService
    ) {}

    public function show(Request $request, string $month): JsonResponse
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json(['error' => 'Invalid month format. Use YYYY-MM'], 400);
        }

        $couple = $this->getCouple($request);

        $summary = $this->clearingService->getMonthSummary($couple, $month);

        return response()->json([
            'summary' => $summary,
        ]);
    }

    public function close(Request $request, string $month): JsonResponse
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json(['error' => 'Invalid month format. Use YYYY-MM'], 400);
        }

        $couple = $this->getCouple($request);

        $close = $this->clearingService->closeMonth($couple, $month);

        return response()->json([
            'close' => (new MonthlyCloseResource($close))->toArray($request),
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $couple = $this->getCouple($request);

        $history = $this->clearingService->listCloseHistory($couple);

        return response()->json([
            'history' => $history->map(
                fn (MonthlyClose $c) => (new MonthlyCloseResource($c))->toArray($request)
            )->values()->all(),
        ]);
    }

    public function debts(Request $request): JsonResponse
    {
        $couple = $this->getCouple($request);

        $debt = $this->clearingService->getDebtStatus($couple);

        return response()->json([
            'debt' => $debt,
        ]);
    }

    public function registerPayment(RegisterDebtPaymentRequest $request): JsonResponse
    {
        $couple = $this->getCouple($request);

        $payment = $this->clearingService->registerDebtPayment($couple, $request->validated());

        return response()->json([
            'payment' => (new DebtPaymentResource($payment))->toArray($request),
        ], 201);
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
