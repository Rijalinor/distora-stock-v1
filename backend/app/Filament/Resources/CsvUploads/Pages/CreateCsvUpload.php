<?php

namespace App\Filament\Resources\CsvUploads\Pages;

use App\Enums\CsvUploadStatus;
use App\Filament\Resources\CsvUploads\CsvUploadResource;
use App\Services\CsvImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateCsvUpload extends CreateRecord
{
    protected static string $resource = CsvUploadResource::class;

    protected static ?string $title = 'Upload Stok Harian';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $storedPath = $data['csv_file'] ?? null;

        if (is_array($storedPath)) {
            $storedPath = $storedPath[0] ?? null;
        }

        if (! $storedPath) {
            throw new \RuntimeException('File CSV wajib diupload.');
        }

        $data['filename'] = $storedPath;
        $data['original_filename'] = basename($storedPath);
        $data['uploaded_by'] = Auth::id();
        $data['status'] = CsvUploadStatus::Pending->value;
        $data['total_rows'] = 0;

        unset($data['csv_file']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $importService = app(CsvImportService::class);
        $fullPath = Storage::disk('local')->path($this->record->filename);

        try {
            $preview = $importService->processUpload($fullPath);

            $this->record->update([
                'total_rows' => $preview->totalRows,
                'status' => CsvUploadStatus::Previewed,
                'summary' => [
                    'principal_groups' => $preview->principalGroups,
                ],
            ]);

            Notification::make()
                ->title('CSV berhasil diproses')
                ->body("Ditemukan {$preview->totalRows} item dari " . count($preview->principalGroups) . ' principal.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->record->update([
                'status' => CsvUploadStatus::Failed,
                'notes' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Gagal memproses CSV')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
