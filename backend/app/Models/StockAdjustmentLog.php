<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'stock_session_item_id',
        'adjusted_by',
        'qty_before_base',
        'qty_after_base',
        'qty_before_display',
        'qty_after_display',
        'reason',
    ];

    public function stockSessionItem()
    {
        return $this->belongsTo(StockSessionItem::class);
    }

    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
