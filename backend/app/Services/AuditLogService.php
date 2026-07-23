<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    private const ENABLED_ACTIONS = [
        'created',
        'updated',
        'deleted',
        'stock_missing',
        'stock_corrected',
        'session_completed',
        'session_closed_by_day',
    ];

    public function log(string $action, Model $record, array $oldValues = [], array $newValues = []): void
    {
        if (! in_array($action, self::ENABLED_ACTIONS, true)) {
            return;
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $record::class,
            'auditable_id' => $record->getKey(),
            'auditable_label' => $this->labelFor($record),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => null,
            'user_agent' => null,
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
