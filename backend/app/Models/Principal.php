<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Principal extends Model
{
    protected $fillable = [
        'kode',
        'nama',
        'group_principal_id',
        'separate_ctn_pcs_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'separate_ctn_pcs_count' => 'bool',
            'status' => 'bool',
        ];
    }

    public function itemMasters()
    {
        return $this->hasMany(ItemMaster::class);
    }

    public function groupPrincipal()
    {
        return $this->belongsTo(self::class, 'group_principal_id');
    }

    public function groupedPrincipals()
    {
        return $this->hasMany(self::class, 'group_principal_id');
    }

    public function stockSessions()
    {
        return $this->hasMany(StockSession::class);
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $query) => $query->whereHas(
            'itemMasters',
            fn (Builder $query) => $query->where('branch_id', $branchId)
        ));
    }

    public function effectivePrincipalId(): int
    {
        $current = $this;
        $visited = [];

        while ($current->group_principal_id) {
            if (in_array($current->id, $visited, true)) {
                return $this->id;
            }

            $visited[] = $current->id;
            $current = self::find($current->group_principal_id);

            if (! $current) {
                return $this->id;
            }
        }

        return $current->id;
    }
}
