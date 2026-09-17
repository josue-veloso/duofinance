<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use App\Services\ExpenseService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $expenseService = app(ExpenseService::class);

        // Create or find Users
        $user1 = User::firstOrCreate(
            ['email' => 'user1@duofinance.dev'],
            [
                'name' => 'Usuário 1',
                'password' => Hash::make('password'),
            ]
        );

        $user2 = User::firstOrCreate(
            ['email' => 'user2@duofinance.dev'],
            [
                'name' => 'Usuário 2',
                'password' => Hash::make('password'),
            ]
        );

        // Create or find Couple
        $couple = Couple::firstOrCreate(
            [
                'user1_id' => $user1->id,
                'user2_id' => $user2->id,
            ],
            [
                'user1_quota' => 0.5,
                'user2_quota' => 0.5,
            ]
        );

        // Seed Categories
        $categoriesData = [
            ['name' => 'Moradia', 'color' => '#6366f1', 'icon' => '🏠'],
            ['name' => 'Mercado', 'color' => '#22c55e', 'icon' => '🛒'],
            ['name' => 'Lazer', 'color' => '#f59e0b', 'icon' => '🎮'],
            ['name' => 'Pets', 'color' => '#ec4899', 'icon' => '🐾'],
            ['name' => 'Transporte', 'color' => '#14b8a6', 'icon' => '🚗'],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['name']] = Category::firstOrCreate(
                [
                    'couple_id' => $couple->id,
                    'name' => $c['name'],
                ],
                [
                    'color' => $c['color'],
                    'icon' => $c['icon'],
                ]
            );
        }

        // Categorias e Casal configurados. Gastos de teste mantidos limpos para teste do zero.
    }
}
