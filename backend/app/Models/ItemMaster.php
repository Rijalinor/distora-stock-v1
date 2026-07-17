<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemMaster extends Model
{
    protected $fillable = [

        'kode_barang',

        'barcode',

        'nama_barang',

        'principal_id',

        'satuan',

        'status',

    ];
    public function principal()
    {
        return $this->belongsTo(Principal::class);
    }

    public function stockSessionItems()
    {
        return $this->hasMany(StockSessionItem::class);
    }
}
