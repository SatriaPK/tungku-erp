<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class StudioSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'updated_by',
    ];

    /**
     * Get the user who last updated this setting.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get a setting value with automatic type casting.
     *
     * @throws InvalidArgumentException if setting not found
     */
    public static function getSetting(string $key): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            throw new InvalidArgumentException("Setting '{$key}' not found.");
        }

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'decimal' => (float) $setting->value,
            'json' => json_decode($setting->value, true),
            'string' => $setting->value,
            default => $setting->value,
        };
    }

    /**
     * Update a setting value with validation.
     *
     * @throws InvalidArgumentException if validation fails
     */
    public static function updateSetting(string $key, mixed $value, int $userId): StudioSetting
    {
        $setting = static::where('key', $key)->firstOrFail();

        // Validate based on key
        match ($key) {
            'po_percentage' => self::validateRange($value, 30, 70, 'PO percentage'),
            'studio_profit_percentage' => self::validateRange($value, 0, 100, 'Studio profit percentage'),
            'hold_duration_minutes' => self::validateRange($value, 10, 30, 'Hold duration'),
            'operating_hours' => self::validateOperatingHours($value),
            default => null,
        };

        // Convert value to string for storage
        $stringValue = match ($setting->type) {
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };

        $setting->update([
            'value' => $stringValue,
            'updated_by' => $userId,
        ]);

        return $setting->fresh();
    }

    /**
     * Validate that a value is within range.
     *
     * @throws InvalidArgumentException
     */
    private static function validateRange(mixed $value, int|float $min, int|float $max, string $label): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(
                "{$label} must be between {$min} and {$max}, got {$value}."
            );
        }
    }

    /**
     * Validate operating hours JSON structure.
     *
     * @throws InvalidArgumentException
     */
    private static function validateOperatingHours(mixed $value): void
    {
        $data = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($data)) {
            throw new InvalidArgumentException('Operating hours must be a valid JSON object.');
        }

        $requiredDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $missingDays = array_diff($requiredDays, array_keys($data));

        if (! empty($missingDays)) {
            throw new InvalidArgumentException(
                'Operating hours must include all 7 days. Missing: '.implode(', ', $missingDays)
            );
        }

        // Validate format HH:MM-HH:MM
        foreach ($data as $day => $hours) {
            if (! preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $hours)) {
                throw new InvalidArgumentException(
                    "Invalid format for {$day}: expected HH:MM-HH:MM, got '{$hours}'."
                );
            }
        }
    }
}
