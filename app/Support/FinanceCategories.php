<?php

namespace App\Support;

class FinanceCategories
{
    public const FEED = 'feed';

    public const MEDICINE = 'medicine';

    public const SALARY = 'salary';

    public const OTHER = 'other';

    /** @var list<string> */
    public const EXPENSE = [
        self::FEED,
        self::MEDICINE,
        self::SALARY,
        self::OTHER,
    ];

    /** @var list<string> */
    public const WAREHOUSE_SYNC = [
        self::FEED,
        self::MEDICINE,
    ];

    public static function label(string $category): string
    {
        return match ($category) {
            self::FEED => 'Yem',
            self::MEDICINE => 'Dori-darmon',
            self::SALARY => 'Ish haqi',
            self::OTHER => 'Boshqa xarajatlar',
            default => $category,
        };
    }

    public static function isFixed(string $category): bool
    {
        return in_array($category, self::EXPENSE, true);
    }

    public static function isCustom(string $category): bool
    {
        return (bool) preg_match('/^custom:\d+$/', $category);
    }

    public static function customId(string $category): ?int
    {
        if (! self::isCustom($category)) {
            return null;
        }

        $id = (int) substr($category, 7);

        return $id > 0 ? $id : null;
    }

    public static function customKey(int $sectionId): string
    {
        return 'custom:'.$sectionId;
    }

    public static function syncsToWarehouse(string $category): bool
    {
        return in_array($category, self::WAREHOUSE_SYNC, true);
    }
}
