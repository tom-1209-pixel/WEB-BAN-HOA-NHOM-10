<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportDetail;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * GET /api/admin/inventory/stock
     * Tra cứu tồn kho hiện tại theo category hoặc toàn bộ.
     * Query params: category_id, search, low_stock (true/false)
     */
    public function stock(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name')
            ->select(['id', 'code', 'name', 'category_id', 'unit', 'quantity', 'low_stock_threshold', 'status']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity', '<=', 'low_stock_threshold');
        }

        $perPage   = min((int) $request->get('per_page', 20), 100);
        $products  = $query->orderBy('quantity')->paginate($perPage);

        return response()->json($products);
    }

    /**
     * GET /api/admin/inventory/low-stock
     * Danh sách sản phẩm đang ở mức cảnh báo tồn kho.
     */
    public function lowStock(): JsonResponse
    {
        $products = Product::with('category:id,name')
            ->select(['id', 'code', 'name', 'category_id', 'unit', 'quantity', 'low_stock_threshold'])
            ->lowStock()
            ->orderBy('quantity')
            ->get();

        return response()->json([
            'total' => $products->count(),
            'data'  => $products,
        ]);
    }

    /**
     * GET /api/admin/inventory/report
     * Báo cáo tổng nhập - xuất theo khoảng thời gian.
     * Query params: from_date (required), to_date (required), product_id (optional)
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        $fromDate   = $request->from_date;
        $toDate     = $request->to_date;
        $productId  = $request->product_id;

        // Tổng nhập theo từng sản phẩm (chỉ tính phiếu nhập hoàn thành)
        $importQuery = ImportDetail::query()
            ->join('imports', 'import_details.import_id', '=', 'imports.id')
            ->join('products', 'import_details.product_id', '=', 'products.id')
            ->where('imports.status', 'completed')
            ->whereBetween('imports.completed_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->select([
                'import_details.product_id',
                'products.code',
                'products.name',
                'products.unit',
                DB::raw('SUM(import_details.quantity) as total_imported_qty'),
                DB::raw('SUM(import_details.subtotal) as total_imported_value'),
            ])
            ->groupBy('import_details.product_id', 'products.code', 'products.name', 'products.unit');

        // Tổng xuất theo từng sản phẩm (đơn hàng đã giao)
        $exportQuery = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->where('orders.status', 'delivered')
            ->whereBetween('orders.updated_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->select([
                'order_details.product_id',
                'products.code',
                'products.name',
                'products.unit',
                DB::raw('SUM(order_details.quantity) as total_sold_qty'),
                DB::raw('SUM(order_details.subtotal) as total_sold_value'),
            ])
            ->groupBy('order_details.product_id', 'products.code', 'products.name', 'products.unit');

        if ($productId) {
            $importQuery->where('import_details.product_id', $productId);
            $exportQuery->where('order_details.product_id', $productId);
        }

        $imports = $importQuery->get()->keyBy('product_id');
        $exports = $exportQuery->get()->keyBy('product_id');

        // Merge nhập và xuất theo product_id
        $productIds = $imports->keys()->merge($exports->keys())->unique();

        $report = $productIds->map(function ($pid) use ($imports, $exports) {
            $imp = $imports->get($pid);
            $exp = $exports->get($pid);

            return [
                'product_id'           => $pid,
                'code'                 => $imp?->code ?? $exp?->code,
                'name'                 => $imp?->name ?? $exp?->name,
                'unit'                 => $imp?->unit ?? $exp?->unit,
                'total_imported_qty'   => (int) ($imp?->total_imported_qty ?? 0),
                'total_imported_value' => round((float) ($imp?->total_imported_value ?? 0), 2),
                'total_sold_qty'       => (int) ($exp?->total_sold_qty ?? 0),
                'total_sold_value'     => round((float) ($exp?->total_sold_value ?? 0), 2),
            ];
        })->values();

        $summary = [
            'from_date'            => $fromDate,
            'to_date'              => $toDate,
            'total_imported_value' => round($report->sum('total_imported_value'), 2),
            'total_sold_value'     => round($report->sum('total_sold_value'), 2),
        ];

        return response()->json([
            'summary' => $summary,
            'data'    => $report,
        ]);
    }
}
