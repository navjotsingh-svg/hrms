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



                if (str_starts_with($path, EmployeeDocument::PUBLIC_UPLOAD_DIR.'/')) {

                    return;

                }



                $newRelativePath = str_starts_with($path, 'images/')

                    ? $path

                    : EmployeeDocument::PUBLIC_UPLOAD_DIR.'/'.ltrim($path, '/');



                if (str_starts_with($path, 'images/') && is_file(public_path($path))) {

                    return;

                }



                $content = null;

                $sourceDisk = null;



                foreach (['public', 'local'] as $disk) {

                    if (Storage::disk($disk)->exists($path)) {

                        $content = Storage::disk($disk)->get($path);

                        $sourceDisk = $disk;

                        break;

                    }

                }



                if ($content === null) {

                    return;

                }



                $destination = public_path($newRelativePath);

                $directory = dirname($destination);



                if (! is_dir($directory)) {

                    mkdir($directory, 0755, true);

                }



                file_put_contents($destination, $content);

                Storage::disk($sourceDisk)->delete($path);



                if ($document->file_path !== $newRelativePath) {

                    $document->update(['file_path' => $newRelativePath]);

                }

            });

    }



    public function down(): void

    {

        // Files remain in public/images/employee-documents after rollback.

    }

};


