<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UserController xử lý CRUD quản lý người dùng (dành cho admin).
 *
 * Tất cả route trong controller này đều yêu cầu:
 * 1. Đã đăng nhập (middleware auth.simple)
 * 2. Có role admin (middleware auth.admin)
 *
 * Các endpoint:
 * GET    /api/admin/users           - danh sách users (có phân trang, tìm kiếm)
 * GET    /api/admin/users/{id}      - chi tiết một user
 * POST   /api/admin/users           - tạo user mới
 * PUT    /api/admin/users/{id}      - cập nhật thông tin user
 * DELETE /api/admin/users/{id}      - xóa user
 * PATCH  /api/admin/users/{id}/toggle-active - bật/tắt trạng thái tài khoản
 */
class UserController extends Controller
{
    /**
     * Lấy danh sách users với phân trang và tìm kiếm.
     *
     * GET /api/admin/users
     * Query params: search, role, is_active, per_page (default 15)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Lọc theo từ khóa tìm kiếm (tìm trong username, email, full_name)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        // Lọc theo vai trò
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Lọc theo trạng thái kích hoạt
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->get('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // giới hạn 1-100 tránh overload

        $users = $query->select([
            'id', 'username', 'full_name', 'email', 'phone', 'role', 'is_active', 'created_at',
        ])->latest()->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Xem chi tiết một user.
     *
     * GET /api/admin/users/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = User::select([
            'id', 'username', 'full_name', 'email', 'phone', 'role', 'is_active', 'created_at', 'updated_at',
        ])->find($id);

        if (!$user) {
            return response()->json(['message' => 'Người dùng không tồn tại'], 404);
        }

        return response()->json(['user' => $user]);
    }

    /**
     * Tạo user mới (admin tạo, không cần xác nhận email).
     *
     * POST /api/admin/users
     * Body: { username, email, password, full_name, phone?, role? }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'username'  => 'required|string|min:3|max:50|unique:users,username',
            'email'     => 'required|string|email|max:255|unique:users,email',
            'password'  => 'required|string|min:8',
            'full_name' => 'required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'role'      => 'nullable|in:admin,user',
        ]);

        $user = User::create([
            'username'  => $request->username,
            'name'      => $request->username,
            'email'     => $request->email,
            'password'  => $request->password,  // Model cast 'hashed' tự hash
            'full_name' => $request->full_name,
            'phone'     => $request->phone,
            'role'      => $request->input('role', 'user'),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Tạo người dùng thành công',
            'user'    => $this->formatUserResponse($user),
        ], 201);
    }

    /**
     * Cập nhật thông tin user (admin cập nhật).
     *
     * PUT /api/admin/users/{id}
     * Body: { username?, email?, full_name?, phone?, role?, is_active? }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Người dùng không tồn tại'], 404);
        }

        $request->validate([
            'username'  => 'sometimes|string|min:3|max:50|unique:users,username,' . $id,
            'email'     => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'full_name' => 'sometimes|string|max:100',
            'phone'     => 'sometimes|nullable|string|max:20',
            'role'      => 'sometimes|in:admin,user',
            'is_active' => 'sometimes|boolean',
        ]);

        // Nếu admin thay đổi username thì đồng bộ name
        $data = $request->only(['username', 'email', 'full_name', 'phone', 'role', 'is_active']);
        if (isset($data['username'])) {
            $data['name'] = $data['username'];
        }

        $user->update($data);

        return response()->json([
            'message' => 'Cập nhật người dùng thành công',
            'user'    => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Xóa user khỏi hệ thống.
     * Admin không thể tự xóa chính mình.
     *
     * DELETE /api/admin/users/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $adminUser = $request->attributes->get('auth_user');

        if ($adminUser->id === $id) {
            return response()->json(['message' => 'Không thể tự xóa tài khoản của chính mình'], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Người dùng không tồn tại'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'Xóa người dùng thành công']);
    }

    /**
     * Bật/tắt trạng thái tài khoản (kích hoạt hoặc vô hiệu hóa).
     * Admin không thể vô hiệu hóa chính mình.
     *
     * PATCH /api/admin/users/{id}/toggle-active
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $adminUser = $request->attributes->get('auth_user');

        if ($adminUser->id === $id) {
            return response()->json(['message' => 'Không thể vô hiệu hóa tài khoản của chính mình'], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Người dùng không tồn tại'], 404);
        }

        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'kích hoạt' : 'vô hiệu hóa';

        return response()->json([
            'message'   => "Đã {$status} tài khoản thành công",
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Admin đặt lại mật khẩu cho user.
     *
     * POST /api/admin/users/{id}/reset-password
     * Body: { new_password }
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Người dùng không tồn tại'], 404);
        }

        $request->validate([
            'new_password' => 'required|string|min:8',
        ]);

        // Thu hồi token và cập nhật mật khẩu mới
        $user->update([
            'password'  => $request->new_password, // Model cast 'hashed' tự bcrypt
            'api_token' => null,                    // buộc user đăng nhập lại
        ]);

        return response()->json(['message' => 'Đặt lại mật khẩu thành công']);
    }

    /**
     * Format response user, loại bỏ trường nhạy cảm.
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id'         => $user->id,
            'username'   => $user->username,
            'full_name'  => $user->full_name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'is_active'  => $user->is_active,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
