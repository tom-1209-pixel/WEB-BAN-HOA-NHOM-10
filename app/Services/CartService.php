<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CartService
{
    /**
     * Lấy giỏ hàng của user, tạo mới nếu chưa có.
     */
    public function getOrCreateCart(User $user): Cart
    {
        return $user->cart ?? Cart::create(['user_id' => $user->id]);
    }

    /**
     * Thêm sản phẩm vào giỏ hàng.
     * Nếu sản phẩm đã có trong giỏ → cộng dồn số lượng.
     */
    public function addItem(Cart $cart, int $productId, int $quantity): CartItem
    {
        $product = Product::find($productId);

        if (!$product || $product->status === ProductStatus::Hidden) {
            throw ValidationException::withMessages([
                'product_id' => 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.',
            ]);
        }

        $existingItem = $cart->items()->where('product_id', $productId)->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $quantity;

            if ($product->quantity < $newQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Sản phẩm \"{$product->name}\" chỉ còn {$product->quantity} {$product->unit} trong kho.",
                ]);
            }

            $existingItem->update(['quantity' => $newQuantity]);
            return $existingItem->fresh();
        }

        if ($product->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Sản phẩm \"{$product->name}\" chỉ còn {$product->quantity} {$product->unit} trong kho.",
            ]);
        }

        return $cart->items()->create([
            'product_id' => $productId,
            'quantity'   => $quantity,
        ]);
    }

    /**
     * Cập nhật số lượng của một cart item.
     * Nếu quantity = 0 → xóa item khỏi giỏ.
     */
    public function updateItem(CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $item->delete();
            return null;
        }

        $product = $item->product;

        if ($product->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Sản phẩm \"{$product->name}\" chỉ còn {$product->quantity} {$product->unit} trong kho.",
            ]);
        }

        $item->update(['quantity' => $quantity]);
        return $item->fresh();
    }

    /**
     * Xóa một sản phẩm khỏi giỏ hàng.
     */
    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * Xóa toàn bộ sản phẩm trong giỏ hàng (sau khi đặt hàng thành công).
     */
    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
    }
}
