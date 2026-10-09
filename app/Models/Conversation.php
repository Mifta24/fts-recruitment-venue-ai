<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'venue_id',
    'guest_token',
    'guest_name',
    'guest_email',
    'locale',
    'status',
    'handover_summary',
    'current_scene',
    'selected_space_type_id',
    'selected_facility_id',
    'reservation_state',
    'last_message_at',
])]
class Conversation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_HANDED_OVER = 'handed_over';

    public const STATUS_CLOSED = 'closed';

    /**
     * The UI scenes the guest can be in while talking to the coordinator.
     */
    public const SCENES = ['spaces', 'space_detail', 'facilities', 'facility_detail', 'reservation', 'handover'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'reservation_state' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function selectedSpaceType(): BelongsTo
    {
        return $this->belongsTo(SpaceType::class, 'selected_space_type_id');
    }

    public function selectedFacility(): BelongsTo
    {
        return $this->belongsTo(VenueKnowledgeItem::class, 'selected_facility_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function handoverRequest(): HasOne
    {
        return $this->hasOne(HandoverRequest::class)->latestOfMany();
    }

    public function isHandedOver(): bool
    {
        return $this->status === self::STATUS_HANDED_OVER;
    }
}
