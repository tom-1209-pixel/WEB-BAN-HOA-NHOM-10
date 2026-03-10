<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    /**
     * GET /api/cart
     * Xem giỏ hàng hiện tại (tạo mới nếu chưa có).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $cart = $this->cartService->getOrCreateCart($user);
        $cart->load(['items.product:id,code,name,unit,selling_price,quantity,status,image']);

        return response()->json(['data' => $cart]);
    }

    /**
     * POST /api/cart/items
     * Thêm sản phẩm vào giỏ hàng.
     * Nếu sản phẩm đã có trong giỏ → cộng dồn số lượng.
     */
    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $user = $request->attributes->get('auth_user');
        $cart = $this->cartService->getOrCreateCart($user);
        $item = $this->cartService->addItem($cart, $data['product_id'], $data['quantity']);

        $item->load('product:id,code,name,unit,selling_price,image');

        return response()->json([
            'message' => 'Đã thêm sản phẩm vào giỏ hàng',
            'data'    => $item,
        ], 201);
    }

    /**
     * PUT /api/cart/items/{itemId}
     * Cập nhật số lượng sản phẩm trong giỏ.
     * Nếu quantity = 0 → xóa sản phẩm khỏi giỏ.
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $user = $request->attributes->get('auth_user');
        $cart = $this->cartService->getOrCreateCart($user);

        $item = $cart->items()->where('id', $itemId)->first();

        if (!$item) {
            return response()->json(['message' => 'Sản phẩm không có trong giỏ hàng'], 404);
        }

        $updatedItem = $this->cartService->updateItem($item, $data['quantity']);

        if ($updatedItem === null) {
            return response()->json(['message' => 'Đã xóa sản phẩm khỏi giỏ hàng']);
        }

        return response()->json([
            'message' => 'Cập nhật số lượng thành công',
            'data'    => $updatedItem->load('product:id,code,name,unit,selling_price,image'),
        ]);
    }

    /**
     * DELETE /api/cart/items/{itemId}
     * Xóa một sản phẩm khỏi giỏ hàng.
     */
    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $cart = $this->cartService->getOrCreateCart($user);

        $item = $cart->items()->where('id', $itemId)->first();

        if (!$item) {
            return response()->json(['message' => 'Sản phẩm không có trong giỏ hàng'], 404);
        }

        $this->cartService->removeItem($item);

        return response()->json(['message' => 'Đã xóa sản phẩm khỏi giỏ hàng']);
    }
}
