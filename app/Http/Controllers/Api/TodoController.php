<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Todo;
use Illuminate\Http\Request;

class TodoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Lấy user đã được middleware xác thực và gán vào request
        $user = $request->get('user');
        
        // Chỉ lấy những todo của chính người đó
        $todos = Todo::where('user_id', $user->id)->get();
        
        return response()->json($todos);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->get('user');

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $todo = Todo::create([
            'title' => $request->title,
            'description' => $request->description,
            'user_id' => $user->id,
            'is_completed' => false,
        ]);

        return response()->json([
            'message' => 'Thêm công việc thành công',
            'data' => $todo
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->get('user');
        $todo = Todo::where('user_id', $user->id)->find($id);

        if (!$todo) {
            return response()->json(['message' => 'Không tìm thấy công việc'], 404);
        }

        return response()->json($todo);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->get('user');
        $todo = Todo::where('user_id', $user->id)->find($id);

        if (!$todo) {
            return response()->json(['message' => 'Không tìm thấy công việc'], 404);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'is_completed' => 'sometimes|boolean',
        ]);

        $todo->update($request->only(['title', 'description', 'is_completed']));

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data' => $todo
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->get('user');
        $todo = Todo::where('user_id', $user->id)->find($id);

        if (!$todo) {
            return response()->json(['message' => 'Không tìm thấy công việc'], 404);
        }

        $todo->delete();

        return response()->json(['message' => 'Đã xóa công việc']);
    }
}
