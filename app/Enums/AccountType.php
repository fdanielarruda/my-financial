<?php

namespace App\Enums;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Wallet = 'wallet';
    case Investment = 'investment';
    case CreditCard = 'credit_card';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Conta corrente',
            self::Savings => 'Poupança',
            self::Wallet => 'Carteira/Dinheiro',
            self::Investment => 'Investimento',
            self::CreditCard => 'Cartão de crédito',
        };
    }
}
