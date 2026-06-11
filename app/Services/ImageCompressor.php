<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImageCompressor
{
    public function __construct(private PublicUploadDirectoryService $uploadDirectories) {}

    public function compressAndSave(
        UploadedFile $file,
        string $directory,
        string $relativeDirectory = 'images/companies/logos',
        int $maxWidth = 800,
        int $quality = 90,
        bool $forceCompress = false,
    ): string {
        $this->uploadDirectories->ensure($relativeDirectory);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        if (in_array($extension, ['svg'], true)) {
            $filename = $this->generateFilename($extension);
            $file->move($directory, $filename);

            return $relativeDirectory.'/'.$filename;
        }

        if (! extension_loaded('gd')) {
            $filename = $this->generateFilename($extension);
            $file->move($directory, $filename);

            return $relativeDirectory.'/'.$filename;
        }

        $dimensions = @getimagesize($file->getRealPath());
        $imageType = $dimensions[2] ?? null;

        if (! $forceCompress && $dimensions && $dimensions[0] <= $maxWidth) {
            $filename = $this->generateFilename($extension);
            $file->move($directory, $filename);

            return $relativeDirectory.'/'.$filename;
        }

        // Decoding a large photo can exceed the PHP memory limit and crash the
        // worker (the server then responds 503). Store the original untouched
        // instead of attempting compression in that case.
        if (! $this->canDecodeWithinMemoryLimit($dimensions)) {
            $filename = $this->generateFilename($extension);
            $file->move($directory, $filename);

            return $relativeDirectory.'/'.$filename;
        }

        $image = $this->createImageResource($file, $imageType);

        if (! $image) {
            $filename = $this->generateFilename($extension);
            $file->move($directory, $filename);

            return $relativeDirectory.'/'.$filename;
        }

        $hasAlpha = ! $forceCompress && in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
        $image = $this->resizeImage($image, $maxWidth, $hasAlpha);

        if ($forceCompress) {
            return $this->saveJpeg($image, $directory, $relativeDirectory, $quality);
        }

        return $this->saveImage($image, $directory, $relativeDirectory, $hasAlpha, $quality);
    }

    /**
     * Decode using the real image type detected from file content, since the
     * client extension can lie (e.g. a JPEG renamed to .png).
     */
    private function createImageResource(UploadedFile $file, ?int $imageType)
    {
        $path = $file->getRealPath();

        return match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function resizeImage($image, int $maxWidth, bool $preserveAlpha = false)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth) {
            return $image;
        }

        $newWidth = $maxWidth;
        $newHeight = (int) round(($height / $width) * $newWidth);
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($preserveAlpha) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    private function saveImage($image, string $directory, string $relativeDirectory, bool $hasAlpha, int $quality): string
    {
        if (! $hasAlpha) {
            return $this->saveJpeg($image, $directory, $relativeDirectory, $quality);
        }

        $filename = $this->generateFilename('png');
        imagepng($image, $directory.DIRECTORY_SEPARATOR.$filename, 6);
        imagedestroy($image);

        return $relativeDirectory.'/'.$filename;
    }

    private function saveJpeg($image, string $directory, string $relativeDirectory, int $quality): string
    {
        $filename = $this->generateFilename('jpg');
        imagejpeg($image, $directory.DIRECTORY_SEPARATOR.$filename, $quality);
        imagedestroy($image);

        return $relativeDirectory.'/'.$filename;
    }

    private function generateFilename(string $extension): string
    {
        return time().'_'.uniqid().'.'.$extension;
    }

    private function canDecodeWithinMemoryLimit(?array $dimensions): bool
    {
        if (! $dimensions || empty($dimensions[0]) || empty($dimensions[1])) {
            return true;
        }

        $limit = $this->memoryLimitBytes();

        if ($limit <= 0) {
            return true;
        }

        // GD stores ~5 bytes per pixel; the resized copy is comparatively
        // small, and 1.8 covers allocator overhead.
        $needed = (int) ($dimensions[0] * $dimensions[1] * 5 * 1.8);

        return memory_get_usage(true) + $needed < $limit;
    }

    private function memoryLimitBytes(): int
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '' || $limit === '-1') {
            return 0;
        }

        $value = (int) $limit;

        return match (strtoupper(substr($limit, -1))) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }
}
