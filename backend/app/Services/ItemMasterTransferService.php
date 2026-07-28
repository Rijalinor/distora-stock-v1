<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ItemMaster;
use App\Models\Principal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ItemMasterTransferService
{
    /**
     * @return array{total:int, created:int, updated:int}
     */
    public function copyPrincipal(int $sourceBranchId, int $targetBranchId, int $principalId): array
    {
        if ($sourceBranchId === $targetBranchId) {
            throw new InvalidArgumentException('Cabang sumber dan tujuan harus berbeda.');
        }

        Branch::findOrFail($sourceBranchId);
        Branch::findOrFail($targetBranchId);
        Principal::findOrFail($principalId);

        return DB::transaction(function () use ($sourceBranchId, $targetBranchId, $principalId): array {
            $stats = ['total' => 0, 'created' => 0, 'updated' => 0];

            ItemMaster::query()
                ->where('branch_id', $sourceBranchId)
                ->where('principal_id', $principalId)
                ->orderBy('id')
                ->chunkById(500, function ($items) use ($targetBranchId, &$stats): void {
                    foreach ($items as $item) {
                        $targetExists = ItemMaster::query()
                            ->where('branch_id', $targetBranchId)
                            ->where('kode_barang', $item->kode_barang)
                            ->exists();

                        ItemMaster::updateOrCreate(
                            [
                                'branch_id' => $targetBranchId,
                                'kode_barang' => $item->kode_barang,
                            ],
                            [
                                'barcode' => $item->barcode,
                                'nama_barang' => $item->nama_barang,
                                'principal_id' => $item->principal_id,
                                'satuan' => $item->satuan,
                                'qty_structure' => $item->qty_structure,
                                'status' => $item->status,
                            ]
                        );

                        $stats['total']++;
                        $stats[$targetExists ? 'updated' : 'created']++;
                    }
                });

            return $stats;
        });
    }
}
