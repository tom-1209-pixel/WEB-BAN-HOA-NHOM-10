<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * GET /api/admin/orders
     * Danh sách đơn hàng với filter: status, city, khoảng thời gian.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user:id,username,full_name,phone'])
            ->withCount('details');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('city')) {
            $query->where('shipping_city', 'like', "%{$request->city}%");
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json(
            $query->latest()->paginate($perPage)
        );
    }

    /**
     * GET /api/admin/orders/{id}
     * Chi tiết đơn hàng kèm danh sách sản phẩm và log trạng thái.
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with([
            'user:id,username,full_name,phone,email',
            'details.product:id,code,name,unit',
            'statusLogs.admin:id,username,full_name',
        ])->find($id);

        if (!$order) {
            return response()->json(['message' => 'Đơn hàng không tồn tại'], 404);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * PATCH /api/admin/orders/{id}/status
     * Cập nhật trạng thái đơn hàng theo state machine một chiều.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::with('details.product')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Đơn hàng không tồn tại'], 404);
        }

        $data = $request->validate([
            'status' => 'required|in:confirmed,delivered,cancelled',
            'note'   => 'nullable|string|max:500',
        ]);

        $toStatus  = OrderStatus::from($data['status']);
        $adminUser = $request->attributes->get('auth_user');

        $order = $this->orderService->updateStatus($order, $toStatus, $adminUser, $data['note'] ?? null);

        return response()->json([
            'message' => "Cập nhật trạng thái đơn hàng thành \"{$toStatus->label()}\" thành công",
            'data'    => $order,
        ]);
    }
}
