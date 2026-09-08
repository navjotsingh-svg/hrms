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
        if ($request->has('sections') && is_string($request->input('sections'))) {
            $decoded = json_decode($request->input('sections'), true);
            $request->merge(['sections' => is_array($decoded) ? $decoded : []]);
        }

        $validated = $request->validate([
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string'],
            'hero_cta_text' => ['nullable', 'string', 'max:120'],
            'hero_cta_url' => ['nullable', 'string', 'max:500'],
            'about_html' => ['nullable', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'theme_primary' => ['nullable', 'string', 'max:20'],
            'theme_accent' => ['nullable', 'string', 'max:20'],
            'sections' => ['nullable', 'array'],
            'sections.marquee_tags' => ['nullable', 'array'],
            'sections.marquee_tags.*' => ['nullable', 'string', 'max:80'],
            'sections.stats' => ['nullable', 'array'],
            'sections.stats.*.value' => ['nullable', 'string', 'max:30'],
            'sections.stats.*.suffix' => ['nullable', 'string', 'max:10'],
            'sections.stats.*.label' => ['nullable', 'string', 'max:120'],
            'sections.why_join' => ['nullable', 'array'],
            'sections.why_join.*.title' => ['nullable', 'string', 'max:120'],
            'sections.why_join.*.description' => ['nullable', 'string', 'max:500'],
            'sections.why_join.*.icon' => ['nullable', 'string', 'max:30'],
            'sections.testimonials' => ['nullable', 'array'],
            'sections.testimonials.*.name' => ['nullable', 'string', 'max:120'],
            'sections.testimonials.*.role' => ['nullable', 'string', 'max:120'],
            'sections.testimonials.*.quote' => ['nullable', 'string', 'max:1000'],
            'sections.testimonials.*.photo_url' => ['nullable', 'string', 'max:500'],
            'sections.badges' => ['nullable', 'array'],
            'sections.badges.*.title' => ['nullable', 'string', 'max:120'],
            'sections.badges.*.image_url' => ['nullable', 'string', 'max:500'],
            'sections.section_titles' => ['nullable', 'array'],
            'sections.social_links' => ['nullable', 'array'],
            'sections.social_links.*.platform' => ['nullable', 'string', 'max:60'],
            'sections.social_links.*.url' => ['nullable', 'string', 'max:500'],
            'sections.footer_note' => ['nullable', 'string', 'max:1000'],
            'sections.show_marquee' => ['nullable', 'boolean'],
            'sections.show_stats' => ['nullable', 'boolean'],
            'sections.show_why_join' => ['nullable', 'boolean'],
            'sections.show_testimonials' => ['nullable', 'boolean'],
            'sections.show_badges' => ['nullable', 'boolean'],
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
            'hero_cta_text' => $settings->hero_cta_text,
            'hero_cta_url' => $settings->hero_cta_url,
            'about_html' => $settings->about_html,
            'header_html' => $settings->header_html,
            'footer_html' => $settings->footer_html,
            'banner_path' => $settings->banner_path,
            'banner_url' => $settings->banner_path ? asset($settings->banner_path) : null,
            'logo_path' => $settings->logo_path,
            'theme_primary' => $settings->theme_primary ?? '#0f172a',
            'theme_accent' => $settings->theme_accent ?? '#2563eb',
            'sections' => $settings->resolvedSections(),
            'is_published' => $settings->is_published,
            'embed_snippet' => $settings->embed_snippet,
            'meta_title' => $settings->meta_title,
            'meta_description' => $settings->meta_description,
            'public_url' => route('careers.show', $company->slug),
        ];
    }
}
