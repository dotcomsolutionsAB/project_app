<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ProductModel;
use App\Models\CategoryModel;
use App\Models\SubCategoryModel;

class AdminProductCatalogController extends Controller
{
    private const PLACEHOLDER_IMAGE = '/storage/uploads/products/placeholder.jpg';

    public function meta()
    {
        $categories = CategoryModel::query()->orderBy('name')->pluck('name')->filter()->values();

        $subCategories = SubCategoryModel::query()->orderBy('name')->pluck('name')->filter()->values();

        $brands = ProductModel::query()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->values();

        return response()->json([
            'data' => [
                'categories' => $categories,
                'sub_categories' => $subCategories,
                'brands' => $brands,
            ],
        ], 200);
    }

    public function list(Request $request)
    {
        $limit = max(1, min(100, (int) $request->input('limit', 25)));
        $offset = max(0, (int) $request->input('offset', 0));
        $search = $request->input('search');
        $category = $request->input('category');
        $subCategory = $request->input('sub_category');
        $brand = $request->input('brand');
        $missingImage = filter_var($request->input('missing_image', false), FILTER_VALIDATE_BOOLEAN);

        $query = ProductModel::query();

        if ($search) {
            $term = trim((string) $search);
            $query->where(function ($q) use ($term) {
                $q->where('product_name', 'like', "%{$term}%")
                    ->orWhere('product_code', 'like', "%{$term}%")
                    ->orWhere('machine_part_no', 'like', "%{$term}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($subCategory) {
            $query->where('sub_category', $subCategory);
        }

        if ($brand) {
            $query->where('brand', $brand);
        }

        if ($missingImage) {
            $ph = self::PLACEHOLDER_IMAGE;
            $query->where(function ($w) use ($ph) {
                $w->whereNull('product_image')
                    ->orWhere('product_image', '')
                    ->orWhere('product_image', $ph);
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByRaw('COALESCE(order_by, 999999) asc')
            ->orderBy('product_code')
            ->skip($offset)
            ->take($limit)
            ->get();

        $data = $rows->map(fn ($p) => $this->transformProduct($p))->values()->all();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Products retrieved.',
            'data' => $data,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
            ],
        ], 200);
    }

    private function transformProduct(ProductModel $p): array
    {
        $brandName = $p->brand !== null && $p->brand !== '' ? (string) $p->brand : '';

        return [
            'id' => $p->id,
            'sku' => $p->product_code,
            'grade_no' => $p->machine_part_no ?? '',
            'item_name' => $p->product_name,
            'size' => $p->size ?? '',
            'brand' => $brandName !== ''
                ? ['id' => abs(crc32($brandName)) % 2147483647, 'name' => $brandName]
                : null,
            'finish_type' => $p->type ?? '-',
            'thread' => null,
            'pitch' => null,
            'head' => null,
            'units' => 'PCS',
            'list_price' => $p->basic !== null ? (string) $p->basic : '',
            'hsn' => $p->hsn ?? null,
            'tax' => $p->tax !== null ? (string) $p->tax : ($p->gst !== null ? (string) $p->gst : ''),
            'low_stock_level' => (int) ($p->re_order_level ?? 0),
            'created_at' => $p->created_at?->toIso8601String() ?? '',
            'updated_at' => $p->updated_at?->toIso8601String() ?? '',
            'product_image' => $p->product_image,
            'category' => $p->category,
            'sub_category' => $p->sub_category,
        ];
    }
}
