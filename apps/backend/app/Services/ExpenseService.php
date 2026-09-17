<?php

namespace App\Services;

use App\Models\Couple;
use App\Models\Expense;
use App\Models\ExpenseAudit;
use App\Models\Installment;
use App\Models\MonthlyClose;
use App\Models\RecurringExpense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExpenseService
{
    /**
     * Creates an Expense and all its Installment records atomically.
     */
    public function createExpense(
        Couple $couple,
        User $actor,
        array $payload,
        ?int $recurringExpenseId = null
    ): Expense {
        $installmentsCount = (int) ($payload['installmentsCount'] ?? 1);
        if ($installmentsCount < 1 || $installmentsCount > 96) {
            throw new HttpException(400, 'installmentsCount must be between 1 and 96');
        }

        $totalAmount = (float) $payload['totalAmount'];
        $purchaseDate = Carbon::parse($payload['purchaseDate']);

        $this->ensureMonthNotClosed($couple, $purchaseDate);

        // Integrated recurring expense creation
        if (!empty($payload['isRecurring']) && $recurringExpenseId === null) {
            $dayOfMonth = (int) ($payload['dayOfMonth'] ?? $purchaseDate->day);
            $startMonth = $payload['startMonth'] ?? $purchaseDate->format('Y-m');
            $endMonth = $payload['endMonth'] ?? null;

            $recurring = RecurringExpense::create([
                'couple_id' => $couple->id,
                'paid_by_user_id' => $payload['paidByUserId'] ?? $actor->id,
                'description' => $payload['description'],
                'total_amount' => $totalAmount,
                'installments_count' => 1,
                'is_shared' => $payload['isShared'] ?? true,
                'category_id' => $payload['categoryId'] ?? null,
                'day_of_month' => min(28, max(1, $dayOfMonth)),
                'start_month' => $startMonth,
                'end_month' => $endMonth,
                'is_active' => true,
                'last_generated_month' => $purchaseDate->copy()->startOfMonth()->toDateString(),
            ]);

            $recurringExpenseId = $recurring->id;
        }

        $installmentsData = $this->buildInstallments($totalAmount, $installmentsCount, $purchaseDate);
        foreach ($installmentsData as $inst) {
            $this->ensureMonthNotClosed($couple, $inst['due_month']);
        }

        return DB::transaction(function () use ($couple, $actor, $payload, $installmentsCount, $totalAmount, $purchaseDate, $installmentsData, $recurringExpenseId) {
            $splitMode = $payload['splitMode'] ?? 'DEFAULT';
            $customQuota = isset($payload['customUser1Quota']) && $payload['customUser1Quota'] !== null
                ? (float) $payload['customUser1Quota']
                : null;
            $status = $payload['status'] ?? 'CONFIRMED';

            $expense = Expense::create([
                'couple_id' => $couple->id,
                'paid_by_user_id' => $payload['paidByUserId'] ?? $actor->id,
                'recurring_expense_id' => $recurringExpenseId,
                'description' => $payload['description'],
                'total_amount' => $totalAmount,
                'installments_count' => $installmentsCount,
                'is_shared' => $payload['isShared'] ?? true,
                'split_mode' => $splitMode,
                'custom_user1_quota' => $customQuota,
                'status' => $status,
                'category_id' => $payload['categoryId'] ?? null,
                'purchase_date' => $purchaseDate->toDateString(),
            ]);

            foreach ($installmentsData as $inst) {
                $expense->installments()->create($inst);
            }

            $expense->load(['installments', 'paidByUser', 'category']);

            ExpenseAudit::create([
                'expense_id' => $expense->id,
                'couple_id' => $couple->id,
                'actor_user_id' => $actor->id,
                'action' => 'CREATED',
                'after' => $this->expenseSnapshot($expense),
            ]);

            return $expense;
        });
    }

    /**
     * Lists installments falling on the specified month for the couple.
     */
    public function listExpensesByMonth(
        Couple $couple,
        User $actor,
        string $month,
        array $filters = []
    ): Collection {
        $this->materializeRecurringExpensesForMonth($couple, $actor, $month);

        $monthDate = Carbon::createFromFormat('Y-m', $month);
        $monthStart = $monthDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $monthDate->copy()->endOfMonth()->toDateString();

        $query = Installment::query()
            ->whereBetween('due_month', [$monthStart, $monthEnd])
            ->whereHas('expense', function ($q) use ($couple, $filters) {
                $q->where('couple_id', $couple->id);

                if (!empty($filters['search'])) {
                    $searchTerm = '%' . trim($filters['search']) . '%';
                    $q->where('description', 'like', $searchTerm);
                }

                if (!empty($filters['categoryId'])) {
                    $q->where('category_id', $filters['categoryId']);
                }

                if (!empty($filters['paidByUserId'])) {
                    $q->where('paid_by_user_id', $filters['paidByUserId']);
                }

                if (isset($filters['isShared']) && is_bool($filters['isShared'])) {
                    $q->where('is_shared', $filters['isShared']);
                }
            })
            ->with(['expense.paidByUser', 'expense.category'])
            ->orderBy('due_month', 'desc')
            ->orderBy('id', 'desc');

        return $query->get();
    }

    /**
     * Updates an expense and regenerates installments if financial fields changed.
     */
    public function updateExpense(
        Couple $couple,
        User $actor,
        int $expenseId,
        array $payload
    ): Expense {
        $expense = Expense::query()
            ->where('id', $expenseId)
            ->where('couple_id', $couple->id)
            ->first();

        if (!$expense) {
            throw new HttpException(404, 'Expense not found');
        }

        $installmentsCount = (int) ($payload['installmentsCount'] ?? $expense->installments_count);
        if ($installmentsCount < 1 || $installmentsCount > 96) {
            throw new HttpException(400, 'installmentsCount must be between 1 and 96');
        }

        $totalAmount = isset($payload['totalAmount']) ? (float) $payload['totalAmount'] : (float) $expense->total_amount;
        $purchaseDateStr = $payload['purchaseDate'] ?? ($expense->purchase_date instanceof Carbon ? $expense->purchase_date->format('Y-m-d') : substr((string) $expense->purchase_date, 0, 10));
        $purchaseDate = Carbon::parse($purchaseDateStr);

        $this->ensureExpenseMonthsNotClosed($couple, $expense);
        $this->ensureMonthNotClosed($couple, $purchaseDate);

        return DB::transaction(function () use ($expense, $couple, $actor, $payload, $totalAmount, $installmentsCount, $purchaseDate) {
            $beforeSnapshot = $this->expenseSnapshot($expense);

            $expense->update([
                'description' => $payload['description'] ?? $expense->description,
                'total_amount' => $totalAmount,
                'installments_count' => $installmentsCount,
                'is_shared' => array_key_exists('isShared', $payload) ? (bool) $payload['isShared'] : $expense->is_shared,
                'split_mode' => $payload['splitMode'] ?? $expense->split_mode,
                'custom_user1_quota' => array_key_exists('customUser1Quota', $payload) ? ($payload['customUser1Quota'] !== null ? (float) $payload['customUser1Quota'] : null) : $expense->custom_user1_quota,
                'status' => $payload['status'] ?? $expense->status,
                'category_id' => array_key_exists('categoryId', $payload) ? $payload['categoryId'] : $expense->category_id,
                'purchase_date' => $purchaseDate->toDateString(),
            ]);

            // Regenerate installments
            $expense->installments()->delete();
            $installmentsData = $this->buildInstallments($totalAmount, $installmentsCount, $purchaseDate);
            foreach ($installmentsData as $inst) {
                $expense->installments()->create($inst);
            }

            $expense->load(['installments', 'paidByUser', 'category']);

            ExpenseAudit::create([
                'expense_id' => $expense->id,
                'couple_id' => $couple->id,
                'actor_user_id' => $actor->id,
                'action' => 'UPDATED',
                'before' => $beforeSnapshot,
                'after' => $this->expenseSnapshot($expense),
            ]);

            return $expense;
        });
    }

    /**
     * Approves a pending shared expense.
     */
    public function approveExpense(Couple $couple, User $actor, int $expenseId): Expense
    {
        $expense = Expense::query()
            ->where('id', $expenseId)
            ->where('couple_id', $couple->id)
            ->first();

        if (!$expense) {
            throw new HttpException(404, 'Expense not found');
        }

        $this->ensureExpenseMonthsNotClosed($couple, $expense);

        $beforeSnapshot = $this->expenseSnapshot($expense);

        $expense->update([
            'status' => 'CONFIRMED',
            'approved_by_user_id' => $actor->id,
            'approved_at' => Carbon::now(),
        ]);

        ExpenseAudit::create([
            'expense_id' => $expense->id,
            'couple_id' => $couple->id,
            'actor_user_id' => $actor->id,
            'action' => 'APPROVED',
            'before' => $beforeSnapshot,
            'after' => $this->expenseSnapshot($expense),
        ]);

        $expense->load(['installments', 'paidByUser', 'category', 'approvedByUser']);

        return $expense;
    }

    /**
     * Rejects a pending shared expense.
     */
    public function rejectExpense(Couple $couple, User $actor, int $expenseId): Expense
    {
        $expense = Expense::query()
            ->where('id', $expenseId)
            ->where('couple_id', $couple->id)
            ->first();

        if (!$expense) {
            throw new HttpException(404, 'Expense not found');
        }

        $this->ensureExpenseMonthsNotClosed($couple, $expense);

        $beforeSnapshot = $this->expenseSnapshot($expense);

        $expense->update([
            'status' => 'REJECTED',
        ]);

        ExpenseAudit::create([
            'expense_id' => $expense->id,
            'couple_id' => $couple->id,
            'actor_user_id' => $actor->id,
            'action' => 'REJECTED',
            'before' => $beforeSnapshot,
            'after' => $this->expenseSnapshot($expense),
        ]);

        $expense->load(['installments', 'paidByUser', 'category', 'approvedByUser']);

        return $expense;
    }

    /**
     * Undoes the last edit made to an expense.
     */
    public function undoLastExpenseEdit(Couple $couple, User $actor, int $expenseId): Expense
    {
        $expense = Expense::query()
            ->where('id', $expenseId)
            ->where('couple_id', $couple->id)
            ->first();

        if (!$expense) {
            throw new HttpException(404, 'Expense not found');
        }

        $lastEdit = ExpenseAudit::query()
            ->where('expense_id', $expenseId)
            ->where('couple_id', $couple->id)
            ->where('action', 'UPDATED')
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastEdit || !$lastEdit->before) {
            throw new HttpException(409, 'No editable history found for this expense');
        }

        $before = $lastEdit->before;
        $previous = [
            'description' => (string) ($before['description'] ?? ''),
            'totalAmount' => (float) ($before['totalAmount'] ?? 0),
            'installmentsCount' => (int) ($before['installmentsCount'] ?? 1),
            'isShared' => (bool) ($before['isShared'] ?? true),
            'categoryId' => $before['categoryId'] ?? null,
            'purchaseDate' => substr((string) ($before['purchaseDate'] ?? ''), 0, 10),
        ];

        return $this->updateExpense($couple, $actor, $expenseId, $previous);
    }

    /**
     * Deletes an expense and its installments (Soft Delete).
     */
    public function deleteExpense(Couple $couple, User $actor, int $expenseId): void
    {
        $expense = Expense::query()
            ->where('id', $expenseId)
            ->where('couple_id', $couple->id)
            ->first();

        if (!$expense) {
            throw new HttpException(404, 'Expense not found');
        }

        $this->ensureExpenseMonthsNotClosed($couple, $expense);

        DB::transaction(function () use ($expense, $couple, $actor) {
            ExpenseAudit::create([
                'expense_id' => $expense->id,
                'couple_id' => $couple->id,
                'actor_user_id' => $actor->id,
                'action' => 'DELETED',
                'before' => $this->expenseSnapshot($expense),
            ]);

            $expense->delete();
        });
    }

    /**
     * Restores a soft-deleted expense.
     */
    public function restoreExpense(Couple $couple, User $actor, int $expenseId): Expense
    {
        $expense = Expense::onlyTrashed()
            ->where('id', $expenseId)
            ->where('couple_id', $couple->id)
            ->first();

        if (!$expense) {
            throw new HttpException(404, 'Expense not found or not deleted');
        }

        $this->ensureExpenseMonthsNotClosed($couple, $expense);

        DB::transaction(function () use ($expense, $couple, $actor) {
            $expense->restore();

            ExpenseAudit::create([
                'expense_id' => $expense->id,
                'couple_id' => $couple->id,
                'actor_user_id' => $actor->id,
                'action' => 'RESTORED',
                'after' => $this->expenseSnapshot($expense),
            ]);
        });

        $expense->load(['installments', 'paidByUser', 'category']);

        return $expense;
    }

    /**
     * Lists audit entries for an expense.
     */
    public function listExpenseAudits(Couple $couple, int $expenseId): Collection
    {
        return ExpenseAudit::query()
            ->where('couple_id', $couple->id)
            ->where('expense_id', $expenseId)
            ->with('actorUser')
            ->orderBy('id', 'desc')
            ->take(20)
            ->get();
    }

    /**
     * Creates a recurring expense definition.
     */
    public function createRecurringExpense(Couple $couple, User $actor, array $payload): RecurringExpense
    {
        $dayOfMonth = (int) $payload['dayOfMonth'];
        if ($dayOfMonth < 1 || $dayOfMonth > 28) {
            throw new HttpException(400, 'dayOfMonth must be between 1 and 28');
        }

        $startMonth = Carbon::createFromFormat('Y-m', $payload['startMonth'])->startOfMonth()->toDateString();
        $endMonth = !empty($payload['endMonth'])
            ? Carbon::createFromFormat('Y-m', $payload['endMonth'])->startOfMonth()->toDateString()
            : null;

        $recurring = RecurringExpense::create([
            'couple_id' => $couple->id,
            'paid_by_user_id' => $actor->id,
            'description' => $payload['description'],
            'total_amount' => (float) $payload['totalAmount'],
            'installments_count' => (int) ($payload['installmentsCount'] ?? 1),
            'is_shared' => $payload['isShared'] ?? true,
            'category_id' => $payload['categoryId'] ?? null,
            'day_of_month' => $dayOfMonth,
            'start_month' => $startMonth,
            'end_month' => $endMonth,
            'frequency' => 'MONTHLY',
            'is_active' => true,
        ]);

        $recurring->load(['paidByUser', 'category']);

        return $recurring;
    }

    /**
     * Lists recurring expenses for a couple.
     */
    public function listRecurringExpenses(Couple $couple): Collection
    {
        return RecurringExpense::query()
            ->where('couple_id', $couple->id)
            ->with(['paidByUser', 'category'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Activates or pauses a recurring expense.
     */
    public function updateRecurringExpenseStatus(Couple $couple, int $recurringId, bool $isActive): RecurringExpense
    {
        $recurring = RecurringExpense::query()
            ->where('id', $recurringId)
            ->where('couple_id', $couple->id)
            ->first();

        if (!$recurring) {
            throw new HttpException(404, 'Recurring expense not found');
        }

        $recurring->update(['is_active' => $isActive]);
        $recurring->load(['paidByUser', 'category']);

        return $recurring;
    }

    /**
     * Materializes active recurring expenses for the given month if not yet generated.
     */
    public function materializeRecurringExpensesForMonth(Couple $couple, User $actor, string $month): void
    {
        $monthDate = Carbon::createFromFormat('Y-m', $month);
        $monthStart = $monthDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $monthDate->copy()->endOfMonth()->toDateString();

        $recurring = RecurringExpense::query()
            ->where('couple_id', $couple->id)
            ->where('is_active', true)
            ->where('start_month', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('end_month')->orWhere('end_month', '>=', $monthStart);
            })
            ->get();

        foreach ($recurring as $rec) {
            $alreadyCreated = Expense::query()
                ->where('couple_id', $couple->id)
                ->where('recurring_expense_id', $rec->id)
                ->whereBetween('purchase_date', [$monthStart, $monthEnd])
                ->exists();

            if ($alreadyCreated) {
                continue;
            }

            $day = min((int) $rec->day_of_month, (int) $monthDate->daysInMonth);
            $purchaseDate = $monthDate->copy()->day($day)->toDateString();

            $this->createExpense(
                $couple,
                $actor,
                [
                    'paidByUserId' => $rec->paid_by_user_id,
                    'description' => $rec->description,
                    'totalAmount' => $rec->total_amount,
                    'installmentsCount' => $rec->installments_count,
                    'isShared' => $rec->is_shared,
                    'categoryId' => $rec->category_id,
                    'purchaseDate' => $purchaseDate,
                ],
                $rec->id
            );

            $rec->update(['last_generated_month' => $monthStart]);
        }
    }

    /**
     * Calculates installments array absorbing rounding cents into the last installment.
     */
    public function buildInstallments(float $totalAmount, int $installmentsCount, Carbon $purchaseDate): array
    {
        $totalCents = (int) round($totalAmount * 100);
        $baseCents = (int) intdiv($totalCents, $installmentsCount);
        $remainderCents = $totalCents - ($baseCents * $installmentsCount);

        $installments = [];
        for ($i = 0; $i < $installmentsCount; $i++) {
            $cents = $baseCents + ($i === $installmentsCount - 1 ? $remainderCents : 0);
            $amount = $cents / 100;
            $dueMonth = $purchaseDate->copy()->startOfMonth()->addMonthsNoOverflow($i)->toDateString();

            $installments[] = [
                'installment_number' => $i + 1,
                'amount' => $amount,
                'due_month' => $dueMonth,
            ];
        }

        return $installments;
    }

    protected function expenseSnapshot(Expense $expense): array
    {
        $purchaseDateStr = $expense->purchase_date instanceof Carbon
            ? $expense->purchase_date->format('Y-m-d')
            : substr((string) $expense->purchase_date, 0, 10);

        return [
            'id' => (string) $expense->id,
            'description' => $expense->description,
            'totalAmount' => (float) $expense->total_amount,
            'installmentsCount' => (int) $expense->installments_count,
            'isShared' => (bool) $expense->is_shared,
            'splitMode' => $expense->split_mode ?? 'DEFAULT',
            'customUser1Quota' => $expense->custom_user1_quota !== null ? (float) $expense->custom_user1_quota : null,
            'status' => $expense->status ?? 'CONFIRMED',
            'categoryId' => $expense->category_id ? (string) $expense->category_id : null,
            'purchaseDate' => $purchaseDateStr,
        ];
    }

    public function ensureMonthNotClosed(Couple $couple, string|Carbon $dateOrMonth): void
    {
        $monthDate = $dateOrMonth instanceof Carbon
            ? $dateOrMonth->copy()->startOfMonth()
            : Carbon::parse($dateOrMonth)->startOfMonth();

        $isClosed = MonthlyClose::query()
            ->where('couple_id', $couple->id)
            ->whereDate('month', $monthDate->toDateString())
            ->whereNotNull('closed_at')
            ->exists();

        if ($isClosed) {
            throw new HttpException(422, 'Não é possível alterar lançamentos em um mês já fechado.');
        }
    }

    public function ensureExpenseMonthsNotClosed(Couple $couple, Expense $expense): void
    {
        if ($expense->purchase_date) {
            $this->ensureMonthNotClosed($couple, $expense->purchase_date);
        }

        $dueMonths = $expense->installments()->pluck('due_month');
        foreach ($dueMonths as $dm) {
            $this->ensureMonthNotClosed($couple, $dm);
        }
    }
}
