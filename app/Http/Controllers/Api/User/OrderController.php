<?php

namespace App\Http\Controllers\Api\User;

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
     * POST /api/orders
     * Đặt hàng từ giỏ hàng hiện tại.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipping_name'     => 'required|string|max:150',
            'shipping_phone'    => 'required|string|max:20',
            'shipping_street'   => 'required|string',
            'shipping_ward'     => 'required|string|max:100',
            'shipping_district' => 'required|string|max:100',
            'shipping_city'     => 'required|string|max:100',
            'payment_method'    => 'required|in:cash,transfer,online',
            'note'              => 'nullable|string|max:500',
        ]);

        $user  = $request->attributes->get('auth_user');
        $order = $this->orderService->placeOrder($user, $data);

        return response()->json([
            'message' => 'Đặt hàng thành công! Đơn hàng đang chờ xác nhận.',
            'data'    => $order,
        ], 201);
    }

    /**
     * GET /api/orders
     * Lịch sử đơn hàng của user đang đăng nhập (gần nhất hiện trên đầu).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $orders = Order::where('user_id', $user->id)
            ->with('details.product:id,code,name,unit,image')
            ->withCount('details')
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * GET /api/orders/{id}
     * Chi tiết một đơn hàng (chỉ xem được đơn của chính mình).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $order = Order::where('user_id', $user->id)
            ->with([
                'details.product:id,code,name,unit,image',
                'statusLogs',
            ])
            ->find($id);

        if (!$order) {
            return response()->json(['message' => 'Đơn hàng không tồn tại'], 404);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * PATCH /api/orders/{id}/cancel
     * User tự hủy đơn hàng (chỉ được hủy khi còn pending).
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $order = Order::where('user_id', $user->id)->with('details.product')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Đơn hàng không tồn tại'], 404);
        }

        if ($order->status !== OrderStatus::Pending) {
            return response()->json([
                'message' => 'Chỉ có thể hủy đơn hàng đang ở trạng thái chờ xác nhận.',
            ], 422);
        }

        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        // User cancel từ pending → không ảnh hưởng tồn kho
        $order = $this->orderService->updateStatus(
            $order,
            OrderStatus::Cancelled,
            $user,
            $data['note'] ?? 'Khách hàng tự hủy đơn hàng'
        );

        return response()->json([
            'message' => 'Đã hủy đơn hàng thành công',
            'data'    => $order,
        ]);
    }
}
