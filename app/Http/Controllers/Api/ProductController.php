<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * Lấy danh sách sản phẩm (chỉ lấy status = visible mặc định).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        // Cho phép filter theo status (admin có thể muốn xem cả hidden)
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        } else {
            $query->where('status', 'visible');
        }

        // Filter theo category_id
        if ($request->has('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        // Cảnh báo tồn kho thấp
        if ($request->query('low_stock') === 'true') {
            $query->whereColumn('quantity', '<=', 'low_stock_threshold');
        }

        $products = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    /**
     * POST /api/products
     * Tạo sản phẩm mới (tự tính selling_price nếu có profit_rate và avg_import_price).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'                => 'required|string|max:50|unique:products,code',
            'name'                => 'required|string|max:200',
            'category_id'         => 'required|integer|exists:categories,id',
            'description'         => 'nullable|string',
            'unit'                => 'required|string|max:30',
            'quantity'            => 'sometimes|integer|min:0',
            'low_stock_threshold' => 'sometimes|integer|min:0',
            'image'               => 'nullable|string|max:500',
            'profit_rate'         => 'sometimes|numeric|min:0|max:999.99',
            'avg_import_price'    => 'sometimes|numeric|min:0',
            'status'              => 'sometimes|in:visible,hidden',
        ]);

        $product = new Product($validated);
        $product->recalculateSellingPrice();
        $product->save();

        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Tạo sản phẩm thành công',
            'data'    => $product,
        ], 201);
    }

    /**
     * GET /api/products/{id}
     * Xem chi tiết sản phẩm.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with('category')->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $product,
        ]);
    }

    /**
     * PUT /api/products/{id}
     * Cập nhật sản phẩm - tự động tính lại selling_price nếu profit_rate hoặc avg_import_price thay đổi.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại',
            ], 404);
        }

        $validated = $request->validate([
            'code'                => 'sometimes|required|string|max:50|unique:products,code,' . $product->id,
            'name'                => 'sometimes|required|string|max:200',
            'category_id'         => 'sometimes|required|integer|exists:categories,id',
            'description'         => 'nullable|string',
            'unit'                => 'sometimes|required|string|max:30',
            'quantity'            => 'sometimes|integer|min:0',
            'low_stock_threshold' => 'sometimes|integer|min:0',
            'image'               => 'nullable|string|max:500',
            'profit_rate'         => 'sometimes|numeric|min:0|max:999.99',
            'avg_import_price'    => 'sometimes|numeric|min:0',
            'status'              => 'sometimes|in:visible,hidden',
        ]);

        $product->fill($validated);

        // Nếu profit_rate hoặc avg_import_price thay đổi -> tính lại selling_price
        if ($product->isDirty('profit_rate') || $product->isDirty('avg_import_price')) {
            $product->recalculateSellingPrice();
        }

        $product->save();
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật sản phẩm thành công',
            'data'    => $product,
        ]);
    }

    /**
     * DELETE /api/products/{id}
     * Xóa sản phẩm - không xóa vật lý, chỉ đặt status = 'hidden'.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại',
            ], 404);
        }

        // Thay vì xóa vật lý, chuyển sang ẩn
        $product->update(['status' => 'hidden']);

        return response()->json([
            'success' => true,
            'message' => 'Sản phẩm đã được ẩn (soft delete)',
        ]);
    }
}
