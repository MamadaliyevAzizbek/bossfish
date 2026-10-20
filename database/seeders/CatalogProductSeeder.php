<?php

namespace Database\Seeders;

use App\Models\CatalogProduct;
use Illuminate\Database\Seeder;

class CatalogProductSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'type' => CatalogProduct::TYPE_MEDICINE,
                'name' => 'careGut — Sog‘lom ichak',
                'slogan' => 'Sog‘lom ichak – barqaror o‘sish!',
                'description' => 'Ichak mikroflorasini tartibga soladi, foydali mikroorganizmlar orqali ferment hosil qiladi, oziq moddalarning so‘rilishini yaxshilaydi va o‘sishni tezlashtiradi. Metabolik chiqindilarni tozalab, mahsulot ta’mini yaxshilaydi.',
                'image_path' => '/images/products/medicine/caregut.jpg',
                'price' => 150000,
                'price_unit' => "so‘m / qop",
                'usage_text' => 'Har tonna yemga 3–5 kg qo‘shish tavsiya etiladi.',
                'theme' => 'teal',
                'specs' => [
                    'Ichak mikroflorasi',
                    'Fermentlar',
                    'O‘sish stimulyatori',
                    'Detoks',
                ],
                'features' => [
                    'Ichak mikroflorasi tuzilishini tartibga soladi va oziq so‘rilishini kuchaytiradi.',
                    'Foydali mikroorganizmlar suv hayvonlarida faol ferment ishlab chiqaradi.',
                    'O‘sishga bog‘liq metabolik omillarni qo‘llab-quvvatlaydi.',
                    'Metabolik chiqindi va toksinlarni tozalaydi.',
                ],
                'sort_order' => 1,
            ],
            [
                'type' => CatalogProduct::TYPE_MEDICINE,
                'name' => 'CareLiver — Sog‘lom jigar',
                'slogan' => 'Sog‘lom jigar – sog‘lom baliq!',
                'description' => 'Jigar va o‘t pufagini himoya qiladi, gepatotsitlar regeneratsiyasini rag‘batlantiradi, immunitetni oshiradi va o‘sishni qo‘llab-quvvatlaydi. Lipiddar metabolizmini me’yorlaydi, jigar kasalliklarining oldini oladi.',
                'image_path' => '/images/products/medicine/careliver.jpg',
                'price' => 150000,
                'price_unit' => "so‘m / qop",
                'usage_text' => 'Har tonna yemga 3–5 kg qo‘shish tavsiya etiladi.',
                'theme' => 'violet',
                'specs' => [
                    'Jigar himoyasi',
                    'Immunitet',
                    'Yuqori o‘sish',
                    'Detoks',
                ],
                'features' => [
                    'Jigar va o‘t pufagini himoya qiladi, safro ishlab chiqarishni yaxshilaydi.',
                    'Shikastlangan jigarning tiklanishiga ijobiy ta’sir ko‘rsatadi.',
                    'Muntazam qo‘shilganda immunitet va o‘sishni oshiradi.',
                    'Lipiddar almashinuvini qo‘llab-quvvatlaydi va kasalliklarning oldini oladi.',
                ],
                'sort_order' => 2,
            ],
            [
                'type' => CatalogProduct::TYPE_MEDICINE,
                'name' => 'BattoMaster — Hovuz nafasi',
                'slogan' => 'Toza hovuz – barqaror hosil!',
                'description' => 'Hovuz suvini tozalash va sifatini yaxshilash vositasi. Hidni bartaraf etadi, zararli mikroorganizmlarni bosadi, ammoniy azot, nitrit va vodorod sulfidini zararsiz nitratlarga aylantiradi, kislorod yetishmovchiligining oldini oladi.',
                'image_path' => '/images/products/medicine/battomaster.jpg',
                'price' => 200000,
                'price_unit' => "so‘m / qop",
                'usage_text' => '1 ga ga 3000–4500 g (taxminan 1.5–2.5 qop). 2 kun ketma-ket; 7–10 kunda takrorlash mumkin.',
                'theme' => 'indigo',
                'specs' => [
                    'Toza suv',
                    'O₂ muvozanati',
                    'Hovuz tubi',
                    'Yuqori hosil',
                ],
                'features' => [
                    'Hidni neytrallashtiradi va tubdagi zararli mikroorganizmlarni bosadi.',
                    'Zararli birikmalarni nitratlarga oksidlaydi.',
                    '3 soatdan ortiq erish vaqti — yumshoq ta’sir.',
                    'Kislorod yetishmovchiligining oldini oladi va davolaydi.',
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($items as $item) {
            CatalogProduct::query()->updateOrCreate(
                ['type' => $item['type'], 'name' => $item['name']],
                $item + ['is_active' => true]
            );
        }
    }
}
