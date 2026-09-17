<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Couple;
use App\Models\MonthlyClose;
use App\Models\RecurringExpense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackendImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected User $userSolo;
    protected Couple $couple;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $this->user2 = User::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'secret123',
        ]);

        $this->userSolo = User::create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'password' => 'secret123',
        ]);

        $this->couple = Couple::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'user1_quota' => 0.5,
            'user2_quota' => 0.5,
        ]);

        $this->category = Category::create([
            'couple_id' => $this->couple->id,
            'name' => 'Mercado',
            'color' => '#10b981',
            'icon' => 'shopping-cart',
        ]);
    }

    public function test_cannot_create_or_modify_expense_in_closed_month(): void
    {
        Sanctum::actingAs($this->user1);

        // Close month 2026-08
        MonthlyClose::create([
            'couple_id' => $this->couple->id,
            'month' => '2026-08-01',
            'total_shared' => 100,
            'user1_paid' => 50,
            'user2_paid' => 50,
            'verdict_amount' => 0,
            'closed_at' => Carbon::now(),
        ]);

        // Attempt to create expense in closed month 2026-08
        $createRes = $this->postJson('/api/expenses', [
            'description' => 'Compras Passadas',
            'totalAmount' => 120.00,
            'installmentsCount' => 1,
            'purchaseDate' => '2026-08-15',
        ]);

        $createRes->assertStatus(422);

        // Create an open expense in 2026-09
        $openCreate = $this->postJson('/api/expenses', [
            'description' => 'Compras Setembro',
            'totalAmount' => 150.00,
            'installmentsCount' => 1,
            'purchaseDate' => '2026-09-10',
        ]);
        $openCreate->assertStatus(201);
        $expenseId = $openCreate->json('expense.id');

        // Now close month 2026-09
        MonthlyClose::create([
            'couple_id' => $this->couple->id,
            'month' => '2026-09-01',
            'total_shared' => 150,
            'user1_paid' => 150,
            'user2_paid' => 0,
            'verdict_amount' => 75,
            'closed_at' => Carbon::now(),
        ]);

        // Attempt to edit expense in closed month
        $updateRes = $this->putJson("/api/expenses/{$expenseId}", [
            'description' => 'Compras Alteradas',
            'totalAmount' => 200.00,
        ]);
        $updateRes->assertStatus(422);

        // Attempt to delete expense in closed month
        $deleteRes = $this->deleteJson("/api/expenses/{$expenseId}");
        $deleteRes->assertStatus(422);
    }

    public function test_ensure_user_has_couple_middleware_blocks_single_user(): void
    {
        Sanctum::actingAs($this->userSolo);

        $res = $this->getJson('/api/expenses/2026-09');
        $res->assertStatus(403);
    }

    public function test_artisan_command_materializes_recurring_expenses(): void
    {
        RecurringExpense::create([
            'couple_id' => $this->couple->id,
            'paid_by_user_id' => $this->user1->id,
            'description' => 'Internet Fibra',
            'total_amount' => 120.00,
            'installments_count' => 1,
            'is_shared' => true,
            'day_of_month' => 10,
            'start_month' => '2026-01',
            'is_active' => true,
        ]);

        $this->artisan('expenses:materialize-recurring', ['month' => '2026-09'])
            ->assertSuccessful();

        $this->assertDatabaseHas('expenses', [
            'couple_id' => $this->couple->id,
            'description' => 'Internet Fibra',
        ]);
    }

    public function test_custom_split_modes_calculate_correctly_in_clearing(): void
    {
        Sanctum::actingAs($this->user1);

        // Couple default quota is 0.5 / 0.5.
        // Expense 1: Alice paid R$ 100 with splitMode = EQUAL_50_50 -> Alice owes 50, Bob owes 50
        $this->postJson('/api/expenses', [
            'description' => 'Jantar 50/50',
            'totalAmount' => 100.00,
            'installmentsCount' => 1,
            'purchaseDate' => '2026-10-05',
            'splitMode' => 'EQUAL_50_50',
            'status' => 'CONFIRMED',
        ])->assertStatus(201);

        // Expense 2: Alice paid R$ 100 with splitMode = CUSTOM, customUser1Quota = 0.8 -> Alice owes 80, Bob owes 20
        $this->postJson('/api/expenses', [
            'description' => 'Curso Alice 80/20',
            'totalAmount' => 100.00,
            'installmentsCount' => 1,
            'purchaseDate' => '2026-10-10',
            'splitMode' => 'CUSTOM',
            'customUser1Quota' => 0.8,
            'status' => 'CONFIRMED',
        ])->assertStatus(201);

        // Expense 3: Bob paid R$ 100 with splitMode = FULL_USER2 -> Alice owes 0, Bob owes 100
        Sanctum::actingAs($this->user2);
        $this->postJson('/api/expenses', [
            'description' => 'Videogame Bob 100% Bob',
            'totalAmount' => 100.00,
            'installmentsCount' => 1,
            'purchaseDate' => '2026-10-12',
            'splitMode' => 'FULL_USER2',
            'status' => 'CONFIRMED',
        ])->assertStatus(201);

        // Totals:
        // Total Shared: 300
        // Alice paid: 200 (Expenses 1 & 2)
        // Bob paid: 100 (Expense 3)
        // Alice owed: 50 (Exp 1) + 80 (Exp 2) + 0 (Exp 3) = 130
        // Bob owed: 50 (Exp 1) + 20 (Exp 2) + 100 (Exp 3) = 170
        // Alice Balance: 200 - 130 = +70 (Alice receives 70 from Bob)
        // Bob Balance: 100 - 170 = -70 (Bob pays 70 to Alice)
        $summaryRes = $this->getJson('/api/clearing/2026-10');
        $summaryRes->assertStatus(200);

        $summary = $summaryRes->json('summary');
        $this->assertEquals(300.00, $summary['totalShared']);
        $this->assertEquals(200.00, $summary['user1Paid']);
        $this->assertEquals(100.00, $summary['user2Paid']);
        $this->assertEquals(70.00, $summary['user1Balance']);
        $this->assertEquals(-70.00, $summary['user2Balance']);
        $this->assertEquals(70.00, $summary['verdictAmount']);
        $this->assertEquals((string) $this->user2->id, $summary['verdictPayerId']);
        $this->assertEquals((string) $this->user1->id, $summary['verdictReceiverId']);
    }

    public function test_expense_approval_workflow_and_close_month_block(): void
    {
        Sanctum::actingAs($this->user1);

        // Alice adds a shared expense with status PENDING_APPROVAL
        $createRes = $this->postJson('/api/expenses', [
            'description' => 'Cinema',
            'totalAmount' => 80.00,
            'installmentsCount' => 1,
            'purchaseDate' => '2026-11-05',
            'status' => 'PENDING_APPROVAL',
        ]);
        $createRes->assertStatus(201);
        $expenseId = $createRes->json('expense.id');
        $this->assertEquals('PENDING_APPROVAL', $createRes->json('expense.status'));

        // Clearing summary shows pendingApprovalCount = 1
        $summaryRes = $this->getJson('/api/clearing/2026-11');
        $this->assertEquals(1, $summaryRes->json('summary.pendingApprovalCount'));

        // Attempting to close month should fail with 422
        $closeRes = $this->postJson('/api/clearing/2026-11/close');
        $closeRes->assertStatus(422);

        // Bob logs in and approves the expense
        Sanctum::actingAs($this->user2);
        $approveRes = $this->postJson("/api/expenses/{$expenseId}/approve");
        $approveRes->assertStatus(200);
        $this->assertEquals('CONFIRMED', $approveRes->json('expense.status'));
        $this->assertEquals((string) $this->user2->id, $approveRes->json('expense.approvedByUserId'));

        // Clearing summary now has pendingApprovalCount = 0
        $summaryRes2 = $this->getJson('/api/clearing/2026-11');
        $this->assertEquals(0, $summaryRes2->json('summary.pendingApprovalCount'));

        // Month close now succeeds
        $closeRes2 = $this->postJson('/api/clearing/2026-11/close');
        $closeRes2->assertStatus(201);
    }

    public function test_create_expense_with_integrated_recurrence(): void
    {
        Sanctum::actingAs($this->user1);

        $res = $this->postJson('/api/expenses', [
            'description' => 'Aluguel Apartamento',
            'totalAmount' => 1800.00,
            'installmentsCount' => 1,
            'purchaseDate' => '2026-12-05',
            'isRecurring' => true,
            'dayOfMonth' => 5,
            'startMonth' => '2026-12',
        ]);

        $res->assertStatus(201);
        $expense = $res->json('expense');
        $this->assertNotNull($expense['recurringExpenseId']);

        $this->assertDatabaseHas('recurring_expenses', [
            'couple_id' => $this->couple->id,
            'description' => 'Aluguel Apartamento',
            'day_of_month' => 5,
            'total_amount' => 1800.00,
        ]);
    }
}
