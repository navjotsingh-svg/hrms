<?php

namespace App\Support;

class CareersPageDefaults
{
    public static function sections(): array
    {
        return [
            'marquee_tags' => ['Engineering', 'Product', 'Design', 'DevOps', 'Sales', 'HR', 'Marketing'],
            'stats' => [
                ['value' => '500', 'suffix' => '+', 'label' => 'Team Members'],
                ['value' => '98', 'suffix' => '%', 'label' => 'Employee Retention'],
                ['value' => '15', 'suffix' => '+', 'label' => 'Countries'],
                ['value' => '4.8', 'suffix' => '★', 'label' => 'Employee Rating'],
            ],
            'why_join' => [
                [
                    'title' => 'Innovation-first culture',
                    'description' => 'Work on meaningful products with modern tools, agile teams, and room to experiment.',
                    'icon' => 'lightbulb',
                ],
                [
                    'title' => 'Growth & learning',
                    'description' => 'Training budgets, mentorship, and clear career paths so you can level up every year.',
                    'icon' => 'chart',
                ],
                [
                    'title' => 'People-centric workplace',
                    'description' => 'Flexible work, wellness support, and a collaborative environment that puts people first.',
                    'icon' => 'heart',
                ],
            ],
            'testimonials' => [
                [
                    'name' => 'Team Member',
                    'role' => 'Software Engineer',
                    'quote' => 'The culture here encourages ownership and creativity. I have grown more in one year than I did in three elsewhere.',
                    'photo_url' => null,
                ],
            ],
            'badges' => [
                ['title' => 'Great Place to Work', 'image_url' => null],
                ['title' => 'ISO Certified', 'image_url' => null],
                ['title' => 'Equal Opportunity', 'image_url' => null],
            ],
            'section_titles' => [
                'culture' => [
                    'eyebrow' => 'Life at our company',
                    'title' => 'Where passion aligns with progress',
                    'subtitle' => 'We build products that matter — and we invest in the people who build them.',
                ],
                'stats' => [
                    'eyebrow' => 'Our numbers',
                    'title' => 'Growth you can measure',
                    'subtitle' => 'A fast-growing team with strong retention and global impact.',
                ],
                'jobs' => [
                    'eyebrow' => 'Open positions',
                    'title' => 'The future looks promising',
                    'subtitle' => 'Explore roles across teams and find where you belong.',
                ],
                'testimonials' => [
                    'eyebrow' => 'Testimonials',
                    'title' => 'Hear from our team',
                    'subtitle' => 'Real stories from people who grow with us every day.',
                ],
                'contact' => [
                    'eyebrow' => 'Join us',
                    'title' => 'Ready to make an impact?',
                    'subtitle' => 'Submit your application — we review every profile carefully.',
                ],
            ],
            'social_links' => [
                ['platform' => 'LinkedIn', 'url' => ''],
                ['platform' => 'Twitter', 'url' => ''],
            ],
            'footer_note' => 'We are an equal opportunity employer. All qualified applicants will receive consideration without regard to race, color, religion, sex, or national origin.',
            'show_marquee' => true,
            'show_stats' => true,
            'show_why_join' => true,
            'show_testimonials' => true,
            'show_badges' => true,
        ];
    }

    /** @param  array<string, mixed>|null  $saved */
    public static function merge(?array $saved): array
    {
        $defaults = self::sections();

        if (! is_array($saved)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $saved);
    }
}
