<?php

namespace App\Support;

class InboxPhone
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = '966'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            $digits = '966'.$digits;
        }

        return $digits;
    }

    public static function e164(string $phone): string
    {
        $digits = self::normalize($phone);

        return $digits === '' ? '' : '+'.$digits;
    }

    public static function isValidE164(string $phone): bool
    {
        $digits = self::normalize($phone);

        return strlen($digits) >= 8 && strlen($digits) <= 15;
    }

    public static function display(string $phone): string
    {
        $digits = self::normalize($phone);

        if ($digits === '') {
            return $phone;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '966')) {
            return '+966 '.substr($digits, 3, 2).' '.substr($digits, 5, 3).' '.substr($digits, 8);
        }

        return '+'.$digits;
    }

    /**
     * Match variants stored on orders (05..., 966..., +966...).
     *
     * @return list<string>
     */
    public static function lookupVariants(string $phone): array
    {
        $digits = self::normalize($phone);

        if ($digits === '') {
            return [];
        }

        $variants = [
            $digits,
            '+'.$digits,
        ];

        if (str_starts_with($digits, '966') && strlen($digits) === 12) {
            $local = '0'.substr($digits, 3);
            $variants[] = $local;
            $variants[] = substr($digits, 3);
        }

        return array_values(array_unique($variants));
    }
}
