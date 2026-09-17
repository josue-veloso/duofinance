<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected Couple $couple;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password' => 'secret123',
        ]);

        $this->user2 = User::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => 'secret123',
        ]);

        $this->couple = Couple::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'user1_quota' => 0.5,
            'user2_quota' => 0.5,
        ]);
    }

    public function test_user_can_create_category(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/categories', [
            'name' => 'Mercado',
            'color' => '#10b981',
            'icon' => '🛒',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'category' => [
                    'name' => 'Mercado',
                    'color' => '#10b981',
                    'icon' => '🛒',
                    'coupleId' => (string) $this->couple->id,
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Mercado',
            'couple_id' => $this->couple->id,
        ]);
    }

    public function test_user_can_list_categories_isolated_by_couple(): void
    {
        // Category for this couple
        Category::create([
            'couple_id' => $this->couple->id,
            'name' => 'Aluguel',
            'color' => '#6366f1',
            'icon' => '🏠',
        ]);

        // Another couple and category
        $otherU1 = User::create(['name' => 'Other 1', 'email' => 'o1@example.com', 'password' => 'secret123']);
        $otherU2 = User::create(['name' => 'Other 2', 'email' => 'o2@example.com', 'password' => 'secret123']);
        $otherCouple = Couple::create([
            'user1_id' => $otherU1->id,
            'user2_id' => $otherU2->id,
            'user1_quota' => 0.5,
            'user2_quota' => 0.5,
        ]);
        Category::create([
            'couple_id' => $otherCouple->id,
            'name' => 'Outro casal',
            'color' => '#ef4444',
            'icon' => '❌',
        ]);

        Sanctum::actingAs($this->user2);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $data = $response->json('categories');
        $this->assertCount(1, $data);
        $this->assertEquals('Aluguel', $data[0]['name']);
    }

    public function test_user_can_delete_category(): void
    {
        $cat = Category::create([
            'couple_id' => $this->couple->id,
            'name' => 'Para Deletar',
            'color' => '#6366f1',
            'icon' => '🗑️',
        ]);

        Sanctum::actingAs($this->user1);

        $response = $this->deleteJson('/api/categories/' . $cat->id);
        $response->assertStatus(204);

        $this->assertDatabaseMissing('categories', ['id' => $cat->id]);
    }
}

