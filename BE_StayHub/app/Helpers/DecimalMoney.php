<?php

namespace App\Helpers;

class DecimalMoney
{
    public const SCALE = 2;

    public static function normalize(mixed $value, int $scale = self::SCALE): string
    {
        $value = trim((string) ($value ?? '0'));
        $value = str_replace(' ', '', $value);

        if ($value === '') {
            $value = '0';
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = preg_replace('/\D/', '', $integer) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction) ?: '';

        $roundDigit = strlen($fraction) > $scale ? (int) $fraction[$scale] : 0;
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $scaled = ((int) $integer * (10 ** $scale)) + (int) $fraction;

        if ($roundDigit >= 5) {
            $scaled++;
        }

        if ($negative) {
            $scaled *= -1;
        }

        return self::fromScaledInteger($scaled, $scale);
    }

    public static function add(array $values, int $scale = self::SCALE): string
    {
        $total = 0;

        foreach ($values as $value) {
            $total += self::toScaledInteger($value, $scale);
        }

        return self::fromScaledInteger($total, $scale);
    }

    public static function subtract(mixed $left, mixed $right, int $scale = self::SCALE): string
    {
        return self::fromScaledInteger(
            self::toScaledInteger($left, $scale) - self::toScaledInteger($right, $scale),
            $scale
        );
    }

    public static function multiply(mixed $left, mixed $right, int $scale = self::SCALE): string
    {
        if (function_exists('bcmul')) {
            $raw = bcmul(self::normalize($left, $scale), self::normalize($right, $scale), $scale + 4);

            return self::roundBc($raw, $scale);
        }

        [$leftSign, $leftDigits] = self::toScaledDigits($left, $scale);
        [$rightSign, $rightDigits] = self::toScaledDigits($right, $scale);
        $product = self::multiplyUnsignedStrings($leftDigits, $rightDigits);
        $scaledProduct = self::divideByPowerOfTenRounded($product, $scale);

        return self::fromScaledDigits($scaledProduct, $leftSign * $rightSign, $scale);
    }

    public static function prorateByDays(mixed $amount, int $actualDays, int $totalDays, int $scale = self::SCALE): string
    {
        if ($actualDays <= 0 || $totalDays <= 0) {
            return self::fromScaledInteger(0, $scale);
        }

        $scaledAmount = self::toScaledInteger($amount, $scale) * $actualDays;
        $sign = $scaledAmount < 0 ? -1 : 1;
        $scaledAmount = abs($scaledAmount);
        $scaled = intdiv($scaledAmount + intdiv($totalDays, 2), $totalDays) * $sign;

        return self::fromScaledInteger($scaled, $scale);
    }

    public static function compare(mixed $left, mixed $right, int $scale = self::SCALE): int
    {
        return self::toScaledInteger($left, $scale) <=> self::toScaledInteger($right, $scale);
    }

    public static function isPositive(mixed $value, int $scale = self::SCALE): bool
    {
        return self::compare($value, '0', $scale) > 0;
    }

    public static function maxZero(mixed $value, int $scale = self::SCALE): string
    {
        return self::compare($value, '0', $scale) < 0 ? self::fromScaledInteger(0, $scale) : self::normalize($value, $scale);
    }

    public static function min(mixed $left, mixed $right, int $scale = self::SCALE): string
    {
        return self::compare($left, $right, $scale) <= 0 ? self::normalize($left, $scale) : self::normalize($right, $scale);
    }

    public static function toIntegerAmount(mixed $value, int $scale = self::SCALE): int
    {
        $scaled = self::toScaledInteger($value, $scale);
        $sign = $scaled < 0 ? -1 : 1;
        $scaled = abs($scaled);

        return intdiv($scaled + 50, 100) * $sign;
    }

    public static function toScaledInteger(mixed $value, int $scale = self::SCALE): int
    {
        $normalized = self::normalize($value, $scale);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$integer, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $scaled = ((int) $integer * (10 ** $scale)) + (int) str_pad($fraction, $scale, '0');

        return $negative ? -$scaled : $scaled;
    }

    public static function fromScaledInteger(int $value, int $scale = self::SCALE): string
    {
        $negative = $value < 0;
        $value = abs($value);
        $factor = 10 ** $scale;
        $integer = intdiv($value, $factor);
        $fraction = str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . $integer . '.' . $fraction;
    }

    private static function roundBc(string $number, int $scale): string
    {
        $increment = '0.' . str_repeat('0', $scale) . '5';

        return bcadd($number, str_starts_with($number, '-') ? '-' . $increment : $increment, $scale);
    }

    private static function toScaledDigits(mixed $value, int $scale): array
    {
        $normalized = self::normalize($value, $scale);
        $sign = str_starts_with($normalized, '-') ? -1 : 1;
        $digits = ltrim(str_replace('.', '', ltrim($normalized, '-')), '0');

        return [$digits === '' ? 1 : $sign, $digits === '' ? '0' : $digits];
    }

    private static function multiplyUnsignedStrings(string $left, string $right): string
    {
        if ($left === '0' || $right === '0') {
            return '0';
        }

        $leftDigits = array_map('intval', array_reverse(str_split($left)));
        $rightDigits = array_map('intval', array_reverse(str_split($right)));
        $result = array_fill(0, count($leftDigits) + count($rightDigits), 0);

        foreach ($leftDigits as $leftIndex => $leftDigit) {
            foreach ($rightDigits as $rightIndex => $rightDigit) {
                $result[$leftIndex + $rightIndex] += $leftDigit * $rightDigit;
            }
        }

        for ($index = 0, $count = count($result); $index < $count; $index++) {
            $carry = intdiv($result[$index], 10);
            $result[$index] %= 10;

            if ($carry > 0) {
                $result[$index + 1] = ($result[$index + 1] ?? 0) + $carry;
            }
        }

        return ltrim(implode('', array_reverse($result)), '0') ?: '0';
    }

    private static function divideByPowerOfTenRounded(string $digits, int $power): string
    {
        if ($digits === '0') {
            return '0';
        }

        $cutPosition = strlen($digits) - $power;

        if ($cutPosition <= 0) {
            $quotient = '0';
            $discarded = str_pad($digits, $power, '0', STR_PAD_LEFT);
        } else {
            $quotient = substr($digits, 0, $cutPosition);
            $discarded = substr($digits, $cutPosition);
        }

        $threshold = '5' . str_repeat('0', max(0, $power - 1));
        if (str_pad($discarded, $power, '0', STR_PAD_RIGHT) >= $threshold) {
            $quotient = self::addUnsignedString($quotient, '1');
        }

        return ltrim($quotient, '0') ?: '0';
    }

    private static function addUnsignedString(string $left, string $right): string
    {
        $left = array_reverse(str_split($left));
        $right = array_reverse(str_split($right));
        $length = max(count($left), count($right));
        $carry = 0;
        $result = [];

        for ($index = 0; $index < $length; $index++) {
            $sum = (int) ($left[$index] ?? 0) + (int) ($right[$index] ?? 0) + $carry;
            $result[] = (string) ($sum % 10);
            $carry = intdiv($sum, 10);
        }

        if ($carry > 0) {
            $result[] = (string) $carry;
        }

        return implode('', array_reverse($result));
    }

    private static function fromScaledDigits(string $digits, int $sign, int $scale): string
    {
        $digits = ltrim($digits, '0') ?: '0';
        $isZero = $digits === '0';
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $integer = substr($digits, 0, -$scale) ?: '0';
        $fraction = substr($digits, -$scale);

        return ($sign < 0 && ! $isZero ? '-' : '') . $integer . '.' . $fraction;
    }
}
