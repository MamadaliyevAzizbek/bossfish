<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogProduct extends Model
{
    public const TYPE_FEED = 'feed';

    public const TYPE_MEDICINE = 'medicine';

    public const TYPE_EQUIPMENT = 'equipment';

    public const TYPES = [
        self::TYPE_FEED,
        self::TYPE_MEDICINE,
        self::TYPE_EQUIPMENT,
    ];

    protected $fillable = [
        'type',
        'name',
        'slogan',
        'description',
        'image_path',
        'price',
        'price_unit',
        'usage_text',
        'theme',
        'specs',
        'features',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'specs' => 'array',
            'features' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function formattedPrice(): string
    {
        $n = (float) $this->price;
        $amount = number_format($n, $n == floor($n) ? 0 : 2, '.', ' ');
        $unit = trim((string) ($this->price_unit ?: "so‘m"));

        return "{$amount} {$unit}";
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'slogan' => $this->slogan,
            'description' => $this->description,
            'image' => $this->image_path,
            'price' => (float) $this->price,
            'price_label' => $this->formattedPrice(),
            'price_unit' => $this->price_unit,
            'usage_text' => $this->usage_text,
            'theme' => $this->theme ?: 'teal',
            'specs' => array_values($this->specs ?? []),
            'features' => array_values($this->features ?? []),
            'sort_order' => $this->sort_order,
        ];
    }

    public function toOwnerArray(): array
    {
        return $this->toPublicArray() + [
            'is_active' => (bool) $this->is_active,
            'created_at' => optional($this->created_at)?->toDateTimeString(),
            'updated_at' => optional($this->updated_at)?->toDateTimeString(),
        ];
    }
}
