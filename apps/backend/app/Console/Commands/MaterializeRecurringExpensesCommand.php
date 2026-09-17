<?php

namespace App\Console\Commands;

use App\Models\Couple;
use App\Models\RecurringExpense;
use App\Services\ExpenseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MaterializeRecurringExpensesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expenses:materialize-recurring {month? : The target month in YYYY-MM format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Materializes active recurring expenses for the specified month';

    /**
     * Execute the console command.
     */
    public function handle(ExpenseService $expenseService): int
    {
        $month = $this->argument('month') ?: Carbon::now()->format('Y-m');

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Invalid month format. Expected YYYY-MM.');
            return Command::FAILURE;
        }

        $this->info("Materializing recurring expenses for month: {$month}");

        $couples = Couple::query()
            ->whereHas('recurringExpenses', function ($q) {
                $q->where('is_active', true);
            })
            ->with(['user1', 'user2'])
            ->get();

        $count = 0;
        foreach ($couples as $couple) {
            $actor = $couple->user1 ?? $couple->user2;
            if (!$actor) {
                continue;
            }

            $expenseService->materializeRecurringExpensesForMonth($couple, $actor, $month);
            $count++;
        }

        $this->info("Successfully processed recurring expenses for {$count} couple(s).");

        return Command::SUCCESS;
    }
}

