<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ExpenseAttachmentService
{
    public function __construct(
        private PublicUploadDirectoryService $uploadDirectories,
        private ImageCompressor $imageCompressor,
    ) {}

    public function storeMany(Expense $expense, Employee $employee, array $files): Collection
    {
        $stored = collect();

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $stored->push($this->storeOne($expense, $employee, $file));
        }

        return $stored;
    }

    public function storeOne(Expense $expense, Employee $employee, UploadedFile $file): ExpenseAttachment
    {
        $relativeDirectory = ExpenseAttachment::PUBLIC_UPLOAD_DIR."/{$employee->company_id}/{$employee->id}";
        $this->uploadDirectories->ensure($relativeDirectory);
        $absoluteDirectory = public_path($relativeDirectory);

        $mime = (string) $file->getMimeType();
        $isImage = str_starts_with($mime, 'image/');

        if ($isImage) {
            $path = $this->imageCompressor->compressAndSave(
                $file,
                $absoluteDirectory,
                $relativeDirectory,
                1200,
                75,
                true,
            );
            $fileSize = filesize(public_path($path)) ?: 0;
        } else {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
            $filename = now()->format('YmdHis').'_'.uniqid().'.'.$extension;
            $fileSize = $file->getSize() ?: 0;
            $file->move($absoluteDirectory, $filename);
            $path = $relativeDirectory.'/'.$filename;
        }

        return ExpenseAttachment::create([
            'expense_id' => $expense->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $mime,
            'file_size' => $fileSize,
        ]);
    }

    public function deleteAll(Expense $expense): void
    {
        $expense->attachments->each(function (ExpenseAttachment $attachment) {
            $attachment->deleteFile();
            $attachment->delete();
        });
    }
}
