<?php

namespace App\Services\Cms;

use App\Models\CmsPage;
use App\Models\CmsPageVersion;
use App\Models\CmsSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The single place that mutates CmsPage + its CmsSections and snapshots a
 * new CmsPageVersion on every content change — mirrors the
 * EmailTemplate/EmailTemplateVersion pattern (full-snapshot versioning,
 * not per-field diffs) already established in this codebase, rather than
 * inventing a different versioning shape for CMS pages.
 */
class CmsPageService
{
    private const LOCALES = ['nl', 'en', 'de', 'fr'];
    private const SOURCE_LOCALE = 'nl';

    public function __construct(private readonly LanguageQualityService $languageQuality)
    {
    }

    public function create(array $data, ?User $actor): CmsPage
    {
        $page = CmsPage::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'status' => CmsPage::STATUS_DRAFT,
            'locale_status' => array_fill_keys(self::LOCALES, CmsPage::LOCALE_STATUS_MISSING),
            'seo' => $data['seo'] ?? null,
            'created_by_id' => $actor?->id,
        ]);

        $this->snapshotVersion($page, $actor, 'Page created');

        return $page->fresh(['sections']);
    }

    public function updateMeta(CmsPage $page, array $data, ?User $actor): CmsPage
    {
        $page->update([
            'name' => $data['name'] ?? $page->name,
            'seo' => array_key_exists('seo', $data) ? $data['seo'] : $page->seo,
        ]);

        $this->snapshotVersion($page, $actor, $data['change_note'] ?? 'Metadata updated');

        return $page->fresh(['sections']);
    }

    /**
     * Replaces the page's full section list — the admin editor always
     * saves the complete ordered set, not incremental patches, which
     * keeps sort_order/enable-disable state trivially consistent.
     *
     * @param array<int, array{component: string, variant: ?string, content: array, sort_order: int, is_enabled: bool}> $sections
     * @throws ValidationException
     */
    public function saveSections(CmsPage $page, array $sections, ?User $actor, ?string $changeNote = null): CmsPage
    {
        $errors = [];
        foreach ($sections as $index => $section) {
            $sectionErrors = CmsComponentRegistry::validate(
                $section['component'] ?? '',
                $section['variant'] ?? null,
                $section['content'] ?? [],
            );

            foreach ($sectionErrors as $error) {
                $errors["sections.{$index}"][] = $error;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $page->loadMissing('sections');
        $previousLocaleContent = $this->extractLocaleContent($page->sections->toArray());

        DB::transaction(function () use ($page, $sections, $actor, $changeNote) {
            $page->sections()->delete();

            foreach ($sections as $index => $section) {
                CmsSection::create([
                    'cms_page_id' => $page->id,
                    'component' => $section['component'],
                    'variant' => $section['variant'] ?? null,
                    'content' => $section['content'] ?? [],
                    'sort_order' => $section['sort_order'] ?? $index,
                    'is_enabled' => $section['is_enabled'] ?? true,
                ]);
            }

            $this->snapshotVersion($page, $actor, $changeNote ?? 'Sections updated');
        });

        $this->recomputeLocaleStatus($page->fresh(['sections']), $previousLocaleContent);

        return $page->fresh(['sections']);
    }

    /**
     * @throws ValidationException when language-quality issues are found and $force is false
     */
    public function publish(CmsPage $page, ?User $actor, bool $force = false): CmsPage
    {
        if (! $force) {
            $issues = $this->languageQuality->check($page);
            if ($issues !== []) {
                throw ValidationException::withMessages([
                    'quality' => 'Language quality issues were found. Review them, then publish again to confirm.',
                    '_issues' => $issues,
                ]);
            }
        }

        $localeStatus = collect($page->locale_status ?? [])
            ->map(fn ($status) => $status === CmsPage::LOCALE_STATUS_DRAFT ? CmsPage::LOCALE_STATUS_PUBLISHED : $status)
            ->all();

        $page->update([
            'status' => CmsPage::STATUS_PUBLISHED,
            'locale_status' => $localeStatus,
            'published_at' => now(),
            'scheduled_publish_at' => null,
        ]);

        $this->snapshotVersion($page, $actor, 'Published');

        return $page->fresh(['sections']);
    }

    public function schedule(CmsPage $page, \DateTimeInterface $at, ?User $actor): CmsPage
    {
        $page->update([
            'status' => CmsPage::STATUS_SCHEDULED,
            'scheduled_publish_at' => $at,
        ]);

        $this->snapshotVersion($page, $actor, "Scheduled for {$at->format('Y-m-d H:i')}");

        return $page->fresh(['sections']);
    }

    public function archive(CmsPage $page, ?User $actor): CmsPage
    {
        $page->update(['status' => CmsPage::STATUS_ARCHIVED]);
        $this->snapshotVersion($page, $actor, 'Archived');

        return $page->fresh(['sections']);
    }

    /**
     * Publishes any page whose scheduled_publish_at has arrived — intended
     * to be called from a scheduled command, matching the
     * campaigns:process polling pattern already used elsewhere. A page
     * with language-quality issues is left in 'scheduled' rather than
     * either force-publishing through them unattended or crashing the
     * whole run for other due pages — "publish blocked until reviewed"
     * still applies with nobody there to review.
     */
    public function publishDueScheduled(): int
    {
        $due = CmsPage::query()
            ->where('status', CmsPage::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '<=', now())
            ->get();

        $published = 0;
        foreach ($due as $page) {
            try {
                $this->publish($page, null);
                $published++;
            } catch (ValidationException $e) {
                Log::warning('[CmsPageService] Scheduled publish blocked by language quality issues', [
                    'cms_page_id' => $page->id,
                    'issues' => $e->errors()['_issues'] ?? [],
                ]);
            }
        }

        return $published;
    }

    public function restoreVersion(CmsPage $page, int $version, ?User $actor): CmsPage
    {
        $target = CmsPageVersion::query()
            ->where('cms_page_id', $page->id)
            ->where('version', $version)
            ->firstOrFail();

        $snapshot = $target->snapshot;

        DB::transaction(function () use ($page, $snapshot, $actor, $version) {
            $page->update([
                'name' => $snapshot['name'] ?? $page->name,
                'seo' => $snapshot['seo'] ?? null,
            ]);

            $page->sections()->delete();
            foreach ($snapshot['sections'] ?? [] as $index => $section) {
                CmsSection::create([
                    'cms_page_id' => $page->id,
                    'component' => $section['component'],
                    'variant' => $section['variant'] ?? null,
                    'content' => $section['content'] ?? [],
                    'sort_order' => $section['sort_order'] ?? $index,
                    'is_enabled' => $section['is_enabled'] ?? true,
                ]);
            }

            $this->snapshotVersion($page, $actor, "Restored from version {$version}");
        });

        return $page->fresh(['sections']);
    }

    /**
     * @param array<string, string> $previousLocaleContent
     */
    private function recomputeLocaleStatus(CmsPage $page, array $previousLocaleContent): void
    {
        $newLocaleContent = $this->extractLocaleContent($page->sections->toArray());
        $changedLocales = array_filter(
            self::LOCALES,
            fn ($locale) => ($previousLocaleContent[$locale] ?? '') !== ($newLocaleContent[$locale] ?? ''),
        );

        $currentStatus = $page->locale_status ?? array_fill_keys(self::LOCALES, CmsPage::LOCALE_STATUS_MISSING);
        $nextStatus = [];

        foreach (self::LOCALES as $locale) {
            $hasContent = trim((string) ($newLocaleContent[$locale] ?? '')) !== '';

            if (! $hasContent) {
                $nextStatus[$locale] = CmsPage::LOCALE_STATUS_MISSING;
            } elseif (in_array($locale, $changedLocales, true)) {
                // This is the locale that was just edited.
                $nextStatus[$locale] = CmsPage::LOCALE_STATUS_DRAFT;
            } elseif ($changedLocales !== []) {
                // A different locale changed — this one may now be stale
                // relative to it, regardless of its previous state.
                $nextStatus[$locale] = CmsPage::LOCALE_STATUS_NEEDS_REVIEW;
            } else {
                $nextStatus[$locale] = $currentStatus[$locale] ?? CmsPage::LOCALE_STATUS_DRAFT;
            }
        }

        $page->update(['locale_status' => $nextStatus]);
    }

    /**
     * Concatenates every translatable field's value per locale across all
     * sections, into one comparable string per locale — used to detect
     * which locale(s) actually changed between two saves.
     *
     * @return array<string, string>
     */
    private function extractLocaleContent(array $sections): array
    {
        $byLocale = array_fill_keys(self::LOCALES, '');

        foreach ($sections as $section) {
            foreach ($section['content'] ?? [] as $value) {
                if (! is_array($value) || ! $this->looksLikeLocaleValue($value)) {
                    continue;
                }

                foreach (self::LOCALES as $locale) {
                    $localeValue = $value[$locale] ?? null;
                    $byLocale[$locale] .= is_array($localeValue) ? implode('|', $localeValue) : (string) $localeValue;
                }
            }
        }

        return $byLocale;
    }

    private function looksLikeLocaleValue(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (! in_array($key, self::LOCALES, true)) {
                return false;
            }
        }

        return true;
    }

    private function snapshotVersion(CmsPage $page, ?User $actor, string $changeNote): void
    {
        $page->loadMissing('sections');
        $nextVersion = $page->current_version + 1;

        CmsPageVersion::create([
            'cms_page_id' => $page->id,
            'version' => $nextVersion,
            'snapshot' => [
                'name' => $page->name,
                'seo' => $page->seo,
                'sections' => $page->sections->map(fn (CmsSection $section) => [
                    'component' => $section->component,
                    'variant' => $section->variant,
                    'content' => $section->content,
                    'sort_order' => $section->sort_order,
                    'is_enabled' => $section->is_enabled,
                ])->all(),
            ],
            'change_note' => $changeNote,
            'created_by_id' => $actor?->id,
            'created_at' => now(),
        ]);

        $page->update(['current_version' => $nextVersion]);
    }
}
