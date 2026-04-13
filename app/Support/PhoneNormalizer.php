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
        $localNormalized = $normalized && $countryCode && str_starts_with($normalized, $countryCode)
            ? substr($normalized, strlen($countryCode))
            : null;

        $baseVariants = array_values(array_filter([
            $normalized,
            $digits,
            $localNormalized,
        ]));

        $variants = $baseVariants;

        foreach ($baseVariants as $variant) {
            $variants = [
                ...$variants,
                ...self::regionalVariants($variant, $countryCode),
            ];
        }

        return array_values(array_unique($variants));
    }

    /**
     * @return list<string>
     */
    private static function regionalVariants(string $value, ?string $countryCode): array
    {
        if ($countryCode !== '55') {
            return [];
        }

        $digits = self::digits($value);

        if ($digits === null) {
            return [];
        }

        $localDigits = str_starts_with($digits, $countryCode)
            ? substr($digits, strlen($countryCode))
            : $digits;

        if (!is_string($localDigits) || !in_array(strlen($localDigits), [10, 11], true)) {
            return [];
        }

        $areaCode = substr($localDigits, 0, 2);
        $subscriber = substr($localDigits, 2);

        if (!is_string($subscriber) || !self::isBrazilianMobileSubscriber($subscriber)) {
            return [];
        }

        if (strlen($subscriber) === 8) {
            $mobileLocalDigits = $areaCode.'9'.$subscriber;

            return [$mobileLocalDigits, $countryCode.$mobileLocalDigits];
        }

        $mobileWithoutNinthDigit = $areaCode.substr($subscriber, 1);

        return [$mobileWithoutNinthDigit, $countryCode.$mobileWithoutNinthDigit];
    }

    private static function isBrazilianMobileSubscriber(string $subscriber): bool
    {
        if (strlen($subscriber) === 8) {
            return preg_match('/^[6-9]/', $subscriber) === 1;
        }

        if (strlen($subscriber) === 9) {
            return str_starts_with($subscriber, '9')
                && preg_match('/^[6-9]/', substr($subscriber, 1)) === 1;
        }

        return false;
    }
}
