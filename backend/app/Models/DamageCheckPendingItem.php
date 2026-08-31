<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageCheckPendingItem extends Model
{
    protected $fillable = [
        'damage_check_id', 'pending_item_id', 'qty_rusak_base',
        'qty_rusak_display', 'last_scanned_at', 'last_scanned_by',
    ];

    protected function casts(): array { return ['last_scanned_at' => 'datetime']; }

    public function damageCheck() { return $this->belongsTo(DamageCheck::class); }
    public function pendingItem() { return $this->belongsTo(PendingItem::class); }
    public function lastScanner() { return $this->belongsTo(User::class, 'last_scanned_by')->withTrashed(); }
}
