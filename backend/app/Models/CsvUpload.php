<?php

namespace App\Models;

use App\Enums\CsvUploadStatus;
use Illuminate\Database\Eloquent\Model;

class CsvUpload extends Model
{
    protected $fillable = [
        'filename',
        'original_filename',
        'upload_date',
        'uploaded_by',
        'total_rows',
        'status',
        'summary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'upload_date' => 'date',
            'status' => CsvUploadStatus::class,
            'summary' => 'array',
        ];
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function stockSessions()
    {
        return $this->hasMany(StockSession::class);
    }
}
