<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUniqueSlug
{
    protected static function bootHasUniqueSlug(): void
    {
        static::saving(function ($model): void {
            $sourceColumn = $model->slugSourceColumn();
            $sourceIsDirty = method_exists($model, 'slugSourceIsDirty')
                ? $model->slugSourceIsDirty()
                : $model->isDirty($sourceColumn);
            $sourceValue = method_exists($model, 'slugSourceValue')
                ? $model->slugSourceValue()
                : $model->getAttribute($sourceColumn);

            if (! $model->isFillable('slug') || blank($sourceValue)) {
                return;
            }

            if (filled($model->slug) && ! $model->isDirty('slug') && ! $sourceIsDirty) {
                return;
            }

            if (blank($model->slug) || $sourceIsDirty) {
                $model->slug = $model->makeUniqueSlug((string) $sourceValue);
            }
        });
    }

    protected function slugSourceColumn(): string
    {
        return property_exists($this, 'slugSourceColumn') ? $this->slugSourceColumn : 'name';
    }

    protected function makeUniqueSlug(string $value): string
    {
        $baseSlug = Str::slug($value);

        if ($baseSlug === '') {
            $baseSlug = Str::lower(class_basename($this));
        }

        $maxLength = 255;
        $baseSlug = Str::limit($baseSlug, $maxLength, '');
        $slug = $baseSlug;
        $suffix = 1;

        while ($this->slugExists($slug)) {
            $suffix++;
            $suffixText = '-'.$suffix;
            $slug = Str::limit($baseSlug, $maxLength - strlen($suffixText), '').$suffixText;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        return static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }
}
