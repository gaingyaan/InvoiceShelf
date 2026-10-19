<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['option', 'value'];

    protected static $settingsCache = [];

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
        static::$settingsCache = [];
    }

    public static function setSetting($key, $setting)
    {
        $old = self::whereOption($key)->first();

        if ($old) {
            $old->value = $setting;
            $old->save();
            static::flushCache();

            return;
        }

        $set = new Setting;
        $set->option = $key;
        $set->value = $setting;
        $set->save();
        static::flushCache();
    }

    public static function setSettings($settings)
    {
        foreach ($settings as $key => $value) {
            self::updateOrCreate(
                [
                    'option' => $key,
                ],
                [
                    'option' => $key,
                    'value' => $value,
                ]
            );
        }
        static::flushCache();
    }

    public static function getSetting($key)
    {
        if (isset(static::$settingsCache[$key])) {
            return static::$settingsCache[$key];
        }

        $setting = static::whereOption($key)->first();

        if ($setting) {
            static::$settingsCache[$key] = $setting->value;

            return $setting->value;
        } else {
            return null;
        }
    }

    public static function getSettings($settings)
    {
        $result = collect();
        $missing = [];

        foreach ($settings as $key) {
            if (isset(static::$settingsCache[$key])) {
                $result->put($key, static::$settingsCache[$key]);
            } else {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            $fetched = static::whereIn('option', $missing)
                ->get()->mapWithKeys(function ($item) {
                    return [$item['option'] => $item['value']];
                });

            foreach ($fetched as $key => $value) {
                static::$settingsCache[$key] = $value;
                $result->put($key, $value);
            }
        }

        return $result;
    }
}
