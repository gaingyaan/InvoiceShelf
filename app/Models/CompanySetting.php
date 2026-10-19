<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'option', 'value'];

    protected static $companySettingsCache = [];

    protected static function booted()
    {
        static::saved(function () {
            static::flushCache();
        });
        static::deleted(function () {
            static::flushCache();
        });
    }

    public static function flushCache()
    {
        static::$companySettingsCache = [];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWhereCompany($query, $company_id)
    {
        $query->where('company_id', $company_id);
    }

    public static function setSetting($key, $value, $company_id)
    {
        self::setSettings([$key => $value], $company_id);
    }

    public static function setSettings($settings, $company_id)
    {
        foreach ($settings as $key => $value) {
            self::updateOrCreate(
                [
                    'option' => $key,
                    'company_id' => $company_id,
                ],
                [
                    'option' => $key,
                    'company_id' => $company_id,
                    'value' => $value,
                ]
            );
        }
        static::flushCache();
    }

    public static function getAllSettings($company_id)
    {
        return static::whereCompany($company_id)->get()->mapWithKeys(function ($item) use ($company_id) {
            $cacheKey = "{$company_id}_{$item['option']}";
            static::$companySettingsCache[$cacheKey] = $item['value'];

            return [$item['option'] => $item['value']];
        });
    }

    public static function getSettings($settings, $company_id)
    {
        $result = collect();
        $missing = [];

        foreach ($settings as $key) {
            $cacheKey = "{$company_id}_{$key}";
            if (isset(static::$companySettingsCache[$cacheKey])) {
                $result->put($key, static::$companySettingsCache[$cacheKey]);
            } else {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            $fetched = static::whereIn('option', $missing)->whereCompany($company_id)
                ->get()->mapWithKeys(function ($item) use ($company_id) {
                    $cacheKey = "{$company_id}_{$item['option']}";
                    static::$companySettingsCache[$cacheKey] = $item['value'];

                    return [$item['option'] => $item['value']];
                });

            foreach ($fetched as $key => $value) {
                $result->put($key, $value);
            }
        }

        return $result;
    }

    public static function getSetting($key, $company_id)
    {
        $cacheKey = "{$company_id}_{$key}";
        if (isset(static::$companySettingsCache[$cacheKey])) {
            return static::$companySettingsCache[$cacheKey];
        }

        $setting = static::whereOption($key)->whereCompany($company_id)->first();

        if ($setting) {
            $value = $setting->value;
            static::$companySettingsCache[$cacheKey] = $value;

            return $value;
        } else {
            return null;
        }
    }
}
