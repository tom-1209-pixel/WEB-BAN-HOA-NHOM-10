<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    /**
     * Bảng imports không dùng updated_at.
     * Trạng thái hoàn thành dùng completed_at thay vì timestamps chuẩn.
     */
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'supplier_id',
        'note',
        'status',
        'total_amount',
    ];

    protected $casts = [
        'status'       => ImportStatus::class,
        'total_amount' => 'decimal:2',
        'created_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isDrafting(): bool
    {
        return $this->status === ImportStatus::Drafting;
    }

    public function isCompleted(): bool
    {
        return $this->status === ImportStatus::Completed;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ImportDetail::class);
    }
}
