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
            $setting = Cache::remember("system_setting.{$key}", 300, function () use ($key) {
                return static::where('key', $key)->first();
            });

            return $setting && $setting->value !== null ? $setting->value : $default;
        } catch (\Throwable $e) {
            // Fallback during early bootstrapping or schema tests before migration
            return $default;
        }
    }

    public static function set(string $key, mixed $value, string $group = 'general'): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
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

