<?php

use App\Models\EmployeeDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        EmployeeDocument::query()
            ->whereNotNull('file_path')
            ->each(function (EmployeeDocument $document): void {
                $path = $document->file_path;

                if (! Storage::disk('local')->exists($path)) {
                    return;
                }

                if (Storage::disk(EmployeeDocument::FILE_DISK)->exists($path)) {
                    Storage::disk('local')->delete($path);

                    return;
                }

                Storage::disk(EmployeeDocument::FILE_DISK)->put(
                    $path,
                    Storage::disk('local')->get($path)
                );

                Storage::disk('local')->delete($path);
            });
    }

    public function down(): void
    {
        EmployeeDocument::query()
            ->whereNotNull('file_path')
            ->each(function (EmployeeDocument $document): void {
                $path = $document->file_path;

                if (! Storage::disk(EmployeeDocument::FILE_DISK)->exists($path)) {
                    return;
                }

                if (Storage::disk('local')->exists($path)) {
                    Storage::disk(EmployeeDocument::FILE_DISK)->delete($path);

                    return;
                }

                Storage::disk('local')->put(
                    $path,
                    Storage::disk(EmployeeDocument::FILE_DISK)->get($path)
                );

                Storage::disk(EmployeeDocument::FILE_DISK)->delete($path);
            });
    }
};
