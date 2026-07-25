<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockFoundItem extends Model
{
    protected $fillable = [
        'stock_session_id',
        'item_master_id',
        'suspected_item_master_id',
        'kode_barang',
        'barcode',
        'nama_barang',
        'satuan',
        'qty_aktual_display',
        'qty_aktual_base',
        'status',
        'note',
        'found_by',
        'found_at',
    ];

    protected function casts(): array
    {
        return [
            'found_at' => 'datetime',
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

    public function suspectedItemMaster()
    {
        return $this->belongsTo(ItemMaster::class, 'suspected_item_master_id');
    }

    public function foundBy()
    {
        return $this->belongsTo(User::class, 'found_by');
    }
}
