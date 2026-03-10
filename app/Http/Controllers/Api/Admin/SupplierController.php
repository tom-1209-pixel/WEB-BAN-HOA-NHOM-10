<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $suppliers = $query->latest('created_at')->paginate($perPage);

        return response()->json($suppliers);
    }

    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::withCount('imports')->find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Nhà cung cấp không tồn tại'], 404);
        }

        return response()->json(['data' => $supplier]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:150',
            'phone'   => 'nullable|string|max:20',
            'email'   => 'nullable|email|max:150',
            'address' => 'nullable|string',
        ]);

        $supplier = Supplier::create($data);

        return response()->json([
            'message' => 'Tạo nhà cung cấp thành công',
            'data'    => $supplier,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Nhà cung cấp không tồn tại'], 404);
        }

        $data = $request->validate([
            'name'    => 'sometimes|string|max:150',
            'phone'   => 'sometimes|nullable|string|max:20',
            'email'   => 'sometimes|nullable|email|max:150',
            'address' => 'sometimes|nullable|string',
        ]);

        $supplier->update($data);

        return response()->json([
            'message' => 'Cập nhật nhà cung cấp thành công',
            'data'    => $supplier->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::withCount('imports')->find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Nhà cung cấp không tồn tại'], 404);
        }

        if ($supplier->imports_count > 0) {
            return response()->json([
                'message' => "Không thể xóa nhà cung cấp vì còn {$supplier->imports_count} phiếu nhập liên kết.",
            ], 422);
        }

        $supplier->delete();

        return response()->json(['message' => 'Xóa nhà cung cấp thành công']);
    }
}
