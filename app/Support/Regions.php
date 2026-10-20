<?php

namespace App\Support;

class Regions
{
    public static function all(): array
    {
        return array_keys(self::coordinates());
    }

    /** Viloyat markazlari — xarita uchun */
    public static function coordinates(): array
    {
        return [
            'Andijon' => ['lat' => 40.782, 'lng' => 72.344],
            'Buxoro' => ['lat' => 39.768, 'lng' => 64.422],
            'Farg\'ona' => ['lat' => 40.384, 'lng' => 71.786],
            'Jizzax' => ['lat' => 40.116, 'lng' => 67.842],
            'Xorazm' => ['lat' => 41.550, 'lng' => 60.633],
            'Namangan' => ['lat' => 40.998, 'lng' => 71.673],
            'Navoiy' => ['lat' => 40.103, 'lng' => 65.379],
            'Qashqadaryo' => ['lat' => 38.861, 'lng' => 65.785],
            'Qoraqalpog\'iston' => ['lat' => 42.460, 'lng' => 59.600],
            'Samarqand' => ['lat' => 39.654, 'lng' => 66.960],
            'Sirdaryo' => ['lat' => 40.850, 'lng' => 68.665],
            'Surxondaryo' => ['lat' => 37.940, 'lng' => 67.570],
            'Toshkent viloyati' => ['lat' => 41.020, 'lng' => 69.620],
            'Toshkent shahri' => ['lat' => 41.311, 'lng' => 69.279],
        ];
    }

    public static function latLng(string $region): ?array
    {
        return self::coordinates()[$region] ?? null;
    }
}
