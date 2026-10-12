<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingHour extends Model
{
    /** Weekday numbers follow Carbon: 0 is Sunday and 6 is Saturday. */
    public const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    protected $fillable = [
        'weekday',
        'is_open',
        'opens_at',
        'closes_at',
        'break_starts_at',
        'break_ends_at',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_open' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    public function dayName(): string
    {
        return self::DAYS[$this->weekday] ?? '';
    }
}
