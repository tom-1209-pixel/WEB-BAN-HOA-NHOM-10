<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly CartService $cartService) {}

    /**
     * Đặt hàng từ giỏ hàng hiện tại của user.
     *
     * Flow:
     * 1. Lấy giỏ hàng, kiểm tra không rỗng
     * 2. Kiểm tra tồn kho cho từng sản phẩm (visible + đủ số lượng)
     * 3. Tạo order với các order_details (snapshot giá bán tại thời điểm đặt)
     * 4. Chưa trừ tồn kho (trừ khi admin confirm)
     * 5. Xóa các cart_items đã đặt khỏi giỏ hàng
     */
    public function placeOrder(User $user, array $data): Order
    {
        $cart = $user->cart()->with('items.product')->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Giỏ hàng trống. Vui lòng thêm sản phẩm trước khi đặt hàng.',
            ]);
        }

        // Kiểm tra tồn kho trước khi tạo order
        $stockErrors = [];
        foreach ($cart->items as $item) {
            $product = $item->product;

            if (!$product || $product->status->value === 'hidden') {
                $stockErrors[] = "Sản phẩm \"{$product?->name}\" không còn kinh doanh.";
                continue;
            }

            if ($product->quantity < $item->quantity) {
                $stockErrors[] = "Sản phẩm \"{$product->name}\" chỉ còn {$product->quantity} {$product->unit} trong kho.";
            }
        }

        if (!empty($stockErrors)) {
            throw ValidationException::withMessages(['stock' => $stockErrors]);
        }

        return DB::transaction(function () use ($user, $data, $cart) {
            $totalAmount = 0;
            $orderDetails = [];

            foreach ($cart->items as $item) {
                $unitPrice    = (float) $item->product->selling_price;
                $subtotal     = round($unitPrice * $item->quantity, 2);
                $totalAmount += $subtotal;

                $orderDetails[] = [
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'unit_price' => $unitPrice,
                    'subtotal'   => $subtotal,
                ];
            }

            $order = Order::create([
                'user_id'            => $user->id,
                'shipping_name'      => $data['shipping_name'],
                'shipping_phone'     => $data['shipping_phone'],
                'shipping_street'    => $data['shipping_street'],
                'shipping_ward'      => $data['shipping_ward'],
                'shipping_district'  => $data['shipping_district'],
                'shipping_city'      => $data['shipping_city'],
                'payment_method'     => $data['payment_method'],
                'note'               => $data['note'] ?? null,
                'status'             => OrderStatus::Pending,
                'total_amount'       => round($totalAmount, 2),
            ]);

            $order->details()->createMany($orderDetails);

            // Xóa các sản phẩm trong giỏ đã đặt thành công
            $this->cartService->clearCart($cart);

            return $order->load('details.product');
        });
    }

    /**
     * Cập nhật trạng thái đơn hàng theo state machine.
     *
     * - Khi pending → confirmed: trừ tồn kho
     * - Khi confirmed → cancelled: hoàn lại tồn kho
     * - Khi pending → cancelled (user tự cancel): không ảnh hưởng tồn kho
     */
    public function updateStatus(
        Order $order,
        OrderStatus $toStatus,
        User $actor,
        ?string $note = null
    ): Order {
        $fromStatus = $order->status;

        if (!$fromStatus->canTransitionTo($toStatus)) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển từ trạng thái \"{$fromStatus->label()}\" sang \"{$toStatus->label()}\".",
            ]);
        }

        return DB::transaction(function () use ($order, $fromStatus, $toStatus, $actor, $note) {
            // Xử lý tồn kho dựa trên chuyển trạng thái
            if ($toStatus === OrderStatus::Confirmed) {
                $this->deductStock($order);
            }

            if ($toStatus === OrderStatus::Cancelled && $fromStatus === OrderStatus::Confirmed) {
                $this->restoreStock($order);
            }

            $order->update(['status' => $toStatus]);

            // Ghi log lịch sử — append-only
            $order->statusLogs()->create([
                'admin_id'    => $actor->isAdmin() ? $actor->id : null,
                'from_status' => $fromStatus,
                'to_status'   => $toStatus,
                'note'        => $note,
            ]);

            return $order->fresh(['details', 'statusLogs.admin']);
        });
    }

    /**
     * Trừ tồn kho khi admin xác nhận đơn hàng.
     */
    private function deductStock(Order $order): void
    {
        $order->details()->with('product')->get()->each(function ($detail) {
            $product = $detail->product;

            if ($product->quantity < $detail->quantity) {
                throw ValidationException::withMessages([
                    'stock' => "Sản phẩm \"{$product->name}\" không đủ tồn kho để xác nhận đơn hàng.",
                ]);
            }

            $product->decrement('quantity', $detail->quantity);
        });
    }

    /**
     * Hoàn lại tồn kho khi hủy đơn đã xác nhận.
     */
    private function restoreStock(Order $order): void
    {
        $order->details()->with('product')->get()->each(function ($detail) {
            $detail->product->increment('quantity', $detail->quantity);
        });
    }
}
