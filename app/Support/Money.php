<?php

namespace App\Support;

/**
 * Exact decimal arithmetic for money amounts, without depending on the
 * bcmath extension (not guaranteed to be installed on every PHP setup).
 * All amounts are decimal strings with 2 fraction digits, e.g. "12.50".
 */
class Money
{
    public static function add(string $a, string $b): string
    {
        return self::fromCents(self::toCents($a) + self::toCents($b));
    }

    public static function sub(string $a, string $b): string
    {
        return self::fromCents(self::toCents($a) - self::toCents($b));
    }

    public static function compare(string $a, string $b): int
    {
        return self::toCents($a) <=> self::toCents($b);
    }

    public static function mul(string $a, int $times): string
    {
        return self::fromCents(self::toCents($a) * $times);
    }

    /**
     * Split a total into $parts installments, allocating the rounding
     * remainder to the last installment so the parts always sum back to $total.
     *
     * @return array<int, string>
     */
    public static function splitEvenly(string $total, int $parts): array
    {
        $totalCents = self::toCents($total);
        $baseCents = intdiv($totalCents, $parts);
        $remainder = $totalCents - ($baseCents * $parts);

        $amounts = array_fill(0, $parts, self::fromCents($baseCents));
        $amounts[$parts - 1] = self::fromCents($baseCents + $remainder);

        return $amounts;
    }

    private static function toCents(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        $cents = ((int) $integer * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        return $sign.number_format(abs($cents) / 100, 2, '.', '');
    }
}
