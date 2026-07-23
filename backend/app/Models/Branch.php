<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'kode',
        'nama',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function csvUploads()
    {
        return $this->hasMany(CsvUpload::class);
    }

    public function stockSessions()
    {
        return $this->hasMany(StockSession::class);
    }
}
