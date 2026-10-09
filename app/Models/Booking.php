<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference',
    'venue_id',
    'space_type_id',
    'conversation_id',
    'guest_name',
    'company_name',
    'event_type',
    'guest_email',
    'guest_phone',
    'contact_type',
    'locale',
    'start_date',
    'end_date',
    'candidates',
    'recruiters',
    'space_count',
    'tech_crew',
    'total_price',
    'status',
    'notes',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * What the booked venue is used for.
     *
     * @var list<string>
     */
    public const EVENT_TYPES = ['job_fair', 'walk_in_interview', 'assessment', 'briefing', 'campus_hiring', 'other'];

    /**
     * Which status a booking may move to from its current one. Cancelled is
     * final: its spaces went back to the inventory and are not held any more.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [],
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'tech_crew' => 'boolean',
            'total_price' => 'decimal:2',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function spaceType(): BelongsTo
    {
        return $this->belongsTo(SpaceType::class)->withTrashed();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * A short, unambiguous, non-sequential code guests can quote to staff.
     */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'BK-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function days(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}
