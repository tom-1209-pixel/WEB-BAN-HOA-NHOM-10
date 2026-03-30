<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     * Lấy danh sách tất cả danh mục.
     * MVC = 
     */
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->get();

        return response()->json([
            'success' => true,
            'data'    => CategoryResource::collection($categories),
        ]);
    }

    /**
     * POST /api/categories
     * Tạo danh mục mới.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tạo danh mục thành công',
            'data'    => new CategoryResource($category),
        ], 201);
    }

    /**
     * GET /api/categories/{id}
     * Xem chi tiết 1 danh mục.
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::withCount('products')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không tồn tại',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new CategoryResource($category),
        ]);
    }

    /**
     * PUT /api/categories/{id}
     * Cập nhật danh mục.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không tồn tại',
            ], 404);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:100|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật danh mục thành công',
            'data'    => new CategoryResource($category),
        ]);
    }

    /**
     * DELETE /api/categories/{id}
     * Xóa danh mục - chỉ xóa khi không còn sản phẩm liên kết.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::withCount('products')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không tồn tại',
            ], 404);
        }

        if ($category->products_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa danh mục vì còn ' . $category->products_count . ' sản phẩm liên kết',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa danh mục',
        ]);
    }
}
