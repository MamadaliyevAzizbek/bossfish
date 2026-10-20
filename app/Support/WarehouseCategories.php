<?php

namespace App\Support;

class WarehouseCategories
{
    public const FEED = 'feed';

    public const MEDICINE = 'medicine';

    public const EQUIPMENT = 'equipment';

    public static function all(): array
    {
        return [self::FEED, self::MEDICINE, self::EQUIPMENT];
    }

    public static function consumable(): array
    {
        return [self::FEED, self::MEDICINE];
    }

    public static function labels(): array
    {
        return [
            self::FEED => 'Yem',
            self::MEDICINE => 'Dori',
            self::EQUIPMENT => 'Texnika',
        ];
    }
}
