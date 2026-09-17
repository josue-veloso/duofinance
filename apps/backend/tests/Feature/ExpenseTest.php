<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Couple;
use App\Models\Expense;
use App\Models\Installment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
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

    public function test_user_can_create_single_installment_expense(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/expenses', [
            'description' => 'Compras da semana',
            'totalAmount' => 150.50,
            'installmentsCount' => 1,
            'isShared' => true,
            'categoryId' => $this->category->id,
            'purchaseDate' => '2026-09-10',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'expense' => [
                    'description' => 'Compras da semana',
                    'totalAmount' => 150.50,
                    'installmentsCount' => 1,
                    'isShared' => true,
                    'categoryId' => (string) $this->category->id,
                    'purchaseDate' => '2026-09-10',
                ],
            ]);

        $expenseId = (int) $response->json('expense.id');
        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'couple_id' => $this->couple->id,
            'total_amount' => 150.50,
        ]);

        $this->assertDatabaseHas('installments', [
            'expense_id' => $expenseId,
            'installment_number' => 1,
            'amount' => 150.50,
        ]);
        $this->assertEquals('2026-09-01', $response->json('expense.installments.0.dueMonth'));

        $this->assertDatabaseHas('expense_audits', [
            'expense_id' => $expenseId,
            'action' => 'CREATED',
        ]);
    }

    public function test_installments_calculation_absorbs_remainder_cents_in_last_installment(): void
    {
        Sanctum::actingAs($this->user1);

        // 100.00 / 3 installments: base = 33.33, remainder = 0.01 -> 33.33, 33.33, 33.34
        $response = $this->postJson('/api/expenses', [
            'description' => 'Notebook parcelado',
            'totalAmount' => 100.00,
            'installmentsCount' => 3,
            'isShared' => true,
            'purchaseDate' => '2026-09-15',
        ]);

        $response->assertStatus(201);
        $installments = $response->json('expense.installments');

        $this->assertCount(3, $installments);
        $this->assertEquals(33.33, $installments[0]['amount']);
        $this->assertEquals('2026-09-01', $installments[0]['dueMonth']);

        $this->assertEquals(33.33, $installments[1]['amount']);
        $this->assertEquals('2026-10-01', $installments[1]['dueMonth']);

        $this->assertEquals(33.34, $installments[2]['amount']);
        $this->assertEquals('2026-11-01', $installments[2]['dueMonth']);

        $totalSum = array_sum(array_column($installments, 'amount'));
        $this->assertEquals(100.00, $totalSum);
    }

    public function test_list_expenses_by_month_and_filters(): void
    {
        Sanctum::actingAs($this->user1);

        // Expense in 2026-09
        $this->postJson('/api/expenses', [
            'description' => 'Supermercado',
            'totalAmount' => 200.00,
            'installmentsCount' => 1,
            'isShared' => true,
            'categoryId' => $this->category->id,
            'purchaseDate' => '2026-09-05',
        ]);

        // Personal expense in 2026-09
        $this->postJson('/api/expenses', [
            'description' => 'Livro pessoal',
            'totalAmount' => 50.00,
            'installmentsCount' => 1,
            'isShared' => false,
            'purchaseDate' => '2026-09-10',
        ]);

        // Expense in 2026-10
        $this->postJson('/api/expenses', [
            'description' => 'Passagem de outubro',
            'totalAmount' => 300.00,
            'installmentsCount' => 1,
            'isShared' => true,
            'purchaseDate' => '2026-10-01',
        ]);

        // Fetch September - should get 2 installments
        $res = $this->getJson('/api/expenses/2026-09');
        $res->assertStatus(200);
        $this->assertCount(2, $res->json('installments'));

        // Filter by shared only
        $resShared = $this->getJson('/api/expenses/2026-09?isShared=true');
        $resShared->assertStatus(200);
        $this->assertCount(1, $resShared->json('installments'));
        $this->assertEquals('Supermercado', $resShared->json('installments.0.expense.description'));

        // Filter by search
        $resSearch = $this->getJson('/api/expenses/2026-09?search=livro');
        $resSearch->assertStatus(200);
        $this->assertCount(1, $resSearch->json('installments'));
        $this->assertEquals('Livro pessoal', $resSearch->json('installments.0.expense.description'));
    }

    public function test_update_expense_regenerates_installments_and_creates_audit(): void
    {
        Sanctum::actingAs($this->user1);

        $createRes = $this->postJson('/api/expenses', [
            'description' => 'TV',
            'totalAmount' => 1000.00,
            'installmentsCount' => 2,
            'isShared' => true,
            'purchaseDate' => '2026-09-01',
        ]);
        $expenseId = $createRes->json('expense.id');

        // Update to 4 installments and 1200.00
        $updateRes = $this->putJson("/api/expenses/{$expenseId}", [
            'description' => 'TV 4K',
            'totalAmount' => 1200.00,
            'installmentsCount' => 4,
        ]);

        $updateRes->assertStatus(200);
        $this->assertEquals(1200.00, $updateRes->json('expense.totalAmount'));
        $this->assertEquals(4, $updateRes->json('expense.installmentsCount'));
        $this->assertCount(4, $updateRes->json('expense.installments'));

        // Check audit created
        $this->assertDatabaseHas('expense_audits', [
            'expense_id' => $expenseId,
            'action' => 'UPDATED',
        ]);
    }

    public function test_undo_restores_previous_expense_state(): void
    {
        Sanctum::actingAs($this->user1);

        $createRes = $this->postJson('/api/expenses', [
            'description' => 'Geladeira',
            'totalAmount' => 2000.00,
            'installmentsCount' => 2,
            'purchaseDate' => '2026-09-01',
        ]);
        $expenseId = $createRes->json('expense.id');

        // Edit
        $this->putJson("/api/expenses/{$expenseId}", [
            'description' => 'Geladeira Inox',
            'totalAmount' => 2500.00,
            'installmentsCount' => 5,
        ]);

        // Undo
        $undoRes = $this->postJson("/api/expenses/{$expenseId}/undo");
        $undoRes->assertStatus(200);
        $this->assertEquals('Geladeira', $undoRes->json('expense.description'));
        $this->assertEquals(2000.00, $undoRes->json('expense.totalAmount'));
        $this->assertEquals(2, $undoRes->json('expense.installmentsCount'));
    }

    public function test_delete_expense_soft_deletes_expense_and_preserves_audit(): void
    {
        Sanctum::actingAs($this->user1);

        $createRes = $this->postJson('/api/expenses', [
            'description' => 'Mesa',
            'totalAmount' => 400.00,
            'installmentsCount' => 2,
            'purchaseDate' => '2026-09-01',
        ]);
        $expenseId = $createRes->json('expense.id');

        $deleteRes = $this->deleteJson("/api/expenses/{$expenseId}");
        $deleteRes->assertStatus(204);

        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);
        $this->assertDatabaseHas('expense_audits', [
            'expense_id' => $expenseId,
            'action' => 'DELETED',
        ]);

        // Verify it doesn't show in month expenses
        $listRes = $this->getJson('/api/expenses/2026-09');
        $listRes->assertStatus(200);
        $this->assertCount(0, $listRes->json('installments'));

        // Verify it can be restored
        $restoreRes = $this->postJson("/api/expenses/{$expenseId}/restore");
        $restoreRes->assertStatus(200);
        $this->assertNotSoftDeleted('expenses', ['id' => $expenseId]);

        $listResAfter = $this->getJson('/api/expenses/2026-09');
        $this->assertCount(1, $listResAfter->json('installments'));
    }
}
