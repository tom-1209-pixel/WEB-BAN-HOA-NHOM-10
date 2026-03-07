<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SimpleTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Vui lòng cung cấp mã xác thực (Token)'], 401);
        }

        $user = \App\Models\User::where('api_token', $token)->first();

        if (!$user) {
            return response()->json(['message' => 'Mã xác thực không hợp lệ hoặc đã hết hạn'], 401);
        }

        // Gán user vào request để dùng ở Controller
        $request->merge(['user' => $user]);
        // Laravel cho phép set user như thế này để auth() helper hoạt động (tùy phiên bản)
        //  dùng $request->user để lấy ra cho dễ hiểu
        
        return $next($request);
    }
}
