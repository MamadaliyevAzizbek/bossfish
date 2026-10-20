<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogProduct;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogProductController extends Controller
{
    public function publicIndex(Request $request)
    {
        $type = $request->query('type');

        $query = CatalogProduct::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($type) {
            $query->where('type', $type);
        }

        return response()->json([
            'products' => $query->get()->map->toPublicArray()->values(),
        ]);
    }

    public function index(Request $request)
    {
        $type = $request->query('type');

        $query = CatalogProduct::query()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($type) {
            $query->where('type', $type);
        }

        return response()->json([
            'products' => $query->get()->map->toOwnerArray()->values(),
            'types' => CatalogProduct::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);
        $data['image_path'] = $this->storeImage($request, $data['type']);
        $data['sort_order'] = (int) (CatalogProduct::query()->where('type', $data['type'])->max('sort_order') ?? 0) + 1;
        $data['is_active'] = true;

        $product = CatalogProduct::query()->create($data);

        return response()->json($product->toOwnerArray(), 201);
    }

    public function update(Request $request, CatalogProduct $catalogProduct)
    {
        // Mavjud mahsulotda faqat narx (va ixtiyoriy price_unit) o‘zgaradi
        $data = $request->validate([
            'price' => 'required|numeric|min:0|max:999999999',
            'price_unit' => 'nullable|string|max:64',
        ]);

        $catalogProduct->update([
            'price' => $data['price'],
            'price_unit' => $data['price_unit'] ?? $catalogProduct->price_unit,
        ]);

        return response()->json($catalogProduct->fresh()->toOwnerArray());
    }

    public function destroy(CatalogProduct $catalogProduct)
    {
        $catalogProduct->update(['is_active' => false]);

        return response()->json(['ok' => true]);
    }

    private function validateProduct(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(CatalogProduct::TYPES)],
            'name' => 'required|string|max:255',
            'slogan' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:0|max:999999999',
            'price_unit' => 'nullable|string|max:64',
            'usage_text' => 'nullable|string|max:1000',
            'theme' => 'nullable|string|max:32',
            'specs' => 'nullable|array',
            'specs.*' => 'nullable|string|max:120',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:500',
            'image' => 'nullable|image|max:5120',
        ]);

        $data['theme'] = $data['theme'] ?? match ($data['type']) {
            CatalogProduct::TYPE_FEED => 'red',
            CatalogProduct::TYPE_EQUIPMENT => 'blue',
            default => 'teal',
        };
        $data['price_unit'] = $data['price_unit'] ?: "so‘m / qop";
        $data['specs'] = array_values(array_filter($data['specs'] ?? []));
        $data['features'] = array_values(array_filter($data['features'] ?? []));

        return $data;
    }

    private function storeImage(Request $request, string $type): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $file = $request->file('image');
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'image' => ['Rasm yuklanmadi.'],
            ]);
        }

        $dir = public_path('images/products/'.$type);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = uniqid($type.'-', true).'.'.$file->getClientOriginalExtension();
        $file->move($dir, $name);

        return '/images/products/'.$type.'/'.$name;
    }
}
