<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed default categories for every user that has none yet.
     */
    public function run(): void
    {
        $expense = [
            'Alimentação' => '#f97316',
            'Transporte' => '#3b82f6',
            'Moradia' => '#8b5cf6',
            'Saúde' => '#ef4444',
            'Lazer' => '#ec4899',
            'Educação' => '#0ea5e9',
            'Compras' => '#f59e0b',
            'Assinaturas' => '#6366f1',
            'Outros' => '#64748b',
        ];

        $income = [
            'Salário' => '#22c55e',
            'Freelance' => '#14b8a6',
            'Rendimentos' => '#84cc16',
            'Outros' => '#64748b',
        ];

        User::all()->each(function (User $user) use ($expense, $income) {
            if ($user->categories()->exists()) {
                return;
            }

            foreach ($expense as $name => $color) {
                $user->categories()->create(['name' => $name, 'type' => TransactionType::Expense, 'color' => $color]);
            }

            foreach ($income as $name => $color) {
                $user->categories()->create(['name' => $name, 'type' => TransactionType::Income, 'color' => $color]);
            }
        });
    }
}
