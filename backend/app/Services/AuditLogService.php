<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function log(string $action, Model $record, array $oldValues = [], array $newValues = []): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $record::class,
            'auditable_id' => $record->getKey(),
            'auditable_label' => $this->labelFor($record),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public function changedValues(Model $record, array $fields): array
    {
        $old = [];
        $new = [];

        foreach ($fields as $field) {
            if (! $record->wasChanged($field)) {
                continue;
            }

            $old[$field] = $record->getOriginal($field);
            $new[$field] = $record->getAttribute($field);
        }

        return [$old, $new];
    }

    protected function labelFor(Model $record): string
    {
        foreach (['nama_barang', 'nama', 'name', 'kode_barang', 'kode'] as $field) {
            if (filled($record->getAttribute($field))) {
                return (string) $record->getAttribute($field);
            }
        }

        return class_basename($record) . ' #' . $record->getKey();
    }
}
