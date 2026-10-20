<?php

namespace App\Support;

class FishTypes
{
    public static function all(): array
    {
        return [
            'sazan',
            'tolstolob',
            'oq_amur',
            'targil_tolstolob',
        ];
    }

    public static function labels(): array
    {
        return [
            'sazan' => 'Sazan',
            'tolstolob' => 'Tolstolob',
            'oq_amur' => 'Oq amur',
            'targil_tolstolob' => 'Targ‘il tolstolob (Pyostriy)',
        ];
    }
}
