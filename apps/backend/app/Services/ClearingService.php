<?php

namespace App\Services;

use App\Models\Couple;
use App\Models\DebtPayment;
use App\Models\Installment;
use App\Models\MonthlyClose;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClearingService
{
    /**
     * Compute the MonthSummary for a given couple + month.
     *
     * Clearing formula:
     *   balance_user1 = user1Paid - (totalShared × user1Quota)
     *   balance_user2 = user2Paid - (totalShared × user2Quota)
     */
    public function getMonthSummary(Couple $couple, string $month): array
    {
        $monthDate = Carbon::createFromFormat('Y-m', $month);
        $monthStart = $monthDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $monthDate->copy()->endOfMonth()->toDateString();

        $prevMonthDate = $monthDate->copy()->subMonthNoOverflow();
        $prevMonthStart = $prevMonthDate->copy()->startOfMonth()->toDateString();
        $prevMonthEnd = $prevMonthDate->copy()->endOfMonth()->toDateString();

        $couple->loadMissing(['user1', 'user2']);

        $installments = Installment::query()
            ->whereBetween('due_month', [$monthStart, $monthEnd])
            ->whereHas('expense', function ($q) use ($couple) {
                $q->where('couple_id', $couple->id)
                    ->where('is_shared', true)
                    ->where(function ($sq) {
                        $sq->whereNull('status')->orWhere('status', '!=', 'REJECTED');
                    });
            })
            ->with(['expense.paidByUser', 'expense.category', 'expense.approvedByUser'])
            ->orderBy('due_month', 'asc')
            ->get();

        $aggregates = Installment::query()
            ->join('expenses', 'installments.expense_id', '=', 'expenses.id')
            ->whereNull('expenses.deleted_at')
            ->where('expenses.couple_id', $couple->id)
            ->where('expenses.is_shared', true)
            ->where(function ($sq) {
                $sq->whereNull('expenses.status')->orWhere('expenses.status', '!=', 'REJECTED');
            })
            ->whereBetween('installments.due_month', [$monthStart, $monthEnd])
            ->selectRaw("
                COALESCE(SUM(installments.amount), 0) as total_shared,
                COALESCE(SUM(CASE WHEN expenses.paid_by_user_id = ? THEN installments.amount ELSE 0 END), 0) as user1_paid,
                COALESCE(SUM(CASE WHEN expenses.paid_by_user_id = ? THEN installments.amount ELSE 0 END), 0) as user2_paid,
                COALESCE(SUM(
                    CASE
                        WHEN expenses.split_mode = 'EQUAL_50_50' THEN installments.amount * 0.5
                        WHEN expenses.split_mode = 'CUSTOM' THEN installments.amount * COALESCE(expenses.custom_user1_quota, ?)
                        WHEN expenses.split_mode = 'FULL_USER1' THEN installments.amount
                        WHEN expenses.split_mode = 'FULL_USER2' THEN 0
                        ELSE installments.amount * ?
                    END
                ), 0) as user1_owed,
                COALESCE(SUM(CASE WHEN expenses.status = 'PENDING_APPROVAL' THEN 1 ELSE 0 END), 0) as pending_approval_count
            ", [$couple->user1_id, $couple->user2_id, $couple->user1_quota, $couple->user1_quota])
            ->first();

        $totalShared = (float) ($aggregates->total_shared ?? 0.0);
        $user1Paid = (float) ($aggregates->user1_paid ?? 0.0);
        $user2Paid = (float) ($aggregates->user2_paid ?? 0.0);
        $user1Owed = (float) ($aggregates->user1_owed ?? 0.0);
        $user2Owed = $totalShared - $user1Owed;

        $user1Balance = $user1Paid - $user1Owed;
        $user2Balance = $user2Paid - $user2Owed;
        $pendingApprovalCount = (int) ($aggregates->pending_approval_count ?? 0);

        $verdictPayerId = null;
        $verdictReceiverId = null;
        $verdictAmount = abs($user1Balance);

        if (abs($user1Balance) > 0.005) {
            if ($user1Balance > 0) {
                // user1 is owed -> user2 pays user1
                $verdictPayerId = (string) $couple->user2_id;
                $verdictReceiverId = (string) $couple->user1_id;
            } else {
                // user2 is owed -> user1 pays user2
                $verdictPayerId = (string) $couple->user1_id;
                $verdictReceiverId = (string) $couple->user2_id;
            }
        }

        $existingClose = MonthlyClose::query()
            ->where('couple_id', $couple->id)
            ->whereDate('month', $monthStart)
            ->first();

        $previousTotalShared = (float) Installment::query()
            ->join('expenses', 'installments.expense_id', '=', 'expenses.id')
            ->whereNull('expenses.deleted_at')
            ->where('expenses.couple_id', $couple->id)
            ->where('expenses.is_shared', true)
            ->where(function ($sq) {
                $sq->whereNull('expenses.status')->orWhere('expenses.status', '!=', 'REJECTED');
            })
            ->whereBetween('installments.due_month', [$prevMonthStart, $prevMonthEnd])
            ->sum('installments.amount');
        $previousTotalShared = round($previousTotalShared, 2);

        $deltaVsPreviousMonth = round($totalShared - $previousTotalShared, 2);

        $catRows = Installment::query()
            ->join('expenses', 'installments.expense_id', '=', 'expenses.id')
            ->leftJoin('categories', 'expenses.category_id', '=', 'categories.id')
            ->whereNull('expenses.deleted_at')
            ->where('expenses.couple_id', $couple->id)
            ->where('expenses.is_shared', true)
            ->where(function ($sq) {
                $sq->whereNull('expenses.status')->orWhere('expenses.status', '!=', 'REJECTED');
            })
            ->whereBetween('installments.due_month', [$monthStart, $monthEnd])
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw("
                categories.id as category_id,
                COALESCE(categories.name, 'Sem categoria') as name,
                SUM(installments.amount) as total
            ")
            ->orderByDesc('total')
            ->take(3)
            ->get();

        $topCategories = $catRows->map(function ($row) use ($totalShared) {
            $catTotal = round((float) $row->total, 2);
            return [
                'categoryId' => $row->category_id ? (string) $row->category_id : null,
                'name' => $row->name,
                'total' => $catTotal,
                'percent' => $totalShared <= 0 ? 0.0 : round(($catTotal / $totalShared) * 100, 2),
            ];
        })->all();

        return [
            'month' => $month,
            'couple' => [
                'id' => (string) $couple->id,
                'user1Id' => (string) $couple->user1_id,
                'user2Id' => (string) $couple->user2_id,
                'user1Quota' => (float) $couple->user1_quota,
                'user2Quota' => (float) $couple->user2_quota,
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
            ],
            'totalShared' => round($totalShared, 2),
            'user1Paid' => round($user1Paid, 2),
            'user2Paid' => round($user2Paid, 2),
            'user1Balance' => round($user1Balance, 2),
            'user2Balance' => round($user2Balance, 2),
            'verdictAmount' => round($verdictAmount, 2),
            'verdictPayerId' => $verdictPayerId,
            'verdictReceiverId' => $verdictReceiverId,
            'isClosed' => !is_null($existingClose?->closed_at),
            'previousTotalShared' => $previousTotalShared,
            'deltaVsPreviousMonth' => $deltaVsPreviousMonth,
            'sharedCount' => $installments->count(),
            'pendingApprovalCount' => $pendingApprovalCount,
            'topCategories' => $topCategories,
            'outstandingDebt' => $this->getDebtStatus($couple)['outstanding'],
            'installments' => $installments->map(fn ($i) => $i->toFrontendArray(true))->values()->all(),
        ];
    }

    /**
     * Persist a MonthlyClose record and mark it as closed.
     */
    public function closeMonth(Couple $couple, string $month): MonthlyClose
    {
        $summary = $this->getMonthSummary($couple, $month);
        if ($summary['isClosed']) {
            throw new HttpException(409, 'Month already closed');
        }

        if (($summary['pendingApprovalCount'] ?? 0) > 0) {
            throw new HttpException(422, 'Existem despesas compartilhadas pendentes de aprovação neste mês. Aprove ou rejeite-as antes de fechar o mês.');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();

        $existing = MonthlyClose::query()
            ->where('couple_id', $couple->id)
            ->whereDate('month', $monthStart)
            ->first();

        return DB::transaction(function () use ($couple, $monthStart, $summary, $existing) {
            $data = [
                'total_shared' => $summary['totalShared'],
                'user1_paid' => $summary['user1Paid'],
                'user2_paid' => $summary['user2Paid'],
                'verdict_amount' => $summary['verdictAmount'],
                'verdict_payer_id' => $summary['verdictPayerId'],
                'verdict_receiver_id' => $summary['verdictReceiverId'],
                'closed_at' => Carbon::now(),
            ];

            if ($existing) {
                $existing->update($data);
                return $existing;
            }

            return MonthlyClose::create(array_merge([
                'couple_id' => $couple->id,
                'month' => $monthStart,
            ], $data));
        });
    }

    /**
     * Lists close history for the couple.
     */
    public function listCloseHistory(Couple $couple, int $limit = 6): Collection
    {
        return MonthlyClose::query()
            ->where('couple_id', $couple->id)
            ->whereNotNull('closed_at')
            ->orderBy('month', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Calculates net debt status between partners.
     */
    public function getDebtStatus(Couple $couple): array
    {
        $closes = MonthlyClose::query()
            ->where('couple_id', $couple->id)
            ->whereNotNull('closed_at')
            ->whereNotNull('verdict_payer_id')
            ->whereNotNull('verdict_receiver_id')
            ->orderBy('month', 'asc')
            ->get();

        $payments = DebtPayment::query()
            ->where('couple_id', $couple->id)
            ->with(['payer', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->take(30)
            ->get();

        $pairBalances = [];
        foreach ($closes as $c) {
            $key = "{$c->verdict_payer_id}->{$c->verdict_receiver_id}";
            $pairBalances[$key] = ($pairBalances[$key] ?? 0.0) + (float) $c->verdict_amount;
        }

        foreach ($payments as $p) {
            $key = "{$p->payer_id}->{$p->receiver_id}";
            $pairBalances[$key] = ($pairBalances[$key] ?? 0.0) - (float) $p->amount;
        }

        $outstanding = [];
        foreach ($pairBalances as $pair => $value) {
            if (abs($value) > 0.005) {
                [$payerId, $receiverId] = explode('->', $pair);
                $outstanding[] = [
                    'payerId' => (string) $payerId,
                    'receiverId' => (string) $receiverId,
                    'amount' => round($value, 2),
                ];
            }
        }

        return [
            'outstanding' => $outstanding,
            'payments' => $payments->map(fn ($p) => $p->toFrontendArray())->values()->all(),
        ];
    }

    /**
     * Registers a debt payment between partners.
     */
    public function registerDebtPayment(Couple $couple, array $payload): DebtPayment
    {
        $amount = (float) $payload['amount'];
        if ($amount <= 0) {
            throw new HttpException(400, 'amount must be positive');
        }

        $monthDate = !empty($payload['month'])
            ? Carbon::createFromFormat('Y-m', $payload['month'])->startOfMonth()->toDateString()
            : null;

        $payment = DebtPayment::create([
            'couple_id' => $couple->id,
            'payer_id' => $payload['payerId'],
            'receiver_id' => $payload['receiverId'],
            'amount' => $amount,
            'note' => $payload['note'] ?? null,
            'month' => $monthDate,
        ]);

        $payment->load(['payer', 'receiver']);

        return $payment;
    }
}
