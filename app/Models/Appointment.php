<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Enums\AppointmentStatus;
use App\Support\Scheduling;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Appointment extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'service_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'reason',
        'admin_note',
        'cancelled_by',
        'reschedule_count',
        'reminder_sent_at',
        'decided_at',
        'strike_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'reason' => Encrypted::class,
            'admin_note' => Encrypted::class,
            'status' => AppointmentStatus::class,
            'reschedule_count' => 'integer',
            'reminder_sent_at' => 'datetime',
            'decided_at' => 'datetime',
            'strike_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(AppointmentFeedback::class);
    }

    public static function newReference(): string
    {
        do {
            $reference = 'APT-'.strtoupper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->format('Y-m-d').' '.$this->start_time, Scheduling::timezone());
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->format('Y-m-d').' '.$this->end_time, Scheduling::timezone());
    }

    public function dateLabel(): string
    {
        return $this->date->format('D, M j, Y');
    }

    public function timeLabel(): string
    {
        return $this->startsAt()->format('g:i A').' – '.$this->endsAt()->format('g:i A');
    }

    /**
     * Filters used by the staff list, the CSV export and the printable report.
     *
     * @param  array{status?: ?string, q?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $status = AppointmentStatus::tryFrom((string) ($filters['status'] ?? ''));
        $search = trim((string) ($filters['q'] ?? ''));

        return $query
            ->when($status, fn (Builder $q) => $q->where('status', $status->value))
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('service', fn (Builder $s) => $s->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(! empty($filters['from']), fn (Builder $q) => $q->whereDate('date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn (Builder $q) => $q->whereDate('date', '<=', $filters['to']));
    }
}
