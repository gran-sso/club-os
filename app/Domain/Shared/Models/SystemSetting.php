<?php

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $cacheKey = "system_setting.{$key}";
            $cached = Cache::get($cacheKey);

            // Store strictly primitive arrays ['value' => scalar|null] so cache drivers with
            // `serializable_classes => false` (such as database/file in production) never
            // unserialize into __PHP_Incomplete_Class and silently fall back to defaults.
            if (! is_array($cached) || ! array_key_exists('value', $cached)) {
                $setting = static::where('key', $key)->first();
                $cached = ['value' => $setting?->value];
                Cache::put($cacheKey, $cached, 300);
            }

            return $cached['value'] !== null ? $cached['value'] : $default;
        } catch (\Throwable $e) {
            // Fallback during early bootstrapping or schema tests before migration
            return $default;
        }
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $raw = static::get($key, null);

        if ($raw === null) {
            return $default;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        $parsed = filter_var((string) $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): self
    {
        $normalized = match (true) {
            is_bool($value) => $value ? '1' : '0',
            $value === null => null,
            default => (string) $value,
        };

        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $normalized,
                'group' => $group,
            ]
        );

        Cache::forget("system_setting.{$key}");

        return $setting;
    }

    public static function has(string $key): bool
    {
        return static::where('key', $key)->exists();
    }
}

