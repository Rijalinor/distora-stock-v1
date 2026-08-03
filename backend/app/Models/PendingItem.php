<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingItem extends Model
{
    protected $fillable = [
        'branch_id', 'barcode', 'temporary_code', 'item_name', 'principal_id',
        'unit_label', 'qty_per_scan', 'status', 'matched_item_master_id',
        'created_by', 'notes',
    ];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function principal() { return $this->belongsTo(Principal::class); }
    public function matchedItemMaster() { return $this->belongsTo(ItemMaster::class, 'matched_item_master_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function damageCheckRows() { return $this->hasMany(DamageCheckPendingItem::class); }
}
