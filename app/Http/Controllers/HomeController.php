<?php

namespace App\Http\Controllers;

// HomeController - xử lý các trang web chính (trả về HTML/View)
// Naming convention: PascalCase, kết thúc bằng "Controller"
class HomeController extends Controller
{
    // Trang chủ - GET /
    // Method "index" là tên chuẩn cho trang danh sách hoặc trang chính
    public function index()
    {
        return view('welcome');
    }

    // Trang home - GET /home
    public function home()
    {
        return view('index');
    }
}
