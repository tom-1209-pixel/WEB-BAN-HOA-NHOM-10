
## Yêu cầu hệ thống

- PHP >= 8.2
- Composer
- MySQL (XAMPP recommended)
## Cài đặt

```bash
# 1. Clone repository
git clone <repository-url>
cd Laravel

# 2. Cài đặt dependencies
composer install
npm install

# 3. Cấu hình environment
cp .env.example .env

# 4. Chỉnh sửa file .env (cập nhật thông tin database)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=

# 5. Sinh application key
php artisan key:generate

# 6. Chạy migration
php artisan migrate

# 7. Khởi động server
php artisan serve
```

## API Endpoints

### Danh mục (Categories)

| Method | Endpoint               | Mô tả                  |
|--------|------------------------|-------------------------|
| GET    | `/api/categories`      | Lấy danh sách danh mục |
| POST   | `/api/categories`      | Tạo danh mục mới       |
| GET    | `/api/categories/{id}` | Xem chi tiết danh mục  |
| PUT    | `/api/categories/{id}` | Cập nhật danh mục      |
| DELETE | `/api/categories/{id}` | Xóa danh mục           |

### Sản phẩm (Products)

| Method | Endpoint              | Mô tả                  |
|--------|-----------------------|-------------------------|
| GET    | `/api/products`       | Lấy danh sách sản phẩm |
| POST   | `/api/products`       | Tạo sản phẩm mới       |
| GET    | `/api/products/{id}`  | Xem chi tiết sản phẩm  |
| PUT    | `/api/products/{id}`  | Cập nhật sản phẩm      |
| DELETE | `/api/products/{id}`  | Xóa sản phẩm           |

## Cấu trúc dự án

```
app/
├── Http/
│   └── Controllers/
│       └── Api/
│           ├── CategoryController.php
│           └── ProductController.php
├── Models/
│   ├── Category.php
│   └── Product.php
database/
└── migrations/
    ├── create_categories_table.php
    └── create_products_table.php
routes/
└── api.php
```

## Postman Collection

## Công nghệ sử dụng

- **Framework:** Laravel 12
- **Ngôn ngữ:** PHP 8.2+
- **Cơ sở dữ liệu:** MySQL / SQLite

