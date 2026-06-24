<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Services\HiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HiringCareersController extends Controller
{
    use ApiResponse;

    public function __construct(private HiringService $hiringService) {}

    public function show(Request $request): JsonResponse
    {
        $settings = $this->hiringService->careersSettingsForUser($request->user());
        $company = $request->user()->company;

        return $this->success([
            'settings' => $this->format($settings, $company),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string'],
            'about_html' => ['nullable', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
            'embed_snippet' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'banner' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $settings = $this->hiringService->updateCareersSettings(
            $request->user(),
            $validated,
            $request->file('banner')
        );

        return $this->success([
            'settings' => $this->format($settings, $request->user()->company),
        ], 'Careers page updated.');
    }

    private function format($settings, $company): array
    {
        return [
            'hero_title' => $settings->hero_title,
            'hero_subtitle' => $settings->hero_subtitle,
            'about_html' => $settings->about_html,
            'header_html' => $settings->header_html,
            'footer_html' => $settings->footer_html,
            'banner_path' => $settings->banner_path,
            'banner_url' => $settings->banner_path ? asset($settings->banner_path) : null,
            'logo_path' => $settings->logo_path,
            'is_published' => $settings->is_published,
            'embed_snippet' => $settings->embed_snippet,
            'meta_title' => $settings->meta_title,
            'meta_description' => $settings->meta_description,
            'public_url' => route('careers.show', $company->slug),
        ];
    }
}
