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
            'Supermercado' => '#fb923c',
            'Restaurantes' => '#fdba74',
            'Transporte' => '#3b82f6',
            'Combustível' => '#60a5fa',
            'Moradia' => '#8b5cf6',
            'Contas de Casa' => '#a78bfa',
            'Internet e Telefone' => '#7c3aed',
            'Saúde' => '#ef4444',
            'Farmácia' => '#f87171',
            'Academia' => '#dc2626',
            'Lazer' => '#ec4899',
            'Viagem' => '#f472b6',
            'Educação' => '#0ea5e9',
            'Cursos' => '#38bdf8',
            'Compras' => '#f59e0b',
            'Vestuário' => '#fbbf24',
            'Eletrônicos' => '#d97706',
            'Assinaturas' => '#6366f1',
            'Streaming' => '#818cf8',
            'Pets' => '#10b981',
            'Filhos' => '#06b6d4',
            'Presentes' => '#e879f9',
            'Impostos e Taxas' => '#78716c',
            'Seguros' => '#57534e',
            'Investimentos' => '#65a30d',
            'Dívidas e Empréstimos' => '#b91c1c',
            'Doações' => '#f43f5e',
            'Outros' => '#64748b',
        ];

        $income = [
            'Salário' => '#22c55e',
            'Freelance' => '#14b8a6',
            'Rendimentos' => '#84cc16',
            'Investimentos' => '#65a30d',
            'Aluguel Recebido' => '#0d9488',
            'Reembolso' => '#059669',
            'Presente Recebido' => '#e879f9',
            'Vendas' => '#4d7c0f',
            '13º Salário' => '#16a34a',
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
