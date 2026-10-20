<?php

namespace App\Support;

class WarehouseUnits
{
    public const KG = 'kg';

    public const PIECE = 'piece';

    public static function all(): array
    {
        return [self::KG, self::PIECE];
    }
}
