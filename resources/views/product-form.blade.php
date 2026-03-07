<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo Sản Phẩm</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }
        .container {
            background: white;
            padding: 36px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 520px;
        }
        h1 {
            color: #1a1a2e;
            text-align: center;
            margin-bottom: 8px;
            font-size: 24px;
        }
        .description {
            text-align: center;
            color: #666;
            margin-bottom: 28px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            color: #444;
            font-weight: 600;
            font-size: 14px;
        }
        label .required {
            color: #e53935;
        }
        input, textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #2196f3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #2196f3;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #1976d2;
        }
        button:active {
            background-color: #1565c0;
        }
        .message {
            margin-top: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            display: none;
        }
        .message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .message.error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2196f3;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }

        /* ========================================
           API Info - hiển thị thông tin API đang gọi
           ======================================== */
        .api-info {
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 24px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .api-info .method {
            display: inline-block;
            padding: 2px 8px;
            background: #2196f3;
            color: white;
            border-radius: 4px;
            font-weight: 700;
            font-size: 12px;
            margin-right: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Tạo Sản Phẩm Mới</h1>
        <p class="description">Nhập thông tin sản phẩm và gửi lên API</p>

        <!-- Hiển thị API endpoint đang gọi để sinh viên hiểu -->
        <div class="api-info">
            <span class="method">POST</span> /api/products
        </div>

        <!--
            Form HTML gửi dữ liệu lên server
            - method="POST" => gửi dữ liệu (không hiện trên URL)
            - Dữ liệu sẽ được gửi bằng JavaScript (fetch API) bên dưới
            - @csrf => tạo CSRF token (bắt buộc trong web.php, không cần trong api.php)
        -->
        <form id="productForm">
            @csrf

            <div class="form-group">
                <label for="name">Tên Sản Phẩm <span class="required">*</span></label>
                <input type="text" id="name" name="name" placeholder="VD: Laptop Dell XPS 15" required>
            </div>

            <div class="form-group">
                <label for="description">Mô Tả <span class="required">*</span></label>
                <textarea id="description" name="description" placeholder="Mô tả chi tiết sản phẩm..." required></textarea>
            </div>

            <div class="form-group">
                <label for="price">Giá (VND) <span class="required">*</span></label>
                <input type="number" id="price" name="price" placeholder="VD: 25000000" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label for="stock">Số Lượng Tồn Kho <span class="required">*</span></label>
                <input type="number" id="stock" name="stock" placeholder="VD: 50" min="0" required>
            </div>

            <div class="form-group">
                <label for="category">Danh Mục <span class="required">*</span></label>
                <input type="text" id="category" name="category" placeholder="VD: Điện tử" required>
            </div>

            <button type="submit">Tạo Sản Phẩm</button>
        </form>

        <div id="message" class="message"></div>

        <a href="/" class="back-link">Quay về trang chủ</a>
    </div>

    <!--
        JavaScript - Gửi dữ liệu lên API bằng fetch()
        fetch() là cách hiện đại để gọi API từ trình duyệt
    -->
    <script>
        document.getElementById('productForm').addEventListener('submit', function(e) {
            // Ngăn form reload trang (hành vi mặc định của form)
            e.preventDefault();

            // Thu thập dữ liệu từ form
            var formData = {
                name: document.getElementById('name').value,
                description: document.getElementById('description').value,
                price: document.getElementById('price').value,
                stock: document.getElementById('stock').value,
                category: document.getElementById('category').value,
            };

            // Gọi API: POST /api/products
            // - URL: /api/products (api.php tự động có prefix /api)
            // - Method: POST
            // - Headers: Content-Type và Accept đều là JSON
            // - Body: dữ liệu sản phẩm dưới dạng JSON
            fetch('/api/products', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(formData),
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                var messageDiv = document.getElementById('message');

                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.innerHTML = 'Thành công! ' + data.message
                        + '<br><strong>ID:</strong> ' + data.data.id
                        + ' | <strong>Tên:</strong> ' + data.data.name;
                    document.getElementById('productForm').reset();
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.innerHTML = 'Lỗi: ' + (data.message || 'Không xác định');
                }

                messageDiv.style.display = 'block';

                setTimeout(function() {
                    messageDiv.style.display = 'none';
                }, 5000);
            })
            .catch(function(error) {
                var messageDiv = document.getElementById('message');
                messageDiv.className = 'message error';
                messageDiv.innerHTML = 'Lỗi kết nối: ' + error.message;
                messageDiv.style.display = 'block';
            });
        });
    </script>
</body>
</html>
