<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Models\ImportDetail;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private readonly ImportService $importService) {}

    /**
     * GET /api/admin/imports
     * Danh sách phiếu nhập với filter và phân trang.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Import::with(['admin:id,username,full_name', 'supplier:id,name'])
            ->withCount('details');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Tìm kiếm theo mã phiếu (id)
        if ($request->filled('search')) {
            $query->where('id', $request->search);
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($query->latest('created_at')->paginate($perPage));
    }

    /**
     * GET /api/admin/imports/{id}
     * Chi tiết phiếu nhập kèm danh sách sản phẩm.
     */
    public function show(int $id): JsonResponse
    {
        $import = Import::with([
            'admin:id,username,full_name',
            'supplier:id,name,phone',
            'details.product:id,code,name,unit,avg_import_price,selling_price',
        ])->find($id);

        if (!$import) {
            return response()->json(['message' => 'Phiếu nhập không tồn tại'], 404);
        }

        return response()->json(['data' => $import]);
    }

    /**
     * POST /api/admin/imports
     * Tạo phiếu nhập mới (trạng thái drafting).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'note'        => 'nullable|string',
        ]);

        $adminUser = $request->attributes->get('auth_user');

        $import = Import::create([
            'admin_id'    => $adminUser->id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'note'        => $data['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Tạo phiếu nhập thành công',
            'data'    => $import->load(['admin:id,username,full_name', 'supplier:id,name']),
        ], 201);
    }

    /**
     * PUT /api/admin/imports/{id}
     * Cập nhật thông tin phiếu nhập (chỉ khi drafting).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $import = Import::find($id);

        if (!$import) {
            return response()->json(['message' => 'Phiếu nhập không tồn tại'], 404);
        }

        if ($import->isCompleted()) {
            return response()->json(['message' => 'Không thể chỉnh sửa phiếu nhập đã hoàn thành'], 422);
        }

        $data = $request->validate([
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'note'        => 'nullable|string',
        ]);

        $import->update($data);

        return response()->json([
            'message' => 'Cập nhật phiếu nhập thành công',
            'data'    => $import->fresh(['admin:id,username,full_name', 'supplier:id,name']),
        ]);
    }

    /**
     * DELETE /api/admin/imports/{id}
     * Xóa phiếu nhập (chỉ khi drafting).
     */
    public function destroy(int $id): JsonResponse
    {
        $import = Import::find($id);

        if (!$import) {
            return response()->json(['message' => 'Phiếu nhập không tồn tại'], 404);
        }

        if ($import->isCompleted()) {
            return response()->json(['message' => 'Không thể xóa phiếu nhập đã hoàn thành'], 422);
        }

        $import->delete(); // Cascade xóa import_details

        return response()->json(['message' => 'Xóa phiếu nhập thành công']);
    }

    /**
     * POST /api/admin/imports/{id}/details
     * Thêm hoặc cập nhật sản phẩm trong phiếu nhập.
     * Nếu product_id đã tồn tại trong phiếu → cộng dồn số lượng.
     */
    public function addDetail(Request $request, int $id): JsonResponse
    {
        $import = Import::find($id);

        if (!$import) {
            return response()->json(['message' => 'Phiếu nhập không tồn tại'], 404);
        }

        if ($import->isCompleted()) {
            return response()->json(['message' => 'Không thể thêm sản phẩm vào phiếu nhập đã hoàn thành'], 422);
        }

        $data = $request->validate([
            'product_id'   => 'required|integer|exists:products,id',
            'quantity'     => 'required|integer|min:1',
            'import_price' => 'required|numeric|min:0.01',
        ]);

        $existingDetail = $import->details()->where('product_id', $data['product_id'])->first();

        if ($existingDetail) {
            $newQty      = $existingDetail->quantity + $data['quantity'];
            $newPrice    = $data['import_price']; // Dùng giá mới nhất
            $newSubtotal = round($newQty * $newPrice, 2);

            $existingDetail->update([
                'quantity'     => $newQty,
                'import_price' => $newPrice,
                'subtotal'     => $newSubtotal,
            ]);
            $detail = $existingDetail->fresh('product');
        } else {
            $subtotal = round($data['quantity'] * $data['import_price'], 2);
            $detail   = $import->details()->create([
                'product_id'   => $data['product_id'],
                'quantity'     => $data['quantity'],
                'import_price' => $data['import_price'],
                'subtotal'     => $subtotal,
            ]);
            $detail->load('product:id,code,name,unit');
        }

        // Cập nhật lại total_amount của phiếu
        $import->update([
            'total_amount' => $import->details()->sum('subtotal'),
        ]);

        return response()->json([
            'message' => 'Cập nhật dòng sản phẩm thành công',
            'data'    => $detail,
        ]);
    }

    /**
     * DELETE /api/admin/imports/{id}/details/{detailId}
     * Xóa một dòng sản phẩm trong phiếu nhập.
     */
    public function removeDetail(int $id, int $detailId): JsonResponse
    {
        $import = Import::find($id);

        if (!$import) {
            return response()->json(['message' => 'Phiếu nhập không tồn tại'], 404);
        }

        if ($import->isCompleted()) {
            return response()->json(['message' => 'Không thể xóa dòng sản phẩm khỏi phiếu nhập đã hoàn thành'], 422);
        }

        $detail = $import->details()->find($detailId);

        if (!$detail) {
            return response()->json(['message' => 'Dòng sản phẩm không tồn tại'], 404);
        }

        $detail->delete();

        $import->update([
            'total_amount' => $import->details()->sum('subtotal'),
        ]);

        return response()->json(['message' => 'Đã xóa dòng sản phẩm khỏi phiếu nhập']);
    }

    /**
     * POST /api/admin/imports/{id}/complete
     * Hoàn thành phiếu nhập — khóa phiếu, cập nhật tồn kho và giá bình quân.
     */
    public function complete(int $id): JsonResponse
    {
        $import = Import::find($id);

        if (!$import) {
            return response()->json(['message' => 'Phiếu nhập không tồn tại'], 404);
        }

        $import = $this->importService->complete($import);

        return response()->json([
            'message' => 'Hoàn thành phiếu nhập thành công. Tồn kho và giá bán đã được cập nhật.',
            'data'    => $import,
        ]);
    }
}
