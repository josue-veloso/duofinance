<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringExpenseTest extends TestCase
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
            'user1_quota' => 0.5,
            'user2_quota' => 0.5,
        ]);

        $this->category = Category::create([
            'couple_id' => $this->couple->id,
            'name' => 'Assinaturas',
            'color' => '#8b5cf6',
            'icon' => '📺',
        ]);
    }

    public function test_user_can_create_recurring_expense(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/recurring-expenses', [
            'description' => 'Netflix',
            'totalAmount' => 55.90,
            'installmentsCount' => 1,
            'isShared' => true,
            'categoryId' => $this->category->id,
            'dayOfMonth' => 10,
            'startMonth' => '2026-09',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'recurring' => [
                    'description' => 'Netflix',
                    'totalAmount' => 55.90,
                    'dayOfMonth' => 10,
                    'startMonth' => '2026-09',
                    'isActive' => true,
                ],
            ]);

        $this->assertDatabaseHas('recurring_expenses', [
            'description' => 'Netflix',
            'couple_id' => $this->couple->id,
            'is_active' => true,
        ]);
    }

    public function test_user_can_list_recurring_expenses(): void
    {
        Sanctum::actingAs($this->user1);

        $this->postJson('/api/recurring-expenses', [
            'description' => 'Spotify',
            'totalAmount' => 34.90,
            'dayOfMonth' => 15,
            'startMonth' => '2026-09',
        ]);

        $res = $this->getJson('/api/recurring-expenses');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('recurring'));
        $this->assertEquals('Spotify', $res->json('recurring.0.description'));
    }

    public function test_user_can_toggle_recurring_expense_status(): void
    {
        Sanctum::actingAs($this->user1);

        $createRes = $this->postJson('/api/recurring-expenses', [
            'description' => 'Internet Fibra',
            'totalAmount' => 120.00,
            'dayOfMonth' => 20,
            'startMonth' => '2026-09',
        ]);
        $recId = $createRes->json('recurring.id');

        // Pause
        $patchRes = $this->patchJson("/api/recurring-expenses/{$recId}", [
            'isActive' => false,
        ]);
        $patchRes->assertStatus(200);
        $this->assertFalse($patchRes->json('recurring.isActive'));
        $this->assertDatabaseHas('recurring_expenses', [
            'id' => $recId,
            'is_active' => false,
        ]);

        // Resume
        $patchRes2 = $this->patchJson("/api/recurring-expenses/{$recId}", [
            'isActive' => true,
        ]);
        $patchRes2->assertStatus(200);
        $this->assertTrue($patchRes2->json('recurring.isActive'));
    }

    public function test_recurring_expense_is_materialized_when_fetching_month(): void
    {
        Sanctum::actingAs($this->user1);

        // Create recurring expense starting in 2026-09
        $this->postJson('/api/recurring-expenses', [
            'description' => 'Academia',
            'totalAmount' => 150.00,
            'dayOfMonth' => 5,
            'startMonth' => '2026-09',
        ]);

        // Initially, no expenses exist in the expenses table
        $this->assertDatabaseCount('expenses', 0);

        // Fetch September - should trigger materialization
        $res = $this->getJson('/api/expenses/2026-09');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('installments'));
        $this->assertEquals('Academia', $res->json('installments.0.expense.description'));
        $this->assertEquals(150.00, $res->json('installments.0.amount'));

        // Now an expense exists in the database
        $this->assertDatabaseCount('expenses', 1);

        // Fetching September again should NOT duplicate the expense
        $res2 = $this->getJson('/api/expenses/2026-09');
        $res2->assertStatus(200);
        $this->assertCount(1, $res2->json('installments'));
        $this->assertDatabaseCount('expenses', 1);
    }
}

