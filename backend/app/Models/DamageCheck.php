<?php

namespace App\Models;

use App\Enums\DamageCheckStatus;
use Illuminate\Database\Eloquent\Model;

class DamageCheck extends Model
{
    protected $fillable = [
        'reference_number',
        'check_date',
        'branch_id',
        'principal_id',
        'officer_id',
        'location',
        'notes',
        'join_pin',
        'status',
        'completed_at',
    ];

    protected $hidden = ['join_pin'];

    protected function casts(): array
    {
        return [
            'check_date' => 'date',
            'completed_at' => 'datetime',
            'status' => DamageCheckStatus::class,
            'join_pin' => 'encrypted',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function principal()
    {
        return $this->belongsTo(Principal::class);
    }

    public function officer()
    {
        return $this->belongsTo(User::class, 'officer_id')->withTrashed();
    }

    public function checkers()
    {
        return $this->belongsToMany(User::class)->withTrashed()->withTimestamps();
    }

    public function items()
    {
        return $this->hasMany(DamageCheckItem::class);
    }

    public function pendingItems()
    {
        return $this->hasMany(DamageCheckPendingItem::class);
    }
}
