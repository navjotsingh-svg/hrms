<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LivenessAssetController extends Controller
{
    private const WASM_FILES = [
        'tfjs-backend-wasm.wasm',
        'tfjs-backend-wasm-simd.wasm',
        'tfjs-backend-wasm-threaded-simd.wasm',
    ];

    private const BLAZEFACE_FILES = [
        'model.json',
        'group1-shard1of1.bin',
    ];

    public function wasm(string $file): BinaryFileResponse
    {
        if (! in_array($file, self::WASM_FILES, true)) {
            throw new NotFoundHttpException('Unknown liveness WASM asset.');
        }

        $path = public_path('vendor/tfjs-backend-wasm/'.$file);

        if (! is_file($path)) {
            throw new NotFoundHttpException('Liveness WASM asset is not deployed.');
        }

        return $this->fileResponse($path, 'application/wasm');
    }

    public function blazeface(string $file): BinaryFileResponse
    {
        if (! in_array($file, self::BLAZEFACE_FILES, true)) {
            throw new NotFoundHttpException('Unknown liveness model asset.');
        }

        $path = public_path('vendor/blazeface/'.$file);

        if (! is_file($path)) {
            throw new NotFoundHttpException('Liveness model asset is not deployed.');
        }

        $contentType = str_ends_with($file, '.json')
            ? 'application/json'
            : 'application/octet-stream';

        return $this->fileResponse($path, $contentType);
    }

    private function fileResponse(string $path, string $contentType): BinaryFileResponse
    {
        return response()->file($path, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
