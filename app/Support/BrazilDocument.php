<?php

namespace App\Support;

class BrazilDocument
{
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    public static function isValid(?string $value): bool
    {
        $digits = self::normalize($value);

        return match (strlen((string) $digits)) {
            11 => self::isValidCpf((string) $digits),
            14 => self::isValidCnpj((string) $digits),
            default => false,
        };
    }

    public static function format(?string $value): ?string
    {
        $digits = self::normalize($value);

        return match (strlen((string) $digits)) {
            11 => sprintf(
                '%s.%s.%s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 3),
                substr($digits, 9, 2),
            ),
            14 => sprintf(
                '%s.%s.%s/%s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 3),
                substr($digits, 5, 3),
                substr($digits, 8, 4),
                substr($digits, 12, 2),
            ),
            default => $digits,
        };
    }

    public static function mask(?string $value): ?string
    {
        $digits = self::normalize($value);

        if ($digits === null) {
            return null;
        }

        $last = substr($digits, -2);

        return strlen($digits) === 14
            ? '**.***.***/****-'.$last
            : '***.***.***-'.$last;
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += ((int) $cpf[$index]) * (($position + 1) - $index);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        return (int) $cnpj[12] === self::calculateCnpjDigit(substr($cnpj, 0, 12), $firstWeights)
            && (int) $cnpj[13] === self::calculateCnpjDigit(substr($cnpj, 0, 13), $secondWeights);
    }

    /**
     * @param  array<int, int>  $weights
     */
    private static function calculateCnpjDigit(string $base, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += ((int) $base[$index]) * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
