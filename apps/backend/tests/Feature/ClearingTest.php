<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClearingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected Couple $couple;
    protected Category $category;
    protected ExpenseService $expenseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expenseService = app(ExpenseService::class);

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

        $this->couple = Couple::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'user1_quota' => 0.6,
            'user2_quota' => 0.4,
        ]);

        $this->category = Category::create([
            'couple_id' => $this->couple->id,
            'name' => 'Mercado',
            'color' => '#10b981',
            'icon' => '🛒',
        ]);
    }

    public function test_month_summary_calculates_correct_verdict_and_balances(): void
    {
        Sanctum::actingAs($this->user1);

        // August expense (previous month)
        $this->expenseService->createExpense($this->couple, $this->user1, [
            'description' => 'Gasto Agosto',
            'totalAmount' => 150.00,
            'installmentsCount' => 1,
            'isShared' => true,
            'purchaseDate' => '2026-08-10',
        ]);

        // September expense 1: User 1 paid 160.00 (shared)
        $this->expenseService->createExpense($this->couple, $this->user1, [
            'description' => 'Supermercado Alice',
            'totalAmount' => 160.00,
            'installmentsCount' => 1,
            'isShared' => true,
            'categoryId' => $this->category->id,
            'purchaseDate' => '2026-09-05',
        ]);

        // September expense 2: User 2 paid 40.00 (shared)
        $this->expenseService->createExpense($this->couple, $this->user2, [
            'description' => 'Padaria Bob',
            'totalAmount' => 40.00,
            'installmentsCount' => 1,
            'isShared' => true,
            'purchaseDate' => '2026-09-12',
        ]);

        // September expense 3: User 1 personal expense = 50.00 (must NOT count)
        $this->expenseService->createExpense($this->couple, $this->user1, [
            'description' => 'Livro pessoal',
            'totalAmount' => 50.00,
            'installmentsCount' => 1,
            'isShared' => false,
            'purchaseDate' => '2026-09-15',
        ]);

        $response = $this->getJson('/api/clearing/2026-09');

        $response->assertStatus(200);
        $summary = $response->json('summary');

        // Total shared: 160 + 40 = 200.00
        $this->assertEquals(200.00, $summary['totalShared']);
        $this->assertEquals(160.00, $summary['user1Paid']);
        $this->assertEquals(40.00, $summary['user2Paid']);

        // User1 balance: 160 - (200 * 0.6) = 160 - 120 = +40.00
        $this->assertEquals(40.00, $summary['user1Balance']);
        // User2 balance: 40 - (200 * 0.4) = 40 - 80 = -40.00
        $this->assertEquals(-40.00, $summary['user2Balance']);

        // Verdict: Bob (user2) pays Alice (user1) 40.00
        $this->assertEquals(40.00, $summary['verdictAmount']);
        $this->assertEquals((string) $this->user2->id, $summary['verdictPayerId']);
        $this->assertEquals((string) $this->user1->id, $summary['verdictReceiverId']);
        $this->assertFalse($summary['isClosed']);

        // Delta vs August (200 - 150 = 50)
        $this->assertEquals(150.00, $summary['previousTotalShared']);
        $this->assertEquals(50.00, $summary['deltaVsPreviousMonth']);

        // Top categories
        $this->assertNotEmpty($summary['topCategories']);
        $this->assertEquals('Mercado', $summary['topCategories'][0]['name']);
        $this->assertEquals(160.00, $summary['topCategories'][0]['total']);
        $this->assertEquals(80.00, $summary['topCategories'][0]['percent']);
    }

    public function test_close_month_persists_verdict_and_prevents_duplicate_close(): void
    {
        Sanctum::actingAs($this->user1);

        $this->expenseService->createExpense($this->couple, $this->user1, [
            'description' => 'Feira',
            'totalAmount' => 100.00,
            'installmentsCount' => 1,
            'isShared' => true,
            'purchaseDate' => '2026-09-01',
        ]);

        // First close
        $closeRes = $this->postJson('/api/clearing/2026-09/close');
        $closeRes->assertStatus(201);
        $this->assertEquals(100.00, $closeRes->json('close.totalShared'));
        $this->assertNotNull($closeRes->json('close.closedAt'));

        $this->assertDatabaseHas('monthly_closes', [
            'couple_id' => $this->couple->id,
            'total_shared' => 100.00,
        ]);

        // Check summary reports isClosed = true
        $summaryRes = $this->getJson('/api/clearing/2026-09');
        $this->assertTrue($summaryRes->json('summary.isClosed'));

        // Attempt second close -> 409 Conflict
        $duplicateRes = $this->postJson('/api/clearing/2026-09/close');
        $duplicateRes->assertStatus(409);
    }

    public function test_history_lists_closed_months(): void
    {
        Sanctum::actingAs($this->user1);

        // Create and close Aug
        $this->expenseService->createExpense($this->couple, $this->user1, [
            'description' => 'Agosto',
            'totalAmount' => 80.00,
            'installmentsCount' => 1,
            'isShared' => true,
            'purchaseDate' => '2026-08-01',
        ]);
        $this->postJson('/api/clearing/2026-08/close');

        // Create and close Sep
        $this->expenseService->createExpense($this->couple, $this->user1, [
            'description' => 'Setembro',
            'totalAmount' => 120.00,
            'installmentsCount' => 1,
            'isShared' => true,
            'purchaseDate' => '2026-09-01',
        ]);
        $this->postJson('/api/clearing/2026-09/close');

        $historyRes = $this->getJson('/api/clearing/history');
        $historyRes->assertStatus(200);

        $history = $historyRes->json('history');
        $this->assertCount(2, $history);
        $this->assertEquals('2026-09', $history[0]['month']);
        $this->assertEquals('2026-08', $history[1]['month']);
    }

    public function test_debt_status_and_payment_registration(): void
    {
        Sanctum::actingAs($this->user1);

        // Total 100.00 paid by Alice (user1), quotas: Alice 60%, Bob 40%
        // Bob owes Alice 40.00
        $this->expenseService->createExpense($this->couple, $this->user1, [
            'description' => 'Compras',
            'totalAmount' => 100.00,
            'installmentsCount' => 1,
            'isShared' => true,
            'purchaseDate' => '2026-09-01',
        ]);
        $this->postJson('/api/clearing/2026-09/close');

        // Check debt status
        $debtRes = $this->getJson('/api/clearing/debts');
        $debtRes->assertStatus(200);
        $outstanding = $debtRes->json('debt.outstanding');
        $this->assertCount(1, $outstanding);
        $this->assertEquals((string) $this->user2->id, $outstanding[0]['payerId']);
        $this->assertEquals((string) $this->user1->id, $outstanding[0]['receiverId']);
        $this->assertEquals(40.00, $outstanding[0]['amount']);

        // Bob makes partial payment of 25.00 to Alice
        $paymentRes = $this->postJson('/api/clearing/debts/payments', [
            'payerId' => $this->user2->id,
            'receiverId' => $this->user1->id,
            'amount' => 25.00,
            'note' => 'Transferência Pix',
            'month' => '2026-09',
        ]);
        $paymentRes->assertStatus(201);

        // Check outstanding again - should be 40 - 25 = 15.00
        $debtRes2 = $this->getJson('/api/clearing/debts');
        $outstanding2 = $debtRes2->json('debt.outstanding');
        $this->assertCount(1, $outstanding2);
        $this->assertEquals(15.00, $outstanding2[0]['amount']);

        // Bob settles the remaining 15.00
        $this->postJson('/api/clearing/debts/payments', [
            'payerId' => $this->user2->id,
            'receiverId' => $this->user1->id,
            'amount' => 15.00,
        ]);

        // Outstanding should now be empty
        $debtRes3 = $this->getJson('/api/clearing/debts');
        $this->assertEmpty($debtRes3->json('debt.outstanding'));
        $this->assertCount(2, $debtRes3->json('debt.payments'));
    }
}
