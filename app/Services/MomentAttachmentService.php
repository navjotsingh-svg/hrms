<?php

namespace App\Services;

use App\Models\CompanyMoment;
use App\Models\CompanyMomentAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MomentAttachmentService
{
    private const MAX_FILES = 5;

    private const MAX_FILE_KB = 5120;

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    public function __construct(
        private PublicUploadDirectoryService $uploadDirectories,
        private ImageCompressor $imageCompressor,
    ) {}

    /** @param  array<int, UploadedFile>  $files */
    public function storeMany(CompanyMoment $moment, array $files): Collection
    {
        $stored = collect();
        $validFiles = collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();

        if ($validFiles->count() > self::MAX_FILES) {
            throw ValidationException::withMessages([
                'attachments' => [sprintf('You can attach up to %d files per post.', self::MAX_FILES)],
            ]);
        }

        foreach ($validFiles as $file) {
            $stored->push($this->storeOne($moment, $file));
        }

        return $stored;
    }

    public function storeOne(CompanyMoment $moment, UploadedFile $file): CompanyMomentAttachment
    {
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'attachments' => ['Only PDF and image files (JPG, PNG, GIF, WEBP) are allowed.'],
            ]);
        }

        if (($file->getSize() ?: 0) > self::MAX_FILE_KB * 1024) {
            throw ValidationException::withMessages([
                'attachments' => [sprintf('Each attachment must be %d MB or smaller.', self::MAX_FILE_KB / 1024)],
            ]);
        }

        $relativeDirectory = CompanyMomentAttachment::PUBLIC_UPLOAD_DIR."/{$moment->company_id}/{$moment->id}";
        $this->uploadDirectories->ensure($relativeDirectory);
        $absoluteDirectory = public_path($relativeDirectory);

        if (str_starts_with($mime, 'image/')) {
            $path = $this->imageCompressor->compressAndSave(
                $file,
                $absoluteDirectory,
                $relativeDirectory,
                1600,
                80,
                true,
            );
            $fileSize = filesize(public_path($path)) ?: 0;
        } else {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');

            if ($extension !== 'pdf') {
                throw ValidationException::withMessages([
                    'attachments' => ['Only PDF files are allowed for document attachments.'],
                ]);
            }

            $filename = now()->format('YmdHis').'_'.uniqid().'.pdf';
            $fileSize = $file->getSize() ?: 0;
            $file->move($absoluteDirectory, $filename);
            $path = $relativeDirectory.'/'.$filename;
        }

        return CompanyMomentAttachment::query()->create([
            'company_moment_id' => $moment->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $mime,
            'file_size' => $fileSize,
        ]);
    }

    public function deleteAll(CompanyMoment $moment): void
    {
        $moment->attachments->each(function (CompanyMomentAttachment $attachment) {
            $attachment->deleteFile();
            $attachment->delete();
        });
    }
}
