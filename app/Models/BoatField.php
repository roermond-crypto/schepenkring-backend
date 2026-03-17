<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BoatField extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_key',
        'labels_json',
        'help_json',
        'options_json',
        'field_type',
        'block_key',
        'step_key',
        'sort_order',
        'storage_relation',
        'storage_column',
        'ai_relevance',
        'is_active',
    ];

    protected $casts = [
        'labels_json' => 'array',
        'help_json' => 'array',
        'options_json' => 'array',
        'sort_order' => 'integer',
        'ai_relevance' => 'boolean',
        'is_active' => 'boolean',
        'mappings_count' => 'integer',
        'value_observations_count' => 'integer',
        'value_observations_total' => 'integer',
    ];

    public function priorities(): HasMany
    {
        return $this->hasMany(BoatFieldPriority::class, 'field_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(BoatFieldMapping::class, 'field_id');
    }

    public function valueObservations(): HasMany
    {
        return $this->hasMany(BoatFieldValueObservation::class, 'field_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function labelForLocale(?string $locale = null): string
    {
        $labels = is_array($this->labels_json) ? $this->labels_json : [];
        $normalizedLocale = Str::lower(Str::substr((string) ($locale ?: app()->getLocale() ?: 'en'), 0, 2));

        foreach ([$normalizedLocale, 'en', 'nl', 'de', 'fr'] as $candidate) {
            $value = trim((string) ($labels[$candidate] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return (string) Str::of($this->internal_key)->replace('_', ' ')->title();
    }

    public function helpTextForLocale(?string $locale = null): string
    {
        $normalizedLocale = Str::lower(Str::substr((string) ($locale ?: app()->getLocale() ?: 'en'), 0, 2));
        $helpTexts = $this->helpTexts();

        foreach ([$normalizedLocale, 'en', 'nl', 'de', 'fr'] as $candidate) {
            $value = trim((string) ($helpTexts[$candidate] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $this->defaultHelpTextForLocale($normalizedLocale);
    }

    public function helpTexts(): array
    {
        $stored = is_array($this->help_json) ? $this->help_json : [];
        $locales = ['nl', 'en', 'de', 'fr'];
        $resolved = [];

        foreach ($locales as $locale) {
            $value = trim((string) ($stored[$locale] ?? ''));
            $resolved[$locale] = $value !== ''
                ? $value
                : $this->defaultHelpTextForLocale($locale);
        }

        return $resolved;
    }

    private function defaultHelpTextForLocale(string $locale): string
    {
        $label = $this->labelForLocale($locale);
        $fieldType = Str::lower((string) $this->field_type);

        if (in_array($fieldType, ['number', 'integer', 'decimal', 'float'], true)) {
            return match ($locale) {
                'nl' => "Vul de numerieke waarde in voor {$label}. Laat dit veld leeg als de informatie onbekend is.",
                'de' => "Geben Sie den numerischen Wert fur {$label} ein. Lassen Sie das Feld leer, wenn die Angabe unbekannt ist.",
                'fr' => "Saisissez la valeur numerique pour {$label}. Laissez ce champ vide si l'information est inconnue.",
                default => "Enter the numeric value for {$label}. Leave this field empty if the information is unknown.",
            };
        }

        if ($fieldType === 'select') {
            return match ($locale) {
                'nl' => "Kies de optie die het beste past bij {$label} voor deze boot.",
                'de' => "Wahlen Sie die Option, die am besten zu {$label} fur dieses Boot passt.",
                'fr' => "Choisissez l'option qui correspond le mieux a {$label} pour ce bateau.",
                default => "Choose the option that best matches {$label} for this boat.",
            };
        }

        if ($fieldType === 'tri_state') {
            return match ($locale) {
                'nl' => "Geef aan of {$label} aanwezig is: ja, nee of onbekend.",
                'de' => "Geben Sie an, ob {$label} vorhanden ist: ja, nein oder unbekannt.",
                'fr' => "Indiquez si {$label} est present : oui, non ou inconnu.",
                default => "Indicate whether {$label} is present: yes, no, or unknown.",
            };
        }

        return match ($locale) {
            'nl' => "Vul de informatie in voor {$label}. Laat dit veld leeg als de informatie niet beschikbaar is.",
            'de' => "Tragen Sie die Angabe fur {$label} ein. Lassen Sie das Feld leer, wenn die Information nicht verfugbar ist.",
            'fr' => "Renseignez {$label}. Laissez ce champ vide si l'information n'est pas disponible.",
            default => "Enter the information for {$label}. Leave this field empty if the information is not available.",
        };
    }
}
