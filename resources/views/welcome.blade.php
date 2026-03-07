<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Chủ - Laravel Demo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 60px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #1a1a2e;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .nav-list {
            list-style: none;
        }
        .nav-list li {
            margin-bottom: 12px;
        }
        .nav-list a {
            display: block;
            padding: 16px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: all 0.2s ease;
            border: 1px solid #e9ecef;
        }
        .nav-list a:hover {
            background: #e3f2fd;
            border-color: #90caf9;
            transform: translateX(4px);
        }
        .nav-list .label {
            font-weight: 600;
            font-size: 16px;
        }
        .nav-list .method {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            margin-right: 8px;
            color: white;
        }
        .method-get { background-color: #4caf50; }
        .method-post { background-color: #2196f3; }
        .method-put { background-color: #ff9800; }
        .method-delete { background-color: #f44336; }
        .nav-list .url {
            display: block;
            font-size: 13px;
            color: #888;
            margin-top: 4px;
            font-family: 'Courier New', monospace;
        }
        .section-title {
            font-size: 18px;
            color: #1a1a2e;
            margin-top: 30px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Laravel - RESTful API</h1>
        <p class="subtitle">API với Laravel</p>

        <h2 class="section-title">Trang Web (web.php)</h2>
        <ul class="nav-list">
            <li>
                <a href="/">
                    <span class="method method-get">GET</span>
                    <span class="label">Trang Welcome</span>
                    <span class="url">/</span>
                </a>
            </li>
            <li>
                <a href="/home">
                    <span class="method method-get">GET</span>
                    <span class="label">Trang Home</span>
                    <span class="url">/home</span>
                </a>
            </li>
            <li>
                <a href="/products/create">
                    <span class="method method-get">GET</span>
                    <span class="label">Form Tạo Sản Phẩm</span>
                    <span class="url">/products/create</span>
                </a>
            </li>
        </ul>

        <h2 class="section-title">API Endpoints (api.php)</h2>
        <ul class="nav-list">
            <li>
                <a href="/api/products" target="_blank">
                    <span class="method method-get">GET</span>
                    <span class="label">Danh sách sản phẩm</span>
                    <span class="url">GET /api/products</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0)">
                    <span class="method method-get">GET</span>
                    <span class="label">Chi tiết sản phẩm</span>
                    <span class="url">GET /api/products/{id}</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0)">
                    <span class="method method-post">POST</span>
                    <span class="label">Tạo sản phẩm mới</span>
                    <span class="url">POST /api/products</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0)">
                    <span class="method method-put">PUT</span>
                    <span class="label">Cập nhật sản phẩm</span>
                    <span class="url">PUT /api/products/{id}</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0)">
                    <span class="method method-delete">DELETE</span>
                    <span class="label">Xóa sản phẩm</span>
                    <span class="url">DELETE /api/products/{id}</span>
                </a>
            </li>
        </ul>
    </div>
</body>
</html>