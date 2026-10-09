<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only source of truth for availability and price. Stands in for a real
 * booking-engine adapter for V1 — the AI must reach this table (or its
 * future adapter) through a tool call, never answer from its own memory.
 */
#[Fillable([
    'space_type_id',
    'event_date',
    'total_spaces',
    'booked_spaces',
    'price',
])]
class SpaceInventory extends Model
{
    protected $table = 'space_inventory';

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function spaceType(): BelongsTo
    {
        return $this->belongsTo(SpaceType::class);
    }

    public function availableSpaces(): int
    {
        return max(0, $this->total_spaces - $this->booked_spaces);
    }

    public function isAvailable(): bool
    {
        return $this->availableSpaces() > 0;
    }
}
