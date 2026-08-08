<?php

namespace App\Filament\Resources\Principals\Pages;

use App\Filament\Resources\Principals\PrincipalResource;
use App\Services\AuditLogService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditPrincipal extends EditRecord
{
    protected static string $resource = PrincipalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(fn (Model $record) => app(AuditLogService::class)->log(
                    'deleted',
                    $record,
                    $record->only([
                        'kode',
                        'nama',
                        'group_principal_id',
                        'separate_ctn_pcs_count',
                        'status',
                    ]),
                    [],
                )),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = Auth::user();

        if ($user && ! $user->isCentralAdmin() && $user->branch_id) {
            $data['status'] = $this->record
                ->itemMasters()
                ->where('branch_id', $user->branch_id)
                ->where('status', true)
                ->exists();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();
        $before = $record->only([
            'kode',
            'nama',
            'group_principal_id',
            'separate_ctn_pcs_count',
            'status',
        ]);

        if ($user && ! $user->isCentralAdmin() && $user->branch_id && array_key_exists('status', $data)) {
            $record->itemMasters()
                ->where('branch_id', $user->branch_id)
                ->update(['status' => (bool) $data['status']]);

            unset($data['status']);
        }

        $record = parent::handleRecordUpdate($record, $data);

        $after = $record->only(array_keys($before));

        if ($before !== $after) {
            app(AuditLogService::class)->log('updated', $record, $before, $after);
        }

        return $record;
    }
}
