<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Principal extends Model
{
    protected $fillable = [
        'kode',
        'nama',
        'status',
    ];

    public function itemMasters()
    {
        return $this->hasMany(ItemMaster::class);
    }

    public function stockSessions()
    {
        return $this->hasMany(StockSession::class);
    }
}