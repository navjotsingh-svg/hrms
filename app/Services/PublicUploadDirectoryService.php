<?php

namespace App\Services;

class PublicUploadDirectoryService
{
    public const BASE_DIRECTORIES = [
        'images/companies/logos',
        'images/employee-documents',
        'images/attendance/selfies',
        'images/leave-attachments',
        'images/expense-receipts',
        'images/moment-attachments',
    ];

    public function ensure(string $relativePath): string
    {
        $directory = public_path(trim($relativePath, '/'));

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create upload directory: {$relativePath}");
        }

        return $directory;
    }

    public function ensureBaseDirectories(): void
    {
        foreach (self::BASE_DIRECTORIES as $relativePath) {
            $this->ensure($relativePath);
        }
    }
}
