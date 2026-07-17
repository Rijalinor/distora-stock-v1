<?php

namespace App\Models;

use App\Enums\StockSessionItemStatus;
use Illuminate\Database\Eloquent\Model;

class StockSessionItem extends Model
{
    protected $fillable = [
        'stock_session_id',
        'item_master_id',
        'kode_barang',
        'nama_barang',
        'satuan',
        'qty_sistem_display',
        'qty_sistem_base',
        'qty_aktual_display',
        'qty_aktual_base',
        'selisih',
        'status',
        'checked_by',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockSessionItemStatus::class,
            'checked_at' => 'datetime',
        ];
    }

    public function stockSession()
    {
        return $this->belongsTo(StockSession::class);
    }

    public function itemMaster()
    {
        return $this->belongsTo(ItemMaster::class);
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function adjustmentLogs()
    {
        return $this->hasMany(StockAdjustmentLog::class);
    }
}
