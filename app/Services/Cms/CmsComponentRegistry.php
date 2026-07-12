<?php

namespace App\Services\Cms;

/**
 * The single source of truth for which CMS components exist, what fields
 * each accepts, and what variants are allowed. Both the admin editor's
 * dynamic form rendering and CmsSection save-time validation read from
 * this — a component is never just a free-form string, and a field is
 * never accepted unless it's declared here.
 *
 * Field `type` values: text | textarea | richtext | cta | list | image |
 * url | number | boolean. `translatable` fields are stored in
 * cms_sections.content as {field_key: {nl,en,de,fr}}; non-translatable
 * fields are stored as a plain value.
 */
class CmsComponentRegistry
{
    /**
     * @return array<string, array{label: string, variants: array<int, string>, fields: array<int, array<string, mixed>>}>
     */
    public static function definitions(): array
    {
        return [
            'HeroSection' => [
                'label' => 'Hero',
                'variants' => ['default', 'centered', 'split'],
                'fields' => [
                    self::field('badge', 'text', translatable: true, required: false),
                    self::field('title', 'text', translatable: true, required: true),
                    self::field('subtitle', 'textarea', translatable: true, required: false),
                    self::field('primary_cta', 'cta', translatable: true, required: false),
                    self::field('secondary_cta', 'cta', translatable: true, required: false),
                    self::field('background_image', 'image', translatable: false, required: false),
                    self::field('trust_items', 'list', translatable: true, required: false),
                ],
            ],
            'CTASection' => [
                'label' => 'Call to Action',
                'variants' => ['default', 'banner'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: true),
                    self::field('subtitle', 'textarea', translatable: true, required: false),
                    self::field('cta', 'cta', translatable: true, required: true),
                    self::field('background_image', 'image', translatable: false, required: false),
                ],
            ],
            'TextImageSection' => [
                'label' => 'Text + Image',
                'variants' => ['image_left', 'image_right'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('body', 'richtext', translatable: true, required: true),
                    self::field('image', 'image', translatable: false, required: true),
                ],
            ],
            'FeatureGrid' => [
                'label' => 'Feature Grid',
                'variants' => ['3_column', '4_column'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('items', 'list', translatable: true, required: true),
                ],
            ],
            'ProductExplorer' => [
                'label' => 'Product Explorer',
                'variants' => ['yachts', 'manual'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('filters_enabled', 'boolean', translatable: false, required: false),
                ],
            ],
            'ProjectGrid' => [
                'label' => 'Project Grid',
                'variants' => ['default'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('items', 'list', translatable: true, required: true),
                ],
            ],
            'TestimonialBlock' => [
                'label' => 'Testimonials',
                'variants' => ['carousel', 'grid'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('items', 'list', translatable: true, required: true),
                ],
            ],
            'PartnerLogoGrid' => [
                'label' => 'Partner Logos',
                'variants' => ['default'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('logos', 'list', translatable: false, required: true),
                ],
            ],
            'StatsBlock' => [
                'label' => 'Stats',
                'variants' => ['default'],
                'fields' => [
                    self::field('items', 'list', translatable: true, required: true),
                ],
            ],
            'FAQBlock' => [
                'label' => 'FAQ',
                'variants' => ['default'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('items', 'list', translatable: true, required: true),
                ],
            ],
            'BlogGrid' => [
                'label' => 'Blog Grid',
                'variants' => ['manual', 'latest'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('items', 'list', translatable: true, required: false),
                ],
            ],
            'FormBlock' => [
                'label' => 'Form',
                'variants' => ['default'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('form_key', 'text', translatable: false, required: true),
                    self::field('fields', 'list', translatable: true, required: false),
                ],
            ],
            'VideoBlock' => [
                'label' => 'Video',
                'variants' => ['default'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('video_url', 'url', translatable: false, required: true),
                    self::field('poster_image', 'image', translatable: false, required: false),
                ],
            ],
            'GalleryBlock' => [
                'label' => 'Gallery',
                'variants' => ['grid', 'carousel'],
                'fields' => [
                    self::field('title', 'text', translatable: true, required: false),
                    self::field('images', 'list', translatable: false, required: true),
                ],
            ],
        ];
    }

    public static function exists(string $component): bool
    {
        return array_key_exists($component, self::definitions());
    }

    /**
     * @return array<int, string>
     */
    public static function validate(string $component, ?string $variant, array $content): array
    {
        $definitions = self::definitions();
        $errors = [];

        if (! array_key_exists($component, $definitions)) {
            return ["Unknown component [{$component}]."];
        }

        $definition = $definitions[$component];

        if ($variant !== null && ! in_array($variant, $definition['variants'], true)) {
            $errors[] = "Variant [{$variant}] is not allowed for {$component}.";
        }

        foreach ($definition['fields'] as $field) {
            if (! $field['required']) {
                continue;
            }

            $value = $content[$field['key']] ?? null;
            $isEmpty = $field['translatable']
                ? ! is_array($value) || collect($value)->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()
                : ($value === null || $value === '');

            if ($isEmpty) {
                $errors[] = "Field [{$field['key']}] is required for {$component}.";
            }
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private static function field(string $key, string $type, bool $translatable, bool $required): array
    {
        return [
            'key' => $key,
            'type' => $type,
            'translatable' => $translatable,
            'required' => $required,
        ];
    }
}
