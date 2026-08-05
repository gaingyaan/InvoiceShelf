<?php

namespace App\Domains\Metadata\Concerns;

use App\Domains\Metadata\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCustomFields
{
    public function fields(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'custom_field_valuable');
    }

    protected static function booted()
    {
        static::deleting(function ($data) {
            if ($data->fields()->exists()) {
                $data->fields()->delete();
            }
        });
    }

    public function getCustomFieldBySlug($slug)
    {
        return $this->fields()
            ->with('customField')
            ->whereHas('customField', function ($query) use ($slug) {
                $query->where('slug', $slug);
            })->first();
    }

    public function getCustomFieldValueBySlug($slug)
    {
        $value = $this->getCustomFieldBySlug($slug);

        if ($value) {
            return $value->defaultAnswer;
        }

        return null;
    }
}
