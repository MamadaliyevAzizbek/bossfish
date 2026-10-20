<?php

namespace App\Support;

class VengerFeedTypes
{
    public const EXTRUDER = 'extruder';

    public const GRANULA = 'granula';

    public static function all(): array
    {
        return [
            self::EXTRUDER,
            self::GRANULA,
        ];
    }

    /** Validatsiya: yangi + eski (proteinli) kodlar */
    public static function accepted(): array
    {
        return [
            self::EXTRUDER,
            self::GRANULA,
            'extruder_37',
            'extruder_35',
            'granula_37',
            'granula_35',
            'granula_32',
        ];
    }

    public static function labels(): array
    {
        return [
            self::EXTRUDER => 'Ekstruder',
            self::GRANULA => 'Granula',
        ];
    }

    public static function normalize(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        if (str_starts_with($type, 'extruder')) {
            return self::EXTRUDER;
        }

        if (str_starts_with($type, 'granula')) {
            return self::GRANULA;
        }

        return in_array($type, self::all(), true) ? $type : null;
    }

    public static function label(string $type): string
    {
        $normalized = self::normalize($type) ?? $type;

        return self::labels()[$normalized] ?? $type;
    }

    public static function group(string $type): string
    {
        return self::normalize($type) ?? $type;
    }
}
