<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products — Public.
     * Chỉ trả về sản phẩm visible, hỗ trợ filter và tìm kiếm.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name')
            ->visible();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Tìm kiếm cơ bản theo tên
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Tìm kiếm nâng cao: khoảng giá
        if ($request->filled('min_price')) {
            $query->where('selling_price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('selling_price', '<=', $request->max_price);
        }

        $perPage   = min((int) $request->get('per_page', 12), 100);
        $products  = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * GET /api/products/{id} — Public.
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with('category:id,name')->find($id);

        if (!$product || $product->status === ProductStatus::Hidden) {
            return response()->json(['message' => 'Sản phẩm không tồn tại'], 404);
        }

        return response()->json(['data' => $product]);
    }

    /**
     * POST /api/admin/products — Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'                => 'required|string|max:50|unique:products,code',
            'name'                => 'required|string|max:200',
            'category_id'         => 'required|integer|exists:categories,id',
            'description'         => 'nullable|string',
            'unit'                => 'required|string|max:30',
            'quantity'            => 'sometimes|integer|min:0',
            'low_stock_threshold' => 'sometimes|integer|min:0',
            'image'               => 'nullable|string|max:500',
            'profit_rate'         => 'sometimes|numeric|min:0|max:999.99',
            'status'              => 'sometimes|in:visible,hidden',
        ]);

        $product = new Product($data);
        $product->recalculateSellingPrice();
        $product->save();

        return response()->json([
            'message' => 'Tạo sản phẩm thành công',
            'data'    => $product->load('category:id,name'),
        ], 201);
    }

    /**
     * PUT /api/admin/products/{id} — Admin only.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Sản phẩm không tồn tại'], 404);
        }

        $data = $request->validate([
            'code'                => "sometimes|string|max:50|unique:products,code,{$id}",
            'name'                => 'sometimes|string|max:200',
            'category_id'         => 'sometimes|integer|exists:categories,id',
            'description'         => 'nullable|string',
            'unit'                => 'sometimes|string|max:30',
            'low_stock_threshold' => 'sometimes|integer|min:0',
            'image'               => 'nullable|string|max:500',
            'profit_rate'         => 'sometimes|numeric|min:0|max:999.99',
            'status'              => 'sometimes|in:visible,hidden',
        ]);

        $product->fill($data);

        if ($product->isDirty('profit_rate') || $product->isDirty('avg_import_price')) {
            $product->recalculateSellingPrice();
        }

        $product->save();

        return response()->json([
            'message' => 'Cập nhật sản phẩm thành công',
            'data'    => $product->fresh('category:id,name'),
        ]);
    }

    /**
     * DELETE /api/admin/products/{id} — Admin only.
     * - Chưa có import_details → xóa vật lý
     * - Đã có import_details → chỉ ẩn (status = hidden)
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::withCount('importDetails')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Sản phẩm không tồn tại'], 404);
        }

        if ($product->import_details_count > 0) {
            $product->update(['status' => ProductStatus::Hidden]);

            return response()->json([
                'message' => 'Sản phẩm đã được ẩn vì có lịch sử nhập hàng. Không thể xóa vật lý.',
            ]);
        }

        $product->delete();

        return response()->json(['message' => 'Đã xóa sản phẩm thành công']);
    }
}
