<?php

use App\Models\EmployeeDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        EmployeeDocument::query()
            ->whereNotNull('file_path')
            ->where('file_path', 'not like', 'images/%')
            ->each(function (EmployeeDocument $document) {
                $legacyPaths = [
                    storage_path('app/public/'.$document->file_path),
                    storage_path('app/'.$document->file_path),
                    public_path('storage/'.$document->file_path),
                ];

                foreach ($legacyPaths as $legacyPath) {
                    if (! is_file($legacyPath)) {
                        continue;
                    }

                    $relativeDirectory = EmployeeDocument::PUBLIC_UPLOAD_DIR
                        ."/{$document->company_id}/{$document->employee_id}";
                    $targetDirectory = public_path($relativeDirectory);

                    if (! is_dir($targetDirectory)) {
                        File::makeDirectory($targetDirectory, 0755, true);
                    }

                    $filename = basename($document->file_path);
                    $targetPath = $targetDirectory.DIRECTORY_SEPARATOR.$filename;

                    if (! is_file($targetPath)) {
                        File::copy($legacyPath, $targetPath);
                    }

                    $document->update([
                        'file_path' => $relativeDirectory.'/'.$filename,
                    ]);

                    break;
                }
            });
    }

    public function down(): void
    {
        // Data migration — no rollback.
    }
};
