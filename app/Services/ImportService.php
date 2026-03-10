<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportService
{
    /**
     * Hoàn thành phiếu nhập — thực hiện trong DB transaction:
     * 1. Kiểm tra trạng thái drafting và có ít nhất 1 dòng chi tiết
     * 2. Với mỗi import_detail: tính avg_import_price mới, cộng quantity, recalculate selling_price
     * 3. Cập nhật imports.status = 'completed', completed_at = now
     * 4. Recalculate total_amount
     */
    public function complete(Import $import): Import
    {
        if ($import->isCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'Phiếu nhập đã được hoàn thành trước đó.',
            ]);
        }

        $details = $import->details()->with('product')->get();

        if ($details->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Phiếu nhập phải có ít nhất một sản phẩm trước khi hoàn thành.',
            ]);
        }

        DB::transaction(function () use ($import, $details) {
            $totalAmount = 0;

            foreach ($details as $detail) {
                $product = $detail->product;

                // Tính lại giá nhập bình quân trước khi cộng tồn kho
                $product->recalculateAvgImportPrice(
                    $detail->quantity,
                    (float) $detail->import_price
                );

                // Cộng số lượng vào tồn kho
                $product->quantity += $detail->quantity;

                // Tính lại giá bán dựa trên avg_import_price mới
                $product->recalculateSellingPrice();
                $product->save();

                $totalAmount += (float) $detail->subtotal;
            }

            $import->status       = ImportStatus::Completed;
            $import->completed_at = now();
            $import->total_amount = round($totalAmount, 2);
            $import->save();
        });

        return $import->fresh(['details.product', 'admin', 'supplier']);
    }
}
