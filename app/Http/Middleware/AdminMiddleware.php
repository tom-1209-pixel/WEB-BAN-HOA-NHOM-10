<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdminMiddleware - Kiểm tra quyền Admin
 *
 * Middleware này phải được đặt SAU SimpleTokenMiddleware trong chuỗi middleware
 * vì nó phụ thuộc vào 'auth_user' đã được set bởi SimpleTokenMiddleware.
 *
 * Luồng hoạt động:
 * Request -> auth.simple (xác thực token) -> auth.admin (kiểm tra role) -> Controller
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user');

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập chức năng này',
            ], 403);
        }

        return $next($request);
    }
}
