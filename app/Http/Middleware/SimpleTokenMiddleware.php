<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SimpleTokenMiddleware - Xác thực API Token
 *
 * Cơ chế hoạt động:
 * 1. Client gửi request với header: Authorization: Bearer <token>
 * 2. Middleware đọc token bằng $request->bearerToken()
 * 3. Tìm user trong DB theo api_token
 * 4. Nếu tìm thấy: gán user vào request attributes, cho đi tiếp
 * 5. Nếu không: trả 401 Unauthorized
 *
 * Lý do dùng $request->attributes->set() thay vì $request->merge():
 * - attributes: dữ liệu nội bộ của framework, không bị ghi đè bởi request body
 * - merge: gộp vào request input, có thể bị conflict nếu client gửi field 'auth_user'
 */
class SimpleTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // bearerToken() tự động parse "Bearer <token>" từ Authorization header
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Chưa cung cấp mã xác thực. Vui lòng đăng nhập',
            ], 401);
        }

        // Tìm user theo token
        $user = User::where('api_token', $token)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Mã xác thực không hợp lệ hoặc đã hết hạn. Vui lòng đăng nhập lại',
            ], 401);
        }

        // Kiểm tra tài khoản có bị vô hiệu hóa không
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Tài khoản đã bị vô hiệu hóa',
            ], 403);
        }

        // Gán user vào attributes (không ảnh hưởng tới request input)
        // Ở Controller lấy bằng: $request->attributes->get('auth_user')
        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
