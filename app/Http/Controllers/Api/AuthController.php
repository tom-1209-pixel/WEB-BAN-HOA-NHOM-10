<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AuthController xử lý:
 * - register : đăng ký tài khoản mới
 * - login    : đăng nhập, cấp token
 * - logout   : thu hồi token
 * - profile  : xem thông tin bản thân (cần token)
 *
 * Về cơ chế token:
 * Mỗi lần login thành công, hệ thống tạo một chuỗi ngẫu nhiên 60 ký tự
 * và lưu vào cột api_token trong bảng users.
 * Client gửi token này ở header: Authorization: Bearer <token>
 * Middleware đọc token, tìm user tương ứng, và cho phép truy cập.
 */
class AuthController extends Controller
{
    /**
     * Đăng ký tài khoản mới.
     *
     * POST /api/register
     * Body: { username, email, password, password_confirmation, full_name, phone? }
     */
    public function register(Request $request): JsonResponse
    {
        // Validate dữ liệu đầu vào
        // 'confirmed' tức là phải có thêm trường password_confirmation khớp với password
        $request->validate([
            'username'  => 'required|string|min:3|max:50|unique:users,username',
            'email'     => 'required|string|email|max:255|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'full_name' => 'required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'street'    => 'nullable|string',
            'ward'      => 'nullable|string|max:100',
            'district'  => 'nullable|string|max:100',
            'city'      => 'nullable|string|max:100',
        ]);

        $user = User::create([
            'username'  => $request->username,
            'name'      => $request->username,
            'email'     => $request->email,
            'password'  => $request->password,
            'full_name' => $request->full_name,
            'phone'     => $request->phone,
            'street'    => $request->street,
            'ward'      => $request->ward,
            'district'  => $request->district,
            'city'      => $request->city,
            'role'      => 'user',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Đăng ký tài khoản thành công',
            'user'    => $this->formatUserResponse($user),
        ], 201);
    }

    /**
     * Đăng nhập và nhận token.
     *
     * POST /api/login
     * Body: { username_or_email, password }
     * Cho phép đăng nhập bằng cả username lẫn email để linh hoạt hơn.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => 'required|string',   // có thể là username hoặc email
            'password' => 'required|string',
        ]);

        // Tìm user theo email hoặc username
        $user = User::where('email', $request->login)
            ->orWhere('username', $request->login)
            ->first();

        // Kiểm tra user tồn tại và mật khẩu đúng
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Thông tin đăng nhập không chính xác',
            ], 401);
        }

        // Kiểm tra tài khoản có bị vô hiệu hóa không
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Tài khoản đã bị vô hiệu hóa. Vui lòng liên hệ quản trị viên',
            ], 403);
        }

        // Tạo token mới mỗi lần đăng nhập (invalidate token cũ)
        // Trong thực tế production nên dùng JWT hoặc Laravel Sanctum
        // Nhưng cách này giúp hiểu rõ cơ chế token-based auth
        $token = Str::random(60);
        $user->update(['api_token' => $token]);

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'token'   => $token,
            'user'    => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Đăng xuất - thu hồi token hiện tại.
     * Sau khi logout, token cũ không còn dùng được nữa.
     *
     * POST /api/logout
     * Header: Authorization: Bearer <token>
     */
    public function logout(Request $request): JsonResponse
    {
        // $request->attributes->get('auth_user') được set bởi SimpleTokenMiddleware
        $user = $request->attributes->get('auth_user');
        $user->update(['api_token' => null]);

        return response()->json([
            'message' => 'Đăng xuất thành công',
        ]);
    }

    /**
     * Xem thông tin tài khoản đang đăng nhập.
     *
     * GET /api/profile
     * Header: Authorization: Bearer <token>
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        return response()->json([
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Cập nhật thông tin cá nhân (user tự cập nhật).
     *
     * PUT /api/profile
     * Header: Authorization: Bearer <token>
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $request->validate([
            'full_name' => 'sometimes|string|max:100',
            'phone'     => 'sometimes|nullable|string|max:20',
            'email'     => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'street'    => 'sometimes|nullable|string',
            'ward'      => 'sometimes|nullable|string|max:100',
            'district'  => 'sometimes|nullable|string|max:100',
            'city'      => 'sometimes|nullable|string|max:100',
        ]);

        $user->update($request->only(['full_name', 'phone', 'email', 'street', 'ward', 'district', 'city']));

        return response()->json([
            'message' => 'Cập nhật thông tin thành công',
            'user'    => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Đổi mật khẩu (user tự đổi).
     *
     * POST /api/change-password
     * Header: Authorization: Bearer <token>
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        // Kiểm tra mật khẩu hiện tại đúng không
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không chính xác',
            ], 422);
        }

        // Cập nhật mật khẩu mới (Model cast 'hashed' tự động bcrypt)
        $user->update(['password' => $request->new_password]);

        // Thu hồi token hiện tại, buộc đăng nhập lại
        $user->update(['api_token' => null]);

        return response()->json([
            'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại',
        ]);
    }

    /**
     * Format response cho user, loại bỏ các trường nhạy cảm.
     * Dù đã có $hidden trong Model, nhưng chủ động format
     * giúp kiểm soát chính xác dữ liệu trả về.
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id'        => $user->id,
            'username'  => $user->username,
            'full_name' => $user->full_name,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'street'    => $user->street,
            'ward'      => $user->ward,
            'district'  => $user->district,
            'city'      => $user->city,
            'role'      => $user->role instanceof \App\Enums\UserRole ? $user->role->value : $user->role,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
        ];
    }
}
