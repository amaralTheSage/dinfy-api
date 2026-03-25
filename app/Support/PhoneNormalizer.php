<?php

namespace App\Support;

class PhoneNormalizer
{
    public static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    public static function normalize(?string $value): ?string
    {
        $digits = self::digits($value);

        if ($digits === null) {
            return null;
        }

        $countryCode = self::digits((string) config('assistant.phone.default_country_code'));
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return null;
        }

        if ($countryCode === null || $countryCode === '') {
            return $digits;
        }

        if (str_starts_with($digits, $countryCode) && strlen($digits) >= strlen($countryCode) + 10) {
            return $digits;
        }

        if (in_array(strlen($digits), [10, 11], true)) {
            return $countryCode.$digits;
        }

        return $digits;
    }

    /**
     * @return list<string>
     */
    public static function variants(?string $value): array
    {
        $digits = self::digits($value);
        $normalized = self::normalize($value);
        $countryCode = self::digits((string) config('assistant.phone.default_country_code'));

        $variants = array_filter([
            $normalized,
            $digits,
            $normalized && $countryCode && str_starts_with($normalized, $countryCode)
                ? substr($normalized, strlen($countryCode))
                : null,
        ]);

        return array_values(array_unique($variants));
    }
}
