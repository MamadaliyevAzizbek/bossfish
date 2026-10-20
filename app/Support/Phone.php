<?php

namespace App\Support;

class Phone
{
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '998')) {
            $digits = substr($digits, 3);
        }

        $digits = substr($digits, 0, 9);

        return '+998'.$digits;
    }

    public static function localPart(?string $phone): string
    {
        $full = self::normalize($phone);

        return substr($full, 4);
    }

    public static function isComplete(?string $phone): bool
    {
        return (bool) preg_match('/^\+998\d{9}$/', self::normalize($phone));
    }
}
