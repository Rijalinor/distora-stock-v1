<?php

namespace App\Models;

use App\Enums\StockSessionStatus;
use Illuminate\Database\Eloquent\Model;

class StockSession extends Model
{
    protected $fillable = [
        'csv_upload_id',
        'principal_id',
        'session_date',
        'assigned_to',
        'status',
        'total_items',
        'checked_items',
        'matched_items',
        'mismatched_items',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'status' => StockSessionStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function csvUpload()
    {
        return $this->belongsTo(CsvUpload::class);
    }

    public function principal()
    {
        return $this->belongsTo(Principal::class);
    }

    public function assignedOfficer()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function items()
    {
        return $this->hasMany(StockSessionItem::class);
    }
}
