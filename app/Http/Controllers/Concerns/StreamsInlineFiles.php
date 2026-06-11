<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait StreamsInlineFiles
{
    protected function inlineFileResponse(array $file): BinaryFileResponse
    {
        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '\\"', $file['name']).'"',
        ]);
    }
}
