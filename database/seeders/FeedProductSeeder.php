<?php

namespace Database\Seeders;

use App\Models\FeedProduct;
use Illuminate\Database\Seeder;

class FeedProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Бугдой месний', 'price_per_kg' => 3800, 'category' => 'feed'],
            ['name' => 'Макка', 'price_per_kg' => 4000, 'category' => 'feed'],
            ['name' => 'Соя', 'price_per_kg' => 8620, 'category' => 'feed'],
            ['name' => 'Семичка киммати', 'price_per_kg' => 3940, 'category' => 'feed'],
            ['name' => 'Семичка Арзон', 'price_per_kg' => 2762, 'category' => 'feed'],
            ['name' => 'МЯС', 'price_per_kg' => 2700, 'category' => 'feed'],
            ['name' => 'Рыба', 'price_per_kg' => 7100, 'category' => 'feed'],
            ['name' => 'Мой', 'price_per_kg' => 18000, 'category' => 'feed'],
            ['name' => 'Кепак', 'price_per_kg' => 3520, 'category' => 'feed'],
            ['name' => 'Арпа', 'price_per_kg' => 3700, 'category' => 'feed'],
            ['name' => 'Мош', 'price_per_kg' => 4700, 'category' => 'feed'],
            ['name' => 'Рапс', 'price_per_kg' => 5000, 'category' => 'feed'],
            ['name' => 'Мучка', 'price_per_kg' => 3000, 'category' => 'feed'],
            ['name' => 'Кунжара', 'price_per_kg' => 6600, 'category' => 'feed'],
            ['name' => 'Сечка', 'price_per_kg' => 3500, 'category' => 'feed'],
            ['name' => 'Ун', 'price_per_kg' => 4300, 'category' => 'feed'],
            ['name' => 'Премикс WB14U', 'price_per_kg' => 51008, 'category' => 'feed'],
            ['name' => 'Премикс WB22HU', 'price_per_kg' => 53692, 'category' => 'feed'],
            ['name' => 'Премикс WB32HU', 'price_per_kg' => 48568, 'category' => 'feed'],
            ['name' => 'Премикс BR25ZY', 'price_per_kg' => 43200, 'category' => 'feed'],
            ['name' => 'Shakar', 'price_per_kg' => 12000, 'category' => 'feed'],
            ['name' => 'Премикх Старт', 'price_per_kg' => 39200, 'category' => 'vitamin'],
            ['name' => 'Премикх Рост', 'price_per_kg' => 34496, 'category' => 'vitamin'],
            ['name' => 'Премикх Финиш', 'price_per_kg' => 33600, 'category' => 'vitamin'],
            ['name' => 'Премикх Без красител', 'price_per_kg' => 75500, 'category' => 'vitamin'],
            ['name' => 'Премикх Красител', 'price_per_kg' => 100500, 'category' => 'vitamin'],
            ['name' => 'Карбомид', 'price_per_kg' => 4600, 'category' => 'vitamin'],
            ['name' => 'Холинг хлорид', 'price_per_kg' => 16400, 'category' => 'vitamin'],
            ['name' => 'Лизинг', 'price_per_kg' => 22500, 'category' => 'vitamin'],
            ['name' => 'Метионин', 'price_per_kg' => 80500, 'category' => 'vitamin'],
            ['name' => 'Соль', 'price_per_kg' => 950, 'category' => 'vitamin'],
            ['name' => 'Сулфат Натрий', 'price_per_kg' => 4500, 'category' => 'vitamin'],
            ['name' => 'Монокалций', 'price_per_kg' => 14200, 'category' => 'vitamin'],
            ['name' => 'Трикалций', 'price_per_kg' => 5090, 'category' => 'vitamin'],
            ['name' => 'Охак', 'price_per_kg' => 400, 'category' => 'vitamin'],
            ['name' => 'Дрож', 'price_per_kg' => 4500, 'category' => 'vitamin'],
            ['name' => 'Ракушка', 'price_per_kg' => 1000, 'category' => 'vitamin'],
            ['name' => 'Хлапковий пратейн', 'price_per_kg' => 14760, 'category' => 'vitamin'],
            ['name' => 'Мол Премикс 895', 'price_per_kg' => 17500, 'category' => 'vitamin'],
            ['name' => 'Премикс Рыба', 'price_per_kg' => 53780, 'category' => 'vitamin'],
            ['name' => 'Karp CCT 5%', 'price_per_kg' => 41500, 'category' => 'vitamin'],
            ['name' => 'Toxo MX', 'price_per_kg' => 25500, 'category' => 'vitamin'],
            ['name' => 'Подкислитель Sal-tech', 'price_per_kg' => 40000, 'category' => 'vitamin'],
            ['name' => 'Sammix Ctart', 'price_per_kg' => 35500, 'category' => 'vitamin'],
            ['name' => 'Sammix Rost', 'price_per_kg' => 30000, 'category' => 'vitamin'],
            ['name' => 'Sammix Finish', 'price_per_kg' => 31000, 'category' => 'vitamin'],
        ];

        foreach ($products as $index => $row) {
            FeedProduct::updateOrCreate(
                ['name' => $row['name']],
                [
                    'price_per_kg' => $row['price_per_kg'],
                    'category' => $row['category'],
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
